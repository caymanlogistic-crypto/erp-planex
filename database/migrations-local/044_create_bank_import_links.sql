CREATE TABLE IF NOT EXISTS `bank_import_transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `import_id` INT UNSIGNED NOT NULL,
    `transaction_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_imp_tx_link` (`import_id`, `transaction_id`),
    KEY `idx_imp_tx_tx` (`transaction_id`),
    KEY `idx_imp_tx_imp` (`import_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_import_balances` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `import_id` INT UNSIGNED NOT NULL,
    `balance_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_imp_bal_link` (`import_id`, `balance_id`),
    KEY `idx_imp_bal_bal` (`balance_id`),
    KEY `idx_imp_bal_imp` (`import_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill legacy data: link existing transactions to imports by account_number
-- and overlapping period. A transaction whose date falls within an import's
-- period range gets linked; when ranges overlap, all matching imports are linked.
INSERT IGNORE INTO `bank_import_transactions` (`import_id`, `transaction_id`)
SELECT DISTINCT imp.`id`, tx.`id`
FROM `bank_statement_imports` imp
JOIN `bank_transactions` tx ON tx.`account_id` = (
    SELECT ba.`id` FROM `bank_accounts` ba WHERE ba.`account_number` = imp.`account_number` LIMIT 1
)
WHERE imp.`period_from` IS NOT NULL
  AND imp.`period_to` IS NOT NULL
  AND tx.`operation_date` >= imp.`period_from`
  AND tx.`operation_date` <= imp.`period_to`;

-- Backfill legacy balance links
INSERT IGNORE INTO `bank_import_balances` (`import_id`, `balance_id`)
SELECT DISTINCT imp.`id`, bal.`id`
FROM `bank_statement_imports` imp
JOIN `bank_daily_balances` bal ON bal.`account_id` = (
    SELECT ba.`id` FROM `bank_accounts` ba WHERE ba.`account_number` = imp.`account_number` LIMIT 1
)
WHERE imp.`period_from` IS NOT NULL
  AND imp.`period_to` IS NOT NULL
  AND bal.`statement_date` >= imp.`period_from`
  AND bal.`statement_date` <= imp.`period_to`;
