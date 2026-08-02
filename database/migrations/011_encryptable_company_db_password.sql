-- Allow authenticated encrypted database credentials (AES-256-GCM payload + version prefix).
ALTER TABLE `companies`
    MODIFY COLUMN `db_password` VARCHAR(1024) NULL;
