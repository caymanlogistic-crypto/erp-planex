# DEAD_CODE_REMOVED_E7

- No business logic files were physically deleted in this pass.
- The safe reduction was structural: route registration was moved out of `public/index.php` into `app/Http/Routes/*.php`.
- This was treated as the lowest-risk E7 move because it preserves existing closures and SQL logic verbatim.
- Verification:
  - `php -l public/index.php`
  - `php -l app/Http/Routes/*.php`
  - HTTP smoke on login, superadmin pages, company pages, role access, and legacy redirects
