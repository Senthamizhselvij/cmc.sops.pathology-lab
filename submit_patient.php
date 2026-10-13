<?php
/**
 * Submit / Finalize Patient Order Handler
 * Pathology Laboratory Management System
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$patient_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
} else if (isset($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
}

if ($patient_id <= 0) {
    setFlash('error', 'Invalid patient identifier.');
    header("Location: dashboard.php");
    exit();
}

// 1. Verify Patient Exists
$patient = getPatientById($pdo, $patient_id);
if (!$patient) {
    setFlash('error', 'Patient not found.');
    header("Location: dashboard.php");
    exit();
}

// 2. Verify Patient is not already submitted
if ((int)$patient['submitted'] === 1) {
    setFlash('info', 'Patient has already been submitted.');
    header("Location: report.php?patient_id=" . $patient_id);
    exit();
}

// 3. Fetch all assigned tests for this patient
$stmtTests = $pdo->prepare("
    SELECT pt.id, pt.status, t.price 
    FROM patient_tests pt 
    JOIN tests t ON pt.test_id = t.id 
    WHERE pt.patient_id = ?
");
$stmtTests->execute([$patient_id]);
$assignedTests = $stmtTests->fetchAll();

// 4. Verify at least one test exists
if (empty($assignedTests)) {
    setFlash('error', 'Cannot submit patient: At least one test must be added.');
    header("Location: patient_tests.php?patient_id=" . $patient_id);
    exit();
}

// 5. Verify all selected tests are COMPLETED
$pendingCount = 0;
$completedCount = 0;
$totalAmount = 0.00;

foreach ($assignedTests as $t) {
    if ($t['status'] !== 'COMPLETED') {
        $pendingCount++;
    } else {
        $completedCount++;
        $totalAmount += (float)$t['price'];
    }
}

if ($pendingCount > 0) {
    setFlash('error', 'Please complete all selected tests before submitting.');
    header("Location: patient_tests.php?patient_id=" . $patient_id);
    exit();
}

// 6. Set submitted = 1 and finalize counts
try {
    $stmtFinalize = $pdo->prepare("
        UPDATE patients 
        SET submitted = 1, report_date = CURDATE(), test_count = ?, total_amount = ? 
        WHERE id = ?
    ");
    $stmtFinalize->execute([$completedCount, $totalAmount, $patient_id]);
    auditLog($pdo, 'SUBMIT PATIENT REPORT', 'Patient ID ' . $patient_id);

    setFlash('success', 'Patient submitted successfully. Final report is ready for printing.');

    // 7. Redirect to report.php?patient_id=...
    header("Location: report.php?patient_id=" . $patient_id);
    exit();

} catch (PDOException $e) {
    setFlash('error', 'Database error finalizing patient submission: ' . $e->getMessage());
    header("Location: patient_tests.php?patient_id=" . $patient_id);
    exit();
}
?>
