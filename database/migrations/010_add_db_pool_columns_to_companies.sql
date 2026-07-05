-- MySQL 5.7 safe: add columns for per-company DB credentials if not exist
SET @dbname = (SELECT DATABASE());
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'db_host');
SET @sql = IF(@exists = 0,
    'ALTER TABLE companies ADD COLUMN db_host VARCHAR(255) NULL AFTER db_identifier',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'db_port');
SET @sql = IF(@exists = 0,
    'ALTER TABLE companies ADD COLUMN db_port INT NULL AFTER db_host',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'db_username');
SET @sql = IF(@exists = 0,
    'ALTER TABLE companies ADD COLUMN db_username VARCHAR(255) NULL AFTER db_port',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'db_password');
SET @sql = IF(@exists = 0,
    'ALTER TABLE companies ADD COLUMN db_password VARCHAR(255) NULL AFTER db_username',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
