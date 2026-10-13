<?php
/** Daily Report - registered patients plus tests completed on the selected date. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$date = trim($_GET['date'] ?? date('Y-m-d'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$where = [
    "p.report_status = 'ACTIVE'",
    "p.patient_date = ?"
];
$params = [$date];


$sql = "
    SELECT
        p.id,
        p.receipt_id,
        p.patient_code,
        p.patient_date,
        p.patient_name,
        p.age,
        p.gender,
        p.phone,
        p.submitted, p.report_status,
        CASE WHEN EXISTS (
            SELECT 1
            FROM patients pold
            WHERE UPPER(REPLACE(TRIM(pold.receipt_id), ' ', '')) = UPPER(REPLACE(TRIM(p.receipt_id), ' ', ''))
              AND pold.id < p.id
        ) THEN 'OLD' ELSE 'NEW' END AS patient_type,
        COUNT(pt.id) AS test_count,
        COALESCE(SUM(t.price), 0.00) AS total_amount,
        COALESCE(
            GROUP_CONCAT(
                CONCAT(t.test_name, ' - ', '₹', FORMAT(t.price, 2))
                ORDER BY pt.id SEPARATOR ' | '
            ),
            ''
        ) AS test_details,
        COALESCE(SUM(CASE WHEN pt.status = 'COMPLETED' THEN 1 ELSE 0 END), 0) AS completed_tests,
        COALESCE(SUM(CASE WHEN pt.status = 'PENDING' OR pt.id IS NULL THEN 1 ELSE 0 END), 0) AS pending_tests
    FROM patients p
    LEFT JOIN patient_tests pt ON pt.patient_id = p.id
    LEFT JOIN tests t ON t.id = pt.test_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY
        p.id, p.receipt_id, p.patient_code, p.patient_date,
        p.patient_name, p.age, p.gender, p.phone
    ORDER BY p.patient_name ASC, p.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$newPatients = 0;
$oldPatients = 0;
$totalTests = 0;
$totalAmount = 0.00;

foreach ($rows as $row) {
    if ($row['patient_type'] === 'NEW') $newPatients++;
    else $oldPatients++;
    $totalTests += (int)$row['test_count'];
    $totalAmount += (float)$row['total_amount'];
}
$totalPatients = $newPatients + $oldPatients;

$pageTitle = 'Daily Report';
require_once __DIR__ . '/includes/header.php';
?>
<div class="portal-card">
    <div class="print-report-header">
        <div style="font-size:12pt;font-weight:600;margin-top:4px;">Date: <?php echo htmlspecialchars(formatDate($date)); ?></div>
        <div style="font-size:18pt;font-weight:800;margin-top:4px;">DAILY REPORT SUMMARY</div>
    </div>
    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px;flex-wrap:wrap;">
        <div>
            <h2 class="portal-card-title">Daily Report</h2>
        </div>
        <button type="button" onclick="window.print();" class="btn btn-outline">🖨️ Print</button>
    </div>

    <form method="GET" class="no-print" style="display:flex;align-items:end;gap:8px;margin-bottom:22px;flex-wrap:wrap;">
        <div><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>"></div>
        <button class="btn btn-primary" type="submit">Search</button>
    </form>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px;">
        <div style="border:1px solid var(--border-color);border-radius:10px;padding:16px;background:#f8fafc;">
            <div style="color:var(--text-muted);font-size:.85rem;">Total Patients</div>
            <strong style="font-size:1.4rem;"><?php echo $totalPatients; ?></strong>
        </div>
        <div style="border:1px solid var(--border-color);border-radius:10px;padding:16px;background:#f8fafc;">
            <div style="color:var(--text-muted);font-size:.85rem;">Total Tests</div>
            <strong style="font-size:1.4rem;"><?php echo $totalTests; ?></strong>
        </div>
        <div style="border:1px solid var(--border-color);border-radius:10px;padding:16px;background:#f8fafc;">
            <div style="color:var(--text-muted);font-size:.85rem;">Total Amount</div>
            <strong style="font-size:1.25rem;color:var(--success-green);"><?php echo formatCurrency($totalAmount); ?></strong>
        </div>
    </div>

    <div class="table-responsive">
        <table class="portal-table" style="border:1px solid var(--border-color);">
            <thead>
                <tr>
                    <th>Registration Date</th>
                    <th>Patient Name</th>
                    <th style="text-align:center;">Result Status</th>
                    <th style="text-align:right;">Total Amount</th>
                    <th class="no-print">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" style="text-align:center;padding:35px;color:var(--text-muted);">No patient records found for this date.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo formatDate($row['patient_date']); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['patient_name']); ?></strong></td>
                    <td style="text-align:center;font-size:.82rem;">
                        <?php if ((int)$row['pending_tests'] > 0): ?><span class="badge badge-pending"><?php echo (int)$row['pending_tests']; ?> PENDING</span><?php endif; ?>
                        <?php if ((int)$row['completed_tests'] > 0): ?><span class="badge badge-completed"><?php echo (int)$row['completed_tests']; ?> TEST</span><?php endif; ?>
                        <?php if ((int)$row['test_count'] === 0): ?><span style="color:#94a3b8;">NO TEST</span><?php endif; ?>
                    </td>
                    <td style="text-align:right;"><strong><?php echo formatCurrency($row['total_amount']); ?></strong></td>
                    <td class="no-print"><?php if ((int)$row['submitted'] === 1 && ($row['report_status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                            <a href="report.php?patient_id=<?php echo (int)$row['id']; ?>" title="Final Report" aria-label="Final Report" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#16a34a;color:#fff;text-decoration:none;font-size:18px;box-shadow:0 1px 3px rgba(0,0,0,.12);">📄</a>
                        <?php else: ?>
                            <a href="patient_tests.php?patient_id=<?php echo (int)$row['id']; ?>" title="Open Patient Tests" aria-label="Open Patient Tests" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#e2e8f0;color:#475569;text-decoration:none;font-size:18px;">📄</a>
                        <?php endif; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style media="print">
@page { size:A4 portrait; margin:6mm; }
.print-report-header { text-align:center; font-size:20pt; font-weight:800; line-height:1.2; margin:0 0 7mm; padding:0 0 3mm; border-bottom:1px solid #111; color:#111 !important; }
.portal-card > div[style*="display:grid"] { grid-template-columns:repeat(3,minmax(0,1fr)) !important; gap:5mm !important; margin-bottom:5mm !important; }
.portal-card > div[style*="display:grid"] > div { padding:3mm !important; border-radius:0 !important; background:#fff !important; border:1.5px solid #111 !important; color:#111 !important; }
.portal-card > div[style*="display:grid"] > div div, .portal-card > div[style*="display:grid"] > div strong { color:#111 !important; }
.portal-card > div[style*="display:grid"] strong { font-size:12pt !important; }
.portal-table th, .portal-table td { padding:2.2mm 2mm !important; font-size:9pt !important; color:#111 !important; border:1px solid #111 !important; }
.portal-table th { background:#222 !important; color:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
.portal-table { table-layout:auto; }
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
