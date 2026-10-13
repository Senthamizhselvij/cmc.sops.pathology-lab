-- Run this in phpMyAdmin on your existing pathology database.
-- It shows Receipt IDs that were used more than once.
SELECT
    UPPER(TRIM(receipt_id)) AS receipt_id,
    COUNT(*) AS times_used,
    GROUP_CONCAT(CONCAT('ID=', id, ', Date=', patient_date, ', Created=', created_at, ', Name=', patient_name) ORDER BY created_at, id SEPARATOR ' | ') AS records
FROM patients
WHERE TRIM(receipt_id) <> ''
GROUP BY UPPER(TRIM(receipt_id))
HAVING COUNT(*) > 1
ORDER BY MIN(created_at), MIN(id);
