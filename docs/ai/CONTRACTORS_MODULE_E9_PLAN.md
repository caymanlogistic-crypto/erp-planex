# E9 Contractors Module Refactor — Plan

## Current State (before refactor)

- `app/Http/Routes/company_contractors.php` — 3212 lines, monolithic closures
- All HTTP logic, business logic, DB queries, validation are inline in route closures
- Shared services: `ContractorContactService`, `CompanyInnLookupService`
- Views: `company_contractors.php`, `company_contractors_create.php`, `company_contractor_edit.php`, `company_contractor_view.php`
- Partials: `contractor_contact_fields.php`, `company_contractor_modal_view.php`, `company_contractor_modal_edit.php`, `legal_entity_create_form.php`

## Target Architecture (matching Clients/E8)

```
Route (thin, <=150 lines)
  → Controller (delegates to Action files via require)
    → Action files (HTTP logic, uses Service)
      → Service (business logic, DB queries)
        → Views (unchanged)
```

## Routes Inventory

| # | Method | Path | Purpose | Action File |
|---|--------|------|---------|-------------|
| 1 | GET | /company/contractors | List | index.php |
| 2 | GET | /company/contractors/create | Create form | create_form.php |
| 3 | POST | /company/contractors/create | Create submit | create_submit.php |
| 4 | GET | /company/requisites/lookup-by-inn | INN autofill | lookup_inn.php |
| 5 | GET | /company/contractors/create-full | Create full form | create_full_form.php |
| 6 | POST | /company/contractors/create-full | Create full submit | create_full_submit.php |
| 7 | GET | /company/contractors/{id}/add-crew | Add crew form | add_crew_form.php |
| 8 | POST | /company/contractors/{id}/add-crew | Add crew submit | add_crew_submit.php |
| 9 | GET | /company/contractors/{id} | View | show.php |
| 10 | GET | /company/contractors/{id}/edit | Edit form | edit_form.php |
| 11 | POST | /company/contractors/{id}/edit | Edit submit | edit_submit.php |
| 12 | POST | /company/contractors/{id}/archive | Archive | archive.php |
| 13 | GET | /company/contractors/{id}/modal-view | Modal view | modal_view.php |
| 14 | GET | /company/contractors/{id}/modal-edit | Modal edit form | modal_edit_form.php |
| 15 | POST | /company/contractors/{id}/modal-edit | Modal edit submit | modal_edit_submit.php |
| 16 | POST | /company/contractors/{id}/modal-archive | Modal archive | modal_archive.php |
| 17 | POST | /company/contractors/{cid}/contacts/create | Contact create | contact_create.php |
| 18 | POST | /company/contractors/{cid}/contacts/{ctid}/edit | Contact edit | contact_edit.php |
| 19 | POST | /company/contractors/{cid}/contacts/{ctid}/delete | Contact delete | contact_delete.php |
| 20 | POST | /company/contractors/{cid}/contacts/{ctid}/set-primary | Set primary | contact_set_primary.php |
| 21 | POST | /company/contractors/{cid}/contacts/{ctid}/set-document-email | Set doc email | contact_set_doc_email.php |
| 22 | POST | /company/contractors/{cid}/tax-history/create | Tax history | tax_history_create.php |

## New Files

1. `app/Service/ContractorService.php` — business logic, DB queries (analog of ClientService)
2. `app/Http/Controllers/Company/ContractorController.php` — delegates to actions
3. `app/Http/Controllers/Company/ContractorActions/` — 22 action files
4. `docs/ai/CONTRACTORS_MODULE_E9_REPORT.md` — final report

## Modified Files

1. `app/Http/Routes/company_contractors.php` — thin route registration (<=150 lines)

## Unchanged

- All view files (company_contractors.php, *_create.php, *_edit.php, *_view.php)
- All partials (contractor_contact_fields.php, legal_entity_create_form.php, modal_*)
- ContractorContactService (already clean)
- CompanyInnLookupService (already clean)
- CSS/JS/design
- Business model, DB schema

## Key Decisions

1. **INN lookup** (`/company/requisites/lookup-by-inn`) stays in route file as its own action — it's a standalone AJAX endpoint used by both contractors and clients
2. **create-full** and **add-crew** routes are complex but self-contained; extracted as separate action files
3. **Contacts CRUD** endpoints are thin redirect-based; extracted as separate action files
4. **Tax history** endpoint is thin; extracted as separate action file
