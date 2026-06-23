-- Migration 036: Add grant_source column to entity_access_grants (idempotent)
-- Needed for BLOCK_D2: contractor cascade sharing with safe revoke.
-- grant_source = 'contractor_cascade' marks grants created by cascade sharing.
-- Allows safe cascade revoke without touching manual grants.
SET @dbname = DATABASE();

-- grant_source
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'entity_access_grants' AND COLUMN_NAME = 'grant_source') > 0,
    'SELECT 1 AS already_exists',
    'ALTER TABLE `entity_access_grants` ADD COLUMN `grant_source` VARCHAR(50) DEFAULT NULL COMMENT ''source of grant: contractor_cascade or NULL for manual'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
