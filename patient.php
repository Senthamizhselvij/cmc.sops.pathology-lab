<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'New Patient Registration';
$todayDateSql = date('Y-m-d');
$yesterdayDateSql = date('Y-m-d', strtotime('-1 day'));

require_once __DIR__ . '/includes/header.php';
?>

<div class="portal-card">
    <h2 class="portal-card-title">New Patient Registration</h2>
    <p class="portal-card-subtitle">Enter the patient details. Tests can be added now and results can be entered later.</p>

    <form id="patient-form" action="save_patient.php" method="POST">

        <div class="form-row-2">
            <div class="form-group">
                <label for="receipt_id" class="form-label">Receipt ID</label>
                <input type="text" name="receipt_id" id="receipt_id" class="form-control"
                       placeholder="Enter Receipt ID" pattern="[A-Za-z0-9]*"
                       title="Letters and numbers only" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="patient_date" class="form-label required">Registration Date</label>
                <input type="date" name="patient_date" id="patient_date" class="form-control"
                       value="<?php echo htmlspecialchars($todayDateSql); ?>"
                       min="<?php echo htmlspecialchars($yesterdayDateSql); ?>"
                       max="<?php echo htmlspecialchars($todayDateSql); ?>" required>
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label for="patient_name" class="form-label required">Patient Name</label>
                <input type="text" name="patient_name" id="patient_name" class="form-control" placeholder="Full name" required>
            </div>
            <div class="form-group">
                <label for="patient_code" class="form-label">Patient ID</label>
                <input type="text" name="patient_code" id="patient_code" class="form-control"
                       placeholder="Enter Patient ID" maxlength="12" pattern="[A-Za-z0-9]{0,12}"
                       title="Patient ID may contain up to 12 letters or numbers" autocomplete="off">
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label for="age" class="form-label required">Age</label>
                <input type="number" name="age" id="age" class="form-control" placeholder="Years" min="1" max="120" required>
            </div>
            <div class="form-group">
                <label class="form-label required">Gender</label>
                <div class="radio-group">
                    <label class="radio-label"><input type="radio" name="gender" value="Male" required checked> Male</label>
                    <label class="radio-label"><input type="radio" name="gender" value="Female" required> Female</label>
                    <label class="radio-label"><input type="radio" name="gender" value="Other" required> Other</label>
                </div>
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label for="phone" class="form-label required">Phone Number</label>
                <input type="tel" name="phone" id="phone" class="form-control" placeholder="Mobile number" required>
            </div>
            <div class="form-group">
                <label for="address" class="form-label">Address</label>
                <input type="text" name="address" id="address" class="form-control" placeholder="Patient address">
            </div>
        </div>

        <div style="margin-top:30px;display:flex;justify-content:flex-end;gap:15px;">
            <a href="dashboard.php" class="btn btn-outline">Back</a>
            <button type="submit" class="btn btn-primary btn-lg">Save Patient &amp; Add Tests &rarr;</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
