<?php

/**
 * Legacy money-account URLs are historical-only from 2026-08-18.
 *
 * Keep the old endpoints registered only for backward compatibility with
 * bookmarks and historical links. No route below can create or mutate an old
 * money-account record. Historical finance_operations and related audit data
 * stay intact for reports and employee settlement history.
 */

$legacyMoneyAccountGet = static function (): void {
    requireRole(['company_owner']);
    redirect_to('/company/finance/employee-payments');
};

$legacyMoneyAccountPost = static function (): void {
    requireRole(['company_owner']);
    verifyCsrfRequest();
    $_SESSION['employee_payments_error'] = 'Этот устаревший способ операции отключён. Используйте операции банка и сотрудников.';
    redirect_to('/company/finance/employee-payments');
};

$router->get('/company/finance/cash', $legacyMoneyAccountGet);
$router->get('/company/finance/cash/account-create', $legacyMoneyAccountGet);
$router->post('/company/finance/cash/account-create', $legacyMoneyAccountPost);
$router->get('/company/finance/cash/operation-create', $legacyMoneyAccountGet);
$router->post('/company/finance/cash/operation-create', $legacyMoneyAccountPost);
$router->get('/company/finance/cash/transfer-create', $legacyMoneyAccountGet);
$router->post('/company/finance/cash/transfer-create', $legacyMoneyAccountPost);
$router->post('/company/finance/cash/dispatch-employee', $legacyMoneyAccountPost);
