# ERP PLANEX — текущая задача

## Актуализация 2026-07-06 — DB pool cleanup safety: release only after successful cleanup + full DB object cleanup

**Статус**: DB_POOL_CLEANUP_SAFETY_FIXED

Выполнено:

### A. `superadmin_company_delete.php`: release pool DB only after successful cleanup
- `releasePoolDb()` now runs ONLY when `cleanCompanyPoolDatabase()` succeeds.
- If cleanup fails, `released_at` stays `NULL`, the DB remains reserved, and `$overallSuccess = false`.
- The delete report records `pool_db_release` as `skipped` with reason `cleanup_failed`.
- This prevents dirty DBs from being reused by the next company.

### B. `cleanCompanyPoolDatabase()` fully cleans a pool DB
Now drops all of the following for the current database:
- **Triggers** (from `information_schema.TRIGGERS`)
- **Views** (from `SHOW FULL TABLES WHERE Table_type = 'VIEW'`)
- **Base tables** (with `SET FOREIGN_KEY_CHECKS=0`)
- **Routines** (procedures/functions from `information_schema.ROUTINES`)
- **Events** (from `information_schema.EVENTS`)
- `FOREIGN_KEY_CHECKS` restored to `1` even if an exception occurs (try/finally).
- Backtick escaping helper `companyDbQuoteIdentifier()` added for safe identifier quoting.
- Does NOT drop the database itself. MySQL 5.7 compatible.

### C. New helper `companyDbQuoteIdentifier(string $identifier): string`
- Safe backtick escaping for MySQL identifiers.
- Double backticks inside names per MySQL standard.

### D. Docs updated
- All relevant docs state: DB is marked free only after successful cleanup; dirty/failed cleanup DB remains reserved.
