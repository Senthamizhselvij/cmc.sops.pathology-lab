<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$patient_id = (int)($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) { setFlash('error','Invalid patient identifier.'); header('Location: dashboard.php'); exit(); }

$patient = getPatientById($pdo, $patient_id);
if (!$patient) { setFlash('error','Patient record not found.'); header('Location: dashboard.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report_date'])) {
    $selectedReportDate = trim($_POST['report_date'] ?? '');
    $validDate = DateTime::createFromFormat('Y-m-d', $selectedReportDate);
    if ($selectedReportDate === '' || !$validDate || $validDate->format('Y-m-d') !== $selectedReportDate) {
        setFlash('error', 'Please choose a valid report date.');
    } else {
        $saveDate = $pdo->prepare('UPDATE patients SET report_date = ? WHERE id = ?');
        $saveDate->execute([$selectedReportDate, $patient_id]);
        setFlash('success', 'Report date saved successfully.');
    }
    header('Location: patient_tests.php?patient_id=' . $patient_id);
    exit();
}

$stmtTests = $pdo->prepare("
    SELECT
        pt.id AS patient_test_id,
        pt.test_id,
        pt.status,
        pt.completed_at,
        pt.result_date,
        t.test_name,
        t.price
    FROM patient_tests pt
    JOIN tests t ON pt.test_id=t.id
    WHERE pt.patient_id=?
    ORDER BY pt.id ASC
");
$stmtTests->execute([$patient_id]);
$patientTests = $stmtTests->fetchAll();

$pendingCount = 0;
$completedCount = 0;
$totalAmount = 0.00;

foreach ($patientTests as $pt) {
    if ($pt['status'] === 'COMPLETED') {
        $completedCount++;
    } else {
        $pendingCount++;
    }
    // Billing shown before final report is based on every test added to the patient.
    $totalAmount += (float)$pt['price'];
}

$allCompleted = !empty($patientTests) && $pendingCount === 0;
$isSubmitted = (int)$patient['submitted'] === 1;
$isCancelled = ($patient['report_status'] ?? 'ACTIVE') === 'CANCELLED';

$pageTitle = 'Patient Tests';
require_once __DIR__ . '/includes/header.php';
?>
<div class="portal-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h2 class="portal-card-title">Patient Tests</h2>
            <p class="portal-card-subtitle" style="margin-bottom:0;">Add tests, enter/edit individual results, and review the amount before generating the final report.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
            <a href="records.php" class="btn btn-outline">All Records</a>
        </div>
    </div>

    <div class="patient-bio-grid">
        <div class="bio-item"><span class="bio-label">Receipt ID</span><span class="bio-value" style="color:var(--primary-blue);"><?php echo htmlspecialchars($patient['receipt_id']); ?></span></div>
        <div class="bio-item"><span class="bio-label">Registration Date</span><span class="bio-value"><?php echo formatDate($patient['patient_date']); ?></span></div>
        <div class="bio-item" style="grid-column:span 2;">
            <form method="POST" style="display:flex;align-items:end;gap:10px;flex-wrap:wrap;width:100%;">
                <div style="flex:1;min-width:180px;">
                    <label class="bio-label" for="report_date" style="display:block;margin-bottom:6px;">Report Date</label>
                    <input type="date" id="report_date" name="report_date" class="form-control" required value="<?php echo htmlspecialchars($patient['report_date'] ?? ''); ?>">
                </div>
                <button type="submit" name="save_report_date" value="1" class="btn btn-primary">Save Report Date</button>
            </form>
        </div>
        <div class="bio-item"><span class="bio-label">Patient Name</span><span class="bio-value"><?php echo htmlspecialchars($patient['patient_name']); ?></span></div>
        <div class="bio-item"><span class="bio-label">Patient ID</span><span class="bio-value"><?php echo htmlspecialchars($patient['patient_code']); ?></span></div>
        <div class="bio-item"><span class="bio-label">Age / Gender</span><span class="bio-value"><?php echo (int)$patient['age']; ?> Yrs / <?php echo htmlspecialchars($patient['gender']); ?></span></div>
        <div class="bio-item"><span class="bio-label">Phone</span><span class="bio-value"><?php echo htmlspecialchars($patient['phone']); ?></span></div>
    </div>

    <?php if (!empty($patient['address'])): ?>
        <div style="margin:0 0 20px;padding:10px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
            <strong>Address:</strong> <?php echo htmlspecialchars($patient['address']); ?>
        </div>
    <?php endif; ?>

    <?php if ($isCancelled): ?>
        <div class="no-print" style="margin-bottom:20px;padding:14px 16px;border:1px solid #fecaca;background:#fff7f7;border-radius:8px;">
            <strong style="color:#991b1b;">REPORT CANCELLED</strong>
            <div style="font-size:.85rem;margin-top:4px;">Reason: <?php echo htmlspecialchars($patient['cancel_reason'] ?? '-'); ?></div>
        </div>
    <?php elseif ($isSubmitted): ?>
    <?php endif; ?>

    <div class="test-summary-strip">
        <div>
            <div class="summary-strip-title">Tests Added</div>
            <div class="summary-strip-value count"><?php echo count($patientTests); ?></div>
        </div>
        <div style="height:50px;width:1px;background:#cbd5e1;"></div>
        <div>
            <div class="summary-strip-title">Results Saved</div>
            <div class="summary-strip-value count"><?php echo $completedCount; ?></div>
        </div>
        <div style="height:50px;width:1px;background:#cbd5e1;"></div>
        <div>
            <div class="summary-strip-title">Waiting for Result</div>
            <div class="summary-strip-value count"><?php echo $pendingCount; ?></div>
        </div>
        <div style="height:50px;width:1px;background:#cbd5e1;"></div>
        <div>
            <div class="summary-strip-title">Total Amount</div>
            <div class="summary-strip-value" style="font-size:1.35rem;color:var(--success-green);"><?php echo formatCurrency($totalAmount); ?></div>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin:25px 0 12px;">
        <h3 style="font-size:1.15rem;color:#1e293b;margin:0;">Ordered Laboratory Tests</h3>
        <?php if (!$isSubmitted && !$isCancelled): ?>
            <a href="select_test.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary btn-sm">+ Add Test</a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="portal-table" style="border:1px solid var(--border-color);">
            <thead>
                <tr>
                    <th style="width:6%;">#</th>
                    <th>Laboratory Test</th>
                    <th style="width:14%;text-align:right;">Test Amount</th>
                    <th style="width:16%;text-align:center;">Result Date</th>
                    <th style="width:17%;text-align:center;">Status</th>
                    <th style="width:18%;text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$patientTests): ?>
                <tr><td colspan="6" style="text-align:center;padding:35px;color:var(--text-muted);">No tests added yet. <a href="select_test.php?patient_id=<?php echo $patient_id; ?>">Add the first test</a>.</td></tr>
            <?php else: foreach ($patientTests as $i=>$test): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td>
                        <a href="test_result.php?patient_test_id=<?php echo (int)$test['patient_test_id']; ?>" style="text-decoration:none;color:var(--primary-blue);font-weight:800;">
                            <?php echo htmlspecialchars($test['test_name']); ?>
                        </a>
                    </td>
                    <td style="text-align:right;font-weight:700;"><?php echo formatCurrency($test['price']); ?></td>
                    <td style="text-align:center;">
                        <?php echo !empty($test['result_date']) ? htmlspecialchars(formatDate($test['result_date'])) : '<span style="color:#94a3b8;">Not completed</span>'; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($test['status']==='COMPLETED'): ?>
                            <span class="badge badge-completed">✓ SAVED</span>
                        <?php else: ?>
                            <span class="badge badge-pending">WAITING FOR RESULT</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if (!$isCancelled): ?>
                            <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;">
                                <a href="test_result.php?patient_test_id=<?php echo (int)$test['patient_test_id']; ?>" class="btn btn-sm btn-outline">
                                    Edit Result
                                </a>
                            </div>
                        <?php else: ?>
                            <span style="color:#94a3b8;">Cancelled</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if ($patientTests): ?>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right;font-weight:800;">TOTAL AMOUNT</td>
                    <td style="text-align:right;font-weight:900;color:var(--success-green);"><?php echo formatCurrency($totalAmount); ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <div class="no-print" style="margin-top:25px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
        <?php if (!$isCancelled && $patientTests):
            $saveTest = null;
            foreach ($patientTests as $pt) {
                if ($pt['status'] !== 'COMPLETED') { $saveTest = $pt; break; }
            }
            if (!$saveTest) { $saveTest = $patientTests[0]; }
        ?>
            <a href="test_result.php?patient_test_id=<?php echo (int)$saveTest['patient_test_id']; ?>" class="btn btn-success">💾 Save</a>
            <a href="report.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">📄 Report</a>
        <?php endif; ?>

        <?php if (!$isSubmitted && !$isCancelled && $allCompleted): ?>
            <form action="submit_patient.php" method="POST" style="margin:0;" onsubmit="return confirm('Generate the final report for this patient?');">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <button type="submit" class="btn btn-success">Generate Final Report</button>
            </form>
        <?php endif; ?>

        <?php if ($isSubmitted || $isCancelled): ?>
            <a href="report.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">View Patient Report</a>
        <?php endif; ?>
    </div>

    <?php if (!$isSubmitted && !$isCancelled && !$allCompleted && $patientTests): ?>
        <div class="no-print" style="margin-top:15px;text-align:right;color:#64748b;font-size:.85rem;">
            Complete all added tests before generating the final report.
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
