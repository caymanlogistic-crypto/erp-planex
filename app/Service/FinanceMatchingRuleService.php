<?php

namespace App\Service;

use PDO;

final class FinanceMatchingRuleService
{
    private const ALLOWED_DIRECTIONS = ['INCOME', 'EXPENSE'];
    private const ALLOWED_ACTION_TYPES = ['categorize', 'match_invoice', 'match_counterparty'];

    // ── Money-safe helpers ──

    private static function cents(string $amount): int
    {
        $normalized = str_replace(',', '.', trim($amount));
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $normalized) !== 1) {
            throw new \InvalidArgumentException('Некорректное денежное значение в правиле сопоставления.');
        }
        $negative = str_starts_with($normalized, '-');
        $unsigned = $negative ? substr($normalized, 1) : $normalized;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        if (strlen($whole) > 16) {
            throw new \InvalidArgumentException('Денежное значение в правиле слишком велико.');
        }
        $value = ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
        return $negative ? -$value : $value;
    }

    private static function normalizeRuleAmount(mixed $value): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        $cents = self::cents((string)$value);
        if ($cents < 0) {
            throw new \InvalidArgumentException('Граница суммы не может быть отрицательной.');
        }
        return self::decimalFromCents($cents);
    }

    private static function assertRuleRange(?string $from, ?string $to): void
    {
        if ($from !== null && $to !== null && self::cents($from) > self::cents($to)) {
            throw new \InvalidArgumentException('Сумма «от» не может быть больше суммы «до».');
        }
    }

    private static function normalizePurposeRegex(mixed $value): ?string
    {
        $regex = trim((string)($value ?? ''));
        if ($regex === '') {
            return null;
        }
        if (strlen($regex) > 500 || @preg_match($regex, '') === false) {
            throw new \InvalidArgumentException('Некорректное регулярное выражение назначения платежа.');
        }
        return $regex;
    }

    private static function decimalFromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $intPart = substr($abs, 0, -2);
        $decPart = str_pad(substr($abs, -2), 2, '0');
        if ($intPart === '' || $intPart === false) {
            $intPart = '0';
        }
        return $sign . $intPart . '.' . $decPart;
    }


    private static function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        // Deterministic UTF-8 fallback for Russian text when mbstring is unavailable.
        return strtr(strtolower($value), [
            'А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й',
            'К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф',
            'Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я',
        ]);
    }

    // ── CRUD ──

    public static function fetchRules(PDO $pdo, array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['active'])) {
            $conditions[] = 'active = :active';
            $params[':active'] = $filters['active'] ? 1 : 0;
        }
        if (!empty($filters['direction'])) {
            $conditions[] = '(direction = :direction OR direction IS NULL)';
            $params[':direction'] = $filters['direction'];
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM finance_matching_rules {$where} ORDER BY priority ASC, name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findRule(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM finance_matching_rules WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function createRule(PDO $pdo, array $data, array $user): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Название правила обязательно.');
        }

        $direction = !empty($data['direction']) ? strtoupper(trim($data['direction'])) : null;
        if ($direction !== null && !in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw new \InvalidArgumentException('Направление должно быть INCOME или EXPENSE.');
        }

        $actionType = $data['action_type'] ?? 'categorize';
        if (!in_array($actionType, self::ALLOWED_ACTION_TYPES, true)) {
            throw new \InvalidArgumentException('Недопустимый тип действия.');
        }
        $amountFrom = self::normalizeRuleAmount($data['amount_from'] ?? null);
        $amountTo = self::normalizeRuleAmount($data['amount_to'] ?? null);
        self::assertRuleRange($amountFrom, $amountTo);
        $purposeRegex = self::normalizePurposeRegex($data['purpose_regex'] ?? null);

        $stmt = $pdo->prepare(
            'INSERT INTO finance_matching_rules
                (name, active, priority, direction, bank_account_id,
                 counterparty_inn, counterparty_id, counterparty_type,
                 invoice_number_pattern, purpose_contains, purpose_regex,
                 amount_from, amount_to,
                 action_type, target_dds_category_id,
                 target_counterparty_id, target_counterparty_type,
                 auto_apply,
                 created_by_user_id, created_by_role,
                 updated_by_user_id, updated_by_role)
             VALUES
                (:name, 1, :priority, :direction, :bank_account_id,
                 :counterparty_inn, :counterparty_id, :counterparty_type,
                 :invoice_number_pattern, :purpose_contains, :purpose_regex,
                 :amount_from, :amount_to,
                 :action_type, :target_dds_category_id,
                 :target_counterparty_id, :target_counterparty_type,
                 :auto_apply,
                 :created_by_user_id, :created_by_role,
                 :updated_by_user_id, :updated_by_role)'
        );
        $stmt->execute([
            ':name' => $name,
            ':priority' => (int)($data['priority'] ?? 100),
            ':direction' => $direction,
            ':bank_account_id' => !empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null,
            ':counterparty_inn' => !empty($data['counterparty_inn']) ? trim($data['counterparty_inn']) : null,
            ':counterparty_id' => !empty($data['counterparty_id']) ? (int)$data['counterparty_id'] : null,
            ':counterparty_type' => !empty($data['counterparty_type']) ? trim($data['counterparty_type']) : null,
            ':invoice_number_pattern' => !empty($data['invoice_number_pattern']) ? trim($data['invoice_number_pattern']) : null,
            ':purpose_contains' => !empty($data['purpose_contains']) ? trim($data['purpose_contains']) : null,
            ':purpose_regex' => $purposeRegex,
            ':amount_from' => $amountFrom,
            ':amount_to' => $amountTo,
            ':action_type' => $actionType,
            ':target_dds_category_id' => !empty($data['target_dds_category_id']) ? (int)$data['target_dds_category_id'] : null,
            ':target_counterparty_id' => !empty($data['target_counterparty_id']) ? (int)$data['target_counterparty_id'] : null,
            ':target_counterparty_type' => !empty($data['target_counterparty_type']) ? trim($data['target_counterparty_type']) : null,
            ':auto_apply' => !empty($data['auto_apply']) ? 1 : 0,
            ':created_by_user_id' => $user['id'] ?? null,
            ':created_by_role' => $user['role'] ?? null,
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateRule(PDO $pdo, int $id, array $data, array $user): void
    {
        $rule = self::findRule($pdo, $id);
        if (!$rule) {
            throw new \InvalidArgumentException('Правило не найдено.');
        }

        $name = isset($data['name']) ? trim((string)$data['name']) : $rule['name'];
        if ($name === '') {
            throw new \InvalidArgumentException('Название правила обязательно.');
        }

        $direction = array_key_exists('direction', $data)
            ? (!empty($data['direction']) ? strtoupper(trim($data['direction'])) : null)
            : $rule['direction'];

        if ($direction !== null && !in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw new \InvalidArgumentException('Направление должно быть INCOME или EXPENSE.');
        }

        $actionType = $data['action_type'] ?? $rule['action_type'];
        if (!in_array($actionType, self::ALLOWED_ACTION_TYPES, true)) {
            throw new \InvalidArgumentException('Недопустимый тип действия.');
        }
        $amountFrom = array_key_exists('amount_from', $data)
            ? self::normalizeRuleAmount($data['amount_from'])
            : self::normalizeRuleAmount($rule['amount_from'] ?? null);
        $amountTo = array_key_exists('amount_to', $data)
            ? self::normalizeRuleAmount($data['amount_to'])
            : self::normalizeRuleAmount($rule['amount_to'] ?? null);
        self::assertRuleRange($amountFrom, $amountTo);
        $purposeRegex = array_key_exists('purpose_regex', $data)
            ? self::normalizePurposeRegex($data['purpose_regex'])
            : self::normalizePurposeRegex($rule['purpose_regex'] ?? null);

        $stmt = $pdo->prepare(
            'UPDATE finance_matching_rules
                SET name = :name, priority = :priority, direction = :direction,
                    bank_account_id = :bank_account_id,
                    counterparty_inn = :counterparty_inn,
                    counterparty_id = :counterparty_id,
                    counterparty_type = :counterparty_type,
                    invoice_number_pattern = :invoice_number_pattern,
                    purpose_contains = :purpose_contains,
                    purpose_regex = :purpose_regex,
                    amount_from = :amount_from, amount_to = :amount_to,
                    action_type = :action_type,
                    target_dds_category_id = :target_dds_category_id,
                    target_counterparty_id = :target_counterparty_id,
                    target_counterparty_type = :target_counterparty_type,
                    auto_apply = :auto_apply,
                    updated_by_user_id = :updated_by_user_id,
                    updated_by_role = :updated_by_role
              WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $name,
            ':priority' => (int)($data['priority'] ?? $rule['priority']),
            ':direction' => $direction,
            ':bank_account_id' => array_key_exists('bank_account_id', $data)
                ? (!empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null)
                : $rule['bank_account_id'],
            ':counterparty_inn' => array_key_exists('counterparty_inn', $data)
                ? (!empty($data['counterparty_inn']) ? trim($data['counterparty_inn']) : null)
                : $rule['counterparty_inn'],
            ':counterparty_id' => array_key_exists('counterparty_id', $data)
                ? (!empty($data['counterparty_id']) ? (int)$data['counterparty_id'] : null)
                : $rule['counterparty_id'],
            ':counterparty_type' => array_key_exists('counterparty_type', $data)
                ? (!empty($data['counterparty_type']) ? trim($data['counterparty_type']) : null)
                : $rule['counterparty_type'],
            ':invoice_number_pattern' => array_key_exists('invoice_number_pattern', $data)
                ? (!empty($data['invoice_number_pattern']) ? trim($data['invoice_number_pattern']) : null)
                : $rule['invoice_number_pattern'],
            ':purpose_contains' => array_key_exists('purpose_contains', $data)
                ? (!empty($data['purpose_contains']) ? trim($data['purpose_contains']) : null)
                : $rule['purpose_contains'],
            ':purpose_regex' => $purposeRegex,
            ':amount_from' => $amountFrom,
            ':amount_to' => $amountTo,
            ':action_type' => $actionType,
            ':target_dds_category_id' => array_key_exists('target_dds_category_id', $data)
                ? (!empty($data['target_dds_category_id']) ? (int)$data['target_dds_category_id'] : null)
                : $rule['target_dds_category_id'],
            ':target_counterparty_id' => array_key_exists('target_counterparty_id', $data)
                ? (!empty($data['target_counterparty_id']) ? (int)$data['target_counterparty_id'] : null)
                : $rule['target_counterparty_id'],
            ':target_counterparty_type' => array_key_exists('target_counterparty_type', $data)
                ? (!empty($data['target_counterparty_type']) ? trim($data['target_counterparty_type']) : null)
                : $rule['target_counterparty_type'],
            ':auto_apply' => isset($data['auto_apply']) ? (!empty($data['auto_apply']) ? 1 : 0) : (int)$rule['auto_apply'],
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
            ':id' => $id,
        ]);
    }

    public static function setActive(PDO $pdo, int $id, bool $active, array $user): void
    {
        $rule = self::findRule($pdo, $id);
        if (!$rule) {
            throw new \InvalidArgumentException('Правило не найдено.');
        }

        $stmt = $pdo->prepare(
            'UPDATE finance_matching_rules
                SET active = :active,
                    updated_by_user_id = :updated_by_user_id,
                    updated_by_role = :updated_by_role
              WHERE id = :id'
        );
        $stmt->execute([
            ':active' => $active ? 1 : 0,
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
            ':id' => $id,
        ]);
    }

    public static function setPriority(PDO $pdo, int $id, int $priority, array $user): void
    {
        $rule = self::findRule($pdo, $id);
        if (!$rule) {
            throw new \InvalidArgumentException('Правило не найдено.');
        }

        $stmt = $pdo->prepare(
            'UPDATE finance_matching_rules
                SET priority = :priority,
                    updated_by_user_id = :updated_by_user_id,
                    updated_by_role = :updated_by_role
              WHERE id = :id'
        );
        $stmt->execute([
            ':priority' => $priority,
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
            ':id' => $id,
        ]);
    }

    // ── Matching / Preview / Apply ──

    public static function previewRule(PDO $pdo, array $rule, int $limit = 10): array
    {
        $conditions = [];
        $params = [];

        if (!empty($rule['direction'])) {
            if ($rule['direction'] === 'INCOME') {
                $conditions[] = 'bt.credit_amount > 0';
            } elseif ($rule['direction'] === 'EXPENSE') {
                $conditions[] = 'bt.debit_amount > 0';
            }
        }
        if (!empty($rule['bank_account_id'])) {
            $conditions[] = 'bt.account_id = :bank_account_id';
            $params[':bank_account_id'] = (int)$rule['bank_account_id'];
        }
        if (!empty($rule['counterparty_inn'])) {
            $conditions[] = 'bt.counterparty_inn = :counterparty_inn';
            $params[':counterparty_inn'] = $rule['counterparty_inn'];
        }
        if (!empty($rule['purpose_contains'])) {
            $conditions[] = 'bt.purpose LIKE :purpose_contains';
            $params[':purpose_contains'] = '%' . $rule['purpose_contains'] . '%';
        }
        if (!empty($rule['purpose_regex'])) {
            $conditions[] = 'bt.purpose REGEXP :purpose_regex';
            $params[':purpose_regex'] = $rule['purpose_regex'];
        }
        if (isset($rule['amount_from']) && $rule['amount_from'] !== '' && $rule['amount_from'] !== null) {
            $conditions[] = '(bt.credit_amount >= :amount_from_credit OR bt.debit_amount >= :amount_from_debit)';
            $normalizedFrom = self::decimalFromCents(self::cents($rule['amount_from']));
            $params[':amount_from_credit'] = $normalizedFrom;
            $params[':amount_from_debit'] = $normalizedFrom;
        }
        if (isset($rule['amount_to']) && $rule['amount_to'] !== '' && $rule['amount_to'] !== null) {
            $conditions[] = '(bt.credit_amount <= :amount_to_credit OR bt.debit_amount <= :amount_to_debit)';
            $normalizedTo = self::decimalFromCents(self::cents($rule['amount_to']));
            $params[':amount_to_credit'] = $normalizedTo;
            $params[':amount_to_debit'] = $normalizedTo;
        }

        if ($conditions === []) {
            return [];
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT bt.*, ba.account_number
                  FROM bank_transactions bt
                  JOIN bank_accounts ba ON ba.id = bt.account_id
                  LEFT JOIN finance_operations fo ON fo.bank_transaction_id = bt.id AND fo.status != 'CANCELLED'
                 WHERE {$where}
                   AND fo.id IS NULL
                 ORDER BY bt.operation_date DESC
                 LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function applyRuleToTransaction(?PDO $pdo, array $rule, array $transaction): array
    {
        $result = [
            'rule_id' => null,
            'reason' => null,
            'confidence' => 'low',
            'result' => 'no_match',
            'applied_at' => null,
            'system' => true,
        ];

        $score = 0;
        $reasons = [];

        if (!empty($rule['direction'])) {
            $creditCents = self::cents($transaction['credit_amount'] ?? '0.00');
            $debitCents = self::cents($transaction['debit_amount'] ?? '0.00');
            $txType = $creditCents > 0 ? 'INCOME' : ($debitCents > 0 ? 'EXPENSE' : null);
            if ($txType !== null && $rule['direction'] === $txType) {
                $score += 10;
                $reasons[] = 'direction';
            } else {
                return $result;
            }
        }

        if (!empty($rule['bank_account_id'])) {
            if ((int)($transaction['account_id'] ?? 0) === (int)$rule['bank_account_id']) {
                $score += 15;
                $reasons[] = 'bank_account';
            } else {
                return $result;
            }
        }

        if (!empty($rule['counterparty_inn'])) {
            $txInn = trim((string)($transaction['counterparty_inn'] ?? ''));
            if ($txInn !== '' && $txInn === trim($rule['counterparty_inn'])) {
                $score += 25;
                $reasons[] = 'exact_inn';
            }
        }

        if (!empty($rule['invoice_number_pattern'])) {
            $purpose = (string)($transaction['purpose'] ?? '');
            $pattern = '/' . str_replace('/', '\/', $rule['invoice_number_pattern']) . '/iu';
            if (@preg_match($pattern, $purpose)) {
                $score += 30;
                $reasons[] = 'invoice_pattern';
            }
        }

        if (!empty($rule['purpose_contains'])) {
            $purpose = self::lower((string)($transaction['purpose'] ?? ''));
            $needle = self::lower((string)$rule['purpose_contains']);
            if (str_contains($purpose, $needle)) {
                $score += 25;
                $reasons[] = 'purpose_contains';
            }
        }

        if (!empty($rule['purpose_regex'])) {
            $purpose = (string)($transaction['purpose'] ?? '');
            if (@preg_match($rule['purpose_regex'], $purpose)) {
                $score += 25;
                $reasons[] = 'purpose_regex';
            }
        }

        if (isset($rule['amount_from']) && $rule['amount_from'] !== '' && $rule['amount_from'] !== null) {
            $creditCents = self::cents($transaction['credit_amount'] ?? '0.00');
            $debitCents = self::cents($transaction['debit_amount'] ?? '0.00');
            $txAmountCents = $creditCents > 0 ? $creditCents : $debitCents;
            if ($txAmountCents >= self::cents($rule['amount_from'])) {
                $score += 10;
                $reasons[] = 'amount_from';
            }
        }

        if (isset($rule['amount_to']) && $rule['amount_to'] !== '' && $rule['amount_to'] !== null) {
            $creditCents = self::cents($transaction['credit_amount'] ?? '0.00');
            $debitCents = self::cents($transaction['debit_amount'] ?? '0.00');
            $txAmountCents = $creditCents > 0 ? $creditCents : $debitCents;
            if ($txAmountCents <= self::cents($rule['amount_to'])) {
                $score += 10;
                $reasons[] = 'amount_to';
            }
        }

        if ($score === 0) {
            return $result;
        }

        $result['rule_id'] = (int)$rule['id'];
        $result['reason'] = implode('+', $reasons);
        $result['score'] = $score;

        if ($score >= 45) {
            $result['confidence'] = 'high';
        } elseif ($score >= 20) {
            $result['confidence'] = 'medium';
        } else {
            $result['confidence'] = 'low';
        }

        if ($score >= 45 && !empty($rule['auto_apply'])) {
            $result['result'] = 'auto_apply';
            $result['applied_at'] = date('Y-m-d H:i:s');
        } elseif ($score >= 20) {
            $result['result'] = 'suggest';
        } elseif ($score > 0) {
            $result['result'] = 'possible';
        }

        return $result;
    }

    public static function findBestMatchingRule(PDO $pdo, array $transaction): ?array
    {
        return self::selectBestMatchingRule(self::fetchRules($pdo, ['active' => true]), $transaction);
    }

    /**
     * Pure selector used by runtime and regression tests. Auto-application is fail-closed:
     * more than one eligible auto rule produces an ambiguous suggestion instead of mutation.
     */
    public static function selectBestMatchingRule(array $rules, array $transaction): ?array
    {
        usort($rules, static fn(array $a, array $b): int =>
            ((int)($a['priority'] ?? 100) <=> (int)($b['priority'] ?? 100))
            ?: strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''))
        );

        $matches = [];
        foreach ($rules as $rule) {
            if (isset($rule['active']) && !$rule['active']) {
                continue;
            }
            $result = self::applyRuleToTransaction(null, $rule, $transaction);
            if (($result['result'] ?? 'no_match') !== 'no_match') {
                $matches[] = ['rule' => $rule, 'result' => $result];
            }
        }
        if ($matches === []) {
            return null;
        }

        $auto = array_values(array_filter($matches, static fn(array $m): bool => ($m['result']['result'] ?? '') === 'auto_apply'));
        if (count($auto) === 1) {
            return $auto[0];
        }
        if (count($auto) > 1) {
            usort($auto, static fn(array $a, array $b): int =>
                ((int)($b['result']['score'] ?? 0) <=> (int)($a['result']['score'] ?? 0))
                ?: ((int)($a['rule']['priority'] ?? 100) <=> (int)($b['rule']['priority'] ?? 100))
            );
            $best = $auto[0];
            $best['result']['result'] = 'ambiguous';
            $best['result']['confidence'] = 'low';
            $best['result']['applied_at'] = null;
            $best['result']['reason'] = 'ambiguous_multiple_auto_rules';
            $best['result']['candidate_rule_ids'] = array_map(static fn(array $m): int => (int)$m['rule']['id'], $auto);
            return $best;
        }

        usort($matches, static fn(array $a, array $b): int =>
            ((int)($b['result']['score'] ?? 0) <=> (int)($a['result']['score'] ?? 0))
            ?: ((int)($a['rule']['priority'] ?? 100) <=> (int)($b['rule']['priority'] ?? 100))
        );
        return $matches[0];
    }

    public static function applyAutoMatchToOperation(PDO $localPdo, int $operationId): array
    {
        $stmt = $localPdo->prepare(
            'SELECT fo.*, bt.id AS bank_tx_id, bt.counterparty_inn, bt.counterparty_name,
                    bt.purpose, bt.credit_amount, bt.debit_amount, bt.account_id
               FROM finance_operations fo
               LEFT JOIN bank_transactions bt ON bt.id = fo.bank_transaction_id
              WHERE fo.id = ?'
        );
        $stmt->execute([$operationId]);
        $operation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$operation) {
            return ['matched' => false, 'reason' => 'Operation not found'];
        }

        $match = self::findBestMatchingRule($localPdo, $operation);
        if ($match === null) {
            return ['matched' => false, 'reason' => 'No matching rule'];
        }

        $rule = $match['rule'];
        $mResult = $match['result'];

        if ($mResult['result'] === 'auto_apply') {
            $updates = [];
            $oldValues = [];

            if (!empty($rule['target_dds_category_id']) && empty($operation['dds_category_id'])) {
                $oldValues['dds_category_id'] = $operation['dds_category_id'];
                $updates['dds_category_id'] = (int)$rule['target_dds_category_id'];
            }
            if (!empty($rule['target_counterparty_id']) && empty($operation['counterparty_entity_id'])) {
                $oldValues['counterparty_entity_id'] = $operation['counterparty_entity_id'];
                $oldValues['counterparty_entity_type'] = $operation['counterparty_entity_type'];
                $updates['counterparty_entity_id'] = (int)$rule['target_counterparty_id'];
                $updates['counterparty_entity_type'] = $rule['target_counterparty_type'] ?? $operation['counterparty_entity_type'];
            }

            if ($updates !== []) {
                $setClauses = [];
                $updateParams = [':id' => $operationId];
                foreach ($updates as $col => $val) {
                    $setClauses[] = "{$col} = :{$col}";
                    $updateParams[":{$col}"] = $val;
                }
                $upd = $localPdo->prepare(
                    'UPDATE finance_operations SET ' . implode(', ', $setClauses) . ' WHERE id = :id'
                );
                $upd->execute($updateParams);

                $newValues = array_merge($oldValues, $updates);

                FinanceAuditLogService::log(
                    $localPdo,
                    'finance_operation',
                    $operationId,
                    'rule_apply',
                    $oldValues,
                    $newValues,
                    $operation['created_by_user_id'] ?? 0,
                    $operation['created_by_role'] ?? 'system'
                );

                self::persistMatchingResult($localPdo, $operationId, $rule, $mResult, 'auto_apply');

                return [
                    'matched' => true,
                    'rule_id' => (int)$rule['id'],
                    'confidence' => $mResult['confidence'],
                    'reason' => $mResult['reason'],
                    'updates' => $updates,
                ];
            }

            self::persistMatchingResult($localPdo, $operationId, $rule, $mResult, 'auto_apply');
        }

        return [
            'matched' => true,
            'rule_id' => (int)$rule['id'],
            'confidence' => $mResult['confidence'],
            'reason' => $mResult['reason'],
            'result' => $mResult['result'],
            'updates' => [],
        ];
    }

    public static function persistMatchingResult(PDO $pdo, int $operationId, array $rule, array $mResult, string $source = 'system'): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO finance_matching_results
                (finance_operation_id, rule_id, reason, confidence, result, applied_at, source, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $operationId,
            (int)$rule['id'],
            $mResult['reason'] ?? null,
            $mResult['confidence'] ?? 'low',
            $mResult['result'] ?? 'no_match',
            $mResult['applied_at'] ?? date('Y-m-d H:i:s'),
            $source,
        ]);
    }

    public static function testRuleOnTransaction(PDO $pdo, array $rule, int $bankTransactionId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM bank_transactions WHERE id = ?');
        $stmt->execute([$bankTransactionId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx) {
            return ['error' => 'Transaction not found'];
        }

        return self::applyRuleToTransaction($pdo, $rule, $tx);
    }
}
