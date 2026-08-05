# P15 — Detailed Implementation Report

## Objective

Close the P14 trip-creation blocker without hiding fields or weakening the schema; provide safe error handling; verify the actual linear-trip lifecycle; isolate test data; and preserve old `/erp`.

## Baseline

- Base SHA: `381a187d7f5cc02eb338b81836dd7ce0099a78b2`.
- P14 blocker: `payment_due_type` NULL on linear trip create.
- P14 SQL exception visible in UI.
- Working test tenant: 27.
- Empty duplicate tenant: 26.

## Implementation

Production commit `f6688855fa3c5e58ee690ca4c8a09e2f0a257e7c`:

- Added canonical condition-to-legacy payment mapping.
- Populated create-path legacy due type/days/kind.
- Shared mapping with edit-save.
- Added protected mutation error service.
- Replaced raw unexpected create/edit messages with correlation IDs.
- Added regression tests.

No PHP 8.4/8.5-only syntax was used. PHP 8.3 verification passed.

## Technical acceptance

- Release gate: run `31004020783`, PASS.
- Complete P15 suite: run `31006209539`, 3 PASS, 0 FAIL.
- Secret scan: PASS.
- Mojibake/UTF-8/BOM scans: PASS.
- Forbidden-path diff: PASS.

## Deployment

- Run: `31004254613`
- Job: `92300192372`
- Target: `/erpv2`
- Deployed SHA: `8c7571c01bcd87f42c4f2e9300fc52a2499935fe`
- Old `/erp` before/after fingerprint: identical.
- Server marker matched deployed SHA.
- Existing P07 atomic deployment mechanism reused.

## Runtime acceptance

Main trip ID 3:

- UI create: PASS.
- Reopen: PASS.
- Edit: PASS.
- Customer and carrier amounts: PASS.
- Modern and legacy payment conditions: PASS.
- Actual loading/unloading dates: PASS.
- Comment persistence: PASS.
- Two PDF uploads: PASS.
- View/download: PASS, HTTP 200.
- Replacement: PASS.
- OWNER/SENIOR_LOGIST visibility: PASS.
- LOGIST №1/№2 isolation: PASS.
- Finance menu restriction: PASS.
- SQL/stack/path leak: absent.

Temporary route ID 4:

- Separate UI create: PASS.
- Soft-delete action: PASS.
- SUPERADMIN deleted-data visibility: PASS.
- Restore action present: PASS.

Duplicate tenant:

- ID 26 proven empty by direct read-only inventory.
- Archived through SUPERADMIN UI.
- Tenant 27 remained active.

## Architecture decision on statuses

The contract requested confirmation of the status chain rather than invention. Source inventory showed that `linear_routes` is not the map-route entity. It stores `active/deleted_at`; `executor_found/pickup_started` occur in another route subsystem. P15 retained the actual domain boundary and did not introduce unverified statuses.

## External notifications

P15 linear-trip scenarios did not invoke map-route status transitions that trigger MAX. Test email addresses and synthetic documents were used. No real notification recipient was configured or contacted.

## Risks carried to P16/P17

- Other historical mutation controllers may still use raw `getMessage()` redirects and must be scanned during system-wide acceptance.
- P16 must populate tenant 27 idempotently with `UIUX_P16_` data before judging dense tables and dashboards.
- P17 must independently retest trip create on the final P16 deployed SHA, not reuse P15 screenshots.

## P15 decision

The confirmed P14 blocker is fixed and verified on deployed runtime. P15 is ready for P16 after the final restore evidence is attached to the runtime matrix.
