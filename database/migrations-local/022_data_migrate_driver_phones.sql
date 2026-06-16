-- Migration 022: Data migration - move driver phones to driver_phones table (idempotent)
SET @dbname = DATABASE();

-- Check if driver_phones table exists
SET @tableExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'driver_phones');

-- Check if driver_phones is empty
SET @phoneCount = (SELECT IF(@tableExists > 0, (SELECT COUNT(*) FROM `driver_phones`), 0));

-- Only insert if table exists, is empty, and drivers table exists with phone data
SET @preparedStatement = (SELECT IF(
    @tableExists > 0 AND @phoneCount = 0
        AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'drivers') > 0,
    'INSERT INTO `driver_phones` (driver_id, phone, is_main, created_at, updated_at)
     SELECT id, phone, 1, NOW(), NOW() FROM `drivers` WHERE phone IS NOT NULL AND phone != \'\'',
    'SELECT 1 AS skip_data_migration'
));
PREPARE dataMigrate FROM @preparedStatement;
EXECUTE dataMigrate;
DEALLOCATE PREPARE dataMigrate;
