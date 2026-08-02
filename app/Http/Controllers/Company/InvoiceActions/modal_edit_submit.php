<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;

requireRole(['company_owner']);
verifyCsrfRequest();

$errors = [];
$success = null;

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

    $invoiceId = (int) $id;
    $existing = FinanceInvoiceService::fetchInvoiceById($localPdo, $invoiceId);

    if (!$existing) {
        throw new \RuntimeException('Счёт не найден.');
    }

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

    $linkRouteId = $_POST['link_route_id'] ?? null;
    $linkPaymentId = $_POST['link_payment_id'] ?? null;
    $linkSide = $_POST['link_side'] ?? null;

    $linkRouteInt = $linkRouteId !== null && $linkRouteId !== '' ? (int) $linkRouteId : null;
    $linkPaymentInt = $linkPaymentId !== null && $linkPaymentId !== '' ? (int) $linkPaymentId : null;
    $linkValidationError = FinanceInvoiceService::validateRouteLink($localPdo, $linkRouteInt, $linkPaymentInt, $linkSide);
    if ($linkValidationError !== null) {
        $errors[] = $linkValidationError;
    }

    if ($errors === []) {
        FinanceInvoiceService::updateInvoice($localPdo, $invoiceId, [
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

        $localPdo->prepare("DELETE FROM finance_invoice_links WHERE invoice_id = ?")->execute([$invoiceId]);

        if ($linkRouteInt !== null) {
            FinanceInvoiceService::createInvoiceLink($localPdo, $invoiceId, [
                'linear_route_id' => $linkRouteInt,
                'linear_route_payment_id' => $linkPaymentInt,
                'amount' => $normalizedAmount,
                'side' => $linkSide,
            ], $userId, $roleCode);
        }

        $invoice = FinanceInvoiceService::fetchInvoiceById($localPdo, $invoiceId);
        $links = FinanceInvoiceService::fetchInvoiceLinks($localPdo, $invoiceId);
        $canEdit = in_array($roleCode, ['company_owner'], true);
        $canDelete = $roleCode === 'company_owner';

        require base_path('app/View/partials/company_invoice_modal_view.php');
        exit;
    }

    $formError = implode(' ', $errors);
    $old = $_POST;
    $invoice = $existing;
    $routes = FinanceInvoiceService::fetchRoutesForSelect($localPdo, $user);
    $clients = FinanceInvoiceService::fetchClientsForSelect($localPdo);
    $contractors = FinanceInvoiceService::fetchContractorsForSelect($localPdo);
    $isEdit = true;
    require base_path('app/View/partials/company_invoice_create_form.php');
} catch (\Throwable $e) {
    echo '<div class="form-alert alert-error">Ошибка: ' . e($e->getMessage()) . '</div>';
}
