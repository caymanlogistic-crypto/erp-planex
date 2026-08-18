-- ERP PLANEX: direct employee money accounts (cashless employee settlements)
-- Historical CASH accounts and operations are intentionally preserved unchanged.

CREATE TABLE IF NOT EXISTS finance_employee_money_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    money_account_id INT UNSIGNED NOT NULL,
    employee_identity_type VARCHAR(20) NOT NULL,
    employee_identity_id INT UNSIGNED NOT NULL,
    employee_name_snapshot VARCHAR(255) NOT NULL,
    employee_role_snapshot VARCHAR(50) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id INT UNSIGNED NULL,
    created_by_role VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_finance_employee_money_accounts_account (money_account_id),
    UNIQUE KEY uq_finance_employee_money_accounts_identity (employee_identity_type, employee_identity_id),
    KEY idx_finance_employee_money_accounts_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
