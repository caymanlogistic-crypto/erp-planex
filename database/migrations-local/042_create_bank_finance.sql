CREATE TABLE IF NOT EXISTS `bank_accounts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_number` VARCHAR(34) NOT NULL,
    `bank_name` VARCHAR(255) DEFAULT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'RUR',
    `company_inn` VARCHAR(20) DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `opening_balance` DECIMAL(15,2) DEFAULT NULL,
    `closing_balance` DECIMAL(15,2) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bank_accounts_number` (`account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_statement_imports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source` VARCHAR(10) NOT NULL DEFAULT 'manual',
    `source_uid` VARCHAR(255) DEFAULT NULL,
    `filename` VARCHAR(255) DEFAULT NULL,
    `file_hash` VARCHAR(64) NOT NULL,
    `account_number` VARCHAR(34) DEFAULT NULL,
    `period_from` DATE DEFAULT NULL,
    `period_to` DATE DEFAULT NULL,
    `imported_transactions` INT UNSIGNED NOT NULL DEFAULT 0,
    `imported_balances` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bank_imports_hash` (`file_hash`),
    KEY `idx_bank_imports_account` (`account_number`),
    KEY `idx_bank_imports_source` (`source`),
    KEY `idx_bank_imports_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT UNSIGNED NOT NULL,
    `operation_date` DATE NOT NULL,
    `document_number` VARCHAR(50) DEFAULT NULL,
    `operation_type` VARCHAR(100) DEFAULT NULL,
    `counterparty_name` VARCHAR(255) DEFAULT NULL,
    `counterparty_inn` VARCHAR(20) DEFAULT NULL,
    `counterparty_bank_bik` VARCHAR(20) DEFAULT NULL,
    `counterparty_account` VARCHAR(34) DEFAULT NULL,
    `debit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `credit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `purpose` TEXT DEFAULT NULL,
    `dedupe_hash` VARCHAR(64) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bank_tx_dedupe` (`dedupe_hash`),
    KEY `idx_bank_tx_account` (`account_id`),
    KEY `idx_bank_tx_date` (`operation_date`),
    KEY `idx_bank_tx_counterparty` (`counterparty_name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_daily_balances` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT UNSIGNED NOT NULL,
    `statement_date` DATE NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'RUR',
    `opening_balance` DECIMAL(15,2) DEFAULT NULL,
    `debit_turnover` DECIMAL(15,2) DEFAULT NULL,
    `credit_turnover` DECIMAL(15,2) DEFAULT NULL,
    `closing_balance` DECIMAL(15,2) DEFAULT NULL,
    `dedupe_hash` VARCHAR(64) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bank_balances_dedupe` (`dedupe_hash`),
    KEY `idx_bank_balances_account` (`account_id`),
    KEY `idx_bank_balances_date` (`statement_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
