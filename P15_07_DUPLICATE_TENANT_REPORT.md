# P15 — Duplicate Tenant Report

## Identification

Two records named `UIUX TEST EXPEDITOR` were identified before mutation:

- Working test tenant: ID `27`.
- Empty duplicate: ID `26`.

Production company is ID `25`, `ООО "ПЛАНЭКС"`.

## Read-only inventory evidence

Live PHP 8.3 inventory:

- Run: `31002788353`
- Job: `92295431315`
- Artifact: `8928994337`

Tenant 26 counts before archive:

- central users: 0
- local users: 0
- clients: 0
- contractors: 0
- drivers: 0
- vehicle units: 0
- vehicle sets: 0
- driver/vehicle blocks: 0
- crews: 0
- linear routes: 0
- documents: 0
- finance invoices: 0
- finance operations: 0
- bank accounts: 0
- entity access grants: 0

Database and storage fingerprints for tenant 26 differ from tenant 27 and company 25. No tenant-bound record was shared.

## Action

The duplicate was not deleted with SQL. The normal SUPERADMIN company archive form was used through Playwright.

Cleanup evidence:

- Run `31006209536`
- Artifact `8930438028`
- `archiveFormFound=true`
- `archived=true`
- working tenant 27 remained active.

## Result

- Tenant 26: archived.
- Tenant 27: active and retained.
- Company 25: not changed.
- No destructive SQL was executed.
- Final permanent deletion was not required after a safe archive and remains a deliberate SUPERADMIN owner action.
