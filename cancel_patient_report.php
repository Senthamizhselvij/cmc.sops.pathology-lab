<?php
/** Cancel a final patient report without deleting patient/tests/results. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request.');
    header('Location: cancel_report.php'); exit();
}

$patient_id = (int)($_POST['patient_id'] ?? 0);
$cancel_reason = trim($_POST['cancel_reason'] ?? '');

if ($patient_id <= 0) {
    setFlash('error', 'Invalid patient report.');
    header('Location: records.php'); exit();
}

if ($cancel_reason === '') {
    setFlash('error', 'Please enter the reason for cancelling the report.');
    header('Location: report.php?patient_id=' . $patient_id); exit();
}

if (strlen($cancel_reason) > 255) {
    $cancel_reason = substr($cancel_reason, 0, 255);
}

$username = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? ($_SESSION['user']['username'] ?? 'Unknown User');
$role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'staff';
$cancelled_by = $username . ' (' . $role . ')';

try {
    $stmt = $pdo->prepare("UPDATE patients
        SET report_status='CANCELLED', cancelled_at=NOW(), cancelled_by=?, cancel_reason=?
        WHERE id=? AND submitted=1 AND report_status='ACTIVE'");
    $stmt->execute([$cancelled_by, $cancel_reason, $patient_id]);

    if ($stmt->rowCount() === 0) {
        setFlash('error', 'Report could not be cancelled. It may already be cancelled or is not final.');
        header('Location: report.php?patient_id=' . $patient_id); exit();
    }

    auditLog($pdo, 'CANCEL REPORT', 'Patient ID ' . $patient_id . ', Cancelled by: ' . $cancelled_by . ', Reason: ' . $cancel_reason);
    setFlash('success', 'Patient report has been cancelled successfully.');
    header('Location: cancel_report.php'); exit();
} catch (PDOException $e) {
    setFlash('error', 'Unable to cancel the report. Please try again.');
    header('Location: report.php?patient_id=' . $patient_id); exit();
}
?>
