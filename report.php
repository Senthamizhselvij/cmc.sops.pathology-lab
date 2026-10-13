<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$patient_id=(int)($_GET['patient_id'] ?? 0);
if($patient_id<=0){setFlash('error','Invalid patient report request.');header('Location: records.php');exit();}
$patient=getPatientById($pdo,$patient_id);
if(!$patient){setFlash('error','Patient record not found.');header('Location: records.php');exit();}

// Allow the referring doctor/name to be edited directly from the final report page.
if($_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['save_referred_by']) || isset($_POST['remove_referred_by']))){
    $referredBy = isset($_POST['remove_referred_by']) ? '' : trim($_POST['referred_by'] ?? '');
    if(mb_strlen($referredBy)>150){
        $referredBy=mb_substr($referredBy,0,150);
    }
    $stmtRef=$pdo->prepare('UPDATE patients SET referred_by=? WHERE id=?');
    $stmtRef->execute([$referredBy !== '' ? $referredBy : null,$patient_id]);
    setFlash('success', isset($_POST['remove_referred_by']) ? 'Referred By removed successfully.' : 'Referred By updated successfully.');
    header('Location: report.php?patient_id='.$patient_id);
    exit();
}
if(($patient['report_status'] ?? 'ACTIVE') === 'CANCELLED'){
    setFlash('info','This report is cancelled and is available only in Cancel Report.');
    header('Location: cancel_report.php');
    exit();
}

$stmt=$pdo->prepare("SELECT pt.id AS patient_test_id,pt.status,pt.completed_at,pt.result_date,pt.mx_inoculation_date,pt.mx_reading_date,pt.sputum_grade,t.id AS test_id,t.test_name FROM patient_tests pt JOIN tests t ON pt.test_id=t.id WHERE pt.patient_id=? ORDER BY pt.id ASC");
$stmt->execute([$patient_id]);$orderedTests=$stmt->fetchAll();

$testsWithResults=[];$testsWithoutResults=[];
// Patient report intentionally shows only the latest saved result. Result-change reason/date history remains in the database and is not printed.
foreach($orderedTests as $test){
    $r=$pdo->prepare("SELECT tp.id AS parameter_id,tp.description,COALESCE(NULLIF(tr.unit,''),tp.unit) AS unit,COALESCE(NULLIF(tr.reference_range,''),tp.reference_range) AS reference_range,tr.result FROM test_parameters tp JOIN tests t ON t.id=tp.test_id INNER JOIN test_results tr ON tr.parameter_id=tp.id AND tr.patient_test_id=? WHERE tp.test_id=? AND TRIM(COALESCE(tr.result,''))<>'' ORDER BY CASE WHEN t.test_name='CBC' THEN CASE tp.description WHEN 'Hemoglobin' THEN 1 WHEN 'Total Leucocyte Count' THEN 2 WHEN 'Neutrophils (Poly)' THEN 3 WHEN 'Lymphocytes' THEN 4 WHEN 'Eosinophils' THEN 5 WHEN 'Monocytes' THEN 6 WHEN 'Basophils' THEN 7 WHEN 'Total RBC Count' THEN 8 WHEN 'PCV' THEN 9 WHEN 'MCV' THEN 10 WHEN 'MCH' THEN 11 WHEN 'MCHC' THEN 12 WHEN 'Platelet Count' THEN 13 ELSE 99 END ELSE 0 END, tp.id ASC");
    $r->execute([$test['patient_test_id'],$test['test_id']]);$raw=$r->fetchAll();
    foreach ($raw as &$rangeRow) { $rangeRow['reference_range'] = referenceRangeForGender($rangeRow['reference_range'] ?? '', $patient['gender'] ?? ''); $rangeRow['result_out_of_range'] = isResultOutOfReferenceRange($rangeRow['result'] ?? '', $rangeRow['reference_range'] ?? ''); } unset($rangeRow);
    $historyByParam=[];
    $params=[];$seen=[];
    foreach($raw as $param){$key=strtolower(trim($param['description']));if($key===''||isset($seen[$key]))continue;$seen[$key]=true;$params[]=$param;}
    if($params){
        $test['parameters']=$params;
        $test['category']=getTestCategory($test['test_name']);
        $test['is_mx']=strcasecmp(trim((string)$test['test_name']),'MX Test')===0;
        $test['is_sputum']=strcasecmp(trim((string)$test['test_name']),'Sputum for AFB')===0;
        $test['is_urine']=strcasecmp(trim((string)$test['test_name']),'Urine (Routine / Microscopic)')===0;
        $test['is_cbc']=strcasecmp(trim((string)$test['test_name']),'CBC')===0;
        $test['is_hiv']=strcasecmp(trim((string)$test['test_name']),'HIV')===0;
        $test['is_malaria']=strcasecmp(trim((string)$test['test_name']),'Malaria Antigen')===0;
        $test['hide_unit']=$test['is_urine']||$test['is_hiv']||$test['is_malaria'];
        $test['hide_reference']=$test['is_urine'];
        $test['show_unit']=false;
        foreach($params as $pp){if(!$test['hide_unit']&&trim((string)$pp['unit'])!==''&&trim((string)$pp['unit'])!=='-'){$test['show_unit']=true;break;}}
        $testsWithResults[]=$test;
    }
    else{$testsWithoutResults[]=$test;}
}
$grouped=[];foreach($testsWithResults as $test)$grouped[$test['category']][]=$test;
$hasPending=!empty($testsWithoutResults);

$pageTitle='Patient Report - '.$patient['receipt_id'];
require_once __DIR__ . '/includes/header.php';
?>
<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:10px;flex-wrap:wrap;">
    <div><a href="patient_tests.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline">&larr; Patient Tests</a> <a href="records.php" class="btn btn-outline">All Records</a></div>
    <?php if((int)$patient['submitted'] === 1 && ($patient['report_status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button type="button" onclick="openPrintLayoutEditor();" class="btn btn-outline btn-lg">✏️ EDIT PRINT LAYOUT</button>
            <button type="button" onclick="window.print();" class="btn btn-primary btn-lg">🖨️ PRINT REPORT</button>
            <button type="button" onclick="openCancelReportModal();" class="btn btn-danger btn-lg">✖ CANCEL REPORT</button>
        </div>
    <?php endif; ?>
</div>

<div id="printLayoutEditor" class="no-print" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.58);z-index:10000;align-items:center;justify-content:center;padding:16px;">
 <div style="background:#fff;color:#111;width:min(720px,100%);max-height:90vh;overflow:auto;border-radius:14px;padding:22px;box-shadow:0 20px 60px rgba(0,0,0,.3)">
  <h2 style="margin:0 0 6px">Edit Print Layout</h2><p style="margin:0 0 18px;color:#475569">These settings are saved in this browser and reused for future patient reports.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px">
   <label>Paper size<select id="plPaper" class="form-control"><option value="A4">A4</option><option value="A5">A5</option><option value="Letter">Letter</option></select></label>
   <label>Orientation<select id="plOrientation" class="form-control"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></label>
   <label>Print scale (%)<input id="plScale" class="form-control" type="number" min="50" max="100" step="5"></label>
   <label>Margins<select id="plMargins" class="form-control"><option value="small">Small</option><option value="normal">Normal</option><option value="large">Large</option></select></label>
   <label>Font size<select id="plFont" class="form-control"><option value="small">Small</option><option value="normal">Normal</option><option value="large">Large</option></select></label>
   <label>Test spacing<select id="plSpacing" class="form-control"><option value="compact">Compact</option><option value="normal">Normal</option><option value="wide">Wide</option></select></label>
  </div>
  <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:22px">
   <button type="button" class="btn btn-outline" onclick="resetPrintLayoutSettings()">Reset defaults</button><button type="button" class="btn btn-outline" onclick="closePrintLayoutEditor()">Cancel</button><button type="button" class="btn btn-primary" onclick="savePrintLayoutSettings()">Save for all reports</button>
  </div>
 </div>
</div>
<script>
(function(){
 const key='pathologyPatientPrintLayoutV1';
 const defaults={paper:'A4',orientation:'portrait',scale:90,margins:'small',font:'normal',spacing:'compact'};
 window.getPrintLayoutSettings=function(){try{return {...defaults,...JSON.parse(localStorage.getItem(key)||'{}')}}catch(e){return defaults}};
 window.applyPrintLayoutSettings=function(){const s=getPrintLayoutSettings();let style=document.getElementById('savedPrintLayoutCSS');if(!style){style=document.createElement('style');style.id='savedPrintLayoutCSS';document.head.appendChild(style)}
 const margin={small:'6mm 6mm 29mm 6mm',normal:'10mm 10mm 32mm 10mm',large:'15mm 15mm 35mm 15mm'}[s.margins]||'6mm 6mm 29mm 6mm';
 const font={small:'7.5pt',normal:'9pt',large:'10.5pt'}[s.font]||'9pt';const gap={compact:'6px',normal:'12px',wide:'20px'}[s.spacing]||'6px';
 const scale=Math.max(50,Math.min(100,Number(s.scale)||90))/100;
 style.textContent='@media print{@page{size:'+s.paper+' '+s.orientation+';margin:'+margin+'}.pathology-report{font-size:'+font+'!important;zoom:'+scale+'}.pathology-report .test-block{margin-bottom:'+gap+'!important}.pathology-report table td,.pathology-report table th{font-size:'+font+'!important}}';
 };
 window.openPrintLayoutEditor=function(){const s=getPrintLayoutSettings();[['plPaper','paper'],['plOrientation','orientation'],['plScale','scale'],['plMargins','margins'],['plFont','font'],['plSpacing','spacing']].forEach(([id,k])=>document.getElementById(id).value=s[k]);document.getElementById('printLayoutEditor').style.display='flex'};
 window.closePrintLayoutEditor=function(){document.getElementById('printLayoutEditor').style.display='none'};
 window.savePrintLayoutSettings=function(){const s={paper:document.getElementById('plPaper').value,orientation:document.getElementById('plOrientation').value,scale:Math.max(50,Math.min(100,Number(document.getElementById('plScale').value)||90)),margins:document.getElementById('plMargins').value,font:document.getElementById('plFont').value,spacing:document.getElementById('plSpacing').value};localStorage.setItem(key,JSON.stringify(s));applyPrintLayoutSettings();closePrintLayoutEditor();alert('Print layout settings saved for future patient reports in this browser. Set the same scale in the browser print dialog if needed.')};
 window.resetPrintLayoutSettings=function(){localStorage.removeItem(key);openPrintLayoutEditor()};
 applyPrintLayoutSettings();window.addEventListener('beforeprint',applyPrintLayoutSettings);
})();
</script>

<?php if((int)$patient['submitted'] === 1 && ($patient['report_status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
<div id="cancelReportModal" class="no-print" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="width:min(560px,100%);background:#fff;border-radius:12px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="font-size:1.15rem;font-weight:800;color:#991b1b;margin-bottom:6px;">Cancel Final Report</div>
        <div style="font-size:.88rem;color:#64748b;margin-bottom:16px;">The report will be cancelled and you will be taken directly to the Cancel Report list. The cancellation reason is required.</div>
        <form action="cancel_patient_report.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this final report?');">
            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
            <label for="cancel_reason" class="form-label required">Reason for Cancellation</label>
            <textarea name="cancel_reason" id="cancel_reason" class="form-control" rows="4" maxlength="255" required placeholder="Enter why this report is being cancelled..."></textarea>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:15px;">
                <button type="button" onclick="closeCancelReportModal();" class="btn btn-outline">Back</button>
                <button type="submit" class="btn btn-danger">Cancel Report</button>
            </div>
        </form>
    </div>
</div>
<script>
function openCancelReportModal(){
    const m=document.getElementById('cancelReportModal');
    if(m){m.style.display='flex'; setTimeout(()=>document.getElementById('cancel_reason')?.focus(),50);}
}
function closeCancelReportModal(){
    const m=document.getElementById('cancelReportModal');
    if(m){m.style.display='none';}
}
</script>
<?php endif; ?>


<style>
/* Multi-page patient report printing:
   - lets a long report flow naturally onto page 2, 3, etc.
   - prevents individual test tables/sections from being split where possible
   - keeps each printed sheet clean and sequential for stapling
*/

.report-letterhead{position:relative;text-align:center;border-bottom:2px solid #111;padding:0 145px 16px 0;min-height:185px;color:#111;}
.report-society{font-family:Georgia,'Times New Roman',serif;font-size:1.75rem;font-weight:900;letter-spacing:.2px;border-bottom:1px solid #111;display:inline-block;padding-bottom:3px;}
.report-founded{font-family:Georgia,'Times New Roman',serif;font-size:.82rem;font-style:italic;font-weight:700;margin-top:5px;}
.report-hindi{font-family:'Noto Sans Devanagari','Mangal',sans-serif;font-size:1.55rem;font-weight:800;margin-top:7px;}
.report-clinic{font-family:Georgia,'Times New Roman',serif;font-size:1.15rem;font-weight:800;margin-top:4px;}
.report-subtitle,.report-address,.report-phone{font-family:Georgia,'Times New Roman',serif;font-size:.82rem;line-height:1.25;margin-top:3px;text-align:center;}
.report-lalaji{position:absolute;right:8px;top:0;width:118px;height:135px;object-fit:contain;filter:grayscale(100%);}
.report-title-box{width:max-content;max-width:100%;margin:14px auto 16px;border:2px solid #111;padding:7px 38px;font-family:Georgia,'Times New Roman',serif;font-size:1.45rem;font-weight:900;letter-spacing:.3px;}
.report-patient-box{border:1px solid #222;margin-bottom:18px;padding:0;font-size:.79rem;line-height:1.5;color:#111;overflow:hidden;}
/* Patient details are intentionally two separate rows. */
.patient-bio-grid{display:block !important;width:100%;}
.report-patient-line{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-items:center;column-gap:14px;row-gap:8px;white-space:normal;width:100%;min-width:0;box-sizing:border-box;padding:10px 12px;}
.report-patient-line>span{display:flex;align-items:flex-start;gap:4px;min-width:0;width:100%;white-space:normal;overflow:visible;overflow-wrap:anywhere;box-sizing:border-box;font-size:.78rem;}
.report-patient-line>span strong{flex:0 0 auto;}
.report-patient-line>span:not(.report-address-inline){text-overflow:unset;}
.report-patient-line .report-address-inline{overflow:visible;text-overflow:unset;}
.report-patient-line .report-date-inline{min-width:0;overflow:visible;white-space:normal;}
.report-patient-line-second{grid-template-columns:none !important;overflow:visible !important;}
.report-address-inline{min-width:0;overflow:hidden;text-overflow:ellipsis;}
.report-patient-line-second{display:flex !important;align-items:center;justify-content:flex-start;width:100%;margin:0;padding:10px 12px;border-top:1px solid #d1d5db;box-sizing:border-box;background:#fafafa;}
.report-ref-field{display:flex !important;align-items:center;gap:12px;flex:1 1 auto;width:100%;min-width:0;overflow:visible !important;box-sizing:border-box;}
.report-ref-field > strong{flex:0 0 auto;min-width:95px;}
.report-ref-field form{display:flex !important;align-items:center;gap:8px;flex-wrap:nowrap;flex:1 1 auto;min-width:0;}
.report-ref-editor{vertical-align:middle;display:inline-flex !important;align-items:center;gap:8px;flex:1 1 auto;min-width:0;}
.report-ref-editor input{flex:0 1 420px;min-width:220px;max-width:420px;}
.print-only{display:none;}
.report-signatures{display:flex;justify-content:flex-start;margin-top:48px;padding:0 45px;}
.report-signature-block{width:250px;text-align:center;font-family:Georgia,'Times New Roman',serif;font-size:.86rem;color:#111;}
.technician-signature-image{display:block;width:145px;height:62px;object-fit:contain;margin:0 auto 2px;}
.signature-line{border-top:1px solid #111;margin:2px 0 6px;}
.report-signature-block small{font-size:.76rem;}

@media (max-width: 900px) {
    .report-patient-line{grid-template-columns:repeat(2,minmax(0,1fr));}
}
@media (max-width: 520px) {
    .report-patient-line{grid-template-columns:minmax(0,1fr);}
}

@media print {
    @page {
        size: A4 portrait;
        /* Reserve a consistent footer band for signatures on every printed page. */
        margin: 6mm 6mm 29mm 6mm;
    }

    html, body {
        width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    .pathology-report {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: 0 !important;
    }

    .pathology-report .patient-bio-grid,
    .pathology-report .test-block,
    .pathology-report .pathology-result-table {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    /* Keep a test title, its date/details, result table and notes together. */
    .pathology-report .test-block {
        display: block !important;
        margin: 0 0 7px !important;
        /* Move a whole test to the next page instead of leaving its final row alone. */
        break-inside: avoid-page !important;
        page-break-inside: avoid !important;
    }
    .pathology-report .test-block table,
    .pathology-report .test-block tbody,
    .pathology-report .test-block tr,
    .pathology-report .test-block th,
    .pathology-report .test-block td {
        break-inside: avoid-page !important;
        page-break-inside: avoid !important;
    }
    .pathology-report .test-block > div:first-child {
        break-after: avoid-page !important;
        page-break-after: avoid !important;
    }
    .pathology-report .test-block + .test-block {
        break-before: auto !important;
        page-break-before: auto !important;
    }
    .pathology-report .pathology-result-table tr {
        orphans: 2;
        widows: 2;
    }

    .pathology-report table {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        border-collapse: collapse !important;
    }

    .pathology-report thead {
        display: table-header-group;
    }

    .pathology-report tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    /* Compact spacing lets complete tests flow continuously without leaving a tiny final row. */
    .pathology-report .test-block {
        margin: 0 0 5px !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
    .pathology-report .test-block table {
        margin-top: 0 !important;
        margin-bottom: 5px !important;
        width: 100% !important;
    }
    .pathology-report .test-block > div { break-inside: avoid; page-break-inside: avoid; }
    .pathology-report .test-block > div:first-child { padding-top: 4px !important; padding-bottom: 4px !important; }
    .pathology-report > div[style*="margin-top:24px"] {
        margin-top: 6px !important;
        break-inside: auto !important;
        page-break-inside: auto !important;
    }
    .pathology-report .test-block td,
    .pathology-report .test-block th { padding: 3px 6px !important; line-height: 1.15 !important; }

    /* Fixed print footer repeats the lab technician signature on each printed page. */
    .pathology-report .report-signatures {
        position: fixed !important;
        left: 6mm !important;
        right: 6mm !important;
        bottom: 4mm !important;
        width: auto !important;
        box-sizing: border-box !important;
        display: flex !important;
        justify-content: flex-start !important;
        align-items: flex-end !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        margin: 0 !important;
        padding: 0 10mm !important;
        gap: 0 !important;
        background: #fff !important;
        z-index: 9999 !important;
    }
    .pathology-report .report-signature-block {
        width: 65mm !important;
        max-width: 65mm !important;
        font-size: 8pt !important;
        line-height: 1.15 !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
    .pathology-report .technician-signature-image {
        width: 38mm !important;
        height: 16mm !important;
        object-fit: contain !important;
    }
    .pathology-report .signature-line { margin: 1mm 0 1.5mm !important; }
    .pathology-report .report-signature-block small { font-size: 7pt !important; }
    .pathology-report .report-patient-box {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .no-print,
    .no-print * {
        display: none !important;
    }

    .print-only { display: inline !important; }
    .report-patient-line { display:grid !important; grid-template-columns:repeat(3,minmax(0,1fr)) !important; column-gap:10px !important; row-gap:6px !important; white-space:normal !important; overflow:visible !important; }
    .report-patient-line > span { display:flex !important; white-space:normal !important; overflow:visible !important; overflow-wrap:anywhere !important; font-size:9pt !important; }
    .report-patient-line .report-date-inline { display:flex !important; visibility:visible !important; overflow:visible !important; white-space:normal !important; }
    .report-patient-line-second .no-print { display:none !important; }
    .report-patient-line-second .print-only { display:inline !important; }
    .report-letterhead { min-height: 165px; padding-right: 130px; border-bottom: 1.5px solid #111; }
    .report-society { font-size: 1.55rem; }
    .report-hindi { font-size: 1.35rem; }
    .report-clinic { font-size: 1.02rem; }
    .report-lalaji { width: 105px; height: 120px; right: 4px; }
    .report-title-box { font-size: 1.3rem; padding: 6px 32px; margin: 12px auto 14px; }
    .report-patient-box { font-size: .72rem; padding: 0; }
    .report-patient-line { grid-template-columns:1.15fr 1.3fr .72fr .82fr 1.1fr .85fr; column-gap:5px; font-size:.64rem; padding:6px 8px; }
    .report-patient-line-second { display:flex !important; padding:6px 8px; border-top:1px solid #999; }
    .report-patient-line>span{gap:3px;}
    .report-ref-editor input{flex:0 1 300px;min-width:180px;max-width:300px;}
    .report-ref-field { flex: 1; }
    .report-signatures { margin-top: 42px; padding: 0 20px; gap: 55px; }
    .report-signature-block { width: 220px; }
}
</style>

<div class="portal-card pathology-report" style="position:relative;box-shadow:0 4px 20px rgba(0,0,0,.06);padding:28px 30px 34px;background:#fff;">
    <div class="report-letterhead">
        <div class="report-society">SERVANTS OF THE PEOPLE SOCIETY</div>
        <div class="report-founded">Founded by Punjab Kesari Lala Lajpat Rai in year 1921</div>
        <div class="report-hindi">लोक सेवक मंडल</div>
        <div class="report-clinic">Major Gen. B. N. Bhandari Charitable Medical Clinic</div>
        <div class="report-subtitle">Servants of the People Society</div>
        <div class="report-address">Lajpat Bhawan, Lajpat Nagar IV, New Delhi 110024</div>
        <div class="report-phone">Tel No.: +91 11-43678155</div>
        <img src="assets/images/lalajinew21.png" alt="Lala Lajpat Rai" class="report-lalaji">
    </div>

    <div class="report-title-box">PATHOLOGY TEST REPORT</div>

    <?php $reportDate = $patient['report_date'] ?? ''; ?>
    <div class="report-patient-box patient-bio-grid">
        <div class="report-patient-line">
            <span><strong>Name:</strong> <?php echo htmlspecialchars($patient['patient_name']); ?></span>
            <span><strong>Receipt ID:</strong> <?php echo htmlspecialchars($patient['receipt_id']); ?></span>
            <span><strong>Age:</strong> <?php echo (int)$patient['age']; ?> Yrs</span>
            <span><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></span>
            <span class="report-address-inline"><strong>Address:</strong> <?php echo htmlspecialchars($patient['address'] ?? ''); ?></span>
            <span class="report-date-inline"><strong>Report Date:</strong> <?php echo $reportDate !== '' ? htmlspecialchars(formatDate($reportDate)) : 'Not set'; ?></span>
        </div>
        <div class="report-patient-line report-patient-line-second">
            <span class="report-ref-field"><strong>Referred By:</strong>
                <span class="no-print report-ref-editor">
                    <form method="POST" action="report.php?patient_id=<?php echo $patient_id; ?>" style="display:inline-flex;align-items:center;gap:8px;margin:0;">
                        <input type="text" name="referred_by" value="<?php echo htmlspecialchars($patient['referred_by'] ?? ''); ?>" maxlength="150" class="form-control" style="width:260px;min-height:34px;padding:6px 9px;display:inline-block;" placeholder="Enter referring doctor/name">
                        <button type="submit" name="save_referred_by" value="1" class="btn btn-sm btn-primary">Save</button>
                        <button type="submit" name="remove_referred_by" value="1" class="btn btn-sm btn-danger" onclick="return confirm('Remove Referred By for this patient?');">Remove</button>
                    </form>
                </span>
                <span class="print-only"><?php echo htmlspecialchars($patient['referred_by'] ?? ''); ?></span>
            </span>
        </div>
    </div>

    <?php if(!$orderedTests): ?>
        <div style="padding:30px;text-align:center;color:var(--text-muted);border:1px solid #e2e8f0;">No laboratory tests have been added.</div>
    <?php endif; ?>

    <?php if($hasPending): ?>
        <div style="margin-top:22px;">
            <div style="text-align:center;font-size:1.05rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;">TESTS ORDERED</div>
            <div style="border:1px solid #cbd5e1;padding:16px;background:#f8fafc;">
                <?php foreach($testsWithoutResults as $test): ?><div style="font-weight:700;padding:8px 4px;border-bottom:1px dashed #cbd5e1;"><?php echo htmlspecialchars(strtoupper($test['test_name'])); ?></div><?php endforeach; ?>
            </div>
            <div style="font-size:.82rem;color:#64748b;margin-top:8px;">Results are pending. The detailed test descriptions will appear automatically after results are entered.</div>
        </div>
    <?php endif; ?>

    <?php foreach($grouped as $category=>$categoryTests): ?>
        <div style="margin-top:24px;">
            <div style="text-align:center;font-size:1.1rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;"><?php echo htmlspecialchars($category); ?></div>
            <?php foreach($categoryTests as $test): ?>
                <div class="test-block">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 12px;background:#f8fafc;border:1px solid #cbd5e1;border-bottom:0;font-weight:800;">
                    <span><?php echo $test['is_cbc'] ? 'COMPLETE BLOOD COUNT (CBC)' : htmlspecialchars(strtoupper($test['test_name'])); ?></span>
                </div>
                <?php if(!empty($test['is_mx'])): ?>
                    <div style="border:1px solid #cbd5e1;border-bottom:0;padding:12px 16px;background:#fff;font-size:.92rem;">
                        <div><strong>Date of inoculation:</strong> <?php echo !empty($test['mx_inoculation_date']) ? htmlspecialchars(formatDate($test['mx_inoculation_date'])) : ''; ?></div>
                        <div style="margin-top:6px;"><strong>Date of Reading:</strong> <?php echo !empty($test['mx_reading_date']) ? htmlspecialchars(formatDate($test['mx_reading_date'])) : ''; ?></div>
                    </div>
                    <table class="portal-table pathology-result-table" style="border:2px solid #1f2937;margin-bottom:20px;">
                        <thead><tr><th style="width:55%;">TEST DESCRIPTION</th><th style="width:45%;">RESULT</th></tr></thead>
                        <tbody>
                        <?php foreach($test['parameters'] as $param): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($param['description']); ?></td>
                                <td style="font-weight:<?php echo (!empty($param['result_out_of_range']) || isResultOutOfReferenceRange($param['result'] ?? '', $param['reference_range'] ?? '')) ? '800' : '400'; ?>;color:#111827;"><?php echo htmlspecialchars($param['result']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif(!empty($test['is_sputum'])): ?>
                    <table class="portal-table pathology-result-table" style="border:2px solid #1f2937;margin-bottom:8px;">
                        <thead><tr><th style="width:45%;">TEST DESCRIPTION</th><th style="width:30%;">RESULT</th><th style="width:25%;">GRADE</th></tr></thead>
                        <tbody>
                        <?php foreach($test['parameters'] as $param): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($param['description']); ?></td>
                                <td style="font-weight:<?php echo (!empty($param['result_out_of_range']) || isResultOutOfReferenceRange($param['result'] ?? '', $param['reference_range'] ?? '')) ? '800' : '400'; ?>;color:#111827;"><?php echo htmlspecialchars($param['result']); ?></td>
                                <td style="font-weight:700;"><?php echo htmlspecialchars($test['sputum_grade'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="font-size:.82rem;color:#475569;margin:0 0 20px 4px;"><strong>NOTE:</strong> Result is dependent on the quality of specimen submitted.</div>
                <?php else: ?>
                    <table class="portal-table pathology-result-table" style="border:2px solid #1f2937;margin-bottom:20px;">
                        <thead><tr><th style="width:<?php echo $test['hide_reference'] ? '70%' : ($test['show_unit'] ? '38%' : '40%'); ?>;">TEST DESCRIPTION</th><th style="width:<?php echo $test['hide_reference'] ? '30%' : '18%'; ?>;">RESULT</th><?php if($test['show_unit']): ?><th style="width:12%;">UNIT</th><?php endif; ?><?php if(!$test['hide_reference']): ?><th style="width:<?php echo $test['show_unit'] ? '38%' : '42%'; ?>;">REFERENCE RANGE</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php $lastUrineHeading=''; foreach($test['parameters'] as $param): ?>
                            <?php
                            if(!empty($test['is_urine'])){
                                $d=strtolower(trim((string)$param['description'])); $heading=null;
                                if(in_array($d,['quantity','color','appearance'],true)) $heading='GROSS EXAMINATION';
                                elseif(in_array($d,['reaction (ph)','proteins','glucose'],true)) $heading='CHEMICAL EXAMINATION';
                                elseif(in_array($d,['rbc','pus cells','epithelial cells','casts','crystals'],true)) $heading='MICROSCOPIC EXAMINATION (PER HPF)';
                                elseif($d==='bile salts') $heading='OTHERS';
                                if($heading && $lastUrineHeading!==$heading) echo '<tr><td colspan="2" style="font-weight:800;font-style:italic;text-decoration:underline;padding:9px 10px 5px;background:#f8fafc;">'.htmlspecialchars($heading).'</td></tr>';
                                $lastUrineHeading=$heading;
                            }
                            if(!empty($test['is_cbc'])):
                                $cbcDesc = trim((string)$param['description']);
                                if(strcasecmp($cbcDesc, 'Neutrophils (Poly)') === 0): ?>
                                    <tr><td colspan="<?php echo $test['show_unit'] ? '4' : '3'; ?>" style="font-weight:800;font-style:italic;text-decoration:underline;padding:9px 10px 5px;background:#f8fafc;">DIFFERENTIAL LEUCOCYTE COUNT</td></tr>
                                <?php endif;
                            endif; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($param['description']); ?></td>
                                <td style="font-weight:<?php echo (!empty($param['result_out_of_range']) || isResultOutOfReferenceRange($param['result'] ?? '', $param['reference_range'] ?? '')) ? '800' : '400'; ?>;color:#111827;"><?php echo htmlspecialchars($param['result']); ?></td>
                                <?php if($test['show_unit']): ?><td><?php echo htmlspecialchars((!empty($test['is_cbc']) && in_array(trim((string)$param['description']), ['Neutrophils (Poly)','Lymphocytes','Eosinophils','Monocytes','Basophils'], true)) ? '%' : $param['unit']); ?></td><?php endif; ?>
                                <?php if(!$test['hide_reference']): ?><td><?php echo htmlspecialchars($param['reference_range']); ?></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div><!-- /.test-block: keep each complete test on one page -->
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="report-signatures">
        <div class="report-signature-block">
            <img class="technician-signature-image" src="assets/images/amitrawat-digital-signature.png" alt="Amitrawat digital signature">
            <div class="signature-line"></div>
            <strong>Amitrawat</strong><br>
            <small>(Lab Technician)</small>
        </div>
    </div>
</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
