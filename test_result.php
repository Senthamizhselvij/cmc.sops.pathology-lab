<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$patient_test_id = (int)($_GET['patient_test_id'] ?? 0);
if ($patient_test_id <= 0) { setFlash('error','Invalid test order reference.'); header('Location: dashboard.php'); exit(); }

$stmt=$pdo->prepare("SELECT pt.id AS patient_test_id,pt.patient_id,pt.test_id,pt.status,pt.result_date,pt.mx_inoculation_date,pt.mx_reading_date,pt.sputum_grade,p.receipt_id,p.patient_name,p.patient_code,p.age,p.gender,p.patient_date,p.report_date,p.phone,p.address,t.test_name FROM patient_tests pt JOIN patients p ON pt.patient_id=p.id JOIN tests t ON pt.test_id=t.id WHERE pt.id=?");
$stmt->execute([$patient_test_id]);
$order=$stmt->fetch();
if(!$order){setFlash('error','Test record not found.');header('Location: dashboard.php');exit();}

$stmtParams=$pdo->prepare("SELECT tp.id,tp.description,tp.unit,tp.reference_range,tr.result,tr.unit AS saved_unit,tr.reference_range AS saved_range FROM test_parameters tp JOIN tests t ON t.id=tp.test_id LEFT JOIN test_results tr ON tr.parameter_id=tp.id AND tr.patient_test_id=? WHERE tp.test_id=? ORDER BY CASE WHEN t.test_name='CBC' THEN CASE tp.description WHEN 'Hemoglobin' THEN 1 WHEN 'Total Leucocyte Count' THEN 2 WHEN 'Neutrophils (Poly)' THEN 3 WHEN 'Lymphocytes' THEN 4 WHEN 'Eosinophils' THEN 5 WHEN 'Monocytes' THEN 6 WHEN 'Basophils' THEN 7 WHEN 'Total RBC Count' THEN 8 WHEN 'PCV' THEN 9 WHEN 'MCV' THEN 10 WHEN 'MCH' THEN 11 WHEN 'MCHC' THEN 12 WHEN 'Platelet Count' THEN 13 ELSE 99 END ELSE 0 END, tp.id ASC");
$stmtParams->execute([$patient_test_id,$order['test_id']]);
$raw=$stmtParams->fetchAll();

// Load per-parameter result history so each test row shows when the current
// value was entered and earlier changes can be reviewed.
$historyByParam=[];
try {
    $stmtHistory=$pdo->prepare("SELECT parameter_id,old_result,new_result,reason,changed_by,changed_at FROM test_result_history WHERE patient_test_id=? ORDER BY changed_at DESC, id DESC");
    $stmtHistory->execute([$patient_test_id]);
    foreach($stmtHistory->fetchAll() as $h){ $historyByParam[(int)$h['parameter_id']][]=$h; }
} catch (Throwable $e) {
    $historyByParam=[];
}

$parameters=[];$seen=[];
foreach($raw as $param){
    $key=strtolower(trim($param['description']));
    if($key===''||isset($seen[$key])) continue;
    $seen[$key]=true;
    $parameters[]=$param;
}
$isMx = strcasecmp(trim((string)$order['test_name']), 'MX Test') === 0;
$isSputum = strcasecmp(trim((string)$order['test_name']), 'Sputum for AFB') === 0;
$isUrine = strcasecmp(trim((string)$order['test_name']), 'Urine (Routine / Microscopic)') === 0;
$isCbc = strcasecmp(trim((string)$order['test_name']), 'CBC') === 0;
$isHiv = strcasecmp(trim((string)$order['test_name']), 'HIV') === 0;
$isMalaria = strcasecmp(trim((string)$order['test_name']), 'Malaria Antigen') === 0;
$hideUnit = $isUrine || $isHiv || $isMalaria || $isMx || $isSputum;
$hideReference = $isUrine || $isMx || $isSputum;
$showUnit=false; foreach($parameters as $p){ if(!$hideUnit && trim((string)$p['unit'])!=='' && trim((string)$p['unit'])!=='-'){ $showUnit=true; break; } }
$colspan=($hideReference ? 2 : ($showUnit ? 4 : 3));

$pageTitle=$order['test_name'].' - Result';
require_once __DIR__.'/includes/header.php';
?>
<div class="portal-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:20px;">
        <div><h2 class="portal-card-title"><?php echo $isCbc ? 'COMPLETE BLOOD COUNT (CBC)' : ($isUrine ? 'URINE ROUTINE & MICROSCOPY' : ($isSputum ? 'SPUTUM EXAMINATION, AFB' : strtoupper(htmlspecialchars($order['test_name'])) . ' RESULT')); ?></h2><p class="portal-card-subtitle" style="margin-bottom:0;">Enter the result whenever it is received. Saved results can be edited later.</p></div>
        <a href="patient_tests.php?patient_id=<?php echo (int)$order['patient_id']; ?>" class="btn btn-outline">&larr; Back to Patient Tests</a>
    </div>

    <div class="patient-bio-grid">
        <div class="bio-item"><span class="bio-label">Receipt ID</span><span class="bio-value" style="color:var(--primary-blue);"><?php echo htmlspecialchars($order['receipt_id']); ?></span></div>
        <div class="bio-item"><span class="bio-label">Patient ID</span><span class="bio-value"><?php echo htmlspecialchars($order['patient_code']); ?></span></div>
        <div class="bio-item"><span class="bio-label">Patient Name</span><span class="bio-value"><?php echo htmlspecialchars($order['patient_name']); ?></span></div>
        <div class="bio-item"><span class="bio-label">Age / Gender</span><span class="bio-value"><?php echo (int)$order['age']; ?> Yrs / <?php echo htmlspecialchars($order['gender']); ?></span></div>
    </div>

    <form action="save_test_result.php" method="POST">
        <input type="hidden" name="patient_test_id" value="<?php echo (int)$order['patient_test_id']; ?>">

        <div style="margin:22px 0 18px;padding:16px 18px;background:#f8fafc;border:1px solid #dbe3ec;border-radius:8px;">
            <?php if($isMx): ?>
                <div style="font-size:1rem;font-weight:800;margin-bottom:14px;">TUBERCULIN TEST (0.1 ml = 10 TU)</div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label required" for="mx_inoculation_date">Date of inoculation</label>
                        <input type="date" name="mx_inoculation_date" id="mx_inoculation_date" class="form-control" value="<?php echo htmlspecialchars($order['mx_inoculation_date'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="mx_reading_date">Date of Reading</label>
                        <input type="date" name="mx_reading_date" id="mx_reading_date" class="form-control" value="<?php echo htmlspecialchars($order['mx_reading_date'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div style="font-size:.82rem;color:#64748b;margin-top:6px;">Enter both dates here. The reading date cannot be before the inoculation date.</div>
            <?php else: ?>
            <div class="form-group" style="max-width:420px;">
                <label class="form-label">Registration Date</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars(formatDate($order['patient_date'])); ?>" readonly style="background:#f1f5f9;">
            </div>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="portal-table" style="border:1px solid var(--border-color);">
                <thead><tr><th style="width:<?php echo $hideReference ? '68%' : ($showUnit ? '38%' : '40%'); ?>;">Test Description</th><th style="width:<?php echo $hideReference ? '32%' : ($showUnit ? '18%' : '18%'); ?>;">Result Value</th><?php if($isSputum): ?><th style="width:25%;">Grade</th><?php elseif($showUnit): ?><th style="width:12%;">Unit</th><?php endif; ?><?php if(!$hideReference): ?><th style="width:<?php echo $showUnit ? '32%' : '42%'; ?>;">Reference Range</th><?php endif; ?></tr></thead>
                <tbody>
                <?php if(!$parameters): ?><tr><td colspan="<?php echo $colspan; ?>" style="text-align:center;padding:25px;">No parameter description configured for this test.</td></tr>
                <?php else: $lastUrineHeading=''; foreach($parameters as $i=>$param): ?>
                    <?php
                    if($isUrine){
                        $d = strtolower(trim((string)$param['description']));
                        $heading = null;
                        if(in_array($d,['quantity','color','appearance'],true)) $heading='GROSS EXAMINATION';
                        elseif(in_array($d,['reaction (ph)','proteins','glucose'],true)) $heading='CHEMICAL EXAMINATION';
                        elseif(in_array($d,['rbc','pus cells','epithelial cells','casts','crystals'],true)) $heading='MICROSCOPIC EXAMINATION (PER HPF)';
                        elseif($d==='bile salts') $heading='OTHERS';
                        if($heading && ($i===0 || $lastUrineHeading !== $heading)) echo '<tr><td colspan="2" style="font-weight:800;font-style:italic;text-decoration:underline;padding:9px 10px 5px;background:#f8fafc;">'.htmlspecialchars($heading).'</td></tr>';
                        $lastUrineHeading=$heading;
                    }
                    if($isCbc):
                        $cbcDesc = trim((string)$param['description']);
                        if(strcasecmp($cbcDesc, 'Neutrophils (Poly)') === 0): ?>
                            <tr><td colspan="<?php echo $colspan; ?>" style="font-weight:800;font-style:italic;text-decoration:underline;padding:9px 10px 5px;background:#f8fafc;">DIFFERENTIAL LEUCOCYTE COUNT</td></tr>
                        <?php endif;
                    endif; ?>
                    <tr>
                        <td style="vertical-align:top;">
                            <strong><?php echo htmlspecialchars($param['description']); ?></strong>
                            <input type="hidden" name="param_ids[]" value="<?php echo (int)$param['id']; ?>">
                        </td>
                        <td style="vertical-align:top;">
                            <!-- Current result: shown once and directly editable. -->
                            <?php $activeRange = referenceRangeForGender((($param['saved_range'] ?? '') !== '') ? $param['saved_range'] : ($param['reference_range'] ?? ''), $order['gender'] ?? ''); $currentResult = $param['result'] ?? ''; $currentOutOfRange = isResultOutOfReferenceRange($currentResult, $activeRange); ?>
                            <input type="text" name="results[<?php echo (int)$param['id']; ?>]" class="form-control range-aware-result <?php echo $currentOutOfRange ? 'result-out-of-range' : 'result-normal'; ?>" data-reference-range="<?php echo htmlspecialchars($activeRange, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars($currentResult); ?>" placeholder="Enter result">
                            <div style="margin-top:8px;border:1px solid #dbe3ec;border-radius:6px;overflow:hidden;background:#fff;">
                                <div style="display:grid;grid-template-columns:minmax(110px,1fr) minmax(150px,1.4fr) 120px;gap:0;background:#f8fafc;font-size:.72rem;font-weight:800;color:#475569;">
                                    <div style="padding:6px 8px;border-right:1px solid #e2e8f0;">RESULT</div>
                                    <div style="padding:6px 8px;border-right:1px solid #e2e8f0;">REASON</div>
                                    <div style="padding:6px 8px;">DATE</div>
                                </div>
                                <div style="display:grid;grid-template-columns:minmax(110px,1fr) minmax(150px,1.4fr) 120px;gap:0;border-top:1px solid #e2e8f0;">
                                    <div style="padding:6px 7px;border-right:1px solid #e2e8f0;">
                                        <input type="text" name="change_result[<?php echo (int)$param['id']; ?>]" class="form-control range-aware-result result-normal" data-reference-range="<?php echo htmlspecialchars($activeRange, ENT_QUOTES); ?>" placeholder="Result">
                                    </div>
                                    <div style="padding:6px 7px;border-right:1px solid #e2e8f0;">
                                        <input type="text" name="change_reason[<?php echo (int)$param['id']; ?>]" class="form-control" placeholder="Reason">
                                    </div>
                                    <div style="padding:6px 7px;">
                                        <input type="date" name="change_date[<?php echo (int)$param['id']; ?>]" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                            <small style="display:block;margin-top:6px;color:#64748b;">The result can be entered/changed up to 3 times. Earlier values remain stored in the database.</small>
                        </td>
                        <?php if($isMx): ?>
                        <?php elseif($isSputum): ?>
                            <td><input type="text" name="sputum_grade" class="form-control" value="<?php echo htmlspecialchars($order['sputum_grade'] ?? ''); ?>" placeholder="Enter grade"></td>
                        <?php else: ?>
                            <?php if($showUnit): ?><td style="vertical-align:top;"><input type="text" name="units[<?php echo (int)$param['id']; ?>]" class="form-control" value="<?php echo htmlspecialchars($isCbc && in_array(trim((string)$param['description']), ['Neutrophils (Poly)','Lymphocytes','Eosinophils','Monocytes','Basophils'], true) ? '%' : ((($param['saved_unit'] ?? '') !== '') ? $param['saved_unit'] : $param['unit'])); ?>"></td><?php endif; ?>
                            <?php if(!$hideReference): ?><td style="vertical-align:top;"><input type="text" name="ranges[<?php echo (int)$param['id']; ?>]" class="form-control" value="<?php echo htmlspecialchars(referenceRangeForGender((($param['saved_range'] ?? '') !== '') ? $param['saved_range'] : $param['reference_range'], $order['gender'] ?? '')); ?>"></td><?php endif; ?>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:25px;display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
            <a href="patient_tests.php?patient_id=<?php echo (int)$order['patient_id']; ?>" class="btn btn-secondary">Back</a>
            <button type="submit" name="action" value="save" class="btn btn-success btn-lg">💾 Save</button>
            <?php if (($order['status'] ?? '') !== 'COMPLETED'): ?>
                <form action="cancel_patient_test.php" method="POST" style="margin:0;display:inline;" onsubmit="return confirm('Remove this test from the patient? This test has no saved result yet.');">
                    <input type="hidden" name="patient_test_id" value="<?php echo (int)$order['patient_test_id']; ?>">
                    <button type="submit" class="btn btn-danger btn-lg" style="background:#dc2626;color:#fff;border:1px solid #dc2626;">🗑 Remove</button>
                </form>
            <?php endif; ?>
        </div>
    </form>
</div>
<style>
    .range-aware-result.result-normal { color: #111827; font-weight: 400; }
    .range-aware-result.result-out-of-range { color: #111827; font-weight: 800; }
</style>
<script>
(function () {
    function outOfRange(value, range) {
        value = (value || '').trim();
        range = (range || '').trim();
        if (!value || !range) return false;
        var cleanRange = range.replace(/,/g, '').replace(/[−–—]/g, '-');
        var cleanValue = value.replace(/,/g, '');
        var interval = cleanRange.match(/^\s*([+-]?\d+(?:\.\d+)?)\s*(?:-|\bto\b)\s*([+-]?\d+(?:\.\d+)?)\s*$/i);
        var number = cleanValue.match(/[-+]?\d+(?:\.\d+)?/);
        if (interval && number) {
            var v = parseFloat(number[0]), a = parseFloat(interval[1]), b = parseFloat(interval[2]);
            return v < Math.min(a, b) || v > Math.max(a, b);
        }
        var limit = cleanRange.match(/^\s*(<=|>=|<|>|≤|≥)\s*([+-]?\d+(?:\.\d+)?)\s*$/);
        if (limit && number) {
            var n = parseFloat(number[0]), l = parseFloat(limit[2]);
            if (limit[1] === '<') return n >= l;
            if (limit[1] === '<=' || limit[1] === '≤') return n > l;
            if (limit[1] === '>') return n <= l;
            return n < l;
        }
        if (/\d/.test(range) || /[\/|,;]|\bor\b/i.test(range)) return false;
        if (/^[a-z ]+$/i.test(range) && /^[a-z ]+$/i.test(value)) return range.replace(/\s+/g, ' ').toLowerCase() !== value.replace(/\s+/g, ' ').toLowerCase();
        return false;
    }
    function update(input) {
        var abnormal = outOfRange(input.value, input.getAttribute('data-reference-range') || '');
        input.classList.toggle('result-out-of-range', abnormal);
        input.classList.toggle('result-normal', !abnormal);
    }
    document.querySelectorAll('.range-aware-result').forEach(function (input) {
        update(input);
        input.addEventListener('input', function () { update(input); });
    });
    document.querySelectorAll('input[name^="ranges["]').forEach(function (rangeInput) {
        rangeInput.addEventListener('input', function () {
            var row = rangeInput.closest('tr');
            if (!row) return;
            row.querySelectorAll('.range-aware-result').forEach(function (resultInput) {
                resultInput.setAttribute('data-reference-range', rangeInput.value);
                update(resultInput);
            });
        });
    });
})();
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
