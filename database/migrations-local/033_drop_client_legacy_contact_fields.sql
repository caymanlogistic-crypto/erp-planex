-- Migration 033: Drop legacy contact fields from clients (idempotent)
SET @schema_name = DATABASE();

SET @drop_contact_person = (
    SELECT IF(
        EXISTS(
            SELECT 1
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = @schema_name
               AND TABLE_NAME = 'clients'
               AND COLUMN_NAME = 'contact_person'
        ),
        'ALTER TABLE clients DROP COLUMN contact_person',
        'SELECT 1'
    )
);
PREPARE stmt_drop_contact_person FROM @drop_contact_person;
EXECUTE stmt_drop_contact_person;
DEALLOCATE PREPARE stmt_drop_contact_person;

SET @drop_contact_phone = (
    SELECT IF(
        EXISTS(
            SELECT 1
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = @schema_name
               AND TABLE_NAME = 'clients'
               AND COLUMN_NAME = 'contact_phone'
        ),
        'ALTER TABLE clients DROP COLUMN contact_phone',
        'SELECT 1'
    )
);
PREPARE stmt_drop_contact_phone FROM @drop_contact_phone;
EXECUTE stmt_drop_contact_phone;
DEALLOCATE PREPARE stmt_drop_contact_phone;

SET @drop_contact_email = (
    SELECT IF(
        EXISTS(
            SELECT 1
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = @schema_name
               AND TABLE_NAME = 'clients'
               AND COLUMN_NAME = 'contact_email'
        ),
        'ALTER TABLE clients DROP COLUMN contact_email',
        'SELECT 1'
    )
);
PREPARE stmt_drop_contact_email FROM @drop_contact_email;
EXECUTE stmt_drop_contact_email;
DEALLOCATE PREPARE stmt_drop_contact_email;
