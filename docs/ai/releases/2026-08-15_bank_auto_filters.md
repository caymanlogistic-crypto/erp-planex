# Bank statement automatic filters — 2026-08-15

Release gate for bank statement register UX and filtering fixes.

- Status, date-from, date-to and text search apply automatically without an Apply button.
- Search is debounced; select/date changes apply immediately.
- Empty date controls are normalized and do not corrupt status/search filtering.
- New filter changes reset stale transaction pagination.
- The visible `Счёт` column is removed from the register layout.
- The freed width is assigned to `Назначение`.
- MySQL behavioral regression covers status/search/date/combined filters.
- P46 production acceptance is read-only and exercises live auto-filter navigation after exact-SHA deploy.
- Employee-payments acceptance remains part of the same release: current-company owner and tenant logist/user must both be selectable, and the obsolete visible double-click hint must remain absent.
