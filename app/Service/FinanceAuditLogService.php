<?php

namespace App\Service;

use InvalidArgumentException;
use JsonException;
use PDO;

final class FinanceAuditLogService
{
    private const MAX_PAYLOAD_BYTES = 65535;
    private const SENSITIVE_KEY_PATTERN = '/(?:pass(?:word|wd)?|secret|token|api[_-]?key|private[_-]?key|encryption[_-]?key|cookie|session|authorization|credential)/i';

    public static function log(
        PDO $pdo,
        string $entityType,
        int $entityId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        int $userId,
        string $roleCode
    ): void {
        self::assertIdentity($entityType, $entityId, $action, $userId, $roleCode);

        $stmt = $pdo->prepare(
            "INSERT INTO finance_audit_log
                (entity_type, entity_id, action, old_values, new_values,
                 created_by_user_id, created_by_role, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $entityType,
            $entityId,
            $action,
            self::encodePayload($oldValues),
            self::encodePayload($newValues),
            $userId,
            $roleCode,
        ]);
    }

    public static function logStatusChange(
        PDO $pdo,
        string $entityType,
        int $entityId,
        string $oldStatus,
        string $newStatus,
        int $userId = 0,
        string $roleCode = 'system'
    ): void {
        self::log(
            $pdo,
            $entityType,
            $entityId,
            'status_change',
            ['status' => $oldStatus],
            ['status' => $newStatus],
            $userId,
            $roleCode
        );
    }

    public static function sanitizePayload(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        return self::sanitizeValue($values, 0);
    }

    private static function sanitizeValue(mixed $value, int $depth): mixed
    {
        if ($depth > 12) {
            return '[TRUNCATED_DEPTH]';
        }
        if (!is_array($value)) {
            if (is_resource($value) || is_object($value)) {
                return '[UNSERIALIZABLE]';
            }
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $keyString = (string) $key;
            if (preg_match(self::SENSITIVE_KEY_PATTERN, $keyString) === 1) {
                $result[$key] = '[REDACTED]';
                continue;
            }
            $result[$key] = self::sanitizeValue($item, $depth + 1);
        }
        return $result;
    }

    private static function encodePayload(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }
        try {
            $encoded = json_encode(
                self::sanitizePayload($values),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Audit payload is not valid UTF-8/JSON.', 0, $e);
        }
        if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw new InvalidArgumentException('Audit payload exceeds the maximum size.');
        }
        return $encoded;
    }

    private static function assertIdentity(string $entityType, int $entityId, string $action, int $userId, string $roleCode): void
    {
        if ($entityId <= 0) {
            throw new InvalidArgumentException('Audit entity id must be positive.');
        }
        foreach (['entity type' => $entityType, 'action' => $action, 'role' => $roleCode] as $label => $value) {
            if ($value === '' || preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $value) !== 1) {
                throw new InvalidArgumentException('Invalid audit ' . $label . '.');
            }
        }
        if ($userId < 0) {
            throw new InvalidArgumentException('Audit user id cannot be negative.');
        }
    }
}
