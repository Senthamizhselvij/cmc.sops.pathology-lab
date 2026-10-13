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

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$hasSearch = ($receipt_search !== '' || $patient_id_search !== '' || $name_search !== '' || $phone_search !== '');

$stmt = $pdo->prepare("SELECT id, receipt_id, patient_date, patient_name, patient_code, age, gender, phone, address FROM patients $where ORDER BY id DESC LIMIT 25");
$stmt->execute($params);
$patients = $stmt->fetchAll();

$counts = [
    'patients' => (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
    'tests' => (int)$pdo->query("SELECT COUNT(*) FROM patient_tests WHERE status='COMPLETED'")->fetchColumn(),
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM patient_tests WHERE status='PENDING'")->fetchColumn(),
];

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="portal-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:22px;">
        <div>
            <h2 class="portal-card-title">Pathology Dashboard</h2>
            <p class="portal-card-subtitle" style="margin-bottom:0;">Search patients, open their tests, and enter results whenever they are received.</p>
        </div>
        <a href="patient.php" class="btn btn-primary">+ Add New Patient</a>
    </div>

    <form method="GET" action="dashboard.php" class="no-print" style="margin-bottom:24px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
            <div>
                <label class="form-label">Receipt ID</label>
                <input type="text" name="receipt_id" class="form-control" value="<?php echo htmlspecialchars($receipt_search); ?>" placeholder="Receipt ID">
            </div>
            <div>
                <label class="form-label">Patient ID</label>
                <input type="text" name="patient_id" class="form-control" value="<?php echo htmlspecialchars($patient_id_search); ?>" placeholder="Patient ID">
            </div>
            <div>
                <label class="form-label">Patient Name</label>
                <input type="text" name="patient_name" class="form-control" value="<?php echo htmlspecialchars($name_search); ?>" placeholder="Patient Name">
            </div>
            <div>
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone_search); ?>" placeholder="Phone Number">
            </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($hasSearch): ?><a href="dashboard.php" class="btn btn-outline">Clear</a><?php endif; ?>
        </div>
    </form>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px;">
        <div style="padding:18px;border:1px solid var(--border-color);border-radius:12px;background:#f8fafc;"><div class="summary-strip-title">Total Patients</div><strong style="font-size:1.6rem;"><?php echo $counts['patients']; ?></strong></div>
        <div style="padding:18px;border:1px solid var(--border-color);border-radius:12px;background:#f8fafc;"><div class="summary-strip-title">Saved Results</div><strong style="font-size:1.6rem;"><?php echo $counts['tests']; ?></strong></div>
        <div style="padding:18px;border:1px solid var(--border-color);border-radius:12px;background:#f8fafc;"><div class="summary-strip-title">Tests Waiting for Result</div><strong style="font-size:1.6rem;"><?php echo $counts['pending']; ?></strong></div>
    </div>

    <h3 style="margin:0 0 12px;color:#1e293b;"><?php echo $hasSearch ? 'Search Results' : 'Recent Patients'; ?></h3>
    <div class="table-responsive">
        <table class="portal-table" style="border:1px solid var(--border-color);">
            <thead><tr><th>Receipt ID</th><th>Date</th><th>Patient Name</th><th>Patient ID</th><th>Age</th><th>Gender</th><th>Phone</th><th>Address</th><th class="no-print">Action</th></tr></thead>
            <tbody>
            <?php if (!$patients): ?>
                <tr><td colspan="9" style="text-align:center;padding:35px;color:var(--text-muted);">No patients found.</td></tr>
            <?php else: foreach ($patients as $p): ?>
                <tr>
                    <td><strong style="color:var(--primary-blue);"><?php echo htmlspecialchars($p['receipt_id']); ?></strong></td>
                    <td><?php echo formatDate($p['patient_date']); ?></td>
                    <td><strong><?php echo htmlspecialchars($p['patient_name']); ?></strong></td>
                    <td><strong><?php echo htmlspecialchars($p['patient_code']); ?></strong></td>
                    <td><?php echo (int)$p['age']; ?></td>
                    <td><?php echo htmlspecialchars($p['gender']); ?></td>
                    <td><?php echo htmlspecialchars($p['phone']); ?></td>
                    <td><?php echo htmlspecialchars($p['address'] ?: '-'); ?></td>
                    <td class="no-print"><a href="patient_tests.php?patient_id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary">Report</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
