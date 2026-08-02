<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;

requireRole(['company_owner']);
verifyCsrfRequest();

$errors = [];
$success = null;
$dbError = null;
$invPage = 1;
$invPerPage = 100;
$invTotal = 0;
$invPages = 1;

try {
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);

    if (!$company || $company['status'] !== 'active') {
        throw new \RuntimeException('Компания недоступна.');
    }

    $localConfig = companyDatabaseConfig($config, $company);
    $localDb = new Database($localConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $user = $_SESSION['user'] ?? [];
    $userId = (int) ($user['user_id'] ?? 0);
    $roleCode = (string) ($user['role_code'] ?? '');

    $direction = $_POST['direction'] ?? '';
    $number = trim((string) ($_POST['number'] ?? ''));
    $invoiceDate = trim((string) ($_POST['invoice_date'] ?? ''));
    $counterpartyNorm = FinanceInvoiceService::normalizeCounterpartyInput(
        $localPdo,
        $_POST['counterparty_entity_type'] ?? null,
        $_POST['counterparty_entity_id'] ?? null,
        $_POST['counterparty_name'] ?? '',
        $_POST['counterparty_inn'] ?? ''
    );
    $errors = array_merge($errors, $counterpartyNorm['errors']);
    $counterpartyType = $counterpartyNorm['type'];
    $counterpartyId = $counterpartyNorm['id'];
    $counterpartyName = $counterpartyNorm['name'];
    $counterpartyInn = $counterpartyNorm['inn'];
    $amount = trim((string) ($_POST['amount'] ?? '0'));
    $vatRate = $_POST['vat_rate'] !== '' ? $_POST['vat_rate'] : null;
    $basis = trim((string) ($_POST['basis'] ?? ''));
    $plannedDate = trim((string) ($_POST['planned_payment_date'] ?? ''));
    $comment = trim((string) ($_POST['comment'] ?? ''));
    $status = $_POST['status'] ?? 'draft';

    $linkRouteId = $_POST['link_route_id'] ?? null;
    $linkPaymentId = $_POST['link_payment_id'] ?? null;
    $linkSide = $_POST['link_side'] ?? null;

    if (!in_array($direction, [FinanceInvoiceService::DIRECTION_OUTGOING, FinanceInvoiceService::DIRECTION_INCOMING], true)) {
        $errors[] = 'Укажите направление счёта.';
    }
    if ($number === '') {
        $errors[] = 'Укажите номер счёта.';
    }
    if ($invoiceDate === '') {
        $errors[] = 'Укажите дату счёта.';
    }
    $normalizedAmount = FinanceInvoiceService::normalizeMoneyInput($amount);
    if ($normalizedAmount === null) {
        $errors[] = 'Сумма должна быть больше нуля.';
    }

    if (!FinanceInvoiceService::isVatRateInputValid($vatRate)) {
        $errors[] = 'Некорректное значение НДС. Допустимые значения: Без НДС, 0%, 5%, 7%, 20%, 22%.';
    }
    $normalizedVat = FinanceInvoiceService::normalizeVatRateInput($vatRate);

    if ($errors === [] && $normalizedAmount !== null) {
        $duplicateStmt = $localPdo->prepare(
            "SELECT id
               FROM finance_invoices
              WHERE direction = :direction
                AND number = :number
                AND invoice_date = :invoice_date
                AND amount = :amount
                AND COALESCE(counterparty_entity_type, '') = :counterparty_type
                AND COALESCE(counterparty_entity_id, 0) = :counterparty_id
                AND COALESCE(counterparty_name, '') = :counterparty_name
                AND cancelled_at IS NULL
              LIMIT 1"
        );
        $duplicateStmt->execute([
            ':direction' => $direction,
            ':number' => $number,
            ':invoice_date' => $invoiceDate,
            ':amount' => $normalizedAmount,
            ':counterparty_type' => $counterpartyType ?? '',
            ':counterparty_id' => $counterpartyId ?? 0,
            ':counterparty_name' => $counterpartyName,
        ]);
        if ($duplicateStmt->fetchColumn() !== false) {
            $errors[] = 'Идентичный счёт уже существует.';
        }
    }

    $linkRouteInt = $linkRouteId !== null && $linkRouteId !== '' ? (int) $linkRouteId : null;
    $linkPaymentInt = $linkPaymentId !== null && $linkPaymentId !== '' ? (int) $linkPaymentId : null;
    $linkValidationError = FinanceInvoiceService::validateRouteLink($localPdo, $linkRouteInt, $linkPaymentInt, $linkSide);
    if ($linkValidationError !== null) {
        $errors[] = $linkValidationError;
    }

    if ($errors === []) {
        $invoiceId = FinanceInvoiceService::createInvoice($localPdo, [
            'direction' => $direction,
            'number' => $number,
            'invoice_date' => $invoiceDate,
            'counterparty_entity_type' => $counterpartyType,
            'counterparty_entity_id' => $counterpartyId,
            'counterparty_name' => $counterpartyName,
            'counterparty_inn' => $counterpartyInn,
            'amount' => $normalizedAmount,
            'vat_rate' => $normalizedVat,
            'basis' => $basis,
            'planned_payment_date' => $plannedDate !== '' ? $plannedDate : null,
            'comment' => $comment,
            'status' => $status,
        ], $userId, $roleCode);

        if ($linkRouteInt !== null) {
            FinanceInvoiceService::createInvoiceLink($localPdo, $invoiceId, [
                'linear_route_id' => $linkRouteInt,
                'linear_route_payment_id' => $linkPaymentInt,
                'amount' => $normalizedAmount,
                'side' => $linkSide,
            ], $userId, $roleCode);
        }

        $_SESSION['invoice_success'] = 'Счёт №' . e($number) . ' создан.';

        $redirectUrl = app_url('/company/finance/invoices');
        header('Location: ' . $redirectUrl);
        exit;
    }

    $formError = implode(' ', $errors);
    $old = $_POST;
    $invResult = FinanceInvoiceService::fetchInvoices($localPdo, $user);
    $invoices = $invResult['data'];
    $invTotal = $invResult['total'];
    $invPages = $invResult['pages'];
} catch (\Throwable $e) {
    $formError = 'Ошибка: ' . $e->getMessage();
    $old = $_POST;
}

$showCreateModal = true;
ob_start();
require base_path('app/View/pages/company_finance_invoices.php');
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
