-- Migration 021: Add extended fields to entity_access_grants (idempotent)
SET @dbname = DATABASE();

-- comment
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'entity_access_grants' AND COLUMN_NAME = 'comment') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `entity_access_grants` ADD COLUMN `comment` TEXT DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- revoked_at
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'entity_access_grants' AND COLUMN_NAME = 'revoked_at') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `entity_access_grants` ADD COLUMN `revoked_at` TIMESTAMP NULL DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- revoked_by_user_id
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'entity_access_grants' AND COLUMN_NAME = 'revoked_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `entity_access_grants` ADD COLUMN `revoked_by_user_id` INT UNSIGNED DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- updated_at
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'entity_access_grants' AND COLUMN_NAME = 'updated_at') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `entity_access_grants` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add INDEX idx_granted_user if not exists
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'entity_access_grants' AND INDEX_NAME = 'idx_granted_user') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `entity_access_grants` ADD INDEX `idx_granted_user` (`granted_to_user_id`)'
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;
