# ARCHITECTURE_STABILIZED_AFTER_E7_1

## Status

`COMPLETE`

E7.1 stabilizes the post-split architecture without changing the runtime contract.

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
