
<?php
/** Cancelled Report - only patients whose final report was cancelled. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$date=trim($_GET['date']??'');$patientName=trim($_GET['patient_name']??'');$receiptId=trim($_GET['receipt_id']??'');
$where=["p.report_status='CANCELLED'"]; $params=[];
if($date!==''){if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){ $where[]='DATE(p.cancelled_at)=?';$params[]=$date; } else $date='';}
if($patientName!==''){ $where[]='p.patient_name LIKE ?';$params[]='%'.$patientName.'%'; }
if($receiptId!==''){ $where[]='p.receipt_id LIKE ?';$params[]='%'.$receiptId.'%'; }
$sql="SELECT p.id,p.receipt_id,p.patient_date,p.report_date,p.patient_name,p.age,p.gender,p.phone,p.test_count,p.total_amount,p.cancelled_at,p.cancelled_by,p.cancel_reason
FROM patients p WHERE ".implode(' AND ',$where)." ORDER BY p.cancelled_at DESC,p.id DESC";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
$pageTitle='Cancel Report';require_once __DIR__.'/includes/header.php';
?>
<div class="portal-card">
<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px;flex-wrap:wrap;"><div><h2 class="portal-card-title">Cancel Report</h2><p class="portal-card-subtitle">Cancelled final patient reports. Patient, tests and results are retained.</p></div><button type="button" onclick="window.print();" class="btn btn-outline">🖨️ Print</button></div>
<form method="GET" class="no-print" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:22px;"><div><label class="form-label">Cancelled Date</label><input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>"></div><div><label class="form-label">Patient Name</label><input type="text" name="patient_name" class="form-control" value="<?php echo htmlspecialchars($patientName); ?>"></div><div><label class="form-label">Receipt ID</label><input type="text" name="receipt_id" class="form-control" value="<?php echo htmlspecialchars($receiptId); ?>"></div><div style="display:flex;align-items:end;gap:8px;"><button class="btn btn-primary" type="submit">Search</button><a href="cancel_report.php" class="btn btn-outline">Clear</a></div></form>
<div class="table-responsive"><table class="portal-table" style="border:1px solid var(--border-color);"><thead><tr><th>Receipt ID</th><th>Registration Date</th><th>Report Date</th><th>Patient Name</th><th>Tests</th><th style="text-align:right;">Amount</th><th>Cancelled At</th><th>Cancelled By</th><th>Reason</th><th class="no-print">Action</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="10" style="text-align:center;padding:35px;color:var(--text-muted);">No cancelled reports found.</td></tr><?php else: foreach($rows as $row): ?><tr><td><strong style="color:#991b1b;"><?php echo htmlspecialchars($row['receipt_id']); ?></strong></td><td><?php echo formatDate($row['patient_date']); ?></td><td><?php echo formatDate($row['report_date']); ?></td><td><strong><?php echo htmlspecialchars($row['patient_name']); ?></strong></td><td style="text-align:center;"><?php echo (int)$row['test_count']; ?></td><td style="text-align:right;"><?php echo formatCurrency($row['total_amount']); ?></td><td><?php echo $row['cancelled_at'] ? date('d-m-Y H:i',strtotime($row['cancelled_at'])) : '-'; ?></td><td><?php echo htmlspecialchars($row['cancelled_by']??'-'); ?></td><td><?php echo htmlspecialchars($row['cancel_reason']??'-'); ?></td><td class="no-print"><span class="badge" style="background:#fee2e2;color:#991b1b;">CANCELLED</span></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
