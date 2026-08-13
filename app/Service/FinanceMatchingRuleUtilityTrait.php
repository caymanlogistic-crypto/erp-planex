<?php

namespace App\Service;

use PDO;

trait FinanceMatchingRuleUtilityTrait
{
    private static function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private static function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    private static function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
        $value = strtolower($value);
        return strtr($value, [
            'А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я',
        ]);
    }

    private static function containsCaseInsensitive(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return str_contains(self::lower($haystack), self::lower($needle));
    }

    private static function decimalCompare(string $left, string $right): int
    {
        $toCents = static function (string $value): int {
            $value = str_replace(',', '.', trim($value));
            $negative = str_starts_with($value, '-');
            if ($negative) $value = substr($value, 1);
            [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
            $whole = preg_replace('/\D/', '', $whole) ?: '0';
            $decimal = substr(str_pad(preg_replace('/\D/', '', $decimal) ?: '', 2, '0'), 0, 2);
            $cents = (int) $whole * 100 + (int) $decimal;
            return $negative ? -$cents : $cents;
        };
        return $toCents($left) <=> $toCents($right);
    }

    private static function assertCashFlowCenterActive(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('SELECT is_active FROM finance_cash_flow_centers WHERE id=:id'); $stmt->execute([':id' => $id]);
        $active = $stmt->fetchColumn();
        if ($active === false || !(int) $active) throw new \InvalidArgumentException('Выбранный ЦФУ не найден или неактивен.');
    }

    private static function assertCashAccountActive(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare("SELECT is_active FROM finance_money_accounts WHERE id=:id AND type='CASH'"); $stmt->execute([':id' => $id]);
        $active = $stmt->fetchColumn();
        if ($active === false || !(int) $active) throw new \InvalidArgumentException('Выбранная касса не найдена или неактивна.');
    }

    private static function assertDdsCategoryForDirection(PDO $pdo, int $id, ?string $direction): void
    {
        $stmt = $pdo->prepare('SELECT direction,is_active FROM finance_dds_categories WHERE id=:id'); $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !(int) $row['is_active']) throw new \InvalidArgumentException('Статья ДДС не найдена или неактивна.');
        if ($direction !== null && !in_array($row['direction'], [$direction, 'BOTH'], true)) throw new \InvalidArgumentException('Направление статьи ДДС не соответствует правилу.');
    }
}
