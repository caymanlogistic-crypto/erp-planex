CREATE TABLE IF NOT EXISTS `linear_route_principals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `linear_route_id` INT UNSIGNED NOT NULL,
    `principal_type` VARCHAR(20) NOT NULL,
    `principal_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by_user_id` INT UNSIGNED DEFAULT NULL,
    `deleted_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_linear_route_principals_route` (`linear_route_id`),
    KEY `idx_linear_route_principals_entity` (`principal_type`, `principal_id`),
    KEY `idx_linear_route_principals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `linear_route_payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `linear_route_id` INT UNSIGNED NOT NULL,
    `party_role` VARCHAR(20) NOT NULL,
    `linear_route_principal_id` INT UNSIGNED DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `legacy_financial_term_id` INT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_type` VARCHAR(50) NOT NULL,
    `payment_due_type` VARCHAR(100) NOT NULL,
    `payment_due_days` INT DEFAULT NULL,
    `payment_due_days_kind` VARCHAR(20) DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_role` VARCHAR(20) DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by_user_id` INT UNSIGNED DEFAULT NULL,
    `deleted_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_linear_route_payments_legacy_term` (`legacy_financial_term_id`),
    KEY `idx_linear_route_payments_route` (`linear_route_id`),
    KEY `idx_linear_route_payments_principal` (`linear_route_principal_id`),
    KEY `idx_linear_route_payments_party` (`party_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `linear_route_principals` (
    `linear_route_id`,
    `principal_type`,
    `principal_id`,
    `sort_order`,
    `status`,
    `created_by_user_id`,
    `created_by_role`,
    `updated_by_user_id`,
    `updated_by_role`
)
SELECT
    lr.`id`,
    lr.`principal_type`,
    lr.`principal_id`,
    1,
    'active',
    lr.`created_by_user_id`,
    lr.`created_by_role`,
    lr.`updated_by_user_id`,
    lr.`updated_by_role`
FROM `linear_routes` lr
WHERE lr.`route_type` = 'agency'
  AND lr.`principal_type` IS NOT NULL
  AND lr.`principal_id` IS NOT NULL
  AND lr.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `linear_route_principals` lrp
      WHERE lrp.`linear_route_id` = lr.`id`
        AND lrp.`principal_type` = lr.`principal_type`
        AND lrp.`principal_id` = lr.`principal_id`
        AND lrp.`deleted_at` IS NULL
  );

INSERT INTO `linear_route_payments` (
    `linear_route_id`,
    `party_role`,
    `linear_route_principal_id`,
    `sort_order`,
    `legacy_financial_term_id`,
    `amount`,
    `payment_type`,
    `payment_due_type`,
    `payment_due_days`,
    `payment_due_days_kind`,
    `created_by_user_id`,
    `created_by_role`,
    `updated_by_user_id`,
    `updated_by_role`,
    `deleted_at`,
    `deleted_by_user_id`,
    `deleted_by_role`,
    `created_at`,
    `updated_at`
)
SELECT
    lrft.`linear_route_id`,
    lrft.`party_role`,
    CASE
        WHEN lrft.`party_role` = 'principal' THEN (
            SELECT MIN(lrp.`id`)
            FROM `linear_route_principals` lrp
            WHERE lrp.`linear_route_id` = lrft.`linear_route_id`
              AND lrp.`deleted_at` IS NULL
        )
        ELSE NULL
    END,
    1,
    lrft.`id`,
    lrft.`amount`,
    lrft.`payment_type`,
    lrft.`payment_due_type`,
    lrft.`payment_due_days`,
    lrft.`payment_due_days_kind`,
    lrft.`created_by_user_id`,
    lrft.`created_by_role`,
    lrft.`updated_by_user_id`,
    lrft.`updated_by_role`,
    lrft.`deleted_at`,
    lrft.`deleted_by_user_id`,
    lrft.`deleted_by_role`,
    lrft.`created_at`,
    lrft.`updated_at`
FROM `linear_route_financial_terms` lrft
WHERE NOT EXISTS (
    SELECT 1
    FROM `linear_route_payments` lrp2
    WHERE lrp2.`legacy_financial_term_id` = lrft.`id`
);
