<?php

namespace App\Service;

use PDO;

final class LocalMigrationService
{
    public static function ensureDocumentTypeRecord(PDO $localPdo, string $name, string $code, string $entityType, string $category = 'predefined'): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        try {
            $insert = $localPdo->prepare(
                'INSERT IGNORE INTO document_types (name, code, entity_type, category, created_by_user_id, created_by_role)
                 VALUES (:name, :code, :entity_type, :category, :user_id, :role)'
            );
            $insert->execute([
                ':name' => $name,
                ':code' => $code !== '' ? $code : null,
                ':entity_type' => $entityType !== '' ? $entityType : null,
                ':category' => $category,
                ':user_id' => (int) ($_SESSION['user_id'] ?? 0),
                ':role' => (string) ($_SESSION['role_code'] ?? 'system'),
            ]);
        } catch (\Throwable $e) {
        }

        $lookup = $localPdo->prepare(
            'SELECT id
               FROM document_types
              WHERE name = :name
                AND ((entity_type = :entity_type_value) OR (entity_type IS NULL AND :entity_type_null IS NULL))
              ORDER BY id DESC
              LIMIT 1'
        );
        $lookup->execute([
            ':name' => $name,
            ':entity_type_value' => $entityType !== '' ? $entityType : null,
            ':entity_type_null' => $entityType !== '' ? $entityType : null,
        ]);

        $id = $lookup->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public static function apply(PDO $localPdo): void
    {
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'));
        if (in_array($requestMethod, ['GET', 'HEAD'], true)) {
            return;
        }

        static $appliedConnections = [];
        $connectionId = spl_object_id($localPdo);
        if (isset($appliedConnections[$connectionId])) {
            return;
        }

        $localPdo->exec(
            "CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `checksum` VARCHAR(64) NOT NULL,
                `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_local_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $files = glob(base_path('database/migrations-local/*.sql')) ?: [];
        sort($files, SORT_STRING);

        $databaseName = (string) $localPdo->query('SELECT DATABASE()')->fetchColumn();
        $lockName = 'planex_migrate_' . substr(hash('sha256', $databaseName), 0, 32);
        $lockStmt = $localPdo->prepare('SELECT GET_LOCK(?, 10)');
        $lockStmt->execute([$lockName]);
        if ((int) $lockStmt->fetchColumn() !== 1) {
            throw new \RuntimeException('Cannot acquire local migration lock.');
        }

        try {
            $applied = $localPdo->query('SELECT migration, checksum FROM schema_migrations')
                ->fetchAll(PDO::FETCH_KEY_PAIR);

            if ($applied === [] && self::tableExists($localPdo, 'vehicle_units')) {
                self::baselinePreviouslyManagedSchema($localPdo, $files);
                $applied = $localPdo->query('SELECT migration, checksum FROM schema_migrations')
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
            }

            foreach ($files as $file) {
                $fileName = basename($file);
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new \RuntimeException('Cannot read local migration ' . $fileName);
                }
                $checksum = hash('sha256', $sql);

                if (isset($applied[$fileName])) {
                    if (!hash_equals((string) $applied[$fileName], $checksum)) {
                        $knownAlt = self::knownAlternateChecksum($fileName, (string) $applied[$fileName]);
                        if ($knownAlt === null && !self::verifyKnownMigrationSchemaCompatible($localPdo, $fileName)) {
                            throw new \RuntimeException('Local migration checksum mismatch: ' . $fileName);
                        }
                        $updateStmt = $localPdo->prepare(
                            'UPDATE schema_migrations SET checksum = ? WHERE migration = ?'
                        );
                        $updateStmt->execute([$checksum, $fileName]);
                    }
                    continue;
                }

                if ($fileName === '019_update_crews.sql') {
                    self::handleMigration019($localPdo);
                } elseif (trim($sql) !== '') {
                    try {
                        $localPdo->exec($sql);
                    } catch (\Throwable $execErr) {
                        throw new \RuntimeException(
                            sprintf('Migration %s failed: %s', $fileName, $execErr->getMessage()),
                            0,
                            $execErr
                        );
                    }
                }

                $stmt = $localPdo->prepare(
                    'INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?)'
                );
                $stmt->execute([$fileName, $checksum]);
            }
            $appliedConnections[$connectionId] = true;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Local migration failed: ' . $e->getMessage(), 0, $e);
        } finally {
            $release = $localPdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }

    /**
     * Older PLANEX builds ran idempotent SQL on every request and did not keep a
     * migration journal. Replaying migration 005 against such a database would
     * recreate the legacy `vehicles` table and make migration 016 ambiguous.
     *
     * This method marks all base migrations (up to 016) as applied without
     * re-executing, since `vehicle_units` exists (proving the schema is already
     * at that level). Migrations 017+ are left for the normal loop in apply()
     * which handles them safely (all migrations are idempotent).
     */
    private static function baselinePreviouslyManagedSchema(PDO $localPdo, array $files): void
    {
        $insert = $localPdo->prepare(
            'INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?)'
        );
        $localPdo->beginTransaction();
        try {
            foreach ($files as $file) {
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new \RuntimeException('Cannot read local migration ' . basename($file));
                }
                $checksum = hash('sha256', $sql);
                $fileName = basename($file);

                $migrationNum = 0;
                if (preg_match('/^(\d+)/', $fileName, $numMatch)) {
                    $migrationNum = (int) $numMatch[1];
                }

                if ($migrationNum <= 16) {
                    $insert->execute([$fileName, $checksum]);
                    continue;
                }
            }
            $localPdo->commit();
        } catch (\Throwable $e) {
            if ($localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            throw $e;
        }
    }

    private static function handleMigration019(PDO $localPdo): void
    {
        $addColumn = function (string $column, string $type) use ($localPdo): void {
            $stmt = $localPdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute(['crews', $column]);
            if ((int) $stmt->fetchColumn() === 0) {
                $localPdo->exec("ALTER TABLE crews ADD COLUMN `{$column}` {$type}");
            }
        };

        $addColumn('driver_vehicle_block_id', 'INT UNSIGNED DEFAULT NULL');
        $addColumn('updated_by_user_id', 'INT UNSIGNED DEFAULT NULL');
        $addColumn('updated_by_role', 'VARCHAR(20) DEFAULT NULL');

        $backfillStmt = $localPdo->prepare(
            'SELECT c.id, c.contractor_id, c.driver_id, c.vehicle_id
               FROM crews c
              WHERE c.driver_vehicle_block_id IS NULL'
        );
        $backfillStmt->execute();
        $needBackfill = $backfillStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($needBackfill as $crew) {
            $crewId = (int) $crew['id'];
            $driverId = (int) $crew['driver_id'];
            $vehicleId = (int) $crew['vehicle_id'];

            if ($vehicleId <= 0 || $driverId <= 0) {
                throw new \RuntimeException(
                    sprintf(
                        'Migration 019: crew id=%d has no vehicle_id or driver_id, cannot backfill driver_vehicle_block_id.',
                        $crewId
                    )
                );
            }

            $vsStmt = $localPdo->prepare(
                'SELECT id FROM vehicle_sets WHERE primary_vehicle_unit_id = ? OR secondary_vehicle_unit_id = ?'
            );
            $vsStmt->execute([$vehicleId, $vehicleId]);
            $vehicleSetIds = $vsStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($vehicleSetIds) === 0) {
                throw new \RuntimeException(
                    sprintf(
                        'Migration 019: crew id=%d vehicle_id=%d has no matching vehicle_sets.primary_vehicle_unit_id or secondary_vehicle_unit_id.',
                        $crewId,
                        $vehicleId
                    )
                );
            }

            if (count($vehicleSetIds) > 1) {
                throw new \RuntimeException(
                    sprintf(
                        'Migration 019: crew id=%d vehicle_id=%d matches multiple vehicle_sets (ids=%s), ambiguous backfill.',
                        $crewId,
                        $vehicleId,
                        implode(',', $vehicleSetIds)
                    )
                );
            }

            $vehicleSetId = (int) $vehicleSetIds[0];

            $dvbLookup = $localPdo->prepare(
                'SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ? LIMIT 1'
            );
            $dvbLookup->execute([$driverId, $vehicleSetId]);
            $existingDvbId = $dvbLookup->fetchColumn();

            if ($existingDvbId !== false) {
                $dvbId = (int) $existingDvbId;
            } else {
                $dvbInsert = $localPdo->prepare(
                    'INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                     VALUES (?, ?, \'active\', NULL, \'system\')'
                );
                $dvbInsert->execute([$driverId, $vehicleSetId]);
                $dvbId = (int) $localPdo->lastInsertId();
            }

            $updateCrew = $localPdo->prepare(
                'UPDATE crews SET driver_vehicle_block_id = ? WHERE id = ?'
            );
            $updateCrew->execute([$dvbId, $crewId]);
        }

        $checkIndexExists = function (string $indexName) use ($localPdo): bool {
            $stmt = $localPdo->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
            );
            $stmt->execute(['crews', $indexName]);
            return (int) $stmt->fetchColumn() > 0;
        };

        if ($checkIndexExists('uk_crew')) {
            $idxCols = $localPdo->query(
                "SELECT COLUMN_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crews' AND INDEX_NAME = 'uk_crew'
                 ORDER BY SEQ_IN_INDEX"
            )->fetchAll(PDO::FETCH_COLUMN);

            $cols = implode(',', $idxCols);
            if ($cols === 'contractor_id,driver_vehicle_block_id') {
                return;
            }

            $localPdo->exec('ALTER TABLE crews DROP INDEX uk_crew');
        }

        if ($checkIndexExists('contractor_id_2')) {
            $localPdo->exec('ALTER TABLE crews DROP INDEX contractor_id_2');
        }

        if (!$checkIndexExists('uk_crew')) {
            $localPdo->exec('ALTER TABLE crews ADD UNIQUE KEY uk_crew (contractor_id, driver_vehicle_block_id)');
        }
    }

    private static function verifyMigration019Compatible(PDO $localPdo): bool
    {
        if (!self::tableExists($localPdo, 'crews')) {
            return false;
        }

        $requiredColumns = ['driver_vehicle_block_id', 'updated_by_user_id', 'updated_by_role'];
        foreach ($requiredColumns as $column) {
            $stmt = $localPdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute(['crews', $column]);
            if ((int) $stmt->fetchColumn() === 0) {
                return false;
            }
        }

        $idxStmt = $localPdo->query(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crews' AND INDEX_NAME = 'uk_crew'
             ORDER BY SEQ_IN_INDEX"
        );
        $idxCols = $idxStmt->fetchAll(PDO::FETCH_COLUMN);
        if (implode(',', $idxCols) !== 'contractor_id,driver_vehicle_block_id') {
            return false;
        }

        return true;
    }

    private static function verifyKnownMigrationSchemaCompatible(PDO $localPdo, string $fileName): bool
    {
        return match ($fileName) {
            '019_update_crews.sql' => self::verifyMigration019Compatible($localPdo),
            '027_vehicle_unit_document_types.sql' => self::verifyMigration027Compatible($localPdo),
            '039_add_soft_delete_columns.sql' => self::verifyMigration039Compatible($localPdo),
            '040_create_linear_routes.sql' => self::verifyMigration040Compatible($localPdo),
            '041_linear_route_repeatable_principals_payments.sql' => self::verifyMigration041Compatible($localPdo),
            default => false,
        };
    }

    private static function verifyMigration027Compatible(PDO $localPdo): bool
    {
        if (!self::tableExists($localPdo, 'document_types') || !self::indexExists($localPdo, 'document_types', 'uk_name_entity_type')) {
            return false;
        }
        $stmt = $localPdo->query("SELECT COUNT(DISTINCT code) FROM document_types WHERE entity_type = 'vehicle_unit' AND category = 'predefined' AND code IN ('sts','diagnostic_card','photo')");
        return (int) $stmt->fetchColumn() === 3;
    }

    private static function verifyMigration039Compatible(PDO $localPdo): bool
    {
        foreach (['clients','contractors','drivers','vehicle_units','vehicle_sets','driver_vehicle_blocks','crews','users','document_types'] as $table) {
            if (!self::hasColumns($localPdo, $table, ['deleted_at','deleted_by_user_id','deleted_by_role'])) {
                return false;
            }
        }
        return self::hasColumns($localPdo, 'documents', ['deleted_at','deleted_by_user_id','deleted_by_role']);
    }

    private static function verifyMigration040Compatible(PDO $localPdo): bool
    {
        if (!self::hasColumns($localPdo, 'cargo_types', ['id','name','normalized_name','usage_count','status'])) return false;
        if (!self::hasColumns($localPdo, 'linear_routes', ['id','route_type','client_id','carrier_contractor_id','route_executor_id','cargo_type_id','planned_loading_date','planned_unloading_date','status','deleted_at'])) return false;
        if (!self::hasColumns($localPdo, 'linear_route_financial_terms', ['id','linear_route_id','party_role','amount','payment_type'])) return false;
        $stmt = $localPdo->query("SELECT COUNT(DISTINCT code) FROM document_types WHERE entity_type = 'linear_route' AND category = 'predefined' AND code IN ('customer_contract_request','carrier_contract_request','principal_contract_request')");
        return (int) $stmt->fetchColumn() === 3;
    }

    private static function verifyMigration041Compatible(PDO $localPdo): bool
    {
        return self::hasColumns($localPdo, 'linear_route_principals', ['id','linear_route_id','principal_type','principal_id','sort_order','status','deleted_at'])
            && self::hasColumns($localPdo, 'linear_route_payments', ['id','linear_route_id','party_role','linear_route_principal_id','sort_order','amount','payment_type','deleted_at']);
    }

    private static function hasColumns(PDO $localPdo, string $table, array $columns): bool
    {
        if (!self::tableExists($localPdo, $table)) return false;
        $stmt = $localPdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $available = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
        foreach ($columns as $column) {
            if (!isset($available[$column])) return false;
        }
        return true;
    }

    private static function indexExists(PDO $localPdo, string $table, string $index): bool
    {
        $stmt = $localPdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $stmt->execute([$table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function knownAlternateChecksum(string $fileName, string $storedChecksum): ?string
    {
        static $known = [
            '019_update_crews.sql' => [
                'f0b9579f3d440d165465010e8693efbb5958dbc730253f53e0ca5b3a8733c4f3',
                '7d5e4768f5cdba4008facf9b394b51f8980b33c0d2076aa56bf0b2cdba15d8f7',
            ],
            '027_vehicle_unit_document_types.sql' => [
                'efa2e433b05ce1e2a000b175e3925590a48ccef8f8ac47d5f6c241651c778fbd',
            ],
            '039_add_soft_delete_columns.sql' => [
                '34524bfbfb0a8f83232c2076e0d1081d10c8eedc38aa5f6a69e84d2b43948038',
            ],
            '040_create_linear_routes.sql' => [
                '88586161f063688f6f1a0133e200a237e2f86320fab1d42335375d7f5ef1bb1f',
            ],
            '041_linear_route_repeatable_principals_payments.sql' => [
                '4d29448de5084d46a430b756ea2709baa9d7a77076734df989627546a0a3925f',
            ],
            '051_restrict_finance_invoice_links_fk.sql' => [
                '2afc6d44bdcf704192e7a6f9c279cfef302ef1da1f98aa2f485ee88837c6f834',
                'c5e8053a9fc5866780b83be7465aec6c7149b074c18954dc6bbbba0f4b7a174a',
            ],
        ];

        $allowed = $known[$fileName] ?? [];
        if (in_array($storedChecksum, $allowed, true)) {
            $filePath = base_path('database/migrations-local/' . $fileName);
            $sql = file_get_contents($filePath);
            return $sql !== false ? hash('sha256', $sql) : null;
        }

        return null;
    }

    private static function tableExists(PDO $localPdo, string $table): bool
    {
        $stmt = $localPdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
