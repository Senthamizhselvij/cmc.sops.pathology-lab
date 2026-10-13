-- TEST RESULT CHANGE HISTORY UPDATE
-- Run this once if the database was created from an older database.sql.
-- The application also creates this table automatically through includes/ensure_schema.php.

USE pathology_lab;

CREATE TABLE IF NOT EXISTS `test_result_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_test_id` INT NOT NULL,
    `parameter_id` INT NOT NULL,
    `old_result` VARCHAR(255) NULL,
    `new_result` VARCHAR(255) NOT NULL,
    `reason` VARCHAR(500) NULL,
    `changed_by` VARCHAR(100) NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_trh_patient_test_parameter` (`patient_test_id`, `parameter_id`),
    CONSTRAINT `fk_trh_patient_test` FOREIGN KEY (`patient_test_id`) REFERENCES `patient_tests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_trh_parameter` FOREIGN KEY (`parameter_id`) REFERENCES `test_parameters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
