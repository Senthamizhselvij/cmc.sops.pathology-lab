USE pathology_lab;

-- Requested price changes.
UPDATE tests SET price = 50.00 WHERE test_name = 'BT / CT';
UPDATE tests SET price = 350.00 WHERE test_name = 'Lipid Profile';
UPDATE tests SET price = 150.00 WHERE test_name = 'Total Protein';
UPDATE tests SET price = 50.00 WHERE test_name IN ('Blood Sugar Fasting', 'Blood Sugar PP');
UPDATE tests SET price = 250.00 WHERE test_name = 'Viral Marker';

-- BT / CT is a flat ₹50 test: BT only, CT only, or both results = ₹50 total.
UPDATE test_parameters tp
JOIN tests t ON t.id = tp.test_id
SET tp.price = 50.00
WHERE t.test_name = 'BT / CT'
  AND tp.description IN ('Bleeding Time', 'Clotting Time');

-- Separate tests requested by the lab. Existing legacy combined tests are kept in the database
-- so old patient records remain safe, but they are hidden from the new test-selection screen.
INSERT INTO tests (test_name, price) VALUES
('HIV', 150.00),
('HCV', 150.00),
('HBsAg', 150.00),
('VDRL', 150.00),
('Blood Sugar Fasting', 50.00),
('Blood Sugar PP', 50.00)
ON DUPLICATE KEY UPDATE price = VALUES(price);

-- BT/CT: each part is ₹50.
UPDATE test_parameters tp
JOIN tests t ON t.id = tp.test_id
SET tp.price = 50.00
WHERE t.test_name = 'BT / CT'
  AND tp.description IN ('Bleeding Time', 'Clotting Time');

-- Blood Sugar (Fasting / PP): each part is ₹50.
UPDATE test_parameters tp
JOIN tests t ON t.id = tp.test_id
SET tp.price = 50.00
WHERE t.test_name = 'Blood Sugar (Fasting / PP)'
  AND tp.description IN ('Glucose Fasting', 'Glucose P.P');

-- Separate viral tests each have one result and cost ₹150.
INSERT INTO test_parameters (test_id, description, unit, reference_range)
SELECT id, 'HIV', '-', 'Negative' FROM tests WHERE test_name='HIV'
  AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=tests.id);
INSERT INTO test_parameters (test_id, description, unit, reference_range)
SELECT id, 'HCV', '-', 'Non-reactive' FROM tests WHERE test_name='HCV'
  AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=tests.id);
INSERT INTO test_parameters (test_id, description, unit, reference_range)
SELECT id, 'HBsAg', '-', 'Non-reactive' FROM tests WHERE test_name='HBsAg'
  AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=tests.id);
INSERT INTO test_parameters (test_id, description, unit, reference_range)
SELECT id, 'VDRL', '-', 'Non-reactive' FROM tests WHERE test_name='VDRL'
  AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=tests.id);

-- Separate fasting / PP tests each have one result and cost ₹50.
INSERT INTO test_parameters (test_id, description, unit, reference_range)
SELECT id, 'Glucose Fasting', 'mg/dL', '< 100' FROM tests WHERE test_name='Blood Sugar Fasting'
  AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=tests.id);
INSERT INTO test_parameters (test_id, description, unit, reference_range)
SELECT id, 'Glucose P.P', 'mg/dL', '< 140' FROM tests WHERE test_name='Blood Sugar PP'
  AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=tests.id);

-- Parameter-level price for the new single tests is not needed; their normal test price is used.

-- The application hides these from NEW test selection without deleting old patient records:
-- Malaria / Malaria Antigen / Urine Test.
