<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireAdminRole();

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE username LIKE ? OR action LIKE ? OR details LIKE ? ORDER BY id DESC LIMIT 500");
    $w = '%' . $search . '%';
    $stmt->execute([$w, $w, $w]);
} else {
    $stmt = $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 500");
}
$logs = $stmt->fetchAll();
$pageTitle = 'Activity Log - Admin';
require_once __DIR__ . '/includes/header.php';
?>
<div class="portal-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:20px;">
        <div><h2 class="portal-card-title">Activity Log</h2><p class="portal-card-subtitle">Shows which login performed important actions. Logs are not deleted by the application.</p></div>
        <a href="admin_users.php" class="btn btn-outline">← User Management</a>
    </div>
    <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;">
        <input class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search username, action or details">
        <button class="btn btn-primary">Search</button>
    </form>
    <div class="table-responsive">
        <table class="portal-table">
            <thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?><tr><td colspan="4" style="text-align:center;padding:30px;">No activity recorded yet.</td></tr><?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                    <td><strong><?php echo htmlspecialchars($log['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                    <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
