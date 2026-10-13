-- ============================================================
-- PER-TEST RESULT DATE UPDATE
-- Run this ONCE on the existing pathology_lab database.
-- This keeps a separate result/completion date for every test.
-- ============================================================
USE pathology_lab;

ALTER TABLE patient_tests
    ADD COLUMN result_date DATE NULL AFTER status;

-- If phpMyAdmin says "Duplicate column name result_date",
-- the column is already installed; do not run the ALTER again.
