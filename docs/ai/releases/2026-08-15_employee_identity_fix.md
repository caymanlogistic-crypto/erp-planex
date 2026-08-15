# Employee payments directory fix — 2026-08-15

Production release gate for the employee-payments directory correction.

- Employee directory combines current-company central `company_users` and tenant-local `users`.
- Company owner and logist are both eligible employees when active.
- No fake tenant owner account is created.
- Existing employee movements are preserved and backfilled by migration 066.
- Visible hint `Двойной клик — полный журнал...` is removed while double-click ledger behavior remains.
- Production runtime acceptance must verify both `COMPANY_USER:*` and `TENANT_USER:*` identities are present.
