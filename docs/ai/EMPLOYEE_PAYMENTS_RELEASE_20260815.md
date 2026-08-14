# Employee Payments production gate — 2026-08-15

Runtime implementation parent SHA: `23b6fc90602399056ccfed479d8e0fccd536d5ca`.

Scope completed before production promotion:

- all active tenant ERP users are eligible employees regardless of role;
- CASH PAYMENT and RETURN use real `finance_operations` through the existing cash ledger;
- BANK PAYMENT and RETURN reuse existing posted bank-backed finance operations;
- employee movement reassignment changes only the employee relation and is audited;
- cancelled settlements remain accessible in history and do not affect current balance;
- employee settlement balances use exact cents/decimal arithmetic instead of floats;
- duplicate bank/operation employee links and internal bank-to-cash transfers are guarded;
- behavioral regression now runs against isolated MySQL 8;
- production Playwright acceptance remains read-only and verifies the completed employee-payment UI.

Pre-promotion evidence for parent SHA:

- ERP PLANEX CI: PASS;
- Finance Matching Regression: PASS;
- Employee settlement MySQL behavioral regression: PASS;
- no new migration required.

This documentation-only commit exists to trigger the canonical push control plane. Final release acceptance must still pass on the resulting exact canonical SHA: CI, Finance Matching Regression, Controlled Deploy, deployed marker, P41, P42 and P44.
