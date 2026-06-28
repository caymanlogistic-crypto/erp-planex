# ARCHITECTURE_STABILIZATION_E7_1_PLAN

## Baseline after E7

- Branch at start: `refactor/remove-index-monolith`
- E7.1 working branch: `refactor/stabilize-modular-structure`
- `public/index.php`: 156 lines / ~7 KB in current tree
- `app/Http/Routes/*.php`: 18 files, still ~19k lines total
- `app/Http/Controllers`: absent
- `app/Service`: exists, but current foundation services are only partially integrated
- `bootstrap/app.php`: present and required by `public/index.php`

## Current structural problems

- Route files still contain large closure-based business logic blocks.
- `app/Http/Routes/core.php` mixes route registration with shared helper functions.
- `public/index.php` still carries auth/session/access helper functions.
- `applyLocalMigrations()` still exists as global procedural logic instead of service-owned logic.
- Critical modules do not yet have controller boundaries:
  - auth
  - route executors
  - responsible assignments
  - superadmin companies / owner

## Baseline runtime before E7.1 changes

- `php -l public/index.php`: PASS
- `superadmin`:
  - `/superadmin/companies` -> 200
  - `/superadmin/companies/create` -> 200
  - `/superadmin/companies/{id}` -> 200
  - `/superadmin/companies/{id}/create-owner` -> 200
  - `/superadmin/companies/{id}/owner` -> 200
- `company_owner`:
  - `/company/dashboard` -> 200
  - `/company/route-executors` -> 200
  - `/company/responsible-assignments` -> 200
  - `/company/clients` -> 200
  - `/company/contractors` -> 200
  - `/company/drivers` -> 200
  - `/company/vehicle-sets` -> 200
  - `/company/logists` -> 200
- `senior_logist`:
  - `/company/route-executors` -> 200
  - `/company/responsible-assignments` -> 403
- `logist`:
  - `/company/route-executors` -> 200
  - `/company/responsible-assignments` -> 403
- legacy redirects:
  - `/company/crews` -> 302 `/company/route-executors`
  - `/company/crews/create` -> 302 `/company/route-executors/create`
  - `/company/driver-vehicle-blocks` -> 302 `/company/route-executors`
  - `/company/driver-vehicle-blocks/create` -> 302 `/company/route-executors/create`
  - `/company/contractor-assignments` -> 302 `/company/responsible-assignments`

## Planned E7.1 stabilization steps

1. Extract shared migration/access logic into service/support layer with compatibility wrappers.
2. Introduce controllers for auth, route executors, responsible assignments, and superadmin company/owner flows.
3. Reduce route files to registration-only maps.
4. Isolate GET legacy redirects into a dedicated route layer.
5. Re-run lint and runtime regression after each major extraction.
