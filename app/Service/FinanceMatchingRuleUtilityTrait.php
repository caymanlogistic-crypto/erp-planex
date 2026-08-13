<?php

namespace App\Service;

use PDO;

trait FinanceMatchingRuleUtilityTrait
{
    private static function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
        return strtr(strtolower($value), [
            'А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я',
        ]);
    }

    private static function containsCi(string $haystack, string $needle): bool
    {
        return $needle === '' || str_contains(self::lower($haystack), self::lower($needle));
    }

    private static function cents(mixed $value): int
    {
        $value = str_replace([' ', ','], ['', '.'], trim((string)$value));
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Некорректное денежное значение.');
        }
        $negative = str_starts_with($value, '-');
        if ($negative) $value = substr($value, 1);
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $result = ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
        return $negative ? -$result : $result;
    }

    private static function moneyOrNull(mixed $value): ?string
    {
        if ($value === null || trim((string)$value) === '') return null;
        $cents = self::cents($value);
        if ($cents < 0) throw new \InvalidArgumentException('Сумма в правиле не может быть отрицательной.');
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $id = (int)$value;
        return $id > 0 ? $id : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    private static function directionForTransaction(array $tx): ?string
    {
        $credit = self::cents($tx['credit_amount'] ?? '0.00');
        $debit = self::cents($tx['debit_amount'] ?? '0.00');
        if ($credit > 0 && $debit <= 0) return 'INCOME';
        if ($debit > 0 && $credit <= 0) return 'EXPENSE';
        return null;
    }

    private static function amountForTransaction(array $tx): string
    {
        return self::directionForTransaction($tx) === 'INCOME'
            ? (string)($tx['credit_amount'] ?? '0.00')
            : (string)($tx['debit_amount'] ?? '0.00');
    }

    private static function validateInn(?string $inn): ?string
    {
        if ($inn === null || $inn === '') return null;
        $inn = preg_replace('/\D/', '', $inn) ?? '';
        if (!in_array(strlen($inn), [10, 12], true)) {
            throw new \InvalidArgumentException('ИНН в правиле должен содержать 10 или 12 цифр.');
        }
        return $inn;
    }

    private static function validateRegex(?string $regex): ?string
    {
        if ($regex === null || $regex === '') return null;
        if (strlen($regex) > 500 || @preg_match($regex, '') === false) {
            throw new \InvalidArgumentException('Некорректное регулярное выражение назначения.');
        }
        return $regex;
    }

    private static function assertCfu(PDO $pdo, int $id): void
    {
        $stmt=$pdo->prepare('SELECT is_active FROM finance_cash_flow_centers WHERE id=?');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() !== 1) throw new \InvalidArgumentException('Выбранный ЦФУ не найден или неактивен.');
    }

    private static function assertDds(PDO $pdo, int $id, ?string $direction): void
    {
        $stmt=$pdo->prepare('SELECT direction,is_active FROM finance_dds_categories WHERE id=?');
        $stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['is_active'] !== 1) throw new \InvalidArgumentException('Статья ДДС не найдена или неактивна.');
        if ($direction !== null && !in_array($row['direction'], [$direction, 'BOTH'], true)) {
            throw new \InvalidArgumentException('Направление статьи ДДС не соответствует правилу.');
        }
    }

    private static function assertCashAccount(PDO $pdo, int $id): void
    {
        $stmt=$pdo->prepare("SELECT is_active FROM finance_money_accounts WHERE id=? AND type='CASH'");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() !== 1) throw new \InvalidArgumentException('Выбранная касса не найдена или неактивна.');
    }

    public static function isManualProtected(array $row): bool
    {
        return !empty($row['classification_locked']) || strtoupper((string)($row['classification_status'] ?? '')) === 'MANUAL';
    }

    public static function classificationStatusLabel(?string $status): string
    {
        return match (strtoupper((string)$status)) {
            'AUTO' => 'Разнесено автоматически',
            'MANUAL' => 'Разнесено вручную',
            'NEEDS_REVIEW' => 'Конфликт / требует проверки',
            default => 'Не разнесено',
        };
    }

    public static function classificationBadgeClass(?string $status): string
    {
        return match (strtoupper((string)$status)) {
            'AUTO', 'MANUAL' => 'badge badge-ok',
            'NEEDS_REVIEW' => 'badge badge-warning',
            default => 'badge badge-neutral',
        };
    }
}
