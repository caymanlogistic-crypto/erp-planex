-- Migration 029: Add director fields to contractors (idempotent)
SET @dbname = DATABASE();

-- director_full_name
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'director_full_name') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `director_full_name` VARCHAR(255) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- director_position
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'director_position') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `contractors` ADD COLUMN `director_position` VARCHAR(255) DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
