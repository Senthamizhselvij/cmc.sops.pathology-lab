<?php
/**
 * Database Configuration using PDO
 * Pathology Laboratory Management System
 */

$host = '127.0.0.1';
$db_name = 'pathology_lab';
$username = 'root';
$password = ''; // Default password in XAMPP is empty
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Automatically connects to active MySQL port (3307 or 3306)
$ports = [3307, 3306];
$pdo = null;
$lastException = null;

foreach ($ports as $port) {
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=$charset";
        $pdo = new PDO($dsn, $username, $password, $options);
        break;
    } catch (PDOException $e) {
        $lastException = $e;
        // If connecting directly to pathology_lab failed (e.g. database does not exist yet),
        // try connecting to MySQL server to auto-create and initialize it automatically.
        try {
            $rootDsn = "mysql:host=$host;port=$port;charset=$charset";
            $rootPdo = new PDO($rootDsn, $username, $password, $options);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db_name;charset=$charset", $username, $password, $options);
            break;
        } catch (PDOException $inner) {
            // Not running on this port, continue to next port
        }
    }
}

if (!$pdo) {
    die("
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 25px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 8px;'>
        <h3 style='margin-top:0;'>Database Connection Failed</h3>
        <p><strong>Error Message:</strong> " . htmlspecialchars($lastException ? $lastException->getMessage() : 'Unable to connect to MySQL') . "</p>
        <hr style='border: 0; border-top: 1px solid #f5c6cb;'>
        <p><strong>Troubleshooting Steps:</strong></p>
        <ol style='line-height: 1.6;'>
            <li>Ensure <strong>MySQL</strong> is started in the XAMPP Control Panel (or double-click <code>START_APP.bat</code>).</li>
        </ol>
    </div>
    ");
}

// Automatically import database.sql if tables are not yet created (zero manual setup in phpMyAdmin!)
try {
    $hasTables = (bool)$pdo->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
    if (!$hasTables) {
        $sqlFile = __DIR__ . '/../database.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $pdo->exec($sqlContent);
        }
    }
} catch (Throwable $t) {
    error_log("Pathology Lab auto-import notice: " . $t->getMessage());
}

// Keep existing XAMPP databases compatible with the current application.
require_once __DIR__ . '/../includes/ensure_schema.php';
?>
