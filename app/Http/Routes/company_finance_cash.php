<?php

/**
 * CASH is historical-only from 2026-08-18.
 *
 * We intentionally keep the legacy URLs registered so bookmarks and old links
 * do not produce 404s, but no route below can create or mutate a CASH record.
 * Existing finance_operations/finance_cash_resolutions remain untouched for
 * reports, audit and historical employee ledgers.
 */

$cashRetiredGet = static function (): void {
    requireRole(['company_owner']);
    $_SESSION['employee_payments_success'] = 'Касса выведена из рабочего контура. Исторические операции сохранены в финансовой истории.';
    redirect_to('/company/finance/employee-payments');
};

$cashRetiredPost = static function (): void {
    requireRole(['company_owner']);
    verifyCsrfRequest();
    $_SESSION['employee_payments_error'] = 'Создание и изменение кассовых операций отключено. Используйте прямые операции банка и сотрудников.';
    redirect_to('/company/finance/employee-payments');
};

$router->get('/company/finance/cash', $cashRetiredGet);
$router->get('/company/finance/cash/account-create', $cashRetiredGet);
$router->post('/company/finance/cash/account-create', $cashRetiredPost);
$router->get('/company/finance/cash/operation-create', $cashRetiredGet);
$router->post('/company/finance/cash/operation-create', $cashRetiredPost);
$router->get('/company/finance/cash/transfer-create', $cashRetiredGet);
$router->post('/company/finance/cash/transfer-create', $cashRetiredPost);
$router->post('/company/finance/cash/dispatch-employee', $cashRetiredPost);
