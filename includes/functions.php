<?php
/**
 * Helper Functions
 * Pathology Laboratory Management System
 */

/**
 * Sanitize and clean input data to prevent XSS
 */
function cleanInput($data) {
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}


/** Return only the patient's gender-specific reference range when a range
 * contains male/female or men/women values. Shared ranges remain unchanged.
 */
function referenceRangeForGender($range, $gender) {
    $range = trim((string)$range);
    if ($range === '') return $range;

    $g = strtolower(trim((string)$gender));
    $wanted = in_array($g, ['female', 'f', 'woman', 'women'], true) ? 'female'
        : (in_array($g, ['male', 'm', 'man', 'men'], true) ? 'male' : '');
    if ($wanted === '') return $range;

    // Recognize labels such as Male, Men, Female, and Women, regardless of order,
    // and separators such as semicolon, comma, pipe, or line break.
    $labelPattern = '(Male|Men|Female|Women)';
    $separatorPattern = '[;|,\/\r\n]+';
    if (preg_match_all('/(?:^|'.$separatorPattern.')\s*'.$labelPattern.'\s*:?\s*(.*?)(?='.$separatorPattern.'\s*(?:'.$labelPattern.')\s*:?|$)/i', $range, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $label = strtolower($match[1]);
            $isFemale = in_array($label, ['female', 'women'], true);
            if (($wanted === 'female' && $isFemale) || ($wanted === 'male' && !$isFemale)) {
                return trim($match[2]);
            }
        }
    }

    // Fallback for compact formats like "Men 0-15 / Women 0-20".
    if (preg_match('/\b(Male|Men)\s*:?\s*(.*?)\s*\/\s*(Female|Women)\s*:?\s*(.*)$/i', $range, $m)) {
        return trim($wanted === 'male' ? $m[2] : $m[4]);
    }
    if (preg_match('/\b(Female|Women)\s*:?\s*(.*?)\s*\/\s*(Male|Men)\s*:?\s*(.*)$/i', $range, $m)) {
        return trim($wanted === 'female' ? $m[2] : $m[4]);
    }
    return $range;
}

/**
 * Determine whether a result is outside its configured reference range.
 * Returns false when the reference format cannot be interpreted safely.
 */
function isResultOutOfReferenceRange($result, $range) {
    $result = trim((string)$result);
    $range = trim((string)$range);
    if ($result === '' || $range === '') return false;

    $normalizedRange = str_replace([',', '−', '–', '—'], ['', '-', '-', '-'], $range);
    $normalizedResult = str_replace(',', '', $result);

    // Widal/titre-style values such as result 1:160 against reference < 1:80.
    // Compare the denominator when both sides use the same 1:n titre format.
    if (preg_match('/^\s*(<=|>=|<|>|≤|≥)?\s*1\s*:\s*(\d+)\s*$/u', $normalizedRange, $rangeTitre)
        && preg_match('/^\s*1\s*:\s*(\d+)\s*$/', $normalizedResult, $resultTitre)) {
        $operator = $rangeTitre[1] ?? '';
        $limit = (int)$rangeTitre[2];
        $value = (int)$resultTitre[1];
        switch ($operator) {
            case '<': return $value >= $limit;
            case '<=': case '≤': return $value > $limit;
            case '>': return $value <= $limit;
            case '>=': case '≥': return $value < $limit;
            default: return $value !== $limit;
        }
    }

    // Numeric intervals such as 3.5 - 5.5 or 4000-11000.
    if (preg_match('/^\s*([+-]?\d+(?:\.\d+)?)\s*(?:-|\bto\b)\s*([+-]?\d+(?:\.\d+)?)\s*$/i', $normalizedRange, $m)) {
        if (preg_match('/[-+]?\d+(?:\.\d+)?/', $normalizedResult, $valueMatch)) {
            $value = (float)$valueMatch[0];
            $low = (float)$m[1];
            $high = (float)$m[2];
            return $value < min($low, $high) || $value > max($low, $high);
        }
        return false;
    }

    // One-sided numeric limits such as <5, <= 10, >40 or >= 1.2.
    if (preg_match('/^\s*(<=|>=|<|>|≤|≥)\s*([+-]?\d+(?:\.\d+)?)\s*$/u', $normalizedRange, $m)
        && preg_match('/[-+]?\d+(?:\.\d+)?/', $normalizedResult, $valueMatch)) {
        $value = (float)$valueMatch[0];
        $limit = (float)$m[2];
        switch ($m[1]) {
            case '<': return $value >= $limit;
            case '<=': case '≤': return $value > $limit;
            case '>': return $value <= $limit;
            case '>=': case '≥': return $value < $limit;
        }
    }

    // For qualitative results, flag a mismatch only when the reference is a
    // clear single expected value. Multiple alternatives are treated as valid.
    if (preg_match('/\d/', $range)) return false;
    if (preg_match('/[\/|,;]|\bor\b/i', $range)) {
        $options = preg_split('/\s*(?:\/|\||,|;|\bor\b)\s*/i', $range, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($options as $option) {
            if (strcasecmp(trim($option), $result) === 0) return false;
        }
        return false;
    }
    $cleanRange = strtolower(preg_replace('/\s+/', ' ', trim($range)));
    $cleanResult = strtolower(preg_replace('/\s+/', ' ', trim($result)));
    if ($cleanRange !== '' && preg_match('/^[a-z ]+$/i', $range) && preg_match('/^[a-z ]+$/i', $result)) {
        return $cleanRange !== $cleanResult;
    }
    return false;
}

/**
 * Fetch a single patient record by ID
 */
function getPatientById($pdo, $patient_id) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    return $stmt->fetch();
}

/**
 * Check if a patient has been finalized/submitted
 */
function isPatientSubmitted($pdo, $patient_id) {
    $stmt = $pdo->prepare("SELECT submitted FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $submitted = $stmt->fetchColumn();
    return ((int)$submitted === 1);
}

/**
 * Calculate completed test count and total price, and update the patient row
 */
function calculateAndUpdatePatientTotals($pdo, $patient_id) {
    // Billing totals are kept for internal reports only. Patient reports never display amounts.
    $stmt = $pdo->prepare("
        SELECT
            pt.id AS patient_test_id,
            t.test_name,
            t.price AS test_price,
            COALESCE(SUM(CASE WHEN TRIM(COALESCE(tr.result, '')) <> '' THEN COALESCE(tp.price, 0) ELSE 0 END), 0) AS parameter_total,
            COUNT(CASE WHEN TRIM(COALESCE(tr.result, '')) <> '' THEN 1 END) AS result_count,
            COUNT(tp.id) AS parameter_count
        FROM patient_tests pt
        JOIN tests t ON pt.test_id = t.id
        LEFT JOIN test_parameters tp ON tp.test_id = t.id
        LEFT JOIN test_results tr ON tr.patient_test_id = pt.id AND tr.parameter_id = tp.id
        WHERE pt.patient_id = ? AND pt.status = 'COMPLETED'
        GROUP BY pt.id, t.test_name, t.price
    ");
    $stmt->execute([$patient_id]);
    $rows = $stmt->fetchAll();

    $count = count($rows);
    $total = 0.00;
    foreach ($rows as $row) {
        $parameterTotal = (float)$row['parameter_total'];
        $resultCount = (int)$row['result_count'];
        $parameterCount = (int)$row['parameter_count'];

        // BT / CT is one test with a flat charge of ₹50.
        if (strcasecmp(trim((string)$row['test_name']), 'BT / CT') === 0) {
            $total += (float)$row['test_price'];
        } elseif ($parameterCount > 0 && $parameterTotal > 0) {
            // Other multi-part tests can use parameter-level prices when configured.
            $total += $parameterTotal;
        } else {
            $total += (float)$row['test_price'];
        }
    }

    $update = $pdo->prepare("UPDATE patients SET test_count = ?, total_amount = ? WHERE id = ?");
    $update->execute([$count, $total, $patient_id]);

    return [
        'count' => $count,
        'total' => $total
    ];
}

/**
 * Map an investigation to the report section used by the supplied reference reports.
 */
function getTestCategory($testName) {
    $name = strtolower(trim($testName));

    $serology = ['widal', 'ra factor', 'vdrl', 'blood group', 'sputum for afb', 'crp', 'pregnancy', 'malaria', 'typhidot', 'viral marker', 'hiv / hcv', 'p/s for mp'];
    foreach ($serology as $needle) {
        if (strpos($name, $needle) !== false) return 'SEROLOGY & IMMUNOLOGY';
    }

    $biochemistry = ['thyroid', 'tsh', 'blood sugar', 'hba1c', 'vitamin', 'kidney', 'urea', 'creatinine', 'calcium', 'uric acid', 'liver', 'bilirubin', 's.g.o.t', 's.g.p.t', 'protein', 'albumin', 'lipid', 'cholesterol', 'triglyceride', 'l.d.l'];
    foreach ($biochemistry as $needle) {
        if (strpos($name, $needle) !== false) return 'BIOCHEMISTRY';
    }

    return 'HEMATOLOGY';
}

/**
 * Format currency with Indian Rupee symbol
 */
function formatCurrency($amount) {
    return '₹' . number_format((float)$amount, 2);
}

/**
 * Format date for display
 */
function formatDate($dateStr) {
    if (!$dateStr) return '-';
    return date('d-m-Y', strtotime($dateStr));
}

/**
 * Record an application activity in the append-only audit log.
 * The username is stored in the log so the record remains understandable
 * even if that user is later edited or deleted.
 */
function auditLog($pdo, $action, $details = '') {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $username = $_SESSION['admin_username'] ?? 'unknown';
        $userId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, action, details, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $username, $action, $details, $ip]);
    } catch (Throwable $e) {
        // Auditing must never break the main laboratory workflow.
    }
}

/**
 * Set a session flash message (type: 'success', 'error', 'info')
 */
function setFlash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][$type] = $message;
}

/**
 * Display and clear session flash messages
 */
function renderFlashMessages() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $type => $message) {
            $class = ($type === 'error') ? 'alert-danger' : (($type === 'success') ? 'alert-success' : 'alert-info');
            echo '<div class="alert ' . $class . ' alert-dismissible" id="flash-alert">';
            echo '<span>' . htmlspecialchars($message) . '</span>';
            echo '<button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>';
            echo '</div>';
        }
        unset($_SESSION['flash']);
    }
}
?>
