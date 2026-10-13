USE `pathology_lab`;

-- Latest requested reference ranges / unit removals. Safe to run repeatedly.
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.2 - 1.2' WHERE t.test_name='S. Bilirubin (Total / Direct / Indirect)' AND tp.description IN ('Total Bilirubin','Serum Bilirubin (Total)');
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.1 - 0.3' WHERE t.test_name IN ('S. Bilirubin (Total / Direct / Indirect)','Liver Function Test') AND tp.description IN ('Direct Bilirubin','Serum Bilirubin (Direct)');
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.2 - 1' WHERE t.test_name IN ('S. Bilirubin (Total / Direct / Indirect)','Liver Function Test') AND tp.description IN ('Indirect Bilirubin','Serum Bilirubin (Indirect)');
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='Men 40 - 129; Women 35 - 104' WHERE t.test_name='Liver Function Test' AND tp.description='Alkaline Phosphatase (ALP)';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 37' WHERE t.test_name='Liver Function Test' AND tp.description='SGOT (AST)';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 40' WHERE t.test_name='Liver Function Test' AND tp.description='SGPT (ALT)';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='6.4 - 8.3' WHERE t.test_name='Liver Function Test' AND tp.description='Serum Total Protein';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='3.5 - 5.2' WHERE t.test_name='Liver Function Test' AND tp.description='Serum Albumin';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='1.8 - 3.6' WHERE t.test_name='Liver Function Test' AND tp.description='Globulin';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 100' WHERE t.test_name='Lipid Profile' AND tp.description='LDL';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='40 - 60' WHERE t.test_name='Lipid Profile' AND tp.description='HDL';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='5 - 40' WHERE t.test_name='Lipid Profile' AND tp.description='VLDL';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 150' WHERE t.test_name='Lipid Profile' AND tp.description='S. Triglyceride';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='40 - 60' WHERE t.test_name='Cholesterol / HDL' AND tp.description='HDL';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 100' WHERE t.test_name='L.D.L' AND tp.description='LDL';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='' WHERE t.test_name IN ('Widal Test','Typhidot');
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='' WHERE t.test_name='Viral Marker';

-- Final requested test/report layout updates
UPDATE tests SET price=100.00 WHERE test_name='Malaria Antigen';

UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id
SET tp.unit=''
WHERE t.test_name='Malaria Antigen';

DELETE tr FROM test_results tr JOIN test_parameters tp ON tp.id=tr.parameter_id JOIN tests t ON t.id=tp.test_id WHERE t.test_name='HIV' AND tp.description='HIV';
DELETE tp FROM test_parameters tp JOIN tests t ON t.id=tp.test_id WHERE t.test_name='HIV' AND tp.description='HIV';

INSERT INTO test_parameters (test_id,description,unit,reference_range)
SELECT t.id,'HIV 1','','Negative / Positive'
FROM tests t
WHERE t.test_name='HIV'
AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='HIV 1');

INSERT INTO test_parameters (test_id,description,unit,reference_range)
SELECT t.id,'HIV 2','','Negative / Positive'
FROM tests t
WHERE t.test_name='HIV'
AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='HIV 2');

UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id
SET tp.unit='', tp.reference_range=''
WHERE t.test_name='Urine (Routine / Microscopic)';

UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id
SET tp.unit=''
WHERE t.test_name='CBC';

UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id
SET tp.reference_range='Male 13 - 17; Female 11.6 - 14.5'
WHERE t.test_name='CBC' AND tp.description='Hemoglobin';

-- Remove legacy CBC rows that should no longer appear. Keep the newly requested CBC rows.
DELETE tr FROM test_results tr
JOIN test_parameters tp ON tp.id=tr.parameter_id
JOIN tests t ON t.id=tp.test_id
WHERE t.test_name='CBC'
AND tp.description IN ('RBC','WBC','White Blood Cell Count','Platelets','Hematocrit');

DELETE tp FROM test_parameters tp
JOIN tests t ON t.id=tp.test_id
WHERE t.test_name='CBC'
AND tp.description IN ('RBC','WBC','White Blood Cell Count','Platelets','Hematocrit');

INSERT INTO test_parameters (test_id,description,unit,reference_range)
SELECT t.id,'Total Leucocyte Count','cumm','4000-11000' FROM tests t
WHERE t.test_name='CBC'
AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='Total Leucocyte Count');

INSERT INTO test_parameters (test_id,description,unit,reference_range)
SELECT t.id,'Total RBC Count','million/cu.mm','3.5-5.5' FROM tests t
WHERE t.test_name='CBC'
AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='Total RBC Count');

INSERT INTO test_parameters (test_id,description,unit,reference_range)
SELECT t.id,'PCV','%','34 - 50' FROM tests t
WHERE t.test_name='CBC'
AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='PCV');

INSERT INTO test_parameters (test_id,description,unit,reference_range)
SELECT t.id,'Platelet Count','lakhs/cumm','1.5-4.5' FROM tests t
WHERE t.test_name='CBC'
AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='Platelet Count');

UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id
SET tp.unit='cumm', tp.reference_range='4000-11000'
WHERE t.test_name='CBC' AND tp.description='Total Leucocyte Count';

UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id
SET tp.unit='million/cu.mm', tp.reference_range='3.5-5.5'
WHERE t.test_name='CBC' AND tp.description='Total RBC Count';

UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id
SET tp.unit='lakhs/cumm', tp.reference_range='1.5-4.5'
WHERE t.test_name='CBC' AND tp.description='Platelet Count';

UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='g/dl', tp.reference_range='Male 13 - 17; Female 11.6 - 14.5' WHERE t.test_name='CBC' AND tp.description='Hemoglobin';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='%', tp.reference_range='34 - 50' WHERE t.test_name='CBC' AND tp.description='PCV';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='fl', tp.reference_range='75 - 95' WHERE t.test_name='CBC' AND tp.description='MCV';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='pg', tp.reference_range='25 - 32' WHERE t.test_name='CBC' AND tp.description='MCH';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='%', tp.reference_range='32 - 36' WHERE t.test_name='CBC' AND tp.description='MCHC';

-- Exact CBC units to match the laboratory report format
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='g/dl' WHERE t.test_name='CBC' AND tp.description='Hemoglobin';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='cumm' WHERE t.test_name='CBC' AND tp.description='Total Leucocyte Count';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='%' WHERE t.test_name='CBC' AND tp.description IN ('Neutrophils (Poly)','Lymphocytes','Eosinophils','Monocytes','Basophils','PCV','MCHC');
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='million/cu.mm' WHERE t.test_name='CBC' AND tp.description='Total RBC Count';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='fl' WHERE t.test_name='CBC' AND tp.description='MCV';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='pg' WHERE t.test_name='CBC' AND tp.description='MCH';
UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='lakhs/cumm' WHERE t.test_name='CBC' AND tp.description='Platelet Count';
