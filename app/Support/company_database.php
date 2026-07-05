<?php

if (!function_exists('companyDatabaseConfig')) {
    function companyDatabaseConfig(array $config, array $company): array
    {
        $cfg = $config['database'];
        $cfg['database'] = $company['db_identifier'] ?? $cfg['database'];
        if (!empty($company['db_host'])) {
            $cfg['host'] = $company['db_host'];
        }
        if (!empty($company['db_port'])) {
            $cfg['port'] = $company['db_port'];
        }
        if (!empty($company['db_username'])) {
            $cfg['username'] = $company['db_username'];
        }
        if (!empty($company['db_password'])) {
            $cfg['password'] = $company['db_password'];
        }
        return $cfg;
    }
}

if (!function_exists('parseCompanyDbPool')) {
    function parseCompanyDbPool(): array
    {
        $json = env('COMPANY_DB_POOL_JSON', '');
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $pool = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $database = $entry['database'] ?? $entry['db'] ?? '';
            $username = $entry['username'] ?? '';
            $password = $entry['password'] ?? '';
            if ($database === '' || $username === '' || $password === '') {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
                continue;
            }
            $pool[] = [
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'host'     => $entry['host'] ?? '',
                'port'     => $entry['port'] ?? '',
            ];
        }
        return $pool;
    }
}

if (!function_exists('ensureCompanyDatabaseColumns')) {
    function ensureCompanyDatabaseColumns(PDO $pdo): void
    {
        $needed = [
            'db_host'     => 'VARCHAR(255) NULL',
            'db_port'     => 'INT NULL',
            'db_username' => 'VARCHAR(255) NULL',
            'db_password' => 'VARCHAR(255) NULL',
        ];
        $existing = $pdo->query("SHOW COLUMNS FROM companies")->fetchAll(PDO::FETCH_COLUMN, 0);
        $existingMap = array_flip($existing);
        foreach ($needed as $col => $def) {
            if (!isset($existingMap[$col])) {
                $pdo->exec("ALTER TABLE companies ADD COLUMN `{$col}` {$def}");
            }
        }
    }
}

if (!function_exists('ensureCompanyDbPoolUsageTable')) {
    function ensureCompanyDbPoolUsageTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS company_db_pool_usage (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                db_identifier VARCHAR(255) NOT NULL UNIQUE,
                company_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                released_at DATETIME NULL,
                note VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('findFreePoolDb')) {
    function findFreePoolDb(PDO $centralPdo): ?array
    {
        $pool = parseCompanyDbPool();
        if (empty($pool)) {
            return null;
        }
        ensureCompanyDbPoolUsageTable($centralPdo);
        $usedStmt = $centralPdo->query("SELECT db_identifier FROM companies WHERE db_identifier IS NOT NULL AND db_identifier != ''");
        $used = $usedStmt->fetchAll(PDO::FETCH_COLUMN);
        $usedJournalStmt = $centralPdo->query("SELECT db_identifier FROM company_db_pool_usage");
        $usedJournal = $usedJournalStmt->fetchAll(PDO::FETCH_COLUMN);
        $used = array_merge($used, $usedJournal);
        $usedMap = array_flip($used);
        foreach ($pool as $entry) {
            if (!isset($usedMap[$entry['database']])) {
                return $entry;
            }
        }
        return null;
    }
}

if (!function_exists('markPoolDbUsed')) {
    function markPoolDbUsed(PDO $centralPdo, string $dbIdentifier, int $companyId): void
    {
        ensureCompanyDbPoolUsageTable($centralPdo);
        $stmt = $centralPdo->prepare(
            'INSERT IGNORE INTO company_db_pool_usage (db_identifier, company_id, note) VALUES (:db, :cid, :note)'
        );
        $stmt->execute([
            ':db'   => $dbIdentifier,
            ':cid'  => $companyId,
            ':note' => 'superadmin_create',
        ]);
    }
}
