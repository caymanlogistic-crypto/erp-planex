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

        $assignedStmt = $centralPdo->query("SELECT db_identifier FROM companies WHERE db_identifier IS NOT NULL AND db_identifier != ''");
        $assigned = $assignedStmt->fetchAll(PDO::FETCH_COLUMN);
        $assignedMap = array_flip($assigned);

        $reservedStmt = $centralPdo->query("SELECT db_identifier FROM company_db_pool_usage WHERE released_at IS NULL");
        $reserved = $reservedStmt->fetchAll(PDO::FETCH_COLUMN);
        $reservedMap = array_flip($reserved);

        foreach ($pool as $entry) {
            $dbId = $entry['database'];
            if (!isset($assignedMap[$dbId]) && !isset($reservedMap[$dbId])) {
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

        $stmt = $centralPdo->prepare("SELECT id, company_id, released_at FROM company_db_pool_usage WHERE db_identifier = ?");
        $stmt->execute([$dbIdentifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($row['released_at'] === null && (int)$row['company_id'] !== $companyId) {
                throw new \RuntimeException("Pool database '{$dbIdentifier}' is already reserved for company #{$row['company_id']}.");
            }
            $update = $centralPdo->prepare(
                "UPDATE company_db_pool_usage SET company_id = :cid, released_at = NULL, note = :note WHERE db_identifier = :db"
            );
            $update->execute([
                ':cid'  => $companyId,
                ':note' => 'superadmin_create',
                ':db'   => $dbIdentifier,
            ]);
        } else {
            $insert = $centralPdo->prepare(
                'INSERT INTO company_db_pool_usage (db_identifier, company_id, note) VALUES (:db, :cid, :note)'
            );
            $insert->execute([
                ':db'   => $dbIdentifier,
                ':cid'  => $companyId,
                ':note' => 'superadmin_create',
            ]);
        }
    }
}

if (!function_exists('releasePoolDb')) {
    function releasePoolDb(PDO $centralPdo, string $dbIdentifier, int $companyId): void
    {
        ensureCompanyDbPoolUsageTable($centralPdo);
        $stmt = $centralPdo->prepare(
            "UPDATE company_db_pool_usage SET released_at = NOW(), note = :note WHERE db_identifier = :db AND company_id = :cid"
        );
        $stmt->execute([
            ':note' => 'superadmin_delete',
            ':db'   => $dbIdentifier,
            ':cid'  => $companyId,
        ]);
    }
}

if (!function_exists('companyDbQuoteIdentifier')) {
    function companyDbQuoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

if (!function_exists('cleanCompanyPoolDatabase')) {
    function cleanCompanyPoolDatabase(PDO $poolPdo): void
    {
        try {
            // 1. Drop triggers
            $dbName = $poolPdo->query('SELECT DATABASE()')->fetchColumn();
            if ($dbName) {
                $triggers = $poolPdo->query(
                    "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = " . $poolPdo->quote($dbName)
                )->fetchAll(PDO::FETCH_COLUMN);
                foreach ($triggers as $trigger) {
                    $poolPdo->exec("DROP TRIGGER IF EXISTS " . companyDbQuoteIdentifier($trigger));
                }
            }

            // 2. Drop views
            $views = $poolPdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($views as $view) {
                $poolPdo->exec("DROP VIEW IF EXISTS " . companyDbQuoteIdentifier($view));
            }

            // 3. Drop base tables with FK checks off
            $poolPdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $tables = $poolPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $poolPdo->exec("DROP TABLE IF EXISTS " . companyDbQuoteIdentifier($table));
            }

            // 4. Drop routines (procedures/functions)
            if ($dbName) {
                $routines = $poolPdo->query(
                    "SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = " . $poolPdo->quote($dbName)
                )->fetchAll(PDO::FETCH_ASSOC);
                foreach ($routines as $routine) {
                    $type = $routine['ROUTINE_TYPE'];
                    $name = companyDbQuoteIdentifier($routine['ROUTINE_NAME']);
                    $poolPdo->exec("DROP {$type} IF EXISTS {$name}");
                }
            }

            // 5. Drop events
            if ($dbName) {
                $events = $poolPdo->query(
                    "SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . $poolPdo->quote($dbName)
                )->fetchAll(PDO::FETCH_COLUMN);
                foreach ($events as $event) {
                    $poolPdo->exec("DROP EVENT IF EXISTS " . companyDbQuoteIdentifier($event));
                }
            }
        } finally {
            $poolPdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
