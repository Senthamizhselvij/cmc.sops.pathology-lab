<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$patient_id = (int)($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) { setFlash('error','Invalid patient ID.'); header('Location: dashboard.php'); exit(); }
$patient = getPatientById($pdo,$patient_id);
if (!$patient) { setFlash('error','Patient record not found.'); header('Location: dashboard.php'); exit(); }

// Search/filter tests from the catalog.
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM tests WHERE test_name NOT IN ('Blood Sugar (Fasting / PP)','HIV / HCV / HBsAg / VDRL','Blood Sugar','Malaria','Urine Test','Thyroid') AND test_name LIKE ? ORDER BY test_name ASC");
    $stmt->execute(['%' . $search . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM tests WHERE test_name NOT IN ('Blood Sugar (Fasting / PP)','HIV / HCV / HBsAg / VDRL','Blood Sugar','Malaria','Urine Test','Thyroid') ORDER BY id ASC");
}
$allTests = $stmt->fetchAll();
$stmtAssigned = $pdo->prepare('SELECT id,test_id,status FROM patient_tests WHERE patient_id=?');
$stmtAssigned->execute([$patient_id]);
$assignedMap=[];
while($row=$stmtAssigned->fetch()) $assignedMap[(int)$row['test_id']]=['id'=>(int)$row['id'],'status'=>$row['status']];

$pageTitle='Add Laboratory Test'; require_once __DIR__ . '/includes/header.php';
?>
<div class="portal-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;">
        <div><h2 class="portal-card-title">Add Laboratory Test</h2><p class="portal-card-subtitle" style="margin-bottom:0;"><?php echo htmlspecialchars($patient['patient_name']); ?> · Patient ID: <strong><?php echo htmlspecialchars($patient['patient_code']); ?></strong> · Receipt ID: <strong><?php echo htmlspecialchars($patient['receipt_id']); ?></strong></p></div>
        <a href="patient_tests.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline">&larr; Back</a>
    </div>
    <form method="get" style="display:flex;gap:10px;align-items:center;margin:0 0 20px;flex-wrap:wrap;">
        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
        <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search test name..." autocomplete="off" style="flex:1;min-width:240px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px;">
        <button type="submit" class="btn btn-primary">Search Test</button>
        <?php if ($search !== ''): ?><a href="select_test.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline">Clear</a><?php endif; ?>
    </form>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
    <?php foreach($allTests as $test): $id=(int)$test['id']; $assigned=isset($assignedMap[$id]); ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;">
            <div><h4 style="font-size:1.1rem;color:#1e293b;margin:0 0 8px;"><?php echo htmlspecialchars($test['test_name']); ?></h4><div style="font-weight:800;color:#166534;font-size:1rem;margin-bottom:14px;">Test Amount: <?php echo formatCurrency($test['price']); ?></div></div>
            <div>
            <?php if($assigned): ?>
                <span class="badge <?php echo $assignedMap[$id]['status']==='COMPLETED'?'badge-completed':'badge-pending'; ?>" style="display:block;text-align:center;padding:10px;margin-bottom:9px;"><?php echo $assignedMap[$id]['status']==='COMPLETED'?'SAVED':'ADDED - RESULT PENDING'; ?></span>
                <a href="test_result.php?patient_test_id=<?php echo (int)$assignedMap[$id]['id']; ?>" class="btn btn-outline" style="width:100%;">Open Result</a>
            <?php else: ?>
                <a href="add_test.php?patient_id=<?php echo $patient_id; ?>&test_id=<?php echo $id; ?>" class="btn btn-primary" style="width:100%;">+ ADD TEST</a>
            <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
