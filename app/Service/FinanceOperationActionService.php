<?php

namespace App\Service;

use PDO;

final class FinanceOperationActionService
{
    public static function cancelOperation(PDO $pdo, int $operationId, array $user, string $reason): array
    {
        $started = !$pdo->inTransaction();
        if ($started) $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM finance_operations WHERE id = ? FOR UPDATE');
            $stmt->execute([$operationId]);
            $operation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$operation) throw new \RuntimeException('Операция не найдена.');
            if (($operation['status'] ?? '') === 'CANCELLED') {
                if ($started) $pdo->commit();
                return ['changed' => false, 'operation' => $operation];
            }
            if (!in_array($operation['status'] ?? '', ['POSTED', 'PENDING_CONFIRMATION'], true)) {
                throw new \RuntimeException('Отменять можно только проведённые операции или ожидающие подтверждения.');
            }
            $reason = trim($reason);
            if ($reason === '') throw new \RuntimeException('Укажите причину отмены.');

            $allocStmt = $pdo->prepare('SELECT COUNT(*) FROM finance_operation_allocations WHERE operation_id = ? AND cancelled_at IS NULL');
            $allocStmt->execute([$operationId]);
            if ((int) $allocStmt->fetchColumn() > 0) {
                throw new \RuntimeException('Сначала отмените активные распределения этой операции.');
            }

            $userId = (int) ($user['user_id'] ?? 0);
            $roleCode = (string) ($user['role_code'] ?? '');
            $oldStatus = (string) $operation['status'];
            $upd = $pdo->prepare("UPDATE finance_operations SET status = 'CANCELLED', cancelled_at = NOW(), cancelled_by_user_id = :user_id, cancelled_by_role = :role, cancellation_reason = :reason, updated_at = NOW() WHERE id = :id AND status <> 'CANCELLED'");
            $upd->execute([':user_id' => $userId, ':role' => $roleCode, ':reason' => $reason, ':id' => $operationId]);
            FinanceAuditLogService::log($pdo, 'finance_operation', $operationId, 'cancel', [
                'status' => $oldStatus,
                'operation_type' => $operation['operation_type'] ?? null,
                'amount' => $operation['amount'] ?? null,
            ], ['status' => 'CANCELLED', 'cancellation_reason' => $reason], $userId, $roleCode);
            if ($started) $pdo->commit();
            $operation['status'] = 'CANCELLED';
            $operation['cancellation_reason'] = $reason;
            return ['changed' => true, 'operation' => $operation];
        } catch (\Throwable $e) {
            if ($started && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function fetchOperationHistory(PDO $pdo, int $operationId, int $limit = 100): array
    {
        $stmt = $pdo->prepare("SELECT fal.* FROM finance_audit_log fal WHERE (fal.entity_type = 'finance_operation' AND fal.entity_id = :operation_id) OR (fal.entity_type = 'finance_allocation' AND fal.entity_id IN (SELECT foa.id FROM finance_operation_allocations foa WHERE foa.operation_id = :operation_id2)) ORDER BY fal.created_at DESC, fal.id DESC LIMIT :limit");
        $stmt->bindValue(':operation_id', $operationId, PDO::PARAM_INT);
        $stmt->bindValue(':operation_id2', $operationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
