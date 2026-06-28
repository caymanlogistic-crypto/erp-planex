# DEAD_CODE_CANDIDATES_E7

- `public/index.php` still contains shared helper functions such as access helpers, upload helpers, and vehicle-set helper logic.
- `applyLocalMigrations()` still lives in `public/index.php` and should be moved to a dedicated service in a later pass.
- Legacy route files `company_crews_legacy.php` and `company_driver_vehicle_blocks_legacy.php` still keep old URI handlers because they are required redirects / compatibility endpoints.
- The old contractor assignment POST flow remains in `app/Http/Routes/company_contractor_assignments.php`; it was not removed because it still has a concrete route and side effects.
- Protected core CRUD blocks for clients, contractors, drivers, and vehicle sets were intentionally not simplified beyond route extraction.
