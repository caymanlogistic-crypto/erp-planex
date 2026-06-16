-- Migration: add director requisites columns to companies table
-- DECISION: director is stored in companies as document requisites, NOT as ERP user

-- Use stored procedure for idempotent column addition
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

CREATE PROCEDURE add_column_if_not_exists(
    IN tableName VARCHAR(128),
    IN columnName VARCHAR(128),
    IN columnDefinition VARCHAR(1024)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = tableName
          AND COLUMN_NAME = columnName
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END;

CALL add_column_if_not_exists('companies', 'director_position', 'VARCHAR(255) DEFAULT NULL AFTER `contact_email`');
CALL add_column_if_not_exists('companies', 'director_full_name', 'VARCHAR(255) DEFAULT NULL AFTER `director_position`');

DROP PROCEDURE IF EXISTS add_column_if_not_exists;
