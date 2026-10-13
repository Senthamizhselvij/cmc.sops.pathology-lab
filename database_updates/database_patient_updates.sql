-- SAFE UPDATE for an EXISTING pathology_lab database.
-- This does not delete patient records.
USE pathology_lab;

ALTER TABLE patients MODIFY receipt_id VARCHAR(100) NOT NULL;

ALTER TABLE patients ADD COLUMN patient_code VARCHAR(12) NULL AFTER patient_name;

UPDATE patients
SET patient_code = CONCAT('P', id)
WHERE patient_code IS NULL OR TRIM(patient_code) = '';

ALTER TABLE patients MODIFY patient_code VARCHAR(12) NOT NULL;

-- Patient IDs may be duplicated. includes/ensure_schema.php removes legacy UNIQUE indexes automatically.

-- If phpMyAdmin says a column/index already exists, it is already installed;
-- do not run that individual ALTER statement again.
