<?php

use App\Service\LocalMigrationService;

/**
 * Local schema migrations must never run as a side effect of normal ERP CRUD.
 * They are allowed only for CLI maintenance or the explicit SUPERADMIN tenant
 * provisioning endpoint. This prevents unrelated checksum/history problems in
 * an old migration from blocking clients, contractors, drivers, vehicles, etc.
 */
if (!function_exists('applyLocalMigrations')) {
    function applyLocalMigrations(PDO $localPdo): void
    {
        $isCli = PHP_SAPI === 'cli';
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
        $path = function_exists('current_app_path') ? current_app_path() : '';
        $role = (string) ($_SESSION['role_code'] ?? '');

        $isExplicitTenantProvisioning = $method === 'POST'
            && $path === '/superadmin/companies/create'
            && $role === 'superadmin';

        if (!$isCli && !$isExplicitTenantProvisioning) {
            return;
        }

        LocalMigrationService::apply($localPdo);
    }
}
