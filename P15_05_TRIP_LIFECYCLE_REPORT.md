# P15 — Linear Trip Lifecycle Report

## Entity and runtime

- Tenant: `UIUX TEST EXPEDITOR`, ID `27`
- Main P15 trip: `P15 UI рейс полный жизненный цикл`, ID `3`
- Temporary delete/restore trip: `P15 DELETE TEMP рейс`, ID `4`
- Runtime: `https://plan-ex.ru/erpv2/`
- Primary viewport: 1920×1080, DPR 1

## Verified lifecycle for `linear_routes`

Source inventory confirmed that `linear_routes` currently uses `status=active` plus `deleted_at`; map-route statuses belong to a different `routes` entity. P15 therefore verified the actual implemented lifecycle instead of inventing status values:

1. OWNER opened the linear trip registry.
2. Completed the real create form.
3. Assigned client, carrier contractor and route executor.
4. Entered customer and carrier amounts.
5. Entered modern payment conditions.
6. Submitted through the UI.
7. Reopened the created row and confirmed trip ID 3.
8. Opened the edit modal through the real double-click/card/edit flow.
9. Added planned and actual dates.
10. Updated comment and customer amount.
11. Saved and reopened the record.
12. Confirmed financial terms and legacy compatibility data persisted.
13. Uploaded customer and carrier PDF documents through real file inputs.
14. Viewed and downloaded documents with HTTP 200 and `application/pdf`.
15. Replaced the customer document and confirmed the new filename.
16. Confirmed role visibility and finance restrictions.
17. Created a separate temporary trip, soft-deleted it, and observed it in SUPERADMIN deleted-data.
18. Confirmed the restore control is provided by the system.

## Main lifecycle evidence

- Create run: `31004709154`, artifact `8929805480`.
- Full lifecycle run: `31005836928`, job `92305372614`, artifact `8930280906`.
- Cleanup run: `31006209536`, artifact `8930438028`.

## Role results

- COMPANY_OWNER: trip visible and editable.
- SENIOR_LOGIST: trip visible according to current business access rules.
- LOGIST №1: foreign trip not visible.
- LOGIST №2: foreign trip not visible.
- Finance menu: absent for SENIOR_LOGIST/LOGIST roles.

No direct tenant SQL mutation was used for the create/edit/document/delete browser scenarios.

## Documents

- `P15_договор_заказчик_v1.pdf` uploaded.
- `P15_заявка_перевозчик.pdf` uploaded.
- Both view/download endpoints returned 200.
- Customer document replaced with `P15_договор_заказчик_v2.pdf`.
- No real personal or business documents were used.

## Diagnostics

- Console errors: 0
- Page errors: 0
- Request failures: 0
- Unexpected HTTP 500: 0
- SQLSTATE/column/stack leakage: not detected

## Correction loops

1. Initial P14 create failed on `payment_due_type=NULL` and exposed SQL.
2. First P15 runtime submit intentionally stopped on normal payment-date validation because the custom selector held `specific_date` without a date. No record was created.
3. Runtime input was corrected to a valid specific date; creation passed.
4. Initial cleanup assertion inspected the stale row before a full reload. The actual soft-delete succeeded, deleted-data and restore were confirmed, and the assertion was corrected rather than changing ERP behavior.
