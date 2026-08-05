# P15 — Command and Runtime Log

This log records high-level commands and their effective exit status. Secret-bearing commands are described without values.

| Phase | Command/action | Exit/result | Evidence |
|---|---|---:|---|
| GitHub gate | Read repository, branches, commits, workflows, PR #9 | 0 / PASS | GitHub connector responses |
| Archive gate | ZIP CRC, file enumeration, JSON validation | 0 / PASS | local archive inspection |
| Browser gate | Playwright smoke, 1920×1080 | 0 / PASS | run 30958430862 |
| Login gate | SUPERADMIN UI login and protected navigation | 0 / PASS | run 30958430841 |
| Tenant gate | PHP 8.3 read-only SHOW TABLES/COUNT using per-tenant credentials | 0 / PASS | run 31002788353 |
| Patch driver | Apply exact anchored P15 production patch | 0 / PASS | production commit f6688855… |
| PHP lint | `php -l` under PHP 8.3 | 0 / PASS | run 31004020783 |
| Regression | execute all `tests/p15*.php` | 0 / PASS | run 31006209539, 3/3 |
| JS syntax | `node --check` application JS | 0 / PASS | run 31004020783 |
| Secret scan | deterministic UTF-8 scanner | 0 / PASS | run 31004020783 |
| Mojibake/BOM | UTF-8, marker and BOM scan | 0 / PASS | run 31004020783 |
| Diff review | `git diff --check`, forbidden path review | 0 / PASS | run 31004020783 |
| Deploy build | `git archive` application paths, deterministic gzip/SHA-256 | 0 / PASS | run 31004254613 |
| Deploy | P07 server script targeting only `/erpv2` | 0 / PASS | run 31004254613 |
| Old ERP check | before/after filesystem fingerprint | 0 / PASS | deploy artifact 8929610352 |
| Trip create | UI form submit | 0 / PASS | run 31004709154 |
| Trip lifecycle | edit/reopen/finance/documents/roles | 0 / PASS | run 31005836928 |
| Soft-delete | UI danger action on separate temp route | 0 / PASS with assertion correction | run 31006209536 |
| Duplicate tenant | SUPERADMIN archive form for tenant 26 | 0 / PASS | run 31006209536 |

## Correction loops

1. Startup browser gate initially required a public deploy marker. The marker is intentionally server-side; the check was moved to SSH without weakening UI checks.
2. Tenant inventory initially selected a build directory without live `.env`, then a tenant config without per-tenant DB credentials. Both probes failed closed and made no mutations. The final live PHP 8.3 inventory passed.
3. Patch regression initially treated a business document-upload exception as an unexpected leak. The assertion was narrowed to `catch (Throwable)` while retaining expected validation messages.
4. The first Playwright create submit used `specific_date` without the corresponding date because of a custom selector. The business validation correctly rejected it; no route was created. The valid date was added and the blocker test passed.
5. Cleanup checked a stale client-side row immediately after delete. Server-side soft-delete, deleted-data and restore control were present. The evidence was retained and the restore verification was separated.

All failed correction-loop jobs uploaded evidence and did not write partial production changes because commit/deploy steps were gated after successful checks.
