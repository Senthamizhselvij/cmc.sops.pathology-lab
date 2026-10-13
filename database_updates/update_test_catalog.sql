ALTER TABLE patient_tests ADD COLUMN IF NOT EXISTS sputum_grade VARCHAR(100) NULL;
-- PATHOLOGY LAB - TEST CATALOG UPDATE
-- Use this after backing up an existing database.
-- It adds the supplied test list/prices and keeps existing patient records.

USE `pathology_lab`;

-- Safely align the original starter test names/prices where possible.
UPDATE tests SET price = 150.00 WHERE test_name = 'CBC';
UPDATE tests SET price = 100.00 WHERE test_name = 'Uric Acid';
UPDATE tests SET price = 567.00 WHERE test_name = 'Malaria';
UPDATE tests SET price = 50.00 WHERE test_name IN ('Blood Sugar Fasting', 'Blood Sugar PP');
UPDATE tests SET price = 350.00 WHERE test_name = 'Lipid Profile';
UPDATE tests SET price = 150.00 WHERE test_name = 'Total Protein';
UPDATE tests SET price = 50.00 WHERE test_name = 'BT / CT';

-- The new catalog is inserted below. Existing tests with the same name are updated.

-- --------------------------------------------------------
-- 7. Insert / update laboratory test catalog
-- Prices are based on the supplied handwritten test list.
-- Where the note says "each", the combined test price is the sum of both parts.
-- --------------------------------------------------------
INSERT INTO `tests` (`test_name`, `price`) VALUES
('CBC', 150.00),
('BT / CT', 50.00),
('ESR (Westergren)', 100.00),
('HB', 50.00),
('Platelet Count', 50.00),
('P/S for MP', 50.00),
('Urine Pregnancy Test', 50.00),
('Urine (Routine / Microscopic)', 50.00),
('Widal Test', 100.00),
('RA Factor', 100.00),
('VDRL', 150.00),
('Blood Group & Rh Factor', 100.00),
('Sputum for AFB', 50.00),
('CRP', 100.00),
('Thyroid Profile', 400.00),
('TSH', 250.00),
('HbA1C', 300.00),
('MX Test', 100.00),
('Typhidot', 150.00),
('Vitamin B12', 500.00),
('Vitamin D3', 500.00),
('Malaria Antigen', 100.00),
('Kidney Function Test', 250.00),
('Blood Urea', 100.00),
('Creatinine', 100.00),
('S. Calcium', 100.00),
('Uric Acid', 100.00),
('Liver Function Test', 250.00),
('S. Bilirubin (Total / Direct / Indirect)', 100.00),
('S.G.O.T', 100.00),
('S.G.P.T', 100.00),
('Total Protein / Albumin', 100.00),
('Lipid Profile', 350.00),
('Cholesterol / HDL', 100.00),
('Triglyceride', 100.00),
('L.D.L', 100.00),
('Total Protein', 150.00),
('Viral Marker', 500.00),
('HIV / HCV / HBsAg / VDRL', 600.00),
('HIV', 150.00),
('HCV', 150.00),
('HBsAg', 150.00),
('Blood Sugar Fasting', 50.00),
('Blood Sugar PP', 50.00)
ON DUPLICATE KEY UPDATE `price` = VALUES(`price`);

-- --------------------------------------------------------
-- 8. Parameter definitions. Values/ranges are based on the supplied
-- reference PDF where readable. Blank result fields are intentionally
-- allowed in the application; only entered results appear on reports.
-- --------------------------------------------------------

-- CBC
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Hemoglobin', 'g/dl', 'Male 13 - 17; Female 11.6 - 14.5' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'Neutrophils (Poly)', '%', '40 - 75' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'Lymphocytes', '%', '20 - 45' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'Eosinophils', '%', '1 - 6' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'Monocytes', '%', '2 - 8' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'Basophils', '%', '0 - 1' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'Total Leucocyte Count', 'cumm', '4000-11000' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'Total RBC Count', 'million/cu.mm', '3.5-5.5' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'Platelet Count', 'lakhs/cumm', '1.5-4.5' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'MCV', 'fl', '75 - 95' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'MCH', 'pg', '25 - 32' FROM tests WHERE test_name='CBC'
UNION ALL SELECT id, 'MCHC', '%', '32 - 36' FROM tests WHERE test_name='CBC';

-- BT / CT
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Bleeding Time', 'min', '2 - 7' FROM tests WHERE test_name='BT / CT'
UNION ALL SELECT id, 'Clotting Time', 'min', '4 - 8' FROM tests WHERE test_name='BT / CT';

-- ESR
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'ESR (Westergren)', 'mm/hr', 'Men 0 - 15; Women 0 - 20' FROM tests WHERE test_name='ESR (Westergren)';

-- Simple hematology / serology
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Hemoglobin', 'g/dl', 'Male 13 - 17; Female 11.6 - 14.5' FROM tests WHERE test_name='HB'
UNION ALL SELECT id, 'Platelet Count', 'lakhs/cumm', '1.5 - 4.5' FROM tests WHERE test_name='Platelet Count'
UNION ALL SELECT id, 'Malaria Parasite', '-', 'Negative / Positive' FROM tests WHERE test_name='P/S for MP'
UNION ALL SELECT id, 'Pregnancy Test (Urine)', '-', 'Negative / Positive' FROM tests WHERE test_name='Urine Pregnancy Test'
UNION ALL SELECT id, 'RA Factor', '-', 'Negative / Positive' FROM tests WHERE test_name='RA Factor'
UNION ALL SELECT id, 'VDRL', '-', 'Non-reactive / Reactive' FROM tests WHERE test_name='VDRL'
UNION ALL SELECT id, 'Blood Group', '-', 'ABO Group' FROM tests WHERE test_name='Blood Group & Rh Factor'
UNION ALL SELECT id, 'Rh Factor', '-', 'Positive / Negative' FROM tests WHERE test_name='Blood Group & Rh Factor'
UNION ALL SELECT id, 'SPUTUM EXAMINATION, AFB', '', '' FROM tests WHERE test_name='Sputum for AFB'
UNION ALL SELECT id, 'C-Reactive Protein (CRP)', '-', 'Negative / Positive' FROM tests WHERE test_name='CRP';

-- Urine routine & microscopy
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Quantity', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Color', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Appearance', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Reaction (pH)', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Proteins', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Glucose', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Pus Cells', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Epithelial Cells', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'RBC', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Casts', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Crystals', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Bile Salts', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)'
UNION ALL SELECT id, 'Bile Pigments', '', '' FROM tests WHERE test_name='Urine (Routine / Microscopic)';

-- Widal
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Typhi O', '', '< 1:80' FROM tests WHERE test_name='Widal Test'
UNION ALL SELECT id, 'Typhi H', '', '< 1:80' FROM tests WHERE test_name='Widal Test'
UNION ALL SELECT id, 'Typhi AH', '', '< 1:80' FROM tests WHERE test_name='Widal Test'
UNION ALL SELECT id, 'Typhi BH', '', '< 1:80' FROM tests WHERE test_name='Widal Test';

-- Thyroid / glucose / HbA1c
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'T3', 'ng/mL', '0.52 - 1.85' FROM tests WHERE test_name='Thyroid Profile'
UNION ALL SELECT id, 'T4', 'µg/dL', '4.8 - 11.6' FROM tests WHERE test_name='Thyroid Profile'
UNION ALL SELECT id, 'TSH', 'µIU/mL', '0.39 - 5.5' FROM tests WHERE test_name='Thyroid Profile'
UNION ALL SELECT id, 'TSH', 'µIU/mL', '0.39 - 5.5' FROM tests WHERE test_name='TSH'
UNION ALL SELECT id, 'HbA1C', '%', 'Normal < 5.7%; Prediabetes 5.7 - 6.4%; Diabetes 6.5% or higher' FROM tests WHERE test_name='HbA1C';

-- MX / Tuberculin and Typhidot
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Tuberculin Test (0.1 ml = 10 TU)', '', '' FROM tests WHERE test_name='MX Test'
UNION ALL SELECT id, 'IgM', '', 'Negative / Positive' FROM tests WHERE test_name='Typhidot'
UNION ALL SELECT id, 'IgG', '', 'Negative / Positive' FROM tests WHERE test_name='Typhidot';

-- Vitamins
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Vitamin B12', 'pg/mL', '200 - 950' FROM tests WHERE test_name='Vitamin B12'
UNION ALL SELECT id, 'Vitamin D3', 'ng/mL', 'Deficiency < 20; Insufficiency 20 - 30; Sufficiency 30 - 100; Toxicity > 100' FROM tests WHERE test_name='Vitamin D3';

-- Malaria antigen
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Plasmodium vivax Antigen', '', 'Negative / Positive' FROM tests WHERE test_name='Malaria Antigen'
UNION ALL SELECT id, 'Plasmodium falciparum Antigen', '', 'Negative / Positive' FROM tests WHERE test_name='Malaria Antigen';

-- Kidney / individual biochemistry
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Urea', 'mg/dL', '6 - 40' FROM tests WHERE test_name='Kidney Function Test'
UNION ALL SELECT id, 'Creatinine', 'mg/dL', '0.7 - 1.3' FROM tests WHERE test_name='Kidney Function Test'
UNION ALL SELECT id, 'Uric Acid', 'mg/dL', '3.5 - 7.2' FROM tests WHERE test_name='Kidney Function Test'
UNION ALL SELECT id, 'S. Calcium', 'mg/dL', '8.5 - 10.2' FROM tests WHERE test_name='Kidney Function Test'
UNION ALL SELECT id, 'Blood Urea', 'mg/dL', '6 - 40' FROM tests WHERE test_name='Blood Urea'
UNION ALL SELECT id, 'Creatinine', 'mg/dL', '0.7 - 1.3' FROM tests WHERE test_name='Creatinine'
UNION ALL SELECT id, 'S. Calcium', 'mg/dL', '8.5 - 10.2' FROM tests WHERE test_name='S. Calcium'
UNION ALL SELECT id, 'Uric Acid', 'mg/dL', '3.5 - 7.2' FROM tests WHERE test_name='Uric Acid';

-- Liver function
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Serum Bilirubin (Total)', 'mg/dL', '0.2 - 1.2' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'Serum Bilirubin (Direct)', 'mg/dL', '0.1 - 0.3' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'Serum Bilirubin (Indirect)', 'mg/dL', '0.2 - 1' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'Alkaline Phosphatase (ALP)', 'IU/L', 'Men 40 - 129; Women 35 - 104' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'SGOT (AST)', 'IU/L', '< 37' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'SGPT (ALT)', 'IU/L', '< 40' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'Serum Total Protein', 'g/dL', '6.4 - 8.3' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'Serum Albumin', 'g/dL', '3.5 - 5.2' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'Globulin', 'g/dL', '1.8 - 3.6' FROM tests WHERE test_name='Liver Function Test'
UNION ALL SELECT id, 'Total Bilirubin', 'mg/dL', '0.2 - 1.2' FROM tests WHERE test_name='S. Bilirubin (Total / Direct / Indirect)'
UNION ALL SELECT id, 'Direct Bilirubin', 'mg/dL', '0.1 - 0.3' FROM tests WHERE test_name='S. Bilirubin (Total / Direct / Indirect)'
UNION ALL SELECT id, 'Indirect Bilirubin', 'mg/dL', '0.2 - 1' FROM tests WHERE test_name='S. Bilirubin (Total / Direct / Indirect)'
UNION ALL SELECT id, 'S.G.O.T', 'IU/L', '< 37' FROM tests WHERE test_name='S.G.O.T'
UNION ALL SELECT id, 'S.G.P.T', 'IU/L', '< 40' FROM tests WHERE test_name='S.G.P.T';

-- Protein / albumin
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Total Protein', 'g/dL', '6.4 - 8.3' FROM tests WHERE test_name='Total Protein / Albumin'
UNION ALL SELECT id, 'Albumin', 'g/dL', '3.5 - 5.2' FROM tests WHERE test_name='Total Protein / Albumin'
UNION ALL SELECT id, 'Total Protein', 'g/dL', '6.4 - 8.3' FROM tests WHERE test_name='Total Protein';

-- Lipid profile
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'Total Cholesterol', 'mg/dL', '< 200' FROM tests WHERE test_name='Lipid Profile'
UNION ALL SELECT id, 'S. Triglyceride', 'mg/dL', '< 150' FROM tests WHERE test_name='Lipid Profile'
UNION ALL SELECT id, 'HDL', 'mg/dL', '40 - 60' FROM tests WHERE test_name='Lipid Profile'
UNION ALL SELECT id, 'LDL', 'mg/dL', '< 100' FROM tests WHERE test_name='Lipid Profile'
UNION ALL SELECT id, 'VLDL', 'mg/dL', '5 - 40' FROM tests WHERE test_name='Lipid Profile'
UNION ALL SELECT id, 'Cholesterol', 'mg/dL', '< 200' FROM tests WHERE test_name='Cholesterol / HDL'
UNION ALL SELECT id, 'HDL', 'mg/dL', '40 - 60' FROM tests WHERE test_name='Cholesterol / HDL'
UNION ALL SELECT id, 'Triglyceride', 'mg/dL', '< 150' FROM tests WHERE test_name='Triglyceride'
UNION ALL SELECT id, 'LDL', 'mg/dL', '< 100' FROM tests WHERE test_name='L.D.L';

-- Viral markers / individual viral tests
INSERT INTO `test_parameters` (`test_id`, `description`, `unit`, `reference_range`)
SELECT id, 'HIV-1', '', '' FROM tests WHERE test_name='Viral Marker'
UNION ALL SELECT id, 'HIV-2', '', '' FROM tests WHERE test_name='Viral Marker'
UNION ALL SELECT id, 'VDRL', '-', 'Non-reactive' FROM tests WHERE test_name='Viral Marker'
UNION ALL SELECT id, 'HCV', '-', 'Non-reactive' FROM tests WHERE test_name='Viral Marker'
UNION ALL SELECT id, 'HBsAg', '-', 'Non-reactive' FROM tests WHERE test_name='Viral Marker'
UNION ALL SELECT id, 'HIV', '-', 'Negative' FROM tests WHERE test_name='HIV / HCV / HBsAg / VDRL'
UNION ALL SELECT id, 'HCV', '-', 'Non-reactive' FROM tests WHERE test_name='HIV / HCV / HBsAg / VDRL'
UNION ALL SELECT id, 'HBsAg', '-', 'Non-reactive' FROM tests WHERE test_name='HIV / HCV / HBsAg / VDRL'
UNION ALL SELECT id, 'VDRL', '-', 'Non-reactive' FROM tests WHERE test_name='HIV / HCV / HBsAg / VDRL';
