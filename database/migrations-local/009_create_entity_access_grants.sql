CREATE TABLE IF NOT EXISTS entity_access_grants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL COMMENT 'contractor/driver/crew/client/vehicle/document',
    entity_id INT UNSIGNED NOT NULL,
    granted_to_user_id INT UNSIGNED NOT NULL,
    granted_by_user_id INT UNSIGNED NOT NULL,
    access_level VARCHAR(20) NOT NULL DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_grant (entity_type, entity_id, granted_to_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
