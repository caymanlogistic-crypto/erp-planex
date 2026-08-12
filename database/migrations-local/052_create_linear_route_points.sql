CREATE TABLE IF NOT EXISTS `linear_route_points` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `linear_route_id` INT UNSIGNED NOT NULL,
  `point_type` ENUM('loading','unloading') NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
  `address_text` VARCHAR(500) NOT NULL,
  `created_by_user_id` INT UNSIGNED DEFAULT NULL,
  `created_by_role` VARCHAR(20) DEFAULT NULL,
  `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
  `updated_by_role` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_linear_route_points_route` (`linear_route_id`, `point_type`, `sort_order`),
  CONSTRAINT `fk_linear_route_points_route`
    FOREIGN KEY (`linear_route_id`) REFERENCES `linear_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
