-- Drop the legacy unique phone index only when it still exists.
SET @dbname = DATABASE();
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers' AND INDEX_NAME = 'uk_phone') > 0,
    'ALTER TABLE `drivers` DROP INDEX `uk_phone`',
    'SELECT 1 AS no_legacy_phone_index'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
