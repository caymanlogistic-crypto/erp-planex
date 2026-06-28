<?php

namespace App\Service;

use App\Core\Database;
use PDO;

final class VehicleSetService
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {}

    public function getCompanyId(): int { return (int)(getSessionCompanyId() ?? 0); }

    public function loadCompany(int $companyId): ?array
    {
        if ($companyId <= 0) return null;
        $pdo = $this->db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getLocalPdo(array $company): PDO
    {
        $cfg = $this->config['database'];
        $cfg['database'] = $company['db_identifier'];
        $localDb = new Database($cfg);
        $pdo = $localDb->connection();
        applyLocalMigrations($pdo);
        try { $pdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); }
        catch (\Exception $e) { $pdo->exec(file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'))); }
        try { $pdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch(); }
        catch (\Exception $e) { try { $pdo->exec("RENAME TABLE vehicles TO vehicle_units"); } catch (\Exception $x) {} }
        try { $pdo->query("SELECT created_by_user_id FROM vehicle_sets LIMIT 1")->fetch(); }
        catch (\Exception $e) { $pdo->exec("ALTER TABLE vehicle_sets ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL"); }
        return $pdo;
    }

    public function listVehicleSets(PDO $pdo, bool $isLogist, int $userId): array
    {
        $sql = "SELECT vs.*,
            vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model,
            vu1.volume_m3 AS primary_volume_m3, vu1.capacity_tons AS primary_capacity_tons,
            vu1.diagnostic_card_number AS primary_diagnostic_card_number,
            vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model,
            vu2.volume_m3 AS secondary_volume_m3, vu2.capacity_tons AS secondary_capacity_tons,
            vu2.diagnostic_card_number AS secondary_diagnostic_card_number,
            EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id=d.document_type_id WHERE d.entity_type='vehicle_unit' AND d.entity_id=vu1.id AND d.deleted_at IS NULL AND dt.code='sts') AS has_sts,
            EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id=d.document_type_id WHERE d.entity_type='vehicle_unit' AND d.entity_id=vu1.id AND d.deleted_at IS NULL AND dt.code='diagnostic_card') AS has_diagnostic_card_doc,
            EXISTS (SELECT 1 FROM documents d WHERE d.entity_type='vehicle_unit' AND d.entity_id=vu1.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%%') AS has_photo,
            EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id=d.document_type_id WHERE d.entity_type='vehicle_unit' AND d.entity_id=vu2.id AND d.deleted_at IS NULL AND dt.code='sts') AS has_sts_sec,
            EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id=d.document_type_id WHERE d.entity_type='vehicle_unit' AND d.entity_id=vu2.id AND d.deleted_at IS NULL AND dt.code='diagnostic_card') AS has_diagnostic_card_doc_sec,
            EXISTS (SELECT 1 FROM documents d WHERE d.entity_type='vehicle_unit' AND d.entity_id=vu2.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%%') AS has_photo_sec
            FROM vehicle_sets vs
            LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id=vu1.id
            LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id=vu2.id";
        if ($isLogist) {
            $sql .= " WHERE (vs.created_by_user_id=? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type='vehicle_set' AND granted_to_user_id=? AND access_level IN ('view','edit') AND revoked_at IS NULL))";
            $stmt = $pdo->prepare($sql . " ORDER BY vs.created_at DESC");
            $stmt->execute([$userId, $userId]);
        } else {
            $stmt = $pdo->query($sql . " ORDER BY vs.created_at DESC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVehicleSetById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM vehicle_sets WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getUnitsByIds(PDO $pdo, array $unitIds): array
    {
        if (empty($unitIds)) return [];
        $ph = implode(',', array_fill(0, count($unitIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($ph)");
        $stmt->execute($unitIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocsForUnits(PDO $pdo, array $unitIds): array
    {
        if (empty($unitIds)) return [];
        $ph = implode(',', array_fill(0, count($unitIds), '?'));
        $stmt = $pdo->prepare("SELECT id,entity_id,document_type,original_name,mime_type,stored_name,file_size FROM documents WHERE entity_type='vehicle_unit' AND entity_id IN ($ph) AND deleted_at IS NULL ORDER BY id");
        $stmt->execute($unitIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function checkLogistAccess(PDO $pdo, int $entityId, int $userId): ?string
    {
        $stmt = $pdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type='vehicle_set' AND entity_id=? AND granted_to_user_id=? AND revoked_at IS NULL LIMIT 1");
        $stmt->execute([$entityId, $userId]);
        return $stmt->fetchColumn() ?: null;
    }
}
