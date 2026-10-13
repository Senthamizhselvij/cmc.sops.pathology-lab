<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireAdminRole();

function generateStrongPassword($length = 12) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $out = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $requestedRole = $_POST['role'] ?? 'staff';
            $role = in_array($requestedRole, ['admin', 'hod', 'staff'], true) ? $requestedRole : 'staff';

            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
                throw new Exception('Username must be 3-50 characters and use only letters, numbers, dot, underscore or hyphen.');
            }
            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters.');
            }

            $check = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
            $check->execute([$username]);
            if ($check->fetch()) {
                throw new Exception('Username already exists.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admins (username, password, role) VALUES (?, ?, ?)');
            $stmt->execute([$username, $hash, $role]);
            $newId = $pdo->lastInsertId();

            auditLog($pdo, 'CREATE USER', 'Created username ' . $username . ' with role ' . strtoupper($role) . ' (ID ' . $newId . ')');
            setFlash('success', 'User ' . $username . ' created successfully. Save the password you entered/generated.');
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $requestedRole = $_POST['role'] ?? 'staff';
            $role = in_array($requestedRole, ['admin', 'hod', 'staff'], true) ? $requestedRole : 'staff';
            $newPassword = $_POST['new_password'] ?? '';

            if ($id <= 0) throw new Exception('Invalid user.');
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) throw new Exception('Invalid username.');

            $oldStmt = $pdo->prepare('SELECT id, username, role FROM admins WHERE id = ?');
            $oldStmt->execute([$id]);
            $old = $oldStmt->fetch();
            if (!$old) throw new Exception('User not found.');

            if ($id === (int)($_SESSION['admin_id'] ?? 0) && !in_array($role, ['admin', 'hod'], true)) {
                throw new Exception('You cannot remove your own administrative access.');
            }

            $check = $pdo->prepare('SELECT id FROM admins WHERE username = ? AND id <> ? LIMIT 1');
            $check->execute([$username, $id]);
            if ($check->fetch()) throw new Exception('Another user already has that username.');

            $pdo->beginTransaction();
            if ($newPassword !== '') {
                if (strlen($newPassword) < 8) throw new Exception('New password must be at least 8 characters.');
                $stmt = $pdo->prepare('UPDATE admins SET username = ?, role = ?, password = ? WHERE id = ?');
                $stmt->execute([$username, $role, password_hash($newPassword, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE admins SET username = ?, role = ? WHERE id = ?');
                $stmt->execute([$username, $role, $id]);
            }
            $pdo->commit();

            auditLog($pdo, 'EDIT USER', 'User ID ' . $id . ': ' . $old['username'] . ' -> ' . $username . ', role ' . $old['role'] . ' -> ' . $role . ($newPassword !== '' ? ', password changed' : ''));
            setFlash('success', 'User updated successfully.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid user.');
            if ($id === (int)($_SESSION['admin_id'] ?? 0)) throw new Exception('You cannot delete your own account while logged in.');

            $stmt = $pdo->prepare('SELECT id, username, role FROM admins WHERE id = ?');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) throw new Exception('User not found.');

            $del = $pdo->prepare('DELETE FROM admins WHERE id = ?');
            $del->execute([$id]);
            auditLog($pdo, 'DELETE USER', 'Deleted username ' . $user['username'] . ' (ID ' . $id . ', role ' . $user['role'] . ')');
            setFlash('success', 'User deleted. The deletion remains recorded in Activity Log.');
        } else {
            throw new Exception('Invalid admin action.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        setFlash('error', $e->getMessage());
    }

    header('Location: admin_users.php');
    exit();
}

$users = $pdo->query("SELECT id, username, CASE WHEN role IS NULL OR role = '' THEN 'staff' ELSE role END AS role, created_at FROM admins ORDER BY id ASC")->fetchAll();
$pageTitle = 'User Management - Admin';
require_once __DIR__ . '/includes/header.php';
?>

<div class="portal-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h2 class="portal-card-title">User Management</h2>
            <p class="portal-card-subtitle">Create staff, HOD, or admin login accounts. HOD and admin can manage users and view the activity log. Every create, edit and delete action is recorded.</p>
        </div>
        <a href="audit_log.php" class="btn btn-outline">🧾 View Activity Log</a>
    </div>

    <div style="border:1px solid var(--border-color);border-radius:12px;padding:20px;margin-bottom:25px;background:#f8fafc;">
        <h3 style="margin-top:0;">Create New User</h3>
        <form method="POST" id="create-user-form">
            <input type="hidden" name="action" value="create">
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label required">Username</label>
                    <input type="text" name="username" class="form-control" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" required placeholder="e.g. staff01">
                </div>
                <div class="form-group">
                    <label class="form-label required">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="staff">Staff</option>
                        <option value="hod">HOD</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label required">Password</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="password" id="new-password" class="form-control" minlength="8" required placeholder="Minimum 8 characters">
                        <button type="button" class="btn btn-outline" onclick="generatePassword('new-password')">Generate</button>
                    </div>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-primary" style="width:100%;">+ Create User</button>
                </div>
            </div>
        </form>
    </div>

    <h3>Existing Users</h3>
    <div class="table-responsive">
        <table class="portal-table">
            <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo (int)$u['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                    <td><span class="badge" style="background:<?php echo in_array($u['role'], ['admin', 'hod'], true) ? '#dcfce7' : '#e0f2fe'; ?>;color:#0f172a;"><?php echo strtoupper($u['role']); ?></span></td>
                    <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline" onclick='editUser(<?php echo json_encode($u, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                        <?php if ((int)$u['id'] !== (int)($_SESSION['admin_id'] ?? 0)): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user? This cannot be undone. The deletion will remain in Activity Log.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline" style="color:#b91c1c;">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:14px;padding:25px;width:min(520px,100%);">
        <h3>Edit User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group"><label class="form-label">Username</label><input class="form-control" name="username" id="edit-username" required maxlength="50" pattern="[A-Za-z0-9._-]+"></div>
            <div class="form-group"><label class="form-label">Role</label><select class="form-control" name="role" id="edit-role"><option value="staff">Staff</option><option value="hod">HOD</option><option value="admin">Admin</option></select></div>
            <div class="form-group"><label class="form-label">New Password (leave blank to keep current)</label><div style="display:flex;gap:8px;"><input class="form-control" type="text" name="new_password" id="edit-password" minlength="8"><button type="button" class="btn btn-outline" onclick="generatePassword('edit-password')">Generate</button></div></div>
            <div style="display:flex;justify-content:flex-end;gap:10px;"><button type="button" class="btn btn-outline" onclick="closeEdit()">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function generatePassword(id) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    let p = '';
    for (let i=0;i<12;i++) p += chars[Math.floor(Math.random()*chars.length)];
    document.getElementById(id).value = p;
}
function editUser(u) {
    document.getElementById('edit-id').value = u.id;
    document.getElementById('edit-username').value = u.username;
    document.getElementById('edit-role').value = u.role;
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEdit() { document.getElementById('edit-modal').style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
