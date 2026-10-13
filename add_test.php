<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$patient_id = (int)($_GET['patient_id'] ?? 0);
$test_id = (int)($_GET['test_id'] ?? 0);
if ($patient_id <= 0 || $test_id <= 0) {
    setFlash('error', 'Invalid patient or test.');
    header('Location: dashboard.php'); exit();
}

$patient = getPatientById($pdo, $patient_id);
if (!$patient) { setFlash('error', 'Patient not found.'); header('Location: dashboard.php'); exit(); }

$stmtTest = $pdo->prepare('SELECT * FROM tests WHERE id=?');
$stmtTest->execute([$test_id]);
$test = $stmtTest->fetch();
if (!$test) { setFlash('error', 'Test not found.'); header('Location: select_test.php?patient_id='.$patient_id); exit(); }

$check = $pdo->prepare('SELECT id, status FROM patient_tests WHERE patient_id=? AND test_id=?');
$check->execute([$patient_id, $test_id]);
$existing = $check->fetch();
if ($existing) {
    header('Location: patient_tests.php?patient_id='.$patient_id);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO patient_tests (patient_id,test_id,status) VALUES (?,?, 'PENDING')");
    $stmt->execute([$patient_id, $test_id]);
    auditLog($pdo, 'ADD TEST', 'Patient ID '.$patient_id.', Test: '.($test['test_name'] ?? $test_id));
    setFlash('success', 'Test saved. You can enter the result now or later.');
} catch (PDOException $e) {
    setFlash('error', 'Database error adding test: '.$e->getMessage());
}
header('Location: patient_tests.php?patient_id='.$patient_id);
exit();
