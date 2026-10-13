<?php
/**
 * Authentication Checker
 * Pathology Laboratory Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

/** Require an ADMIN role for administrative pages/actions. */
function requireAdminRole() {
    if (!in_array(($_SESSION['admin_role'] ?? 'staff'), ['admin', 'hod'], true)) {
        if (function_exists('setFlash')) {
            setFlash('error', 'Admin access is required for this action.');
        }
        header('Location: dashboard.php');
        exit();
    }
}
?>
