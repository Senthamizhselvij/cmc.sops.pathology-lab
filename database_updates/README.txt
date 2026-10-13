========================================================================
PATHOLOGY LABORATORY MANAGEMENT SYSTEM - HISTORICAL SQL UPDATES ARCHIVE
========================================================================

NOTE:
If you are setting up a fresh installation or need the complete, unified schema,
DO NOT USE THE FILES IN THIS FOLDER.
Instead, use the single consolidated master database file:
    ../database.sql

The SQL files in this folder are historical incremental patch scripts used when
migrating an older version of the pathology_lab database in XAMPP:

1. CHECK_RECEIPT_ID_DUPLICATES.sql
   - Verification query to find repeated Receipt IDs in the database.

2. admin_security_update.sql
   - Adds role (admin/hod/staff) to admins and creates audit_logs table.

3. database_patient_updates.sql
   - Adds patient_code to patients and widens receipt_id.

4. database_per_test_result_date_update.sql
   - Adds result_date column to patient_tests.

5. database_receipt_id_old_patient_update.sql
   - Removes legacy UNIQUE index from patients.receipt_id.

6. database_report_date_cancel_update.sql
   - Adds report_date to patients.

7. database_reports_update.sql
   - Adds cancellation columns (report_status, cancelled_at, etc.) to patients.

8. database_test_catalog_update.sql
   - Price and parameter updates for single viral tests and fasting/PP.

9. database_test_catalog_update_current_batch.sql
   - Reference range adjustments, units update, and CBC parameter structure.

10. database_test_catalog_update_v2.sql
    - Removes legacy duplicate "Thyroid" test and sets "Viral Marker" to ₹500.

11. database_test_result_history_update.sql
    - Creates test_result_history table for auditing result edits.

12. update_test_catalog.sql
    - Test catalog and parameter definition script for existing setups.
========================================================================
