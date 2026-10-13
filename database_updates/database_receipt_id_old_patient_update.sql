-- Receipt ID duplicate / Old Patient update
-- Run this once in the existing pathology_lab database if needed.
-- It removes UNIQUE indexes on patients.receipt_id so the same Receipt ID
-- can be registered again. The report pages classify later registrations as OLD.

SET @db := DATABASE();

SELECT CONCAT('ALTER TABLE `patients` DROP INDEX `', INDEX_NAME, '`;')
INTO @drop_sql
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = @db
  AND TABLE_NAME = 'patients'
  AND COLUMN_NAME = 'receipt_id'
  AND NON_UNIQUE = 0
  AND INDEX_NAME <> 'PRIMARY'
LIMIT 1;

SET @drop_sql = COALESCE(@drop_sql, 'SELECT 1;');
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `patients` MODIFY `receipt_id` VARCHAR(100) NOT NULL;
