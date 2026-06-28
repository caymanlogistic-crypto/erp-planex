# ARCHITECTURE_STABILIZED_AFTER_E7_2

## Status

`COMPLETE`

E7.2 hardens the post-split architecture, fixes two runtime blockers, and adds automated guardrails.

### E7.2 Changes (2026-06-28)

- **BLOCKER 1 FIXED**: `ContractorContactService` class existed but `use` statements were missing in `company_contractors.php` and `company_clients.php`. PHP `use` is file-scoped — declarations in `index.php` do not carry into `require`d files. Added `use App\Service\ContractorContactService;` and `use App\Service\CompanyInnLookupService;` to `company_contractors.php`, `use App\Service\ClientContactService;` to `company_clients.php`.
- **BLOCKER 2 FIXED**: `app/View/partials/legal_entity_create_form.php` had double-encoded UTF-8 (UTF-8 read as CP1251 and re-encoded). Rewrote file with correct Cyrillic text.
- **Duplicate rollback removed** in `core.php` `/test-db` route.
- **`tools/architecture_guard.php`** created: validates structure, file sizes, load order, controller wiring, service autoloading.
- **`docs/ai/MODULAR_DEVELOPMENT_RULES.md`** created: hard rules for Route->Controller->Service->View, SQL prohibition in routes, size limits.
- **`docs/ai/RUNTIME_SMOKE_CHECKLIST_E7_2.md`** created: full smoke test results.

## E7.1 Baseline (unchanged)

## What Changed

- `public/index.php` is now a thin front controller with `80` lines.
- Auth, route executors, responsible assignments, and superadmin company/owner flows now use dedicated controllers.
- Shared procedural helpers were extracted into:
  - `app/Support/http_runtime.php`
  - `app/Support/core_runtime.php`
- Local migration runtime moved behind `LocalMigrationService`.
- GET legacy redirects are centralized in `app/Http/Routes/legacy_redirects.php`.

## Controllers Added

- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/Company/RouteExecutorController.php`
- `app/Http/Controllers/Company/ResponsibleAssignmentController.php`
- `app/Http/Controllers/Superadmin/CompanyController.php`
- `app/Http/Controllers/Superadmin/CompanyOwnerController.php`

## Service And Support Additions

- `app/Support/http_runtime.php`
- `app/Support/core_runtime.php`
- `app/Service/LocalMigrationService.php`
- `app/Service/RouteExecutorService.php`
- `app/Service/ResponsibleAssignmentService.php`
- `app/Service/SuperadminCompanyService.php`

## Preserved Contracts

- Protected core behavior was not functionally rewritten:
  - `/company/drivers`
  - `/company/vehicle-sets`
  - `/company/clients`
  - `/company/contractors`
- Legacy redirects remain:
  - `/company/crews`
  - `/company/crews/create`
  - `/company/driver-vehicle-blocks`
  - `/company/driver-vehicle-blocks/create`
  - `/company/contractor-assignments`
- Route executor data rules remain unchanged:
  - `driver_vehicle_blocks` does not receive `vehicle_id`
  - `crews.vehicle_id` still maps from `vehicle_sets.primary_vehicle_unit_id`
  - logist grants still require `revoked_at IS NULL` and `access_level IN ('view','edit')`

## Verification

### Static

- `php -l public/index.php` -> PASS
- `php -l` on new routes/controllers/services/support/action files -> PASS
- `git diff --check` -> PASS, excluding repository line-ending warnings

### Runtime

- `/login` -> `200`
- `superadmin` companies and owner pages -> `200`
- `company_owner` dashboard, route executors, responsible assignments, clients, contractors, drivers, vehicle sets, logists -> `200`
- `senior_logist`:
  - `/company/route-executors` -> `200`
  - `/company/responsible-assignments` -> `403`
- `logist`:
  - `/company/route-executors` -> `200`
  - `/company/responsible-assignments` -> `403`
- Legacy redirects:
  - `/company/crews` -> `302 /company/route-executors`
  - `/company/crews/create` -> `302 /company/route-executors/create`
  - `/company/driver-vehicle-blocks` -> `302 /company/route-executors`
  - `/company/driver-vehicle-blocks/create` -> `302 /company/route-executors/create`
  - `/company/contractor-assignments` -> `302 /company/responsible-assignments`
