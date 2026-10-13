-- PATHOLOGY LAB - TEST CATALOG UPDATE v2
-- Run this once on an existing pathology_lab database.
-- BACK UP THE pathology_lab database before running this script.

USE `pathology_lab`;

-- 1. Remove the legacy duplicate test named exactly "Thyroid".
-- Keep "Thyroid Profile" as the single thyroid test in the catalog.
SET @legacy_thyroid_id = (SELECT id FROM tests WHERE test_name = 'Thyroid' LIMIT 1);

DELETE FROM test_results
WHERE patient_test_id IN (
    SELECT id FROM patient_tests WHERE test_id = @legacy_thyroid_id
);

DELETE FROM patient_tests
WHERE test_id = @legacy_thyroid_id;

DELETE FROM test_parameters
WHERE test_id = @legacy_thyroid_id;

DELETE FROM tests
WHERE id = @legacy_thyroid_id;

SET @legacy_thyroid_id = NULL;

-- 2. Viral Marker is now ₹500.
UPDATE tests SET price = 500.00 WHERE test_name = 'Viral Marker';

-- 3. Keep the catalog's thyroid profile price at ₹400.
UPDATE tests SET price = 400.00 WHERE test_name = 'Thyroid Profile';
