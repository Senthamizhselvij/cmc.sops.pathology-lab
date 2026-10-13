/**
 * Vanilla JavaScript Utilities & Form Validation
 * Pathology Laboratory & Clinic Portal
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Auto-dismiss alerts after 5 seconds
    const flashAlert = document.getElementById('flash-alert');
    if (flashAlert) {
        setTimeout(function () {
            flashAlert.style.transition = 'opacity 0.5s ease';
            flashAlert.style.opacity = '0';
            setTimeout(function () {
                flashAlert.remove();
            }, 500);
        }, 5000);
    }

    // 2. Attach client-side validation to Patient Form
    const patientForm = document.getElementById('patient-form');
    if (patientForm) {
        patientForm.addEventListener('submit', function (event) {
            if (!validatePatientForm()) {
                event.preventDefault();
            } else {
                disableSubmitButton(patientForm);
            }
        });
    }

    // 3. Attach validation to Test Results Form
    const testResultForm = document.getElementById('test-result-form');
    if (testResultForm) {
        testResultForm.addEventListener('submit', function (event) {
            if (!validateTestResultsForm()) {
                event.preventDefault();
            } else {
                disableSubmitButton(testResultForm);
            }
        });
    }

    // 4. Attach validation to Login Form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            if (!validateLoginForm()) {
                event.preventDefault();
            } else {
                disableSubmitButton(loginForm);
            }
        });
    }
});

/**
 * Validate Patient Registration Form
 */
function validatePatientForm() {
    const nameInput = document.getElementById('patient_name');
    const ageInput  = document.getElementById('age');
    const phoneInput = document.getElementById('phone');
    const genderChecked = document.querySelector('input[name="gender"]:checked');

    if (!nameInput || !nameInput.value.trim()) {
        alert('Please enter the patient full name.');
        if (nameInput) nameInput.focus();
        return false;
    }

    if (!ageInput || !ageInput.value.trim()) {
        alert('Please enter the patient age.');
        if (ageInput) ageInput.focus();
        return false;
    }

    const age = parseInt(ageInput.value.trim(), 10);
    if (isNaN(age) || age < 1 || age > 120) {
        alert('Please enter a valid age between 1 and 120.');
        ageInput.focus();
        return false;
    }

    if (!genderChecked) {
        alert('Please select patient gender.');
        return false;
    }

    if (!phoneInput || !phoneInput.value.trim()) {
        alert('Please enter a mobile phone number.');
        if (phoneInput) phoneInput.focus();
        return false;
    }

    const phoneRegex = /^[0-9]{10,15}$/;
    const cleanPhone = phoneInput.value.trim().replace(/[\s\-()+]/g, '');
    if (!phoneRegex.test(cleanPhone)) {
        alert('Please enter a valid phone number (10 to 15 digits).');
        phoneInput.focus();
        return false;
    }

    return true;
}

/**
 * Validate Test Result Form inputs
 *
 * Results are optional for individual parameters.
 * The user may enter 1, 2, or all parameters.
 * At least one result is required to complete the test.
 * Blank parameters are ignored and will not appear in the patient report.
 */
function validateTestResultsForm() {
    const resultInputs = document.querySelectorAll('input[name^="results["]');

    if (resultInputs.length === 0) {
        return true;
    }

    let enteredCount = 0;

    for (let i = 0; i < resultInputs.length; i++) {
        if (resultInputs[i].value.trim() !== '') {
            enteredCount++;
        }
    }

    // Allow 1, 2, 3, or all results. Only reject when none is entered.
    if (enteredCount === 0) {
        alert('Please enter at least one result.');
        resultInputs[0].focus();
        return false;
    }

    return true;
}

/**
 * Validate Login Form
 */
function validateLoginForm() {
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');

    if (!usernameInput || !usernameInput.value.trim()) {
        alert('Please enter your Username / Employee ID.');
        if (usernameInput) usernameInput.focus();
        return false;
    }

    if (!passwordInput || !passwordInput.value) {
        alert('Please enter your password.');
        if (passwordInput) passwordInput.focus();
        return false;
    }

    return true;
}

/**
 * Confirmation dialog before logging out
 */
function confirmLogout(event) {
    if (!confirm('Are you sure you want to log out?')) {
        if (event) event.preventDefault();
        return false;
    }
    return true;
}

/**
 * Confirmation dialog before finalizing/submitting a patient
 */
function confirmPatientSubmission(event) {
    const message = "Are you sure you want to finalize and submit this patient?\n\nOnce submitted:\n- All test investigations are locked\n- Totals and counts are permanently recorded\n- Final report will be generated and printed";
    if (!confirm(message)) {
        if (event) event.preventDefault();
        return false;
    }
    return true;
}

/**
 * Disable submit button to prevent double submissions
 */
function disableSubmitButton(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        const originalText = submitBtn.innerText;
        submitBtn.innerText = 'Processing...';
        setTimeout(function () {
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }, 4000);
    }
}
