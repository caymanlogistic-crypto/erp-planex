<?php

namespace App\Service;

use InvalidArgumentException;

final class RoutePaymentStatusService
{
    public const STATUS_WAITING_EVENT = 'waiting_event';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_WAITING_EVENT => 'Ожидается событие',
        self::STATUS_PLANNED => 'Запланирован',
        self::STATUS_PARTIALLY_PAID => 'Частично оплачен',
        self::STATUS_PAID => 'Оплачен',
        self::STATUS_OVERDUE => 'Просрочен',
        self::STATUS_CANCELLED => 'Отменён',
    ];

    public const STATUS_LABELS_RU = self::STATUS_LABELS;

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'badge badge-ok',
            self::STATUS_PARTIALLY_PAID => 'badge badge-warning',
            self::STATUS_OVERDUE => 'badge badge-danger',
            default => 'badge badge-neutral',
        };
    }

    private static function amountToCents(?string $value, bool $allowNull, bool $mustBePositive, string $field): int
    {
        if ($value === null) {
            if ($allowNull) {
                return 0;
            }
            throw new InvalidArgumentException($field . ': значение обязательно.');
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException($field . ': ожидается неотрицательное число с точностью до двух знаков.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');
        if (strlen($whole) > 16) {
            throw new InvalidArgumentException($field . ': значение слишком велико.');
        }
        $cents = ((int) $whole * 100) + (int) $fraction;
        if ($mustBePositive && $cents <= 0) {
            throw new InvalidArgumentException($field . ': значение должно быть больше нуля.');
        }
        return $cents;
    }

    public static function computeStatus(
        ?string $amount,
        ?string $paidAmount,
        ?string $calculatedDueDate,
        ?string $cancelledAt = null,
        ?string $closingDocumentsReceivedDate = null,
        ?string $conditionType = null
    ): string {
        $amountCents = self::amountToCents($amount, false, true, 'Сумма платежа');
        $paidCents = self::amountToCents($paidAmount, true, false, 'Оплаченная сумма');

        foreach (['Срок оплаты' => $calculatedDueDate, 'Дата получения документов' => $closingDocumentsReceivedDate] as $label => $date) {
            if ($date !== null && !DateCalculationService::isValidDate($date)) {
                throw new InvalidArgumentException($label . ': некорректная дата.');
            }
        }

        $conditionType = trim((string) $conditionType);
        if ($conditionType !== '' && !array_key_exists($conditionType, DateCalculationService::CONDITION_LABELS)) {
            throw new InvalidArgumentException('Неизвестное условие оплаты.');
        }

        if ($cancelledAt !== null) {
            return self::STATUS_CANCELLED;
        }
        if ($paidCents >= $amountCents) {
            return self::STATUS_PAID;
        }
        if ($conditionType === DateCalculationService::CONDITION_AFTER_DOCUMENTS && $closingDocumentsReceivedDate === null) {
            return self::STATUS_WAITING_EVENT;
        }

        $overdue = $calculatedDueDate !== null && $calculatedDueDate < date('Y-m-d');
        if ($paidCents > 0) {
            return $overdue ? self::STATUS_OVERDUE : self::STATUS_PARTIALLY_PAID;
        }
        return $overdue ? self::STATUS_OVERDUE : self::STATUS_PLANNED;
    }

    public static function isStatusComputed(string $status): bool
    {
        return array_key_exists($status, self::STATUS_LABELS);
    }
}
