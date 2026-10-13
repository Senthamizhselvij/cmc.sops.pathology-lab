<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$receipt_search = trim($_GET['receipt_id'] ?? '');
$patient_id_search = trim($_GET['patient_id'] ?? '');
$name_search = trim($_GET['patient_name'] ?? '');
$phone_search = trim($_GET['phone'] ?? '');

$conditions = [];
$params = [];
if ($receipt_search !== '') { $conditions[] = 'receipt_id LIKE ?'; $params[] = '%' . $receipt_search . '%'; }
if ($patient_id_search !== '') { $conditions[] = 'patient_code LIKE ?'; $params[] = '%' . $patient_id_search . '%'; }
if ($name_search !== '') { $conditions[] = 'patient_name LIKE ?'; $params[] = '%' . $name_search . '%'; }
if ($phone_search !== '') { $conditions[] = 'phone LIKE ?'; $params[] = '%' . $phone_search . '%'; }
$baseCondition = "report_status = 'ACTIVE'";
$whereParts = [$baseCondition];
if ($conditions) { $whereParts = array_merge($whereParts, $conditions); }
$where = 'WHERE ' . implode(' AND ', $whereParts);
$stmt = $pdo->prepare("SELECT * FROM patients $where ORDER BY id DESC");
$stmt->execute($params);
$patients = $stmt->fetchAll();
$hasSearch = ($receipt_search !== '' || $patient_id_search !== '' || $name_search !== '' || $phone_search !== '');

$pageTitle = 'Patient Records';
require_once __DIR__ . '/includes/header.php';
?>
<div class="portal-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:22px;">
        <div><h2 class="portal-card-title">Patient Records</h2><p class="portal-card-subtitle" style="margin-bottom:0;">Receipt ID, date, patient details and test access.</p></div>
        <a href="patient.php" class="btn btn-primary">+ Add New Patient</a>
    </div>

    <form method="GET" action="records.php" style="margin-bottom:22px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
            <div><label class="form-label">Receipt ID</label><input type="text" name="receipt_id" class="form-control" value="<?php echo htmlspecialchars($receipt_search); ?>" placeholder="Receipt ID"></div>
            <div><label class="form-label">Patient ID</label><input type="text" name="patient_id" class="form-control" value="<?php echo htmlspecialchars($patient_id_search); ?>" placeholder="Patient ID"></div>
            <div><label class="form-label">Patient Name</label><input type="text" name="patient_name" class="form-control" value="<?php echo htmlspecialchars($name_search); ?>" placeholder="Patient Name"></div>
            <div><label class="form-label">Phone Number</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone_search); ?>" placeholder="Phone Number"></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($hasSearch): ?><a href="records.php" class="btn btn-outline">Clear</a><?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="portal-table" style="border:1px solid var(--border-color);">
            <thead><tr><th>Receipt ID</th><th>Date</th><th>Patient Name</th><th>Patient ID</th><th>Age</th><th>Gender</th><th>Phone</th><th>Address</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (!$patients): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">No matching patient records found.</td></tr>
            <?php else: foreach ($patients as $p): ?>
                <tr>
                    <td><strong style="color:var(--primary-blue);"><?php echo htmlspecialchars((string)($p['receipt_id'] ?? '') ?: '-'); ?></strong></td>
                    <td><?php echo formatDate($p['patient_date']); ?></td>
                    <td><strong><?php echo htmlspecialchars($p['patient_name']); ?></strong></td>
                    <td><strong><?php echo htmlspecialchars((string)($p['patient_code'] ?? '') ?: '-'); ?></strong></td>
                    <td><?php echo (int)$p['age']; ?></td>
                    <td><?php echo htmlspecialchars($p['gender']); ?></td>
                    <td><?php echo htmlspecialchars($p['phone']); ?></td>
                    <td><?php echo htmlspecialchars($p['address'] ?: '-'); ?></td>
                    <td>
                        <?php if ((int)$p['submitted'] === 1 && ($p['report_status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                            <a href="report.php?patient_id=<?php echo (int)$p['id']; ?>" title="Final Report" aria-label="Final Report" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#16a34a;color:#fff;text-decoration:none;font-size:18px;box-shadow:0 1px 3px rgba(0,0,0,.12);">📄</a>
                        <?php else: ?>
                            <a href="patient_tests.php?patient_id=<?php echo (int)$p['id']; ?>" title="Open Patient Tests" aria-label="Open Patient Tests" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#e2e8f0;color:#475569;text-decoration:none;font-size:18px;">📄</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
