-- Run ONCE on an existing pathology_lab database.
USE pathology_lab;

-- Patient-level report date. Registration date remains patient_date.
ALTER TABLE patients
    ADD COLUMN report_date DATE NULL AFTER patient_date;

-- If your database already has the report_date column, do not run the ALTER again.
