<?php

namespace App\Service;

use App\Core\Database;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CompanyDeletionService
{
    public static function delete(PDO $centralPdo, array $company, array $config): array
    {
        $companyId = (int)$company['id'];
        $dbIdentifier = $company['db_identifier'] ?? '';
        $isPoolDb = !empty($company['db_username']);

        $result = [
            'success' => true,
            'errors' => [],
            'steps' => [],
            'verification' => [],
        ];

        $storageAbs = storage_path('companies/' . $companyId);
        $storageAbsReal = realpath($storageAbs);
        $projectRoot = realpath(base_path(''));

        if ($storageAbsReal !== false && strpos($storageAbsReal, $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'companies' . DIRECTORY_SEPARATOR) === 0) {
            $resolvedStorage = $storageAbsReal;
        } else {
            $resolvedStorage = false;
        }

        $storageExists = $resolvedStorage !== false && is_dir($resolvedStorage);

        // === STEP 1: Clean pool DB or drop separate DB ===
        if ($isPoolDb && $dbIdentifier !== '') {
            try {
                $localDbCfg = self::companyDatabaseConfig($config, $company);
                $localDb = new Database($localDbCfg);
                $localPdoClean = $localDb->connection();

                // Verify pool DB is accessible before cleaning
                $localPdoClean->query('SELECT 1');

                cleanCompanyPoolDatabase($localPdoClean);

                // Verify tenant DB has zero base tables/views/triggers/routines/events
                $remaining = 0;
                $dbName = $localPdoClean->query('SELECT DATABASE()')->fetchColumn();
                if ($dbName) {
                    $tables = $localPdoClean->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
                    $remaining += count($tables);
                    $views = $localPdoClean->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(\PDO::FETCH_COLUMN);
                    $remaining += count($views);
                    $triggers = $localPdoClean->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = " . $localPdoClean->quote($dbName))->fetchAll(\PDO::FETCH_COLUMN);
                    $remaining += count($triggers);
                    $routines = $localPdoClean->query("SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = " . $localPdoClean->quote($dbName))->fetchAll(\PDO::FETCH_COLUMN);
                    $remaining += count($routines);
                    $events = $localPdoClean->query("SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . $localPdoClean->quote($dbName))->fetchAll(\PDO::FETCH_COLUMN);
                    $remaining += count($events);
                }
                if ($remaining > 0) {
                    throw new \RuntimeException("Pool DB cleanup incomplete: {$remaining} objects remain in database.");
                }

                $result['steps'][] = ['step' => 'pool_db_clean', 'status' => 'success', 'db' => $dbIdentifier];
            } catch (\Exception $e) {
                $msg = 'Ошибка очистки pool БД: ' . $e->getMessage();
                $result['steps'][] = ['step' => 'pool_db_clean', 'status' => 'fail', 'error' => $msg];
                $result['errors'][] = $msg;
                $result['success'] = false;
                return $result;
            }
        } elseif (!empty($dbIdentifier) && !$isPoolDb) {
            try {
                $dbConfig = $config['database'];
                $dbConfig['database'] = '';
                $sysDb = new Database($dbConfig);
                $sysPdo = $sysDb->connection();
                $sysPdo->exec("DROP DATABASE IF EXISTS `{$dbIdentifier}`");
                $result['steps'][] = ['step' => 'drop_database', 'status' => 'success', 'db' => $dbIdentifier];
            } catch (\Exception $e) {
                $msg = 'Ошибка удаления отдельной БД: ' . $e->getMessage();
                $result['steps'][] = ['step' => 'drop_database', 'status' => 'fail', 'error' => $msg];
                $result['errors'][] = $msg;
                $result['success'] = false;
                return $result;
            }
        } else {
            $result['steps'][] = ['step' => 'drop_database', 'status' => 'skipped', 'reason' => 'no separate or pool DB'];
        }

        // === STEP 2: Delete storage/companies/{id} ===
        if ($storageExists) {
            try {
                self::removeDirectory($resolvedStorage);
                if (is_dir($resolvedStorage)) {
                    throw new \RuntimeException('Директория storage не была удалена: ' . $resolvedStorage);
                }
                $result['steps'][] = ['step' => 'delete_storage', 'status' => 'success'];
            } catch (\Exception $e) {
                $msg = 'Ошибка удаления storage: ' . $e->getMessage();
                $result['steps'][] = ['step' => 'delete_storage', 'status' => 'fail', 'error' => $msg];
                $result['errors'][] = $msg;
                $result['success'] = false;
                return $result;
            }
        } else {
            $result['steps'][] = ['step' => 'delete_storage', 'status' => 'skipped', 'reason' => 'storage directory does not exist'];
        }

        // === STEP 3: Central transaction — delete all company-owned records ===
        try {
            $centralPdo->beginTransaction();

            // Release pool DB inside the same central transaction before commit
            if ($isPoolDb && $dbIdentifier !== '') {
                releasePoolDb($centralPdo, $dbIdentifier);
            }

            // Clean up any pre-existing deleted_entities rows for this company
            $delEntStmt = $centralPdo->prepare('SELECT COUNT(*) FROM deleted_entities WHERE company_id = ?');
            $delEntStmt->execute([$companyId]);
            $deletedEntitiesCount = (int)$delEntStmt->fetchColumn();
            if ($deletedEntitiesCount > 0) {
                $centralPdo->prepare('DELETE FROM deleted_entities WHERE company_id = ?')->execute([$companyId]);
            }

            // Delete company_features
            $centralPdo->prepare('DELETE FROM company_features WHERE company_id = ?')->execute([$companyId]);

            // Delete company_users
            $centralPdo->prepare('DELETE FROM company_users WHERE company_id = ?')->execute([$companyId]);

            // Delete the company record itself (last)
            $centralPdo->prepare('DELETE FROM companies WHERE id = ?')->execute([$companyId]);

            $centralPdo->commit();

            $result['steps'][] = [
                'step' => 'central_delete',
                'status' => 'success',
                'details' => 'companies+company_features+company_users+deleted_entities',
            ];
        } catch (\Exception $e) {
            if ($centralPdo->inTransaction()) {
                $centralPdo->rollBack();
            }
            $msg = 'Ошибка центрального удаления: ' . $e->getMessage();
            $result['steps'][] = ['step' => 'central_delete', 'status' => 'fail', 'error' => $msg];
            $result['errors'][] = $msg;
            $result['success'] = false;
            return $result;
        }

        // === STEP 4: Verification ===
        $verifyErrors = [];

        // Verify company record absent
        $checkStmt = $centralPdo->prepare('SELECT COUNT(*) FROM companies WHERE id = ?');
        $checkStmt->execute([$companyId]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            $verifyErrors[] = 'Запись компании всё ещё существует в companies';
        }

        // Verify no company_features rows
        $cfStmt = $centralPdo->prepare('SELECT COUNT(*) FROM company_features WHERE company_id = ?');
        $cfStmt->execute([$companyId]);
        if ((int)$cfStmt->fetchColumn() > 0) {
            $verifyErrors[] = 'Остались записи в company_features для этой компании';
        }

        // Verify no company_users rows
        $cuStmt = $centralPdo->prepare('SELECT COUNT(*) FROM company_users WHERE company_id = ?');
        $cuStmt->execute([$companyId]);
        if ((int)$cuStmt->fetchColumn() > 0) {
            $verifyErrors[] = 'Остались записи в company_users для этой компании';
        }

        // Verify no deleted_entities rows
        $deStmt = $centralPdo->prepare('SELECT COUNT(*) FROM deleted_entities WHERE company_id = ?');
        $deStmt->execute([$companyId]);
        if ((int)$deStmt->fetchColumn() > 0) {
            $verifyErrors[] = 'Остались записи в deleted_entities для этой компании';
        }

        // Verify pool DB was released: company_id=NULL, released_at set, row preserved
        if ($dbIdentifier !== '') {
            $puCheck = $centralPdo->prepare('SELECT company_id, released_at FROM company_db_pool_usage WHERE db_identifier = ?');
            $puCheck->execute([$dbIdentifier]);
            $poolRow = $puCheck->fetch(PDO::FETCH_ASSOC);
            if ($poolRow) {
                if ($poolRow['company_id'] !== null) {
                    $verifyErrors[] = 'company_id в company_db_pool_usage не NULL после освобождения';
                }
                if ($poolRow['released_at'] === null) {
                    $verifyErrors[] = 'released_at не установлен после освобождения pool БД';
                }
            } elseif ($isPoolDb) {
                $verifyErrors[] = 'Запись в company_db_pool_usage не найдена для данной pool БД';
            }
        }

        // Verify storage absent
        if (is_dir($resolvedStorage ?: $storageAbs)) {
            $verifyErrors[] = 'Директория storage всё ещё существует';
        }

        if (!empty($verifyErrors)) {
            $result['verification'] = $verifyErrors;
            $result['errors'] = array_merge($result['errors'], $verifyErrors);
            $result['success'] = false;
        } else {
            $result['verification'] = ['Все проверки пройдены: компания и все связанные данные удалены.'];
        }

        return $result;
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (!rmdir($item->getPathname())) {
                    throw new \RuntimeException('Не удалось удалить директорию: ' . $item->getPathname());
                }
            } else {
                if (!unlink($item->getPathname())) {
                    throw new \RuntimeException('Не удалось удалить файл: ' . $item->getPathname());
                }
            }
        }
        if (!rmdir($path)) {
            throw new \RuntimeException('Не удалось удалить корневую директорию: ' . $path);
        }
    }

    private static function companyDatabaseConfig(array $config, array $company): array
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
            $cfg['password'] = companyDbResolvePassword((string) $company['db_password']);
        }
        return $cfg;
    }
}
