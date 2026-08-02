-- Migration 052: Route payment condition model — new condition types, route dates, payment line fields
-- Idempotent via conditional column checks.
-- Replaces legacy payment_due_type with approved condition_type model.

SET @dbname = DATABASE();

-- ==============================
-- 1. Add closing_documents_received_date to linear_routes
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_routes' AND COLUMN_NAME = 'closing_documents_received_date') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_routes` ADD COLUMN `closing_documents_received_date` DATE DEFAULT NULL AFTER `actual_unloading_date`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 2. Add condition_type to linear_route_payments (new payment condition model)
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'condition_type') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `condition_type` VARCHAR(50) DEFAULT NULL AFTER `payment_due_days_kind`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 3. Add side column (income/expense)
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'side') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `side` VARCHAR(10) DEFAULT NULL COMMENT ''income/expense'' AFTER `condition_type`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 4. Add days_count (replaces payment_due_days in new model)
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'days_count') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `days_count` INT DEFAULT NULL AFTER `side`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 5. Add days_kind (calendar/working)
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'days_kind') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `days_kind` VARCHAR(10) DEFAULT ''calendar'' AFTER `days_count`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 6. Add specific_due_date
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'specific_due_date') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `specific_due_date` DATE DEFAULT NULL AFTER `days_kind`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 7. Add condition_comment
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'condition_comment') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `condition_comment` TEXT DEFAULT NULL AFTER `specific_due_date`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 8. Add forecast_due_date (computed from planned dates)
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'forecast_due_date') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `forecast_due_date` DATE DEFAULT NULL AFTER `condition_comment`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 9. Add first_paid_at
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'first_paid_at') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `first_paid_at` DATETIME DEFAULT NULL AFTER `forecast_due_date`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 10. Add fully_paid_at
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'fully_paid_at') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `fully_paid_at` DATETIME DEFAULT NULL AFTER `first_paid_at`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 11. Add cancelled_at
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'cancelled_at') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL AFTER `fully_paid_at`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 12. Add cancellation_reason
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND COLUMN_NAME = 'cancellation_reason') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD COLUMN `cancellation_reason` TEXT DEFAULT NULL AFTER `cancelled_at`'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 13. Backfill: Map legacy payment_due_type to new condition_type
--     Safe rows: deterministic, data-complete mappings only
--     Ambiguous rows: marked NULL (condition_type stays NULL) for manual review
--     'Предоплата на загрузке' -> prepayment requires specific_due_date which legacy lacks
--     'До выгрузки' -> end_day is not an exact semantic match ("before unloading" != "on end day")
-- ==============================
UPDATE `linear_route_payments`
   SET `condition_type` = CASE
       WHEN `payment_due_type` = 'После загрузки' THEN 'after_start'
       WHEN `payment_due_type` = 'После выгрузки' THEN 'after_end'
       WHEN `payment_due_type` = '' OR `payment_due_type` IS NULL THEN NULL
       ELSE NULL
   END,
   `days_count` = CASE
       WHEN `payment_due_type` = 'После загрузки' OR `payment_due_type` = 'После выгрузки' THEN `payment_due_days`
       ELSE NULL
   END,
   `days_kind` = CASE
       WHEN `payment_due_type` = 'После загрузки' OR `payment_due_type` = 'После выгрузки' THEN COALESCE(`payment_due_days_kind`, 'calendar')
       ELSE NULL
   END
 WHERE `condition_type` IS NULL
   AND `deleted_at` IS NULL;

-- ==============================
-- 14. Index for condition_type queries
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND INDEX_NAME = 'idx_lrp_condition_type') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD INDEX `idx_lrp_condition_type` (`condition_type`)'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND INDEX_NAME = 'idx_lrp_forecast_due_date') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD INDEX `idx_lrp_forecast_due_date` (`forecast_due_date`)'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_route_payments' AND INDEX_NAME = 'idx_lrp_side') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_route_payments` ADD INDEX `idx_lrp_side` (`side`)'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================
-- 15. Index for closing_documents_received_date on linear_routes
-- ==============================
SET @preparedStatement = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'linear_routes' AND INDEX_NAME = 'idx_lr_closing_docs_date') > 0,
        'SELECT 1 AS already_exists',
        'ALTER TABLE `linear_routes` ADD INDEX `idx_lr_closing_docs_date` (`closing_documents_received_date`)'
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
