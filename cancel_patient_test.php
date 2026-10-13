<?php
/** Remove a test that was added to a patient but has not yet been completed. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request.');
    header('Location: dashboard.php');
    exit();
}

$patient_test_id = (int)($_POST['patient_test_id'] ?? 0);
if ($patient_test_id <= 0) {
    setFlash('error', 'Invalid test.');
    header('Location: dashboard.php');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT pt.patient_id, pt.status, p.submitted
        FROM patient_tests pt
        JOIN patients p ON p.id = pt.patient_id
        WHERE pt.id = ?
    ");
    $stmt->execute([$patient_test_id]);
    $row = $stmt->fetch();

    if (!$row) {
        setFlash('error', 'Test record not found.');
        header('Location: dashboard.php');
        exit();
    }

    if ((int)$row['submitted'] === 1) {
        setFlash('error', 'Finalized patient tests cannot be cancelled.');
        header('Location: patient_tests.php?patient_id=' . (int)$row['patient_id']);
        exit();
    }

    $patientId = (int)$row['patient_id'];
    auditLog($pdo, 'DELETE/CANCEL TEST', 'Patient ID ' . $patientId . ', Patient Test ID ' . $patient_test_id);
    $delete = $pdo->prepare("DELETE FROM patient_tests WHERE id = ?");
    $delete->execute([$patient_test_id]);

    calculateAndUpdatePatientTotals($pdo, $patientId);
    setFlash('success', 'Test cancelled and removed. It will not appear in the patient report.');
    header('Location: patient_tests.php?patient_id=' . $patientId);
    exit();
} catch (PDOException $e) {
    setFlash('error', 'Unable to cancel the test. Please try again.');
    header('Location: dashboard.php');
    exit();
}
?>
