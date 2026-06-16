-- Migration 020: Add extended fields to documents (idempotent)
SET @dbname = DATABASE();

-- document_name
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'document_name') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `document_name` VARCHAR(500) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- document_number
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'document_number') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `document_number` VARCHAR(100) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- document_date
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'document_date') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `document_date` DATE DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- document_expire
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'document_expire') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `document_expire` DATE DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- deleted_at
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'deleted_at') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- deleted_by_user_id
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'deleted_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `deleted_by_user_id` INT UNSIGNED DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- delete_comment
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'delete_comment') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `delete_comment` TEXT DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- updated_by_user_id
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'updated_by_user_id') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `updated_by_user_id` INT UNSIGNED DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- updated_by_role
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'updated_by_role') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD COLUMN `updated_by_role` VARCHAR(20) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add INDEX idx_deleted if not exists
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_deleted') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `documents` ADD INDEX `idx_deleted` (`deleted_at`)'
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;
