# ERP PLANEX — текущая задача

## Актуализация 2026-07-05 — DB pool usage journal (one-way consumption safety)

**Статус**: DB_POOL_USAGE_JOURNAL_IMPLEMENTED

Выполнено:

### A. Central usage journal table
- `app/Support/company_database.php`: `ensureCompanyDbPoolUsageTable(PDO $pdo): void` creates `company_db_pool_usage` table in central DB if not exists.
- Table: `company_db_pool_usage` with `id`, `db_identifier` (UNIQUE), `company_id`, `created_at`, `released_at`, `note`.
- No passwords, host or user secrets stored in this table.

### B. findFreePoolDb() updated
- Now calls `ensureCompanyDbPoolUsageTable()` first.
- Considers a pool DB used if it exists in `companies.db_identifier` OR in `company_db_pool_usage.db_identifier`.
- Returns only entries not consumed by either source.

### C. markPoolDbUsed() helper
- `markPoolDbUsed(PDO $centralPdo, string $dbIdentifier, int $companyId): void`
- Calls `ensureCompanyDbPoolUsageTable()` and inserts with `INSERT IGNORE` (idempotent).
- Records `db_identifier`, `company_id`, note `superadmin_create`.

### D. Pool usage recorded in create_submit.php
- After successful pool DB assignment and central company update, calls `markPoolDbUsed($pdo, $finalDbName, $companyId)`.
- If marking fails, company row is rolled back (`DELETE FROM companies`) and clear error shown.
- One-way consumption: pool DBs are never reused, even if company is deleted later.

### E. Docs updated
- `docs/ai/CURRENT_TASK.md`, `docs/ai/DECISIONS.md`, `docs/ai/HANDOFF_FOR_NEW_CHAT.md` updated.
- Pool DBs documented as one-time consumed; when exhausted owner must create new DBs and append to `COMPANY_DB_POOL_JSON`.
