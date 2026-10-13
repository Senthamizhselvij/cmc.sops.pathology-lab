<?php
/**
 * Clinic Portal Header Template
 * Pathology Laboratory & Clinic Portal
 * Matching Design: Image 2 (cmc.sopsdelhi.in/nuser.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : ''; ?>Patient Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Top Navigation Bar (from Screenshot 2) -->
<header class="portal-navbar">
    <div class="portal-navbar-inner">
        <a href="dashboard.php" class="portal-brand">
            <span class="portal-brand-icon">💙</span>
            <span class="portal-brand-text">Clinic Portal</span>
        </a>
        <div class="portal-nav-right">
            <span class="portal-user-tag">User: <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? '5001'); ?></strong></span>
            <?php if (in_array(($_SESSION['admin_role'] ?? 'staff'), ['admin', 'hod'], true)): ?>
                <a href="admin_users.php" class="btn btn-outline" style="margin-right:8px;">👥 User Management</a>
                <a href="audit_log.php" class="btn btn-outline" style="margin-right:8px;">🧾 Activity Log</a>
            <?php endif; ?>
            <a href="logout.php" class="btn-secure-logout" onclick="return confirmLogout(event);">
                🔒  Logout
            </a>
        </div>
    </div>
</header>

<!-- Main Portal Layout with Left Sidebar & Right Content -->
<div class="portal-layout">
    <!-- LEFT SIDEBAR: PATIENT HUB (from Screenshot 2) -->
    <aside class="patient-hub-sidebar no-print">
        <div class="hub-title">PATIENT HUB</div>

        <div class="hub-top-buttons">
            <a href="patient.php" class="btn-hub-add">+ Add New</a>
            <a href="records.php" class="btn-hub-view">📋 View All</a>
        </div>

        <!-- Search by Receipt ID -->
        <div class="hub-search-group">
            <form action="records.php" method="GET" class="hub-search-form">
                <input type="text" name="receipt_id" class="hub-search-input" placeholder="Find by Receipt ID...">
                <button type="submit" class="btn-hub-go">Go</button>
            </form>
        </div>

        <!-- Search by Patient ID -->
        <div class="hub-search-group">
            <form action="records.php" method="GET" class="hub-search-form">
                <input type="text" name="patient_id" class="hub-search-input" placeholder="Find by Patient ID...">
                <button type="submit" class="btn-hub-go">Go</button>
            </form>
        </div>

        <!-- Search by Name -->
        <div class="hub-search-group">
            <form action="records.php" method="GET" class="hub-search-form">
                <input type="text" name="patient_name" class="hub-search-input" placeholder="Find patient by name...">
                <button type="submit" class="btn-hub-go">Go</button>
            </form>
        </div>

        <!-- Search by Phone -->
        <div class="hub-search-group">
            <form action="records.php" method="GET" class="hub-search-form">
                <input type="text" name="phone" class="hub-search-input" placeholder="Find patient by phone...">
                <button type="submit" class="btn-hub-go">Go</button>
            </form>
        </div>

        <div class="hub-report-label">REPORT</div>

        <a href="daily_report.php" class="hub-report-btn">Daily Report</a>
        <a href="weekly_report.php" class="hub-report-btn">Weekly Report</a>
        <a href="summary_report.php" class="hub-report-btn">Summary Report</a>
        <a href="cancel_report.php" class="hub-report-btn">Cancel Report</a>
    </aside>

    <!-- RIGHT MAIN CONTENT AREA -->
    <main class="portal-main-content">
        <?php renderFlashMessages(); ?>
