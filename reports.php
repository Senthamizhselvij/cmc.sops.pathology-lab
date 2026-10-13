<?php
/**
 * Legacy reports.php entry point.
 * Reports are now separated into individual pages.
 */
require_once __DIR__ . '/includes/auth.php';
header('Location: daily_report.php');
exit();
?>
