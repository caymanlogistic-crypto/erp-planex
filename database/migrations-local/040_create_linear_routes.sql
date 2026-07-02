SET @dbname = DATABASE();

CREATE TABLE IF NOT EXISTS `cargo_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `normalized_name` VARCHAR(191) NOT NULL,
    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
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
    UNIQUE KEY `uk_cargo_types_normalized_name` (`normalized_name`),
    KEY `idx_cargo_types_status` (`status`),
    KEY `idx_cargo_types_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `linear_routes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `route_type` VARCHAR(20) NOT NULL,
    `client_id` INT UNSIGNED NOT NULL,
    `carrier_contractor_id` INT UNSIGNED NOT NULL,
    `principal_type` VARCHAR(20) DEFAULT NULL,
    `principal_id` INT UNSIGNED DEFAULT NULL,
    `agency_contract_with` VARCHAR(20) DEFAULT NULL,
    `route_executor_id` INT UNSIGNED NOT NULL,
    `cargo_type_id` INT UNSIGNED NOT NULL,
    `planned_loading_date` DATE NOT NULL,
    `planned_unloading_date` DATE NOT NULL,
    `actual_loading_date` DATE DEFAULT NULL,
    `actual_unloading_date` DATE DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `comments` TEXT DEFAULT NULL,
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
    KEY `idx_linear_routes_type` (`route_type`),
    KEY `idx_linear_routes_status` (`status`),
    KEY `idx_linear_routes_client` (`client_id`),
    KEY `idx_linear_routes_carrier` (`carrier_contractor_id`),
    KEY `idx_linear_routes_executor` (`route_executor_id`),
    KEY `idx_linear_routes_cargo_type` (`cargo_type_id`),
    KEY `idx_linear_routes_created_by` (`created_by_user_id`),
    KEY `idx_linear_routes_principal` (`principal_type`, `principal_id`),
    KEY `idx_linear_routes_dates` (`planned_loading_date`, `planned_unloading_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `linear_route_financial_terms` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `linear_route_id` INT UNSIGNED NOT NULL,
    `party_role` VARCHAR(20) NOT NULL,
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
    UNIQUE KEY `uk_linear_route_party_role` (`linear_route_id`, `party_role`),
    KEY `idx_linear_route_financial_terms_route` (`linear_route_id`),
    KEY `idx_linear_route_financial_terms_party` (`party_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_types` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `entity_type` VARCHAR(20) DEFAULT NULL,
    `category` VARCHAR(20) NOT NULL DEFAULT 'custom',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_by_role` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_entity_type` (`entity_type`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE t1 FROM `document_types` t1
INNER JOIN `document_types` t2
WHERE t1.id > t2.id
  AND t1.name = t2.name
  AND COALESCE(t1.entity_type, '') = COALESCE(t2.entity_type, '');

SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'document_types' AND INDEX_NAME = 'uk_name_entity_type') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `document_types` ADD UNIQUE INDEX `uk_name_entity_type` (`name`, `entity_type`)'
    )
);
PREPARE addUniqueIfNotExists FROM @preparedStatement;
EXECUTE addUniqueIfNotExists;
DEALLOCATE PREPARE addUniqueIfNotExists;

INSERT IGNORE INTO `document_types` (`name`, `code`, `entity_type`, `category`, `sort_order`) VALUES
('Договор/заявка с заказчиком', 'customer_contract_request', 'linear_route', 'predefined', 1),
('Договор/заявка с перевозчиком', 'carrier_contract_request', 'linear_route', 'predefined', 2),
('Договор/заявка с принципалом', 'principal_contract_request', 'linear_route', 'predefined', 3);
