# Stage 1: Unified Soft-Delete / Archive Plan

> **For agentic workers:** Use compose:subagent to implement task-by-task.

**Goal:** Convert all company-level delete/archive actions to unified soft-delete (`deleted_at` + audit) and add SUPERADMIN «Удалённые данные» section.

**Architecture:** Each company's local DB tables get `deleted_at`, `deleted_by_user_id`, `deleted_by_role`. A central `deleted_entities` table in the superadmin DB records every deletion for cross-company audit. Company users see «Удалить» (not «Архив»). Only SUPERADMIN sees restore functionality.

**Tech Stack:** PHP 8.1+, MySQL, PDO, custom micro-framework.

---

## Global Constraints

- All company entity archive methods must use `UPDATE ... SET deleted_at=NOW(), deleted_by_user_id=?, deleted_by_role=?` — NO `status='archived'`
- The driver `modal_delete.php` hard-delete (DELETE FROM) must be replaced with soft-delete
- All company list queries must add `AND deleted_at IS NULL` (or equivalent WHERE)
- Company users must NEVER see words «архив», «восстановить», «SUPERADMIN», «удалённые данные»
- SUPERADMIN menu gets «Удалённые данные» link — no other changes to main layout
- Physical storage files are NEVER deleted by soft-delete
- No hard-delete of company/local DBs/storage in Stage 1
- `document_types` is a system-level entity, covered by soft-delete with all others
- All SQL identifiers must match existing local DB schemas exactly

---

## Inventory (pre-implementation)

### Entity → Current archive handler → Current SQL → Audit?

| Entity | Handler | SQL | Audit |
|--------|---------|-----|-------|
| clients | `ClientService::archiveClient()` | `UPDATE clients SET status='archived'` | None |
| contractors | `ContractorService::archiveContractor()` | `UPDATE contractors SET status='archived'` | None |
| drivers | `DriverService::archiveDriver()` | `UPDATE drivers SET status='archived'` | None |
| vehicle_units | Inline route `company_vehicles.php:831` | `UPDATE vehicle_units SET status='archived'` | None |
| vehicle_sets | `VehicleSetService::archiveVehicleSet()` | `UPDATE vehicle_sets SET status='archived'` | None |
| driver_vehicle_blocks | Inline route `company_driver_vehicle_blocks_legacy.php:411` | `UPDATE driver_vehicle_blocks SET status='archived'` | None |
| crews (route_executors) | Inline + RouteExecutorActions/archive.php | `UPDATE crews SET status='archived'` | None |
| users/logists | Inline route `company_logists.php:951` | `UPDATE users SET status='archived'` | None |
| documents | `DocumentActions/delete.php` | `UPDATE documents SET deleted_at=NOW(),deleted_by_user_id=?` | `deleted_at` + `deleted_by_user_id` only |
| document_types | `DocumentActions/doc_types_delete.php` | `DELETE FROM document_types WHERE id=?` (HARD DELETE!) | None |
| **driver modal_delete** | `DriverActions/modal_delete.php` | `DELETE FROM drivers`, `DELETE FROM documents`, `DELETE FROM driver_phones`, `UNLINK files` | NONE — HARD DELETE! |

### List query filters

| Entity | Current filter | Issue |
|--------|---------------|-------|
| clients | `WHERE status = 'active'` (in service list) | archived not visible |
| contractors | `WHERE status = 'active'` (in service list) | archived not visible |
| drivers | `WHERE status = 'active'` (in service list) | archived not visible |
| vehicle_units | `SELECT * FROM vehicle_units` **NO FILTER** | archived records visible! |
| vehicle_sets | `WHERE status = 'active'` (in service list + inline) | archived not visible |
| driver_vehicle_blocks | `WHERE status = 'active'` (in inline) | archived not visible |
| crews | No direct list filter; archive check via `status != 'archived'` | |
| users/logists | `SELECT * FROM users ORDER BY created_at DESC` **NO FILTER** | |
| documents | `WHERE deleted_at IS NULL` | Already correct |

### Views with archive references (must change)

Files: `company_client_view.php:225`, `company_contractor_view.php:424`, `company_driver_view.php:326`, `company_crew_view.php:201`, `company_route_executor_view.php:181`, `company_vehicle_view.php:229`, `company_vehicle_set_view.php`, `company_driver_vehicle_block_view.php:198`, `company_logist_view.php:151`  
Badge/label refs: `company_clients.php:101`, `company_client_edit.php:118`, `company_crew_edit.php:110+187`, `company_driver_edit.php:195`, `company_vehicle_edit.php:91+206`, `company_vehicle_set_edit.php:152`, `company_driver_vehicle_blocks.php:108-110`, `company_driver_vehicle_block_edit.php:131`, `company_logists.php:98`

---

## Implementation Tasks

### Task 1: Central DB migration — `deleted_entities` table

**Files:**
- Create: `database/migrations/009_create_deleted_entities.sql`

**SQL:**
```sql
CREATE TABLE IF NOT EXISTS deleted_entities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(255) DEFAULT NULL,
    local_db VARCHAR(64) DEFAULT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    source_table VARCHAR(64) NOT NULL,
    display_name VARCHAR(512) DEFAULT NULL,
    deleted_by_user_id INT UNSIGNED DEFAULT NULL,
    deleted_by_role VARCHAR(32) DEFAULT NULL,
    deleted_by_name VARCHAR(255) DEFAULT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(512) DEFAULT NULL,
    snapshot_json LONGTEXT DEFAULT NULL,
    documents_count INT UNSIGNED DEFAULT 0,
    storage_paths_json TEXT DEFAULT NULL,
    status VARCHAR(16) DEFAULT 'archived',
    restored_at TIMESTAMP NULL DEFAULT NULL,
    restored_by_user_id INT UNSIGNED DEFAULT NULL,
    restored_by_role VARCHAR(32) DEFAULT NULL,
    INDEX idx_deleted_entities_company (company_id),
    INDEX idx_deleted_entities_type (entity_type),
    INDEX idx_deleted_entities_status (status),
    INDEX idx_deleted_entities_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] Create migration file
- [ ] Verify syntax with `php -l`

---

### Task 2: Local DB migration — add soft-delete columns to all entity tables

**Files:**
- Create: `database/migrations-local/039_add_soft_delete_columns.sql`

**SQL:**
```sql
-- This migration adds deleted_at, deleted_by_user_id, deleted_by_role
-- to all entity tables for unified soft-delete.
-- document_types is included since it currently does HARD DELETE.

-- Helper: add column if not exists
-- (Using MySQL procedural logic as in other local migrations)

-- NOTE: documents already has deleted_at and deleted_by_user_id — skip those columns
```

Define column additions for: `clients`, `contractors`, `drivers`, `vehicle_units`, `vehicle_sets`, `driver_vehicle_blocks`, `crews`, `users`, `document_types`.

- [ ] Create migration file for all 9 tables
- [ ] Helper: `applyLocalMigrations()` auto-runs this

---

### Task 3: Helper — `AuditService` to write `deleted_entities` audit records

**Files:**
- Create: `app/Service/AuditService.php`

**API:**
```php
class AuditService {
    /**
     * Record a deletion in the central deleted_entities table.
     * Must be called from the company DB context (has $company, $localPdo).
     */
    public static function recordDeletion(
        PDO $centralPdo,    // superadmin DB connection
        array $company,     // company row from central companies table
        string $entityType, // e.g. 'client', 'contractor', 'driver'
        int $entityId,
        string $sourceTable, // local DB table name
        string $displayName,
        int $deletedByUserId,
        string $deletedByRole,
        ?string $deletedByName = null,
        ?string $reason = null,
        ?string $snapshotJson = null,
        int $documentsCount = 0,
        ?string $storagePathsJson = null
    ): int;

    /**
     * Restore a deleted entity.
     * Returns true if successful, string error message on failure.
     */
    public static function restore(
        PDO $centralPdo,
        PDO $localPdo,
        int $deletedEntityId,
        int $restoredByUserId,
        string $restoredByRole,
        array $company
    ): true|string;
}
```

- [ ] Create `AuditService.php`

---

### Task 4: Modify all archive service methods + delete actions

**Files to modify:**
- `app/Service/ClientService.php:222-226` — archiveClient
- `app/Service/ContractorService.php:237-241` — archiveContractor
- `app/Service/DriverService.php:243-246` — archiveDriver
- `app/Service/VehicleSetService.php:118-121` — archiveVehicleSet
- `app/Http/Controllers/Company/DriverActions/modal_delete.php` — convert to soft-delete
- `app/Http/Controllers/Company/RouteExecutorActions/archive.php` — crews inline
- `app/Http/Controllers/Company/VehicleSetActions/modal_archive.php` — VehicleSet
- `app/Http/Controllers/Company/ClientActions/archive.php` — requires central PDO
- `app/Http/Controllers/Company/ClientActions/modal_archive.php` — requires central PDO
- `app/Http/Controllers/Company/ContractorActions/archive.php` — requires central PDO
- `app/Http/Controllers/Company/ContractorActions/modal_archive.php` — requires central PDO
- `app/Http/Controllers/Company/DriverActions/archive.php` — requires central PDO
- `app/Http/Routes/company_vehicles.php:763-840` — vehicle archive inline
- `app/Http/Routes/company_crews_legacy.php:832-910` — crews archive inline
- `app/Http/Routes/company_driver_vehicle_blocks_legacy.php:382-417` — DVB archive
- `app/Http/Routes/company_logists.php:901-960` — user archive
- `app/Http/Controllers/Company/DocumentActions/delete.php` — already soft-delete, add audit
- `app/Http/Controllers/Company/DocumentActions/doc_types_delete.php` — convert to soft-delete
- `app/Http/Controllers/Company/DriverActions/document_delete.php` — already soft-delete, add audit
- `app/Http/Controllers/Company/DriverActions/modal_edit_submit.php` — already uses deleted_at

**Change pattern for each:**
```php
// Before:
$localPdo->prepare("UPDATE clients SET status = 'archived' WHERE id = ?")->execute([$id]);

// After:
$localPdo->prepare("UPDATE clients SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ? WHERE id = ?")
    ->execute([$userId, $role, $id]);

// After (with snapshot for audit):
$displayName = $client['name'] ?? $client['full_name'] ?? "#{$id}";
$snapshot = json_encode($client, JSON_UNESCAPED_UNICODE);
// Get central PDO and write audit
$pdo = $db->connection();
AuditService::recordDeletion($pdo, $company, 'client', $id, 'clients', $displayName, $userId, $role);
```

- [ ] Modify all archive methods (see matrix above)

---

### Task 5: Fix list queries — add `deleted_at IS NULL` filter

**Files to search and modify:**
- `app/Service/ClientService.php` — listClients
- `app/Service/ContractorService.php` — listContractors
- `app/Service/DriverService.php` — listDrivers
- `app/Service/VehicleSetService.php` — listVehicleSets
- `app/Service/DriverService.php:120` — `WHERE dvb.status != 'archived'` → `AND d.deleted_at IS NULL`
- `app/Http/Routes/company_vehicles.php:91,96` — vehicle_units list
- `app/Http/Routes/company_crews_legacy.php` — contractors select, dvb select (already filter status='active')
- `app/Http/Routes/company_driver_vehicle_blocks_legacy.php` — drivers select, vehicle_sets select
- `app/Http/Routes/company_logists.php:108,113` — users list
- Any `WHERE status = 'active'` in select dropdowns should use `deleted_at IS NULL` instead

Where we find `status = 'active'` in SELECT FORMS (not list pages), we must keep `deleted_at IS NULL` equivalent.

- [ ] Modify all list/select queries

---

### Task 6: Update views — remove archive language, show delete language

**Pattern for each entity view's archive button section:**

Change from:
```php
<form method="post" action="/company/clients/<?= $client['id'] ?>/archive" onsubmit="return confirm('Вы уверены? Запись будет перемещена в архив.')">
    <button type="submit" class="btn btn-danger">Архивировать</button>
</form>
```

To:
```php
<form method="post" action="/company/clients/<?= $client['id'] ?>/archive" onsubmit="return confirm('Удалить запись? Запись будет удалена из списка.')">
    <button type="submit" class="btn btn-danger">Удалить</button>
</form>
```

Remove `status === 'archived'` badge displays from list pages for company users.  
Remove «Архив» status option from edit forms (status dropdowns).  
Remove `archived` from status badge logic in list tables.

**Files to modify:**
- `app/View/pages/company_clients.php:101` — remove archived badge
- `app/View/pages/company_client_view.php:223-227` — change archive button
- `app/View/pages/company_contractor_view.php:424` — change archive button
- `app/View/pages/company_driver_view.php:326` — change archive button
- `app/View/pages/company_crew_view.php:197-203` — change archive button
- `app/View/pages/company_vehicle_view.php:229` — change archive button
- `app/View/pages/company_vehicle_set_view.php` — change archive button
- `app/View/pages/company_driver_vehicle_block_view.php:198` — change archive button
- `app/View/pages/company_route_executor_view.php:180-183` — change archive button
- `app/View/pages/company_logist_view.php:150-152` — change archive button
- `app/View/pages/company_client_edit.php:118` — remove Архив option
- `app/View/pages/company_contractor_edit.php:190` — remove Архив option
- `app/View/pages/company_driver_edit.php:195` — remove Архив option
- `app/View/pages/company_vehicle_edit.php:206` — remove Архив option
- `app/View/pages/company_vehicle_set_edit.php:152` — remove Архив option
- `app/View/pages/company_crew_edit.php:187` — remove Архив option
- `app/View/pages/company_driver_vehicle_block_edit.php:131` — remove Архив option
- `app/View/pages/company_route_executor_edit.php:203` — remove Архив option
- `app/View/pages/company_driver_vehicle_blocks.php:108-110` — remove archived badge
- `app/View/pages/company_logists.php:98` — remove archived badge
- `app/View/pages/company_dashboard.php:114` — consider if archive count needed

- [ ] Update all views

---

### Task 7: SUPERADMIN — add «Удалённые данные» page

**Files:**
- Create: `app/Http/Routes/superadmin_deleted_data.php`
- Create: `app/Http/Controllers/Superadmin/DeletedDataController.php`
- Create: `app/View/pages/superadmin_deleted_data.php`
- Modify: `app/View/layouts/main.php` — add menu item
- Modify: `public/index.php` — require new route file

**Route:**
```php
$router->get('/superadmin/deleted-data', [$c, 'index']);
$router->get('/superadmin/deleted-data/{id}', [$c, 'view']);
$router->post('/superadmin/deleted-data/{id}/restore', [$c, 'restore']);
```

**Controller methods:**
- `index()` — list all deleted entities from `deleted_entities` table, paginated, with search/filter by company, entity_type, date range
- `view($id)` — show single deleted entity details (snapshot, who deleted, when, company info, documents)
- `restore($id)` — soft-restore (set entity.deleted_at=NULL + audit.status='restored' + restored_at/restored_by)

**Menu addition in main.php** (inside superadmin nav-group):
```php
<a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/superadmin/deleted-data') ? ' is-active' : '' ?>" href="/superadmin/deleted-data">
    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">...</svg>
    <span class="nav-label">Удалённые данные</span>
</a>
```

**View requirements:**
- Table: Date deleted, Company, Who deleted, Role, Entity type, Display name, Documents, Status, Actions (View / Restore)
- Only SUPERADMIN can access
- No archive/restore language visible to company users

- [ ] Create controller
- [ ] Create route file
- [ ] Create view
- [ ] Register in index.php
- [ ] Add menu item

---

### Task 8: SUPERADMIN — restore logic

**Controller `restore` method:**
1. Validate: auth → superadmin
2. Load `deleted_entities` record by id
3. Validate: company_id exists in `companies` table, company DB is accessible
4. Load entity from local DB: `SELECT * FROM source_table WHERE id = entity_id AND deleted_at IS NOT NULL`
5. Check for unique field conflicts if applicable (e.g., plate_number, login, inn)
6. Transaction: `UPDATE source_table SET deleted_at = NULL WHERE id = entity_id`
7. Update `deleted_entities`: SET `status = 'restored'`, `restored_at = NOW()`, `restored_by_user_id = ?`, `restored_by_role = ?`
8. Return success or error

- [ ] Implement restore in controller

---

### Task 9: Lint, guard, diff checks

- [ ] `php -l` on ALL modified PHP files
- [ ] `cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && php tools\architecture_guard.php"`
- [ ] `cmd.exe /c "cd /d C:\Users\Vladimir\Desktop\PLANEX\SITE\erp && git diff --check"`
- [ ] Check for mojibake: `Рџ`, `РЎ`, `Ð`, `Ñ`, `�`
- [ ] Check `.env`, `.kilo`, `storage/*`, `tmp/*`, `logs/*`, `vendor/*`, `node_modules/*` not in diff

---

## Execution

Use compose:subagent — fresh subagent per task. Tasks 1-2 (migrations) and Task 3 (service) first, then Tasks 4-6 (main logic), then Tasks 7-8 (SUPERADMIN), followed by Task 9 (verification).
