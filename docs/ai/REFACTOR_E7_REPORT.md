# REFACTOR_E7_REPORT

STATUS: E7.1_ARCHITECTURE_STABILIZED

1. Original `public/index.php` before E7: `19627` lines.
2. E7 result: `192` lines.
3. E7.1 result: `80` lines.
4. Route registry now uses `19` files under `app/Http/Routes/`.
5. Controllers added in E7.1:
   - `app/Http/Controllers/AuthController.php`
   - `app/Http/Controllers/Company/RouteExecutorController.php`
   - `app/Http/Controllers/Company/ResponsibleAssignmentController.php`
   - `app/Http/Controllers/Superadmin/CompanyController.php`
   - `app/Http/Controllers/Superadmin/CompanyOwnerController.php`
6. Support/service files added in E7.1:
   - `app/Support/http_runtime.php`
   - `app/Support/core_runtime.php`
   - `app/Service/LocalMigrationService.php`
   - `app/Service/RouteExecutorService.php`
   - `app/Service/ResponsibleAssignmentService.php`
   - `app/Service/SuperadminCompanyService.php`
7. Shared helper logic no longer lives in `public/index.php` or `app/Http/Routes/core.php`.
8. GET legacy redirects were isolated to `app/Http/Routes/legacy_redirects.php`.
9. `php -l`: PASS for front controller, route registries, controllers, services, support files, and extracted action includes.
10. `git diff --check`: PASS for whitespace; repository line-ending warnings remain.
11. Runtime regression after E7.1:
    - `/login` -> `200`
    - `superadmin`:
      - `/superadmin/companies` -> `200`
      - `/superadmin/companies/create` -> `200`
      - `/superadmin/companies/24` -> `200`
      - `/superadmin/companies/24/create-owner` -> `200`
      - `/superadmin/companies/24/owner` -> `200`
    - `company_owner`:
      - `/company/dashboard` -> `200`
      - `/company/route-executors` -> `200`
      - `/company/responsible-assignments` -> `200`
      - `/company/clients` -> `200`
      - `/company/contractors` -> `200`
      - `/company/drivers` -> `200`
      - `/company/vehicle-sets` -> `200`
      - `/company/logists` -> `200`
    - `senior_logist`:
      - `/company/route-executors` -> `200`
      - `/company/responsible-assignments` -> `403`
    - `logist`:
      - `/company/route-executors` -> `200`
      - `/company/responsible-assignments` -> `403`
12. Legacy redirects after E7.1:
    - `/company/crews` -> `302 /company/route-executors`
    - `/company/crews/create` -> `302 /company/route-executors/create`
    - `/company/driver-vehicle-blocks` -> `302 /company/route-executors`
    - `/company/driver-vehicle-blocks/create` -> `302 /company/route-executors/create`
    - `/company/contractor-assignments` -> `302 /company/responsible-assignments`
13. Remaining architectural debt:
    - action bodies still live in include files
    - protected core remains intentionally untouched
