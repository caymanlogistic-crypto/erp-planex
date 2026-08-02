-- Migration 051: Ensure finance_invoice_links.invoice_id FK uses ON DELETE RESTRICT
-- Forward migration for existing production DBs where migration 046
-- was applied with an older FK rule (e.g. ON DELETE CASCADE).
-- Idempotent via INFORMATION_SCHEMA checks using KEY_COLUMN_USAGE for column precision.

-- Step 1: Drop existing FK on finance_invoice_links.invoice_id -> finance_invoices.id if NOT RESTRICT
SET @fk_name = (
    SELECT rc.CONSTRAINT_NAME
    FROM information_schema.REFERENTIAL_CONSTRAINTS rc
    JOIN information_schema.KEY_COLUMN_USAGE kcu
        ON  kcu.CONSTRAINT_NAME   = rc.CONSTRAINT_NAME
        AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
        AND kcu.TABLE_NAME        = rc.TABLE_NAME
    WHERE rc.CONSTRAINT_SCHEMA    = DATABASE()
      AND rc.TABLE_NAME           = 'finance_invoice_links'
      AND rc.REFERENCED_TABLE_NAME = 'finance_invoices'
      AND rc.DELETE_RULE          <> 'RESTRICT'
      AND kcu.COLUMN_NAME         = 'invoice_id'
      AND kcu.REFERENCED_TABLE_NAME = 'finance_invoices'
      AND kcu.REFERENCED_COLUMN_NAME = 'id'
    LIMIT 1
);

SET @sql = IF(@fk_name IS NOT NULL,
    CONCAT('ALTER TABLE `finance_invoice_links` DROP FOREIGN KEY `', @fk_name, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add FK with ON DELETE RESTRICT if none with RESTRICT exists on that exact column
SET @has_restrict = (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS rc
    JOIN information_schema.KEY_COLUMN_USAGE kcu
        ON  kcu.CONSTRAINT_NAME   = rc.CONSTRAINT_NAME
        AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
        AND kcu.TABLE_NAME        = rc.TABLE_NAME
    WHERE rc.CONSTRAINT_SCHEMA    = DATABASE()
      AND rc.TABLE_NAME           = 'finance_invoice_links'
      AND rc.REFERENCED_TABLE_NAME = 'finance_invoices'
      AND rc.DELETE_RULE          = 'RESTRICT'
      AND kcu.COLUMN_NAME         = 'invoice_id'
      AND kcu.REFERENCED_TABLE_NAME = 'finance_invoices'
      AND kcu.REFERENCED_COLUMN_NAME = 'id'
);

SET @sql = IF(@has_restrict = 0,
    'ALTER TABLE `finance_invoice_links` ADD CONSTRAINT `fk_fil_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `finance_invoices` (`id`) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
