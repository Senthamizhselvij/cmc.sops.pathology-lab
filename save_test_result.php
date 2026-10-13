<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit(); }
$patient_test_id=(int)($_POST['patient_test_id'] ?? 0);
$results=is_array($_POST['results'] ?? null)?$_POST['results']:[];
$change_reasons=is_array($_POST['change_reason'] ?? null)?$_POST['change_reason']:[];
$change_dates=is_array($_POST['change_date'] ?? null)?$_POST['change_date']:[];
$change_results=is_array($_POST['change_result'] ?? null)?$_POST['change_result']:[];
$units=is_array($_POST['units'] ?? null)?$_POST['units']:[];
$ranges=is_array($_POST['ranges'] ?? null)?$_POST['ranges']:[];
$report_date=date('Y-m-d');
$mx_inoculation_date=trim($_POST['mx_inoculation_date'] ?? '');
$mx_reading_date=trim($_POST['mx_reading_date'] ?? '');
$sputum_grade=trim($_POST['sputum_grade'] ?? '');
$action=($_POST['action'] ?? 'save') === 'report' ? 'report' : 'save';
if($patient_test_id<=0){setFlash('error','Invalid test order reference.');header('Location: dashboard.php');exit();}

$stmt=$pdo->prepare('SELECT pt.*,p.id AS patient_id,p.patient_date,p.submitted,t.test_name FROM patient_tests pt JOIN patients p ON pt.patient_id=p.id JOIN tests t ON pt.test_id=t.id WHERE pt.id=?');
$stmt->execute([$patient_test_id]);$order=$stmt->fetch();
if(!$order){setFlash('error','Test record not found.');header('Location: dashboard.php');exit();}
$patient_id=(int)$order['patient_id'];
$isMx = strcasecmp(trim((string)$order['test_name']), 'MX Test') === 0;
$isSputum = strcasecmp(trim((string)$order['test_name']), 'Sputum for AFB') === 0;
if($isMx){
    if($mx_inoculation_date==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$mx_inoculation_date) || $mx_reading_date==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$mx_reading_date)){
        setFlash('error','Please enter both Date of inoculation and Date of Reading.');
        header('Location: test_result.php?patient_test_id='.$patient_test_id); exit();
    }
    if($mx_reading_date < $mx_inoculation_date){
        setFlash('error','Date of Reading cannot be before Date of inoculation.');
        header('Location: test_result.php?patient_test_id='.$patient_test_id); exit();
    }
    // MX has no separate report-date field; use the reading date as its result date.
    $report_date=$mx_reading_date;
}

$stmtParams=$pdo->prepare('SELECT id,description,unit,reference_range FROM test_parameters WHERE test_id=? ORDER BY id ASC');
$stmtParams->execute([$order['test_id']]);$raw=$stmtParams->fetchAll();
$expected=[];$seen=[];
foreach($raw as $param){$key=strtolower(trim($param['description']));if($key===''||isset($seen[$key]))continue;$seen[$key]=true;$expected[]=$param;}

$entered=0;
foreach($expected as $param){
    $id=(int)$param['id'];
    $current=isset($results[$id])?trim((string)$results[$id]):'';
    $extra=isset($change_results[$id])?trim((string)$change_results[$id]):'';
    if($extra!=='') $current=$extra;
    if($current!=='') $entered++;
}

try{
    $pdo->beginTransaction();
    // Keep the current result editable, but preserve every result change in history.
    $currentStmt=$pdo->prepare('SELECT parameter_id,result,unit,reference_range FROM test_results WHERE patient_test_id=?');
    $currentStmt->execute([$patient_test_id]);
    $currentRows=[];
    foreach($currentStmt->fetchAll() as $cr){ $currentRows[(int)$cr['parameter_id']]=$cr; }

    $delete=$pdo->prepare('DELETE FROM test_results WHERE patient_test_id=?');
    $delete->execute([$patient_test_id]);
    $insert=$pdo->prepare('INSERT INTO test_results (patient_test_id,parameter_id,result,unit,reference_range) VALUES (?,?,?,?,?)');
    $historyInsert=$pdo->prepare('INSERT INTO test_result_history (patient_test_id,parameter_id,old_result,new_result,reason,changed_by,changed_at) VALUES (?,?,?,?,?,?,?)');
    $username=$_SESSION['admin_username'] ?? 'unknown';
    // A parameter may have at most 3 saved result values: initial result + 2 later changes.
    $historyCountStmt=$pdo->prepare('SELECT COUNT(*) FROM test_result_history WHERE patient_test_id=? AND parameter_id=?');
    foreach($expected as $param){
        $id=(int)$param['id'];
        $val=isset($results[$id])?trim((string)$results[$id]):'';
        $old=isset($currentRows[$id]) ? trim((string)$currentRows[$id]['result']) : '';
        $currentVal=$old;
        $historyCountStmt->execute([$patient_test_id,$id]);
        $historyCount=(int)$historyCountStmt->fetchColumn();
        // Older records may have a current result but no history row yet; count that
        // existing result as the first of the maximum three result entries.
        if($historyCount===0 && $old!=='') $historyCount=1;

        $extra=isset($change_results[$id])?trim((string)$change_results[$id]):'';
        $reason=isset($change_reasons[$id])?trim((string)$change_reasons[$id]):'';
        $entryDate=isset($change_dates[$id])?trim((string)$change_dates[$id]):date('Y-m-d');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$entryDate)) $entryDate=date('Y-m-d');

        // The top box remains editable. If it changes an existing value, the single
        // change row supplies the reason and date for that edit.
        if($val!=='' && $val!==$old){
            if($historyCount>=3) throw new RuntimeException('Maximum 3 result entries are allowed for '.$param['description'].'.');
            if($old!=='' && $reason==='') throw new RuntimeException('Please enter the reason for changing the existing result for '.$param['description'].'.');
            $historyInsert->execute([$patient_test_id,$id,($old!==''?$old:null),$val,($old===''?'Initial result':$reason),$username,$entryDate.' '.date('H:i:s')]);
            $historyCount++;
            $currentVal=$val;
        } elseif($val!==''){
            $currentVal=$val;
        }

        // One visible row is reused for the next change. It can be filled on the
        // next save, so the user can make up to 3 total result entries without
        // displaying multiple old-result rows.
        if($extra!=='' && $extra!==$currentVal){
            if($historyCount>=3) throw new RuntimeException('Maximum 3 result entries are allowed for '.$param['description'].'.');
            if($currentVal!=='' && $reason==='') throw new RuntimeException('Please enter the reason for changing the existing result for '.$param['description'].'.');
            $historyInsert->execute([$patient_test_id,$id,($currentVal!==''?$currentVal:null),$extra,($reason!==''?$reason:'Initial result entry'),$username,$entryDate.' '.date('H:i:s')]);
            $historyCount++;
            $currentVal=$extra;
        }
        $val=$currentVal;
        if($val==='') continue;
        $unit=(isset($units[$id])&&trim($units[$id])!=='')?trim($units[$id]):$param['unit'];
        $range=(isset($ranges[$id])&&trim($ranges[$id])!=='')?trim($ranges[$id]):$param['reference_range'];
        $insert->execute([$patient_test_id,$id,$val,$unit,$range]);
    }
    if($entered>0){
        if($isMx){
            $pdo->prepare("UPDATE patient_tests SET status='COMPLETED',result_date=?,completed_at=?,mx_inoculation_date=?,mx_reading_date=? WHERE id=?")->execute([$report_date,$report_date . ' 00:00:00',$mx_inoculation_date,$mx_reading_date,$patient_test_id]);
        }elseif($isSputum){
            $pdo->prepare("UPDATE patient_tests SET status='COMPLETED',result_date=?,completed_at=?,sputum_grade=? WHERE id=?")->execute([$report_date,$report_date . ' 00:00:00',$sputum_grade ?: null,$patient_test_id]);
        }else{
            $pdo->prepare("UPDATE patient_tests SET status='COMPLETED',result_date=?,completed_at=? WHERE id=?")->execute([$report_date,$report_date . ' 00:00:00',$patient_test_id]);
        }
        $message='Result saved successfully. You can edit it again later.';
    }else{
        if($isMx){
            $pdo->prepare("UPDATE patient_tests SET status='PENDING',result_date=NULL,completed_at=NULL,mx_inoculation_date=?,mx_reading_date=? WHERE id=?")->execute([$mx_inoculation_date ?: null,$mx_reading_date ?: null,$patient_test_id]);
        }elseif($isSputum){
            $pdo->prepare("UPDATE patient_tests SET status='PENDING',result_date=NULL,completed_at=NULL,sputum_grade=? WHERE id=?")->execute([$sputum_grade ?: null,$patient_test_id]);
        }else{
            $pdo->prepare("UPDATE patient_tests SET status='PENDING',result_date=NULL,completed_at=NULL WHERE id=?")->execute([$patient_test_id]);
        }
        $message='Test saved without a result. Enter the result later.';
    }
    $pdo->commit();
    calculateAndUpdatePatientTotals($pdo,$patient_id);
    auditLog($pdo,'SAVE TEST RESULT','Patient ID '.$patient_id.', Patient Test ID '.$patient_test_id.', Entered parameters: '.$entered);
    setFlash('success',$message);
    if($action === 'report'){
        header('Location: report.php?patient_id='.$patient_id);
    }else{
        header('Location: patient_tests.php?patient_id='.$patient_id);
    }
    exit();
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();setFlash('error',$e->getMessage());header('Location: test_result.php?patient_test_id='.$patient_test_id);exit();}catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();setFlash('error','Database error saving result: '.$e->getMessage());header('Location: test_result.php?patient_test_id='.$patient_test_id);exit();}
