-- Migration 064: CFU -> DDS category structure links
-- Existing dictionaries remain the source of truth; this table only defines allowed pairs.

CREATE TABLE IF NOT EXISTS `finance_cash_flow_center_dds_categories` (
    `cash_flow_center_id` INT UNSIGNED NOT NULL,
    `dds_category_id` INT UNSIGNED NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 100,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`cash_flow_center_id`, `dds_category_id`),
    KEY `idx_fsc_dds` (`dds_category_id`),
    KEY `idx_fsc_active` (`cash_flow_center_id`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backward-compatible rollout: do not make existing bank allocation unusable on day one.
-- All active articles are initially linked to all active CFUs; the owner narrows the tree
-- from the Financial Structure screen. Historical classifications are never rewritten.
INSERT IGNORE INTO `finance_cash_flow_center_dds_categories`
    (`cash_flow_center_id`, `dds_category_id`, `sort_order`, `is_active`)
SELECT c.id, d.id, d.sort_order, 1
FROM `finance_cash_flow_centers` c
CROSS JOIN `finance_dds_categories` d
WHERE c.is_active = 1 AND d.is_active = 1;
