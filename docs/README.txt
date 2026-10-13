========================================================================
PATHOLOGY LABORATORY MANAGEMENT SYSTEM - DOCUMENTATION INDEX
========================================================================

QUICK START (NO MANUAL CONFIGURATION NEEDED!):
------------------------------------------------------------------------
You NO LONGER need to manually open XAMPP, click start buttons, or open
phpMyAdmin to import database.sql.

Simply double-click:
  -> "START_APP.bat"  (in the pathology-lab folder)
  OR
  -> "Pathology Lab - Start App" shortcut on your Desktop!

It will automatically:
1. Start Apache and MySQL services in the background.
2. Automatically create the database and import database.sql if not yet present.
3. Open your browser directly to: http://localhost/pathology-lab/

To stop services anytime, double-click:
  -> "STOP_APP.bat"

------------------------------------------------------------------------
DOCUMENTATION & GUIDES IN THIS FOLDER:
------------------------------------------------------------------------

1. ADMIN_USER_GUIDE.txt
   - How to manage users (Staff, HOD, Admin) and use the Activity Log.

2. UPDATED_INSTALL.txt & INSTALL_UPDATE.txt
   - Installation guide for XAMPP (Apache, PHP, MySQL).
   - How to setup the database and start the application.

3. PATIENT_WORKFLOW.txt
   - Step-by-step workflow: Patient registration, adding tests, entering results,
     report generation, and final submission.

4. TEST_RESULT_RULES.txt
   - Rules for entering and updating test results.
   - Result editing rules, blank result handling, and pending tests.

5. TEST_CATALOG_AND_REPORT_CHANGES.txt
   - Complete details of test catalog pricing, parameters, and PDF-style reports.

6. OLD_PATIENT_RULE.txt & RECEIPT_ID_OLD_PATIENT_UPDATE.txt
   - Rules for classifying repeat patients as OLD vs NEW based on Receipt ID.

7. PER_TEST_RESULT_DATE_UPDATE.txt
   - Explanation of per-test completion and result dates.

8. CANCEL_REPORT_GUIDE.txt
   - Guide on how to cancel final submitted reports with mandatory audit reasons.

9. BACKDATED_REGISTRATION_UPDATE.txt
   - Instructions for backdating patient registrations.

10. REPORT_FILES_SETUP.txt
    - Overview of the reporting pages (Daily, Weekly, Summary, Cancel Report).

========================================================================
