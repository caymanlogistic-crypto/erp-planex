-- Migration 029: scalable crew membership for one or more drivers per route executor.
-- Legacy crews.driver_id remains populated with the first driver for backward compatibility.
CREATE TABLE IF NOT EXISTS `crew_drivers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `crew_id` INT UNSIGNED NOT NULL,
  `driver_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crew_driver` (`crew_id`,`driver_id`),
  UNIQUE KEY `uq_crew_position` (`crew_id`,`position`),
  KEY `idx_crew_drivers_driver` (`driver_id`),
  KEY `idx_crew_drivers_crew` (`crew_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `crew_drivers` (`crew_id`,`driver_id`,`position`)
SELECT `id`,`driver_id`,1 FROM `crews` WHERE `driver_id` IS NOT NULL AND `driver_id` > 0;

SET @has_secondary_driver := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crews' AND COLUMN_NAME = 'secondary_driver_id'
);
SET @copy_secondary_sql := IF(
  @has_secondary_driver > 0,
  'INSERT IGNORE INTO crew_drivers (crew_id, driver_id, position) SELECT id, secondary_driver_id, 2 FROM crews WHERE secondary_driver_id IS NOT NULL AND secondary_driver_id > 0',
  'SELECT 1'
);
PREPARE crew_secondary_stmt FROM @copy_secondary_sql;
EXECUTE crew_secondary_stmt;
DEALLOCATE PREPARE crew_secondary_stmt;
