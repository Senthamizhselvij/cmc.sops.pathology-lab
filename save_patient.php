<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: patient.php');
    exit();
}

$receipt_id   = strtoupper(trim($_POST['receipt_id'] ?? ''));
$receipt_id   = $receipt_id === '' ? null : $receipt_id;
$patient_date = trim($_POST['patient_date'] ?? date('Y-m-d'));
$patient_name = trim($_POST['patient_name'] ?? '');
$patient_code = strtoupper(trim($_POST['patient_code'] ?? ''));
$patient_code = $patient_code === '' ? null : $patient_code;
$age          = trim($_POST['age'] ?? '');
$gender       = trim($_POST['gender'] ?? 'Male');
$phone        = trim($_POST['phone'] ?? '');
$address      = trim($_POST['address'] ?? '');
$errors = [];

if ($receipt_id !== null && (strlen($receipt_id) > 100 || !preg_match('/^[A-Z0-9]+$/', $receipt_id))) {
    $errors[] = 'Receipt ID can contain letters and numbers only.';
} elseif (strlen($receipt_id) > 100 || !preg_match('/^[A-Z0-9]+$/', $receipt_id)) {
    $errors[] = 'Receipt ID can contain letters and numbers only.';
}

if ($patient_code !== null && !preg_match('/^[A-Z0-9]{1,12}$/', $patient_code)) {
    $errors[] = 'Patient ID must contain 1 to 12 letters or numbers.';
} elseif (!preg_match('/^[A-Z0-9]{1,12}$/', $patient_code)) {
    $errors[] = 'Patient ID must contain 1 to 12 letters or numbers.';
}

if ($patient_name === '') $errors[] = 'Please enter the patient name.';
if ($age === '' || !is_numeric($age) || (int)$age < 1 || (int)$age > 120) $errors[] = 'Please enter a valid age between 1 and 120.';
if (!in_array($gender, ['Male', 'Female', 'Other'], true)) $gender = 'Male';

$cleanPhone = preg_replace('/[\s\-()+]/', '', $phone);
if ($phone === '' || !preg_match('/^[0-9]{10,15}$/', $cleanPhone)) {
    $errors[] = 'Please enter a valid phone number (10 to 15 digits).';
}

$dateObject = DateTime::createFromFormat('!Y-m-d', $patient_date);
$dateErrors = DateTime::getLastErrors();
if (!$dateObject || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $dateObject->format('Y-m-d') !== $patient_date) {
    $errors[] = 'Please select a valid registration date.';
} elseif ($patient_date < date('Y-m-d', strtotime('-1 day'))) {
    $errors[] = 'Registration date can only be today or yesterday.';
} elseif ($patient_date > date('Y-m-d')) {
    $errors[] = 'Registration date cannot be in the future.';
}

// Duplicate Receipt IDs, Patient IDs, names, phone numbers, addresses, and
// other patient details are allowed. Each submission creates a new patients.id.

if ($errors) {
    setFlash('error', implode(' ', $errors));
    header('Location: patient.php');
    exit();
}

try {
    $stmt = $pdo->prepare('INSERT INTO patients (receipt_id, patient_date, patient_name, patient_code, age, gender, phone, address, test_count, total_amount, submitted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0.00, 0)');
    $stmt->execute([$receipt_id, $patient_date, $patient_name, $patient_code, (int)$age, $gender, $cleanPhone, $address]);
    $newPatientId = (int)$pdo->lastInsertId();
    auditLog($pdo, 'CREATE PATIENT', 'Patient DB ID ' . $newPatientId . ', Patient ID ' . $patient_code . ', Receipt ID ' . $receipt_id);
    setFlash('success', 'Patient saved successfully. Add the required tests now; results can be entered later.');
    header('Location: patient_tests.php?patient_id=' . $newPatientId);
    exit();
} catch (PDOException $e) {
    setFlash('error', 'Database error saving patient: ' . $e->getMessage());
    header('Location: patient.php');
    exit();
}
