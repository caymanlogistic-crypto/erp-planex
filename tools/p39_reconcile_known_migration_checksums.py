from pathlib import Path

p=Path('app/Service/LocalMigrationService.php')
s=p.read_text(encoding='utf-8')

old="""                        if ($knownAlt === null) {
                            if ($fileName === '019_update_crews.sql' && self::verifyMigration019Compatible($localPdo)) {
                                // Schema is compatible with migration 019 final state — accept updated checksum
                            } else {
                                throw new \\RuntimeException('Local migration checksum mismatch: ' . $fileName);
                            }
                        }
"""
new="""                        if ($knownAlt === null && !self::verifyKnownMigrationSchemaCompatible($localPdo, $fileName)) {
                            throw new \\RuntimeException('Local migration checksum mismatch: ' . $fileName);
                        }
"""
if old in s:
    s=s.replace(old,new,1)
elif new not in s:
    raise SystemExit('checksum conditional not found')

marker="""    private static function knownAlternateChecksum(string $fileName, string $storedChecksum): ?string
"""
helpers=r'''    private static function verifyKnownMigrationSchemaCompatible(PDO $localPdo, string $fileName): bool
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

'''
if 'private static function verifyKnownMigrationSchemaCompatible' not in s:
    if marker not in s: raise SystemExit('method insertion marker not found')
    s=s.replace(marker,helpers+marker,1)

p.write_text(s,encoding='utf-8')
print('P39 schema-compatible checksum reconciliation patch applied')
