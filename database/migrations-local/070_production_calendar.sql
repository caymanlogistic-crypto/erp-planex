CREATE TABLE IF NOT EXISTS production_calendar_years (
    calendar_year SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
    source_title VARCHAR(255) NULL,
    source_url VARCHAR(500) NULL,
    note VARCHAR(1000) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_role VARCHAR(50) NULL,
    updated_by_user_id INT UNSIGNED NULL,
    updated_by_role VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (calendar_year),
    KEY idx_production_calendar_year_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_calendar_days (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    calendar_date DATE NOT NULL,
    calendar_year SMALLINT UNSIGNED NOT NULL,
    day_type VARCHAR(32) NOT NULL,
    is_working_day TINYINT(1) NOT NULL DEFAULT 0,
    name VARCHAR(255) NULL,
    note VARCHAR(1000) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_role VARCHAR(50) NULL,
    updated_by_user_id INT UNSIGNED NULL,
    updated_by_role VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_production_calendar_date (calendar_date),
    KEY idx_production_calendar_year (calendar_year),
    KEY idx_production_calendar_working (calendar_year, is_working_day),
    CONSTRAINT fk_production_calendar_days_year FOREIGN KEY (calendar_year)
        REFERENCES production_calendar_years(calendar_year)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;