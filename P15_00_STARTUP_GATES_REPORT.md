# P15 — Startup Gates Report

## Status

- `GITHUB=PASS`
- `ARCHIVE=PASS`
- `BROWSER=PASS`
- `SUPERADMIN=PASS`
- `TENANT_ISOLATION=PASS`

Production code was not changed before all five gates passed.

## GitHub gate

- Repository: `caymanlogistic-crypto/erp-planex`
- Base branch: `chatgpt/production-stabilization-20260802`
- Base HEAD at start: `381a187d7f5cc02eb338b81836dd7ce0099a78b2`
- P14 audit branch: `chatgpt/p14-browser-audit-20260805`
- P14 audit PR: `#9`, draft, not merged
- Implementation branch: `chatgpt/p15-p17-erp-fullhd-completion-20260805`
- Implementation PR: `#10`, draft
- P14 audit branch differs from base only by P14 audit/browser files and workflows; production files were not imported by merging PR #9.
- Existing deploy workflow: `.github/workflows/P07_deploy_erpv2.yml`
- Server deploy script: `/home/s/<redacted>/planexp/deploy/P07_deploy_erpv2.sh`
- Live target: `/home/s/<redacted>/planexp/public_html/erpv2`
- Old ERP target is separate: `/home/s/<redacted>/planexp/public_html/erp`
- Deployment marker: `/home/s/<redacted>/planexp/deploy/P07_erpv2_deployed_commit.txt`

## Archive gate

Input archive: `P14_ERP_PLANEX_AUDIT_COMPLETE_READY_FOR_REVIEW(2).zip`.

- ZIP integrity: PASS
- Files: 88
- PNG screenshots: 67
- JSON files: 2
- JSON validation errors: 0
- Main P14 report, route/runtime JSON and blocker evidence found and read.
- P14 blocker reconfirmed against current code: create-path payment rows do not populate legacy payment fields, while legacy finance persistence reads them; the create catch exposes the raw exception message.

## Browser and SUPERADMIN gate

Runtime: `https://plan-ex.ru/erpv2/`

- GitHub-hosted Ubuntu 24.04
- Node.js 22
- Playwright 1.54.2
- Chromium headless
- viewport 1920×1080
- deviceScaleFactor 1
- locale `ru-RU`
- timezone `Europe/Moscow`
- UI login: PASS
- same BrowserContext protected navigation: PASS
- companies page: HTTP 200
- company detail pages 25, 26 and 27: HTTP 200
- Console errors: 0
- page errors: 0
- request failures: 0
- unexpected HTTP errors: 0
- old `/erp`: HTTP 200 and separate `/erp/login` final URL

Evidence runs:

- P14 browser smoke re-run: run `30958430862`, job `92290388917`
- P14 SUPERADMIN re-run: run `30958430841`, job `92290426489`
- P15 browser gate: run `31001953032`, browser step PASS

Secrets were supplied through the existing encrypted P11 credential envelope. Plaintext credentials, cookies, session IDs and storageState were not committed or uploaded.

## Tenant isolation gate

Live PHP 8.3 inventory run: `31002788353`.

### Production company

- ID: `25`
- Name: `ООО "ПЛАНЭКС"`
- Database fingerprint differs from tenants 26 and 27.
- Storage fingerprint differs from tenants 26 and 27.
- Production records were read only and were not changed.

### Working test tenant

- ID: `27`
- Name: `UIUX TEST EXPEDITOR`
- Central users: 4
- Local records at gate: clients 3, contractors 1, drivers 1, vehicle units 1, vehicle sets 1, driver/vehicle blocks 1, crews 1, routes 0, documents 7, finance invoices 1.
- This tenant is the only allowed target for P15–P17 test mutations.

### Empty duplicate tenant

- ID: `26`
- Name: `UIUX TEST EXPEDITOR`
- Central users: 0
- Local users: 0
- Clients: 0
- Contractors: 0
- Drivers: 0
- Vehicle units/sets: 0
- Driver/vehicle blocks and crews: 0
- Linear routes: 0
- Documents: 0
- Finance invoices: 0
- Finance operations: 0
- Bank accounts: 0
- Entity access grants: 0
- Database and storage are distinct from tenant 27 and production company 25.

Tenant 26 is proven safe for deletion only through the normal SUPERADMIN UI flow. Tenant 27 and company 25 are forbidden deletion targets.

## Gate decision

`P15_STARTUP_GATES=PASS`

Production implementation may begin on the implementation branch. Deployment remains prohibited until P15 technical tests and review pass.
