CREATE TABLE IF NOT EXISTS `bank_statement_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bank_code` VARCHAR(20) NOT NULL DEFAULT 'vtb',
    `imap_host` VARCHAR(255) NOT NULL,
    `imap_port` INT UNSIGNED NOT NULL DEFAULT 993,
    `imap_ssl` TINYINT(1) NOT NULL DEFAULT 1,
    `username` VARCHAR(255) NOT NULL,
    `password_encrypted` VARCHAR(512) NOT NULL DEFAULT '',
    `sender_filter` VARCHAR(255) NOT NULL DEFAULT 'vtb-inform@vtb.ru',
    `subject_filter` VARCHAR(255) NOT NULL DEFAULT 'Регулярная выписка',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bank_code` (`bank_code`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
