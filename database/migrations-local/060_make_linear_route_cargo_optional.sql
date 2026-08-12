SET @dbname = DATABASE();

SET @cargo_nullable = (
    SELECT IS_NULLABLE
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname
       AND TABLE_NAME = 'linear_routes'
       AND COLUMN_NAME = 'cargo_type_id'
     LIMIT 1
);

SET @preparedStatement = IF(
    @cargo_nullable = 'NO',
    'ALTER TABLE `linear_routes` MODIFY `cargo_type_id` INT UNSIGNED NULL',
    'SELECT 1 AS cargo_type_id_already_nullable'
);

PREPARE makeCargoOptional FROM @preparedStatement;
EXECUTE makeCargoOptional;
DEALLOCATE PREPARE makeCargoOptional;
