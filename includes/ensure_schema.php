<?php
/**
 * Safe one-time schema updater for existing XAMPP installations.
 * This keeps the new Patient ID / report-date / cancellation features
 * compatible with older pathology_lab databases without deleting data.
 */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

function schemaColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function schemaIndexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function schemaAddColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (!schemaColumnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

try {
    // Patient ID is used for display/search; duplicates are permitted.
    schemaAddColumn($pdo, 'patients', 'patient_code', "VARCHAR(12) NULL AFTER patient_name");

    // Patient ID may be blank temporarily for late-entered registrations.
    // Keep existing IDs unchanged and allow SQL NULL for new records.
    $pdo->exec("ALTER TABLE patients MODIFY patient_code VARCHAR(12) NULL");
    // Patient ID is a display/search field, not a unique key. Remove any
    // legacy UNIQUE indexes so duplicate patient IDs can be registered safely.
    $patientCodeIndexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'patient_code' AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($patientCodeIndexes as $patientCodeIndex) {
        $safeIndex = str_replace('`', '``', (string)$patientCodeIndex);
        $pdo->exec("ALTER TABLE patients DROP INDEX `{$safeIndex}`");
    }

    // Report date is separate from registration date.
    schemaAddColumn($pdo, 'patients', 'report_date', "DATE NULL AFTER patient_date");

    // Optional referring doctor/name shown on the final pathology report.
    schemaAddColumn($pdo, 'patients', 'referred_by', "VARCHAR(150) NULL AFTER address");

    // Cancellation tracking and reason.
    schemaAddColumn($pdo, 'patients', 'report_status', "ENUM('ACTIVE','CANCELLED') NOT NULL DEFAULT 'ACTIVE' AFTER submitted");
    schemaAddColumn($pdo, 'patients', 'cancelled_at', "DATETIME NULL AFTER report_status");
    schemaAddColumn($pdo, 'patients', 'cancelled_by', "VARCHAR(100) NULL AFTER cancelled_at");
    schemaAddColumn($pdo, 'patients', 'cancel_reason', "VARCHAR(255) NULL AFTER cancelled_by");

    // Receipt IDs are manually entered alphanumeric values and are NOT unique.
    // A repeated Receipt ID is how the reports identify an OLD patient.
    $pdo->exec("ALTER TABLE patients MODIFY receipt_id VARCHAR(100) NULL");

    // Remove any old UNIQUE index on receipt_id from earlier versions.
    $receiptIndexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'receipt_id' AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($receiptIndexes as $receiptIndex) {
        $safeIndex = str_replace('`', '``', (string)$receiptIndex);
        $pdo->exec("ALTER TABLE patients DROP INDEX `{$safeIndex}`");
    }

    // Per-parameter result change history. Every entry records the result, reason, user and entry date.
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_result_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_test_id INT NOT NULL,
        parameter_id INT NOT NULL,
        old_result VARCHAR(255) NULL,
        new_result VARCHAR(255) NOT NULL,
        reason VARCHAR(500) NULL,
        changed_by VARCHAR(100) NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_trh_patient_test_parameter (patient_test_id, parameter_id),
        CONSTRAINT fk_trh_patient_test FOREIGN KEY (patient_test_id) REFERENCES patient_tests(id) ON DELETE CASCADE,
        CONSTRAINT fk_trh_parameter FOREIGN KEY (parameter_id) REFERENCES test_parameters(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // MX / Tuberculin test stores its two dates separately from the normal result rows.
    schemaAddColumn($pdo, 'patient_tests', 'mx_inoculation_date', "DATE NULL AFTER result_date");
    schemaAddColumn($pdo, 'patient_tests', 'mx_reading_date', "DATE NULL AFTER mx_inoculation_date");
    // Sputum for AFB has a separate Grade field on the result form/report.
    schemaAddColumn($pdo, 'patient_tests', 'sputum_grade', "VARCHAR(100) NULL");


    // Keep the test reference ranges/units aligned with the latest requested catalog.
    // These updates are idempotent and do not change patient results already entered.
    // CBC differential count units must always display as percent, including older databases.
    $catalogUpdates = [
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='%' WHERE t.test_name='CBC' AND tp.description IN ('Neutrophils (Poly)','Lymphocytes','Eosinophils','Monocytes','Basophils')"],

        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.2 - 1.2' WHERE t.test_name='S. Bilirubin (Total / Direct / Indirect)' AND tp.description IN ('Total Bilirubin','Serum Bilirubin (Total)')"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.1 - 0.3' WHERE t.test_name='S. Bilirubin (Total / Direct / Indirect)' AND tp.description IN ('Direct Bilirubin','Serum Bilirubin (Direct)')"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.39 - 5.5' WHERE t.test_name IN ('Thyroid Profile','TSH') AND tp.description='TSH'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 37' WHERE t.test_name='S.G.O.T' AND tp.description='S.G.O.T'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 40' WHERE t.test_name='S.G.P.T' AND tp.description='S.G.P.T'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 150' WHERE t.test_name='Triglyceride' AND tp.description='Triglyceride'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.2 - 1' WHERE t.test_name='S. Bilirubin (Total / Direct / Indirect)' AND tp.description IN ('Indirect Bilirubin','Serum Bilirubin (Indirect)')"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.1 - 0.3' WHERE t.test_name='Liver Function Test' AND tp.description='Serum Bilirubin (Direct)'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='0.2 - 1' WHERE t.test_name='Liver Function Test' AND tp.description='Serum Bilirubin (Indirect)'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='Men 40 - 129; Women 35 - 104' WHERE t.test_name='Liver Function Test' AND tp.description='Alkaline Phosphatase (ALP)'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 37' WHERE t.test_name='Liver Function Test' AND tp.description='SGOT (AST)'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 40' WHERE t.test_name='Liver Function Test' AND tp.description='SGPT (ALT)'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='6.4 - 8.3' WHERE t.test_name='Liver Function Test' AND tp.description='Serum Total Protein'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='3.5 - 5.2' WHERE t.test_name='Liver Function Test' AND tp.description='Serum Albumin'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='1.8 - 3.6' WHERE t.test_name='Liver Function Test' AND tp.description='Globulin'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 100' WHERE t.test_name='Lipid Profile' AND tp.description='LDL'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='40 - 60' WHERE t.test_name='Lipid Profile' AND tp.description='HDL'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='5 - 40' WHERE t.test_name='Lipid Profile' AND tp.description='VLDL'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 150' WHERE t.test_name='Lipid Profile' AND tp.description='S. Triglyceride'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='40 - 60' WHERE t.test_name='Cholesterol / HDL' AND tp.description='HDL'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='< 100' WHERE t.test_name='L.D.L' AND tp.description='LDL'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='' WHERE t.test_name='Widal Test'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='' WHERE t.test_name='Typhidot'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='' WHERE t.test_name='Viral Marker'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='' WHERE t.test_name='Viral Marker' AND tp.description IN ('HIV-1','HIV-2')"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='', tp.reference_range='' WHERE t.test_name='Urine (Routine / Microscopic)'"],
                ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.reference_range='Male 13 - 17; Female 11.6 - 14.5' WHERE t.test_name='CBC' AND tp.description='Hemoglobin'"],
        ["DELETE tr FROM test_results tr JOIN test_parameters tp ON tp.id=tr.parameter_id JOIN tests t ON t.id=tp.test_id WHERE t.test_name='HIV' AND tp.description='HIV'"],
        ["DELETE h FROM test_result_history h JOIN test_parameters tp ON tp.id=h.parameter_id JOIN tests t ON t.id=tp.test_id WHERE t.test_name='HIV' AND tp.description='HIV'"],
        ["DELETE tp FROM test_parameters tp JOIN tests t ON t.id=tp.test_id WHERE t.test_name='HIV' AND tp.description='HIV'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='', tp.reference_range='Negative / Positive' WHERE t.test_name='HIV' AND tp.description IN ('HIV 1','HIV 2')"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='' WHERE t.test_name='Malaria Antigen'"],
        ["INSERT INTO test_parameters (test_id,description,unit,reference_range) SELECT t.id,'Plasmodium vivax Antigen','','Negative / Positive' FROM tests t WHERE t.test_name='Malaria Antigen' AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='Plasmodium vivax Antigen')"],
        ["INSERT INTO test_parameters (test_id,description,unit,reference_range) SELECT t.id,'Plasmodium falciparum Antigen','','Negative / Positive' FROM tests t WHERE t.test_name='Malaria Antigen' AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='Plasmodium falciparum Antigen')"],
        ["UPDATE tests SET price=100.00 WHERE test_name='Malaria Antigen'"],
        ["INSERT INTO tests (test_name,price) SELECT 'Malaria Antigen',100.00 WHERE NOT EXISTS (SELECT 1 FROM tests WHERE test_name='Malaria Antigen')"],
        ["DELETE tr FROM test_results tr JOIN test_parameters tp ON tp.id=tr.parameter_id JOIN tests t ON t.id=tp.test_id WHERE t.test_name='CBC' AND tp.description IN ('RBC','WBC','White Blood Cell Count','Platelets','Hematocrit')"],
        ["DELETE tp FROM test_parameters tp JOIN tests t ON t.id=tp.test_id WHERE t.test_name='CBC' AND tp.description IN ('RBC','WBC','White Blood Cell Count','Platelets','Hematocrit')"],
        ["INSERT INTO test_parameters (test_id,description,unit,reference_range) SELECT t.id,'Total Leucocyte Count','cumm','4000-11000' FROM tests t WHERE t.test_name='CBC' AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='Total Leucocyte Count')"],
        ["INSERT INTO test_parameters (test_id,description,unit,reference_range) SELECT t.id,'Total RBC Count','million/cu.mm','3.5-5.5' FROM tests t WHERE t.test_name='CBC' AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='Total RBC Count')"],
        ["INSERT INTO test_parameters (test_id,description,unit,reference_range) SELECT t.id,'PCV','%','34 - 50' FROM tests t WHERE t.test_name='CBC' AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='PCV')"],
        ["INSERT INTO test_parameters (test_id,description,unit,reference_range) SELECT t.id,'Platelet Count','lakhs/cumm','1.5-4.5' FROM tests t WHERE t.test_name='CBC' AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='Platelet Count')"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='cumm', tp.reference_range='4000-11000' WHERE t.test_name='CBC' AND tp.description='Total Leucocyte Count'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='million/cu.mm', tp.reference_range='3.5-5.5' WHERE t.test_name='CBC' AND tp.description='Total RBC Count'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='lakhs/cumm', tp.reference_range='1.5-4.5' WHERE t.test_name='CBC' AND tp.description='Platelet Count'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='g/dl', tp.reference_range='Male 13 - 17; Female 11.6 - 14.5' WHERE t.test_name='CBC' AND tp.description='Hemoglobin'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='%', tp.reference_range='34 - 50' WHERE t.test_name='CBC' AND tp.description='PCV'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='fl', tp.reference_range='75 - 95' WHERE t.test_name='CBC' AND tp.description='MCV'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='pg', tp.reference_range='25 - 32' WHERE t.test_name='CBC' AND tp.description='MCH'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='%', tp.reference_range='32 - 36' WHERE t.test_name='CBC' AND tp.description='MCHC'"],
        ["INSERT INTO test_parameters (test_id,description,unit,reference_range) SELECT t.id,'HIV 1','','Negative / Positive' FROM tests t WHERE t.test_name='HIV' AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='HIV 1')"],
        ["INSERT INTO test_parameters (test_id,description,unit,reference_range) SELECT t.id,'HIV 2','','Negative / Positive' FROM tests t WHERE t.test_name='HIV' AND NOT EXISTS (SELECT 1 FROM test_parameters x WHERE x.test_id=t.id AND x.description='HIV 2')"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.description='Color' WHERE t.test_name='Urine (Routine / Microscopic)' AND tp.description='Colour'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.description='Glucose' WHERE t.test_name='Urine (Routine / Microscopic)' AND tp.description='Sugar'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.description='Bile Salts' WHERE t.test_name='Urine (Routine / Microscopic)' AND tp.description='Others'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='', tp.reference_range='' WHERE t.test_name='Sputum for AFB'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.unit='', tp.reference_range='' WHERE t.test_name='MX Test'"],
        ["UPDATE tests SET price=100.00 WHERE test_name='Malaria Antigen'"],
        ["UPDATE test_parameters tp JOIN tests t ON t.id=tp.test_id SET tp.description='SPUTUM EXAMINATION, AFB' WHERE t.test_name='Sputum for AFB' AND tp.description='Sputum for AFB'"],
    ];
    foreach ($catalogUpdates as $sql) { $pdo->exec($sql[0]); }

    // Older installations may not yet have the parameter-level price column.
    schemaAddColumn($pdo, 'test_parameters', 'price', "DECIMAL(10,2) DEFAULT NULL AFTER reference_range");
} catch (Throwable $e) {
    // Do not hide the real application page with a migration error. The
    // normal SQL update files remain available if a restricted MySQL user
    // does not have ALTER permission.
    error_log('Pathology Lab schema update: ' . $e->getMessage());
}

// CBC format correction: exact reference units/order from the supplied report format.
