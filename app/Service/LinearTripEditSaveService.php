<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

final class LinearTripEditSaveService
{
    /** @return array{success:bool,errors:array<string,string>,message:string} */
    public static function save(
        PDO $pdo,
        int $companyId,
        int $routeId,
        array $sessionUser,
        array $post,
        array $files,
        string $requestToken,
        array $existingDocsByCode
    ): array {
        $roleCode = (string) ($sessionUser['role_code'] ?? '');
        $userId = (int) ($sessionUser['user_id'] ?? 0);
        $isFinanceRealm = $roleCode === 'company_owner';
        $errors = [];

        $routeType = trim((string) ($post['route_type'] ?? ''));
        $clientId = (int) ($post['client_id'] ?? 0);
        $carrierId = (int) ($post['carrier_contractor_id'] ?? 0);
        $routeExecutorId = (int) ($post['route_executor_id'] ?? 0);
        $cargoTypeName = LinearRouteService::normalizeCargoTypeName((string) ($post['cargo_type_name'] ?? ''));
        $plannedLoadingDate = LinearRouteService::normalizeDate($post['planned_loading_date'] ?? '');
        $plannedUnloadingDate = LinearRouteService::normalizeDate($post['planned_unloading_date'] ?? '');
        $actualLoadingDate = LinearRouteService::normalizeDate($post['actual_loading_date'] ?? '');
        $actualUnloadingDate = LinearRouteService::normalizeDate($post['actual_unloading_date'] ?? '');
        $comments = trim((string) ($post['comments'] ?? ''));

        if (!in_array($routeType, [LinearRouteService::ROUTE_TYPE_LINEAR, LinearRouteService::ROUTE_TYPE_AGENCY], true)) {
            $errors['route_type'] = 'Выберите тип рейса.';
        }
        if ($clientId <= 0) $errors['client_id'] = 'Выберите заказчика.';
        if ($carrierId <= 0) $errors['carrier_contractor_id'] = 'Выберите перевозчика.';
        if ($routeExecutorId <= 0) $errors['route_executor_id'] = 'Выберите исполнителя рейса.';
        if ($cargoTypeName === '') $errors['cargo_type_name'] = 'Укажите тип груза.';
        if ($plannedLoadingDate === null) $errors['planned_loading_date'] = 'Укажите плановую дату загрузки.';
        if ($plannedLoadingDate !== null && $plannedUnloadingDate !== null && $plannedUnloadingDate < $plannedLoadingDate) {
            $errors['planned_unloading_date'] = 'Дата выгрузки не может быть раньше даты загрузки.';
        }
        if ($actualLoadingDate !== null && $actualUnloadingDate !== null && $actualUnloadingDate < $actualLoadingDate) {
            $errors['actual_unloading_date'] = 'Фактическая выгрузка не может быть раньше фактической загрузки.';
        }

        $customerPayments = [];
        $carrierPayments = [];
        $principalRows = [];
        $principalPaymentMap = [];

        if ($isFinanceRealm) {
            $customerPayments = self::parsePaymentRows((array) ($post['customer_payments'] ?? []), 'Заказчик', 'customer_payments', $errors);
            $carrierPayments = self::parsePaymentRows((array) ($post['carrier_payments'] ?? []), 'Перевозчик', 'carrier_payments', $errors);
            if ($customerPayments === []) $errors['customer_payments'] = 'Добавьте хотя бы одну оплату для заказчика.';
            if ($carrierPayments === []) $errors['carrier_payments'] = 'Добавьте хотя бы одну оплату для перевозчика.';

            if ($routeType === LinearRouteService::ROUTE_TYPE_AGENCY) {
                foreach (array_values((array) ($post['principal_rows'] ?? [])) as $rowIndex => $row) {
                    if (!is_array($row)) continue;
                    $entityKey = trim((string) ($row['entity_key'] ?? ''));
                    $payments = self::parsePaymentRows((array) ($row['payments'] ?? []), 'Принципал', 'principal_rows.' . $rowIndex . '.payments', $errors);
                    if ($entityKey === '' && $payments === []) continue;

                    $principal = LinearRouteService::parsePrincipalEntityKey($entityKey);
                    if ($principal === null) {
                        $errors['principal_rows.' . $rowIndex . '.entity_key'] = 'Выберите принципала.';
                        continue;
                    }
                    if (LinearRouteService::principalContractSide($principal['principal_type'], (int) $principal['principal_id'], $clientId, $carrierId) === null) {
                        $errors['principal_rows.' . $rowIndex . '.entity_key'] = 'Принципалом может быть только выбранный заказчик или выбранный перевозчик.';
                        continue;
                    }
                    if ($payments === []) $errors['principal_rows.' . $rowIndex . '.payments'] = 'Добавьте хотя бы одну оплату для принципала.';

                    $normalizedKey = LinearRouteService::principalEntityKey($principal['principal_type'], (int) $principal['principal_id']);
                    if (isset($principalPaymentMap[$normalizedKey])) {
                        $errors['principal_rows.' . $rowIndex . '.entity_key'] = 'Такой принципал уже добавлен.';
                        continue;
                    }
                    $principalRows[] = $principal;
                    $principalPaymentMap[$normalizedKey] = $payments;
                }
                if ($principalRows === []) $errors['principal_rows'] = 'Добавьте хотя бы одного принципала.';
            }
        }

        if ($clientId > 0 && !LinearRouteService::isVisibleEntity($pdo, 'clients', $clientId, $sessionUser, 'client')) {
            $errors['client_id'] = 'Заказчик недоступен.';
        }
        if ($carrierId > 0 && !LinearRouteService::isVisibleEntity($pdo, 'contractors', $carrierId, $sessionUser, 'contractor')) {
            $errors['carrier_contractor_id'] = 'Перевозчик недоступен.';
        }
        $visibleExecutors = LinearRouteService::fetchVisibleRouteExecutors($pdo, $sessionUser);
        $visibleExecutorIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $visibleExecutors);
        if ($routeExecutorId > 0 && !in_array($routeExecutorId, $visibleExecutorIds, true)) {
            $errors['route_executor_id'] = 'Исполнитель рейса недоступен.';
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors, 'message' => 'Форма содержит ошибки. Проверьте отмеченные поля.'];
        }

        $stagedPlans = [];
        $movedFinalFiles = [];
        try {
            $stagedPlans = LinearTripDocumentUploadService::stage(
                $files,
                $post,
                LinearRouteService::routeDocumentDefinitions($routeType),
                $existingDocsByCode,
                $companyId,
                $routeId,
                $requestToken
            );

            $pdo->beginTransaction();
            $lockedRoute = self::lockRoute($pdo, $routeId);
            if ($lockedRoute === null) throw new RuntimeException('Route disappeared before save.');
            if (!LinearRouteService::canEditRoute($lockedRoute, $pdo, $sessionUser)) {
                throw new LinearTripDocumentUploadException('У вас нет права редактировать этот рейс.', 'route_access_denied');
            }

            $cargoTypeId = LinearRouteService::createOrFindCargoType($pdo, $cargoTypeName, $userId, $roleCode);
            $update = $pdo->prepare(
                'UPDATE linear_routes
                    SET route_type = :route_type,
                        client_id = :client_id,
                        carrier_contractor_id = :carrier_id,
                        principal_type = NULL,
                        principal_id = NULL,
                        agency_contract_with = NULL,
                        route_executor_id = :route_executor_id,
                        cargo_type_id = :cargo_type_id,
                        planned_loading_date = :planned_loading_date,
                        planned_unloading_date = :planned_unloading_date,
                        actual_loading_date = :actual_loading_date,
                        actual_unloading_date = :actual_unloading_date,
                        comments = :comments,
                        updated_by_user_id = :updated_by_user_id,
                        updated_by_role = :updated_by_role
                  WHERE id = :id AND deleted_at IS NULL'
            );
            $update->execute([
                ':route_type' => $routeType,
                ':client_id' => $clientId,
                ':carrier_id' => $carrierId,
                ':route_executor_id' => $routeExecutorId,
                ':cargo_type_id' => $cargoTypeId,
                ':planned_loading_date' => $plannedLoadingDate,
                ':planned_unloading_date' => $plannedUnloadingDate,
                ':actual_loading_date' => $actualLoadingDate,
                ':actual_unloading_date' => $actualUnloadingDate,
                ':comments' => $comments !== '' ? $comments : null,
                ':updated_by_user_id' => $userId,
                ':updated_by_role' => $roleCode,
                ':id' => $routeId,
            ]);
            if ($update->rowCount() === 0 && self::lockRoute($pdo, $routeId) === null) {
                throw new RuntimeException('Route update failed.');
            }

            if ($isFinanceRealm) {
                self::storeFinance(
                    $pdo,
                    $routeId,
                    $routeType,
                    $clientId,
                    $carrierId,
                    $customerPayments,
                    $carrierPayments,
                    $principalRows,
                    $principalPaymentMap,
                    $plannedLoadingDate,
                    $plannedUnloadingDate,
                    $userId,
                    $roleCode
                );
            }

            LinearTripDocumentUploadService::persist($pdo, $stagedPlans, $companyId, $routeId, $userId, $roleCode, $movedFinalFiles);
            if ($routeType !== LinearRouteService::ROUTE_TYPE_AGENCY) {
                self::retirePrincipalDocuments($pdo, $existingDocsByCode, $routeId, $userId, $roleCode);
            }

            $pdo->commit();
            LinearTripDocumentUploadService::cleanupStaged($stagedPlans);
            return [
                'success' => true,
                'errors' => [],
                'message' => $stagedPlans === [] ? 'Рейс успешно сохранён.' : 'Рейс и документы успешно сохранены.',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            LinearTripDocumentUploadService::cleanupFinalFiles($movedFinalFiles);
            LinearTripDocumentUploadService::cleanupStaged($stagedPlans);
            throw $e;
        }
    }

    private static function parsePaymentRows(array $rows, string $scopeLabel, string $scopeKey, array &$errors): array
    {
        $result = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) continue;
            $amountRaw = trim((string) ($row['amount'] ?? ''));
            $paymentMethod = trim((string) ($row['payment_method'] ?? ''));
            $vatRateRaw = trim((string) ($row['vat_rate'] ?? ''));
            $conditionType = trim((string) ($row['condition_type'] ?? ''));
            $daysCountRaw = trim((string) ($row['days_count'] ?? ''));
            $daysKind = trim((string) ($row['days_kind'] ?? ''));
            $specificDueDateRaw = trim((string) ($row['specific_due_date'] ?? ''));
            $conditionComment = trim((string) ($row['condition_comment'] ?? ''));

            if ($amountRaw === '' && $paymentMethod === '' && $conditionType === '' && $daysCountRaw === '' && $daysKind === '' && $specificDueDateRaw === '' && $conditionComment === '') continue;

            $amount = LinearRouteService::parseAmount($amountRaw);
            if ($amount === null) $errors[$scopeKey . '.' . $index . '.amount'] = 'Укажите корректную сумму для блока «' . $scopeLabel . '».';
            if (!in_array($paymentMethod, ['cashless', 'cash', ''], true)) $errors[$scopeKey . '.' . $index . '.payment_method'] = 'Выберите способ оплаты для блока «' . $scopeLabel . '».';

            $vatRate = null;
            if ($vatRateRaw !== '') {
                if (in_array($vatRateRaw, ['0', '5', '7', '20', '22'], true)) $vatRate = $vatRateRaw;
                else $errors[$scopeKey . '.' . $index . '.vat_rate'] = 'Выберите ставку НДС для блока «' . $scopeLabel . '».';
            }
            if (!in_array($conditionType, LinearRouteService::CONDITION_TYPES, true)) {
                $errors[$scopeKey . '.' . $index . '.condition_type'] = 'Выберите корректный срок оплаты для блока «' . $scopeLabel . '».';
            }

            $daysCount = null;
            if (LinearRouteService::conditionTypeRequiresDays($conditionType)) {
                if ($daysCountRaw === '' || !ctype_digit($daysCountRaw) || (int) $daysCountRaw <= 0) {
                    $errors[$scopeKey . '.' . $index . '.days_count'] = 'Укажите количество дней для блока «' . $scopeLabel . '».';
                } else $daysCount = (int) $daysCountRaw;
                if (!array_key_exists($daysKind, LinearRouteService::PAYMENT_DUE_DAYS_KINDS)) {
                    $errors[$scopeKey . '.' . $index . '.days_kind'] = 'Выберите тип дней для блока «' . $scopeLabel . '».';
                }
            } else $daysKind = null;

            $specificDueDate = null;
            if (LinearRouteService::conditionTypeRequiresSpecificDate($conditionType)) {
                $specificDueDate = LinearRouteService::normalizeDate($specificDueDateRaw);
                if ($specificDueDate === null) $errors[$scopeKey . '.' . $index . '.specific_due_date'] = 'Укажите конкретную дату для блока «' . $scopeLabel . '».';
            }

            $result[] = [
                'amount' => $amount,
                'payment_type' => LinearRouteService::legacyPaymentTypeFromMethodAndVat($paymentMethod !== '' ? $paymentMethod : 'cashless', $vatRate),
                'payment_method' => $paymentMethod !== '' ? $paymentMethod : 'cashless',
                'vat_rate' => $vatRate,
                'condition_type' => $conditionType,
                'days_count' => $daysCount,
                'days_kind' => $daysKind,
                'specific_due_date' => $specificDueDate,
                'condition_comment' => $conditionComment !== '' ? $conditionComment : null,
            ];
        }
        return $result;
    }

    private static function lockRoute(PDO $pdo, int $routeId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM linear_routes WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([$routeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function storeFinance(
        PDO $pdo,
        int $routeId,
        string $routeType,
        int $clientId,
        int $carrierId,
        array $customerPayments,
        array $carrierPayments,
        array $principalRows,
        array $principalPaymentMap,
        ?string $plannedLoadingDate,
        ?string $plannedUnloadingDate,
        int $userId,
        string $roleCode
    ): void {
        $storedPrincipals = [];
        if ($routeType === LinearRouteService::ROUTE_TYPE_AGENCY) {
            $storedPrincipals = LinearRouteService::storeRoutePrincipals($pdo, $routeId, $principalRows, $clientId, $carrierId, $userId, $roleCode);
            LinearRouteService::syncLegacyRoutePrincipalFields($pdo, $routeId, $storedPrincipals, $clientId, $carrierId, $userId, $roleCode);
        } else {
            LinearRouteService::storeRoutePrincipals($pdo, $routeId, [], $clientId, $carrierId, $userId, $roleCode);
        }

        $oldPayments = LinearRouteService::fetchRoutePayments($pdo, $routeId);
        LinearRouteService::storeRoutePayments(
            $pdo,
            $routeId,
            $customerPayments,
            $carrierPayments,
            $principalPaymentMap,
            $storedPrincipals,
            $userId,
            $roleCode,
            ['planned_loading_date' => $plannedLoadingDate, 'planned_unloading_date' => $plannedUnloadingDate]
        );
        self::syncLegacyTerms($pdo, $routeId, $customerPayments, $carrierPayments, $principalPaymentMap, $userId, $roleCode);
        LinearRouteService::reconcileLegacyPaymentRows($pdo, $routeId);
        LinearRouteService::logFinanceAudit(
            $pdo,
            'linear_route',
            $routeId,
            'route_payments_update',
            ['payments' => $oldPayments],
            ['payments' => LinearRouteService::fetchRoutePayments($pdo, $routeId)],
            $userId,
            $roleCode
        );
    }

    private static function syncLegacyTerms(PDO $pdo, int $routeId, array $customerPayments, array $carrierPayments, array $principalPaymentMap, int $userId, string $roleCode): void
    {
        $upsert = $pdo->prepare(
            'INSERT INTO linear_route_financial_terms (
                linear_route_id, party_role, amount, payment_type, payment_due_type,
                payment_due_days, payment_due_days_kind, created_by_user_id,
                created_by_role, updated_by_user_id, updated_by_role
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                amount = VALUES(amount), payment_type = VALUES(payment_type),
                payment_due_type = VALUES(payment_due_type), payment_due_days = VALUES(payment_due_days),
                payment_due_days_kind = VALUES(payment_due_days_kind), deleted_at = NULL,
                deleted_by_user_id = NULL, deleted_by_role = NULL,
                updated_by_user_id = VALUES(updated_by_user_id), updated_by_role = VALUES(updated_by_role)'
        );
        $legacyRows = ['customer' => $customerPayments[0] ?? null, 'carrier' => $carrierPayments[0] ?? null, 'principal' => null];
        foreach ($principalPaymentMap as $payments) {
            if (!empty($payments[0])) {
                $legacyRows['principal'] = $payments[0];
                break;
            }
        }
        foreach ($legacyRows as $partyRole => $term) {
            if ($term === null) {
                $retire = $pdo->prepare('UPDATE linear_route_financial_terms SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ? WHERE linear_route_id = ? AND party_role = ? AND deleted_at IS NULL');
                $retire->execute([$userId, $roleCode, $routeId, $partyRole]);
                continue;
            }
            $upsert->execute([
                $routeId,
                $partyRole,
                $term['amount'],
                $term['payment_type'],
                self::legacyDueType((string) ($term['condition_type'] ?? '')),
                $term['days_count'],
                $term['days_kind'],
                $userId,
                $roleCode,
                $userId,
                $roleCode,
            ]);
        }
    }

    private static function legacyDueType(string $conditionType): string
    {
        return match ($conditionType) {
            DateCalculationService::CONDITION_PREPAYMENT => 'Предоплата на загрузке',
            DateCalculationService::CONDITION_AFTER_START => 'После загрузки',
            DateCalculationService::CONDITION_END_DAY => 'До выгрузки',
            DateCalculationService::CONDITION_AFTER_END,
            DateCalculationService::CONDITION_AFTER_DOCUMENTS => 'После выгрузки',
            default => 'После загрузки',
        };
    }

    private static function retirePrincipalDocuments(PDO $pdo, array $existingDocsByCode, int $routeId, int $userId, string $roleCode): void
    {
        foreach ((array) ($existingDocsByCode['principal_document'] ?? []) as $document) {
            $documentId = (int) ($document['id'] ?? 0);
            if ($documentId <= 0) continue;
            $stmt = $pdo->prepare(
                "UPDATE documents
                    SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ?,
                        delete_comment = 'Principal document removed after route type change'
                  WHERE id = ? AND entity_type = ? AND entity_id = ? AND deleted_at IS NULL"
            );
            $stmt->execute([$userId, $roleCode, $documentId, LinearTripDocumentUploadService::ENTITY_TYPE, $routeId]);
        }
    }
}
