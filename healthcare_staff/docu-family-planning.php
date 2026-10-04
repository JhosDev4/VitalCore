<?php

/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../db_conn.php');

/* =========================================================
   ACCESS CONTROL
========================================================= */

if (
    !isset($_SESSION['staff_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Staff'], true)
) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Patient not found.");
}

$patient_id     = (int) $_GET['id'];
$measurement_id = isset($_GET['measurement_id']) && is_numeric($_GET['measurement_id']) ? (int) $_GET['measurement_id'] : 0;


/* =========================================================
   FAMILY PLANNING FORM PROCESSING
========================================================= */

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_family_planning'])) {

    $gravida            = isset($_POST['gravida']) ? (int) $_POST['gravida'] : 0;
    $para_count         = isset($_POST['para_count']) ? (int) $_POST['para_count'] : 0;
    $living_children    = isset($_POST['living_children']) ? (int) $_POST['living_children'] : 0;

    $partner_name       = trim($_POST['partner_name'] ?? '');
    $occupation         = trim($_POST['occupation'] ?? '');
    $lmp                = !empty($_POST['lmp']) ? $_POST['lmp'] : null;
    $menstrual_cycle    = trim($_POST['menstrual_cycle'] ?? '');
    $pregnancy_status   = trim($_POST['pregnancy_status'] ?? '');
    $eligibility_status = trim($_POST['eligibility_status'] ?? '');
    $fp_method          = trim($_POST['fp_method'] ?? '');
    $date_started       = !empty($_POST['date_started']) ? $_POST['date_started'] : null;
    $next_schedule      = !empty($_POST['next_schedule']) ? $_POST['next_schedule'] : null;
    $counseling_status  = trim($_POST['counseling_status'] ?? '');
    $provider_name      = trim($_POST['provider_name'] ?? '');
    $remarks            = trim($_POST['remarks'] ?? '');

    /* Basic validation */
    if ($fp_method === '') {

        $error_message = 'Please select a family planning method.';

    } else {

        /* Check if record already exists for this measurement / visit */
        if ($measurement_id > 0) {
            $checkStmt = mysqli_prepare(
                $conn,
                "SELECT id FROM family_planning_records WHERE user_id = ? AND measurement_id = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($checkStmt, "ii", $patient_id, $measurement_id);
        } else {
            $checkStmt = mysqli_prepare(
                $conn,
                "SELECT id FROM family_planning_records WHERE user_id = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($checkStmt, "i", $patient_id);
        }

        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {

            /* =============================================
               UPDATE EXISTING RECORD
            ============================================= */

            $existing = mysqli_fetch_assoc($checkResult);
            $record_id = (int) $existing['id'];

            $updateStmt = mysqli_prepare(
                $conn,
                "UPDATE family_planning_records SET
                    gravida = ?,
                    para_count = ?,
                    living_children = ?,
                    partner_name = ?,
                    occupation = ?,
                    lmp = ?,
                    menstrual_cycle = ?,
                    pregnancy_status = ?,
                    eligibility_status = ?,
                    fp_method = ?,
                    date_started = ?,
                    next_schedule = ?,
                    counseling_status = ?,
                    provider_name = ?,
                    remarks = ?
                 WHERE id = ? AND user_id = ?"
            );

            mysqli_stmt_bind_param(
                $updateStmt,
                "iiissssssssssssii",
                $gravida,
                $para_count,
                $living_children,
                $partner_name,
                $occupation,
                $lmp,
                $menstrual_cycle,
                $pregnancy_status,
                $eligibility_status,
                $fp_method,
                $date_started,
                $next_schedule,
                $counseling_status,
                $provider_name,
                $remarks,
                $record_id,
                $patient_id
            );

            if (mysqli_stmt_execute($updateStmt)) {
                $success_message = 'Family planning record updated successfully.';
            } else {
                $error_message = 'Unable to update family planning record.';
            }

            mysqli_stmt_close($updateStmt);

        } else {

            /* =============================================
               INSERT NEW RECORD FOR THIS VISIT
            ============================================= */

            $insertStmt = mysqli_prepare(
                $conn,
                "INSERT INTO family_planning_records (
                    user_id,
                    measurement_id,
                    gravida,
                    para_count,
                    living_children,
                    partner_name,
                    occupation,
                    lmp,
                    menstrual_cycle,
                    pregnancy_status,
                    eligibility_status,
                    fp_method,
                    date_started,
                    next_schedule,
                    counseling_status,
                    provider_name,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $m_id_param = $measurement_id > 0 ? $measurement_id : null;

            mysqli_stmt_bind_param(
                $insertStmt,
                "iiiiissssssssssss",
                $patient_id,
                $m_id_param,
                $gravida,
                $para_count,
                $living_children,
                $partner_name,
                $occupation,
                $lmp,
                $menstrual_cycle,
                $pregnancy_status,
                $eligibility_status,
                $fp_method,
                $date_started,
                $next_schedule,
                $counseling_status,
                $provider_name,
                $remarks
            );

            if (mysqli_stmt_execute($insertStmt)) {
                $success_message = 'Family planning record saved successfully.';
            } else {
                $error_message = 'Unable to save family planning record.';
            }

            mysqli_stmt_close($insertStmt);
        }

        mysqli_stmt_close($checkStmt);
    }
}


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function displayValue($value, $default = '--')
{
    return (!empty($value) || $value === '0' || $value === 0) ? e($value) : $default;
}

function formatDateValue($date)
{
    if (!empty($date) && strtotime($date) !== false) {
        return date('F d, Y', strtotime($date));
    }

    return '--';
}

/* =========================================================
   1. FETCH PATIENT INFORMATION
========================================================= */

$patientQuery = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE id = $patient_id LIMIT 1"
);

if (!$patientQuery) {
    die("Unable to retrieve patient information.");
}

$patient = mysqli_fetch_assoc($patientQuery);

if (!$patient) {
    die("Patient not found.");
}

/* =========================================================
   2. FETCH VITAL MEASUREMENTS FOR THIS VISIT
========================================================= */

$temp       = null;
$weight     = null;
$height     = null;
$bmi        = null;
$heart_rate = null;
$spo2       = null;
$systolic   = null;
$diastolic  = null;

$last_visited = 'No visits recorded';
$doc_date     = date('F d, Y');

if ($measurement_id > 0) {
    $measurementQuery = mysqli_query(
        $conn,
        "SELECT * FROM measurements WHERE id = $measurement_id LIMIT 1"
    );
} else {
    $measurementQuery = mysqli_query(
        $conn,
        "SELECT * FROM measurements WHERE user_id = $patient_id ORDER BY created_at DESC LIMIT 1"
    );
}

if ($measurementQuery && mysqli_num_rows($measurementQuery) > 0) {

    $measurement = mysqli_fetch_assoc($measurementQuery);

    $temp       = $measurement['temperature'] ?? null;
    $weight     = $measurement['weight'] ?? null;
    $height     = $measurement['height'] ?? null;
    $bmi        = $measurement['bmi'] ?? null;
    $heart_rate = $measurement['heart_rate'] ?? null;
    $spo2       = $measurement['spo2'] ?? null;
    $systolic   = $measurement['systolic'] ?? null;
    $diastolic  = $measurement['diastolic'] ?? null;

    if (!empty($measurement['created_at'])) {
        $last_visited = date(
            'F d, Y - h:i A',
            strtotime($measurement['created_at'])
        );
        $doc_date = date('F d, Y', strtotime($measurement['created_at']));
    }
}

/* =========================================================
   3. FORMAT VITAL VALUES
========================================================= */

$temp_val       = (is_numeric($temp) && (float)$temp > 0) ? (float)$temp : null;
$weight_val     = (is_numeric($weight) && (float)$weight > 0) ? (float)$weight : null;
$height_val     = (is_numeric($height) && (float)$height > 0) ? (float)$height : null;
$bmi_val        = (is_numeric($bmi) && (float)$bmi > 0) ? (float)$bmi : null;
$heart_rate_val = (is_numeric($heart_rate) && (int)$heart_rate > 0) ? (int)$heart_rate : null;
$spo2_val       = (is_numeric($spo2) && (int)$spo2 > 0) ? (int)$spo2 : null;
$systolic_val   = (is_numeric($systolic) && (int)$systolic > 0) ? (int)$systolic : null;
$diastolic_val  = (is_numeric($diastolic) && (int)$diastolic > 0) ? (int)$diastolic : null;

/* =========================================================
   4. DISPLAY VALUES
========================================================= */

$temp_disp       = ($temp_val !== null) ? number_format($temp_val, 1) . ' °C' : '--';
$weight_disp     = ($weight_val !== null) ? number_format($weight_val, 1) . ' kg' : '--';
$height_disp     = ($height_val !== null) ? number_format($height_val, 1) . ' cm' : '--';
$bmi_disp        = ($bmi_val !== null) ? number_format($bmi_val, 2) : '--';
$heart_rate_disp = ($heart_rate_val !== null) ? $heart_rate_val . ' BPM' : '--';
$spo2_disp       = ($spo2_val !== null) ? $spo2_val . '%' : '--';
$bp_disp         = ($systolic_val !== null && $diastolic_val !== null) ? $systolic_val . '/' . $diastolic_val . ' mmHg' : '--';

/* =========================================================
   5. STATUS BADGES
========================================================= */

$temp_status = '--'; $temp_class = 'badge-secondary';
if ($temp_val !== null) {
    if ($temp_val < 36.0) { $temp_status = 'Low'; $temp_class = 'badge-info'; }
    elseif ($temp_val <= 37.5) { $temp_status = 'Normal'; $temp_class = 'badge-success'; }
    elseif ($temp_val <= 38.5) { $temp_status = 'Slight Fever'; $temp_class = 'badge-warning'; }
    else { $temp_status = 'High Fever'; $temp_class = 'badge-danger'; }
}

$heart_status = '--'; $heart_class = 'badge-secondary';
if ($heart_rate_val !== null) {
    if ($heart_rate_val < 60) { $heart_status = 'Low'; $heart_class = 'badge-warning'; }
    elseif ($heart_rate_val > 100) { $heart_status = 'High'; $heart_class = 'badge-danger'; }
    else { $heart_status = 'Normal'; $heart_class = 'badge-success'; }
}

$spo2_status = '--'; $spo2_class = 'badge-secondary';
if ($spo2_val !== null) {
    if ($spo2_val < 90) { $spo2_status = 'Critical Low'; $spo2_class = 'badge-danger'; }
    elseif ($spo2_val < 95) { $spo2_status = 'Mild Low'; $spo2_class = 'badge-warning'; }
    else { $spo2_status = 'Normal'; $spo2_class = 'badge-success'; }
}

$bp_status = '--'; $bp_class = 'badge-secondary';
if ($systolic_val !== null && $diastolic_val !== null) {
    if ($systolic_val > 180 || $diastolic_val > 120) { $bp_status = 'Hypertensive Crisis'; $bp_class = 'badge-danger'; }
    elseif ($systolic_val >= 140 || $diastolic_val >= 90) { $bp_status = 'High'; $bp_class = 'badge-danger'; }
    elseif (($systolic_val >= 130 && $systolic_val <= 139) || ($diastolic_val >= 80 && $diastolic_val <= 89)) { $bp_status = 'High'; $bp_class = 'badge-warning'; }
    elseif ($systolic_val >= 120 && $systolic_val <= 129 && $diastolic_val < 80) { $bp_status = 'Elevated'; $bp_class = 'badge-warning'; }
    elseif ($systolic_val < 90 || $diastolic_val < 60) { $bp_status = 'Low'; $bp_class = 'badge-info'; }
    else { $bp_status = 'Normal'; $bp_class = 'badge-success'; }
}

$bmi_status = '--'; $bmi_class = 'badge-secondary';
if ($bmi_val !== null) {
    if ($bmi_val < 18.5) { $bmi_status = 'Underweight'; $bmi_class = 'badge-info'; }
    elseif ($bmi_val <= 24.9) { $bmi_status = 'Normal'; $bmi_class = 'badge-success'; }
    elseif ($bmi_val <= 29.9) { $bmi_status = 'Overweight'; $bmi_class = 'badge-warning'; }
    else { $bmi_status = 'Obese'; $bmi_class = 'badge-danger'; }
}

/* =========================================================
   10. FETCH FAMILY PLANNING RECORD FOR THIS VISIT
========================================================= */

$fp = [
    'gravida'            => null,
    'para_count'         => null,
    'living_children'    => null,
    'partner_name'       => null,
    'occupation'         => null,
    'lmp'                => null,
    'menstrual_cycle'    => null,
    'pregnancy_status'   => null,
    'eligibility_status' => null,
    'fp_method'          => null,
    'date_started'       => null,
    'next_schedule'      => null,
    'counseling_status'  => null,
    'provider_name'      => null,
    'remarks'            => null
];

$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'family_planning_records'");

if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {

    if ($measurement_id > 0) {
        $fpQuery = mysqli_query(
            $conn,
            "SELECT * FROM family_planning_records WHERE user_id = $patient_id AND measurement_id = $measurement_id LIMIT 1"
        );
    } else {
        $fpQuery = mysqli_query(
            $conn,
            "SELECT * FROM family_planning_records WHERE user_id = $patient_id ORDER BY created_at DESC LIMIT 1"
        );
    }

    if ($fpQuery && mysqli_num_rows($fpQuery) > 0) {
        $fpData = mysqli_fetch_assoc($fpQuery);
        foreach ($fp as $key => $value) {
            if (array_key_exists($key, $fpData)) {
                $fp[$key] = $fpData[$key];
            }
        }
    }
}

/* =========================================================
   11. DISPLAY VALUES & PATIENT DATA
========================================================= */

$gravida            = displayValue($fp['gravida']);
$para_count         = displayValue($fp['para_count']);
$living_children    = displayValue($fp['living_children']);
$partner_name       = displayValue($fp['partner_name']);
$occupation         = displayValue($fp['occupation']);
$lmp                = formatDateValue($fp['lmp']);
$menstrual_cycle    = displayValue($fp['menstrual_cycle']);
$pregnancy_status   = displayValue($fp['pregnancy_status']);
$eligibility_status = displayValue($fp['eligibility_status']);
$fp_method          = displayValue($fp['fp_method']);
$date_started       = formatDateValue($fp['date_started']);
$next_schedule      = formatDateValue($fp['next_schedule']);
$counseling_status  = displayValue($fp['counseling_status']);
$provider_name      = displayValue($fp['provider_name']);
$remarks            = !empty($fp['remarks']) ? nl2br(e($fp['remarks'])) : 'No remarks recorded.';

$patient_name   = !empty($patient['fullname']) ? $patient['fullname'] : '--';
$gender         = !empty($patient['gender']) ? $patient['gender'] : '--';
$age            = !empty($patient['age']) ? $patient['age'] : '--';
$birth_date     = formatDateValue($patient['birth_date'] ?? null);
$blood_type     = !empty($patient['blood_type']) ? $patient['blood_type'] : '--';
$contact_number = !empty($patient['contact_number']) ? $patient['contact_number'] : '--';
$address        = !empty($patient['address']) ? $patient['address'] : '--';
$civil_status   = !empty($patient['civil_status']) ? $patient['civil_status'] : '--';

if (empty($fp['occupation']) && !empty($patient['occupation'])) {
    $occupation = $patient['occupation'];
}

$clean_name = preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', strtolower($patient_name)));
$safe_filename = 'Family_Planning_Record_PT' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT) . '_' . $clean_name;

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <!-- PRE-LOAD STAFF INDEPENDENT DARK MODE -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('staff_theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark-mode');
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Family Planning Record - #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/document.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Dark Mode Override Fix for Clinic Title */
        .clinic-name,
        h1.clinic-name,
        html.dark-mode .clinic-name,
        body.dark-mode .clinic-name,
        .dark-mode h1.clinic-name {
            color: #0a49c4 !important;
        }

        .fp-title { color: #0a49c4; font-weight: 800; }
        .fp-method-box { border: 1px solid #dbe3ef; border-radius: 10px; padding: 16px; background: #f8fafc; }
        .fp-method-value { font-size: 1.05rem; font-weight: 700; color: #0a49c4; }
        .fp-status-box { display: inline-flex; align-items: center; gap: 7px; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .fp-check { color: #198754; font-weight: 700; }
        .fp-section-note { font-size: 0.75rem; color: #6c757d; margin-top: -8px; margin-bottom: 12px; }
        .remarks-content { min-height: 75px; line-height: 1.6; }

        #familyPlanningModal .modal-dialog { max-height: 88vh; }
        #familyPlanningModal .modal-content { max-height: 88vh !important; display: flex; flex-direction: column; }
        #familyPlanningModal .modal-body { overflow-y: auto !important; max-height: calc(88vh - 130px) !important; padding-right: 15px; }
        #familyPlanningModal .modal-footer { flex-shrink: 0; position: sticky; bottom: 0; z-index: 1050; border-top: 1px solid #dee2e6; }
        textarea[name="remarks"] { overflow-y: hidden !important; min-height: 110px; resize: none; }
        body.dark-mode #familyPlanningModal .modal-footer { background-color: #111827 !important; border-top-color: #334155 !important; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .document-wrapper { box-shadow: none !important; margin: 0 !important; width: 100% !important; }
            .page-break { page-break-before: always; }
        }
    </style>

</head>

<body>

<div class="no-print bg-white border-bottom p-3 mb-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 820px;">

        <a href="patient-history-list.php?user_id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Patient History
        </a>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-warning btn-sm px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#familyPlanningModal">
                <i class="bi bi-pencil-square me-1"></i>
                <?= !empty($fp['fp_method']) ? 'Edit Record' : 'Add Record'; ?>
            </button>

            <button onclick="saveAsPDF()" class="btn btn-success btn-sm px-3 fw-bold">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Save As PDF...
            </button>

            <button onclick="saveAsWord()" class="btn btn-info text-white btn-sm px-3 fw-bold">
                <i class="bi bi-file-earmark-word-fill me-1"></i> Save As Word...
            </button>

            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Print
            </button>
        </div>

    </div>
</div>

<div class="container" style="max-width: 820px;">

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show no-print mb-3 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= e($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show no-print mb-3 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="document-wrapper" id="document-wrapper">

        <div class="clinic-header">
            <div class="clinic-brand">
                <img src="../../img/logo.jpg" alt="VitalCore Logo" class="clinic-logo" onerror="this.style.display='none';">
                <div>
                    <h1 class="clinic-name" style="color:#0a49c4 !important;">Family Planning Chart</h1>
                    <p class="clinic-sub">Clinical Patient Record & Health Assessment</p>
                </div>
            </div>

            <div class="text-end">
                <span class="doc-title-badge">FAMILY PLANNING RECORD</span>
                <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                    <strong>Document Date:</strong> <?= $doc_date; ?>
                </div>
            </div>
        </div>

        <div class="section-header">
            <i class="bi bi-person-lines-fill text-primary"></i> Client Information
        </div>

        <div class="info-grid">
            <div class="info-item"><span class="info-label">Patient ID:</span><span class="info-value">#PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></span></div>
            <div class="info-item"><span class="info-label">Full Name:</span><span class="info-value"><?= e($patient_name); ?></span></div>
            <div class="info-item"><span class="info-label">Gender:</span><span class="info-value"><?= e($gender); ?></span></div>
            <div class="info-item"><span class="info-label">Age:</span><span class="info-value"><?= e($age); ?> years</span></div>
            <div class="info-item"><span class="info-label">Birth Date:</span><span class="info-value"><?= e($birth_date); ?></span></div>
            <div class="info-item"><span class="info-label">Civil Status:</span><span class="info-value"><?= e($civil_status); ?></span></div>
            <div class="info-item"><span class="info-label">Blood Type:</span><span class="info-value"><?= e($blood_type); ?></span></div>
            <div class="info-item"><span class="info-label">Contact No:</span><span class="info-value"><?= e($contact_number); ?></span></div>
            <div class="info-item" style="grid-column: span 2;"><span class="info-label">Address:</span><span class="info-value"><?= e($address); ?></span></div>
            <div class="info-item" style="grid-column: span 2;"><span class="info-label">Last Visit:</span><span class="info-value text-primary"><?= e($last_visited); ?></span></div>
        </div>

        <div class="section-header">
            <i class="bi bi-clipboard2-pulse text-primary"></i> Reproductive History
        </div>

        <div class="info-grid">
            <div class="info-item"><span class="info-label">Gravida:</span><span class="info-value"><?= $gravida; ?></span></div>
            <div class="info-item"><span class="info-label">Para:</span><span class="info-value"><?= $para_count; ?></span></div>
            <div class="info-item"><span class="info-label">Living Children:</span><span class="info-value"><?= $living_children; ?></span></div>
            <div class="info-item"><span class="info-label">Last Menstrual Period:</span><span class="info-value"><?= e($lmp); ?></span></div>
            <div class="info-item"><span class="info-label">Menstrual Cycle:</span><span class="info-value"><?= $menstrual_cycle; ?></span></div>
            <div class="info-item"><span class="info-label">Partner Name:</span><span class="info-value"><?= $partner_name; ?></span></div>
            <div class="info-item" style="grid-column: span 2;"><span class="info-label">Occupation:</span><span class="info-value"><?= $occupation; ?></span></div>
        </div>

        <div class="section-header">
            <i class="bi bi-heart-pulse-fill text-danger"></i> Vital Signs & Physical Measurements
        </div>

        <div class="fp-section-note">
            Latest measurements recorded by the VitalCore kiosk.
        </div>

        <table class="vitals-table">
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Measured Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>Blood Pressure</strong></td><td><?= $bp_disp; ?></td><td><span class="status-badge <?= $bp_class; ?>"><?= $bp_status; ?></span></td></tr>
                <tr><td><strong>Heart Rate</strong></td><td><?= $heart_rate_disp; ?></td><td><span class="status-badge <?= $heart_class; ?>"><?= $heart_status; ?></span></td></tr>
                <tr><td><strong>SpO₂</strong></td><td><?= $spo2_disp; ?></td><td><span class="status-badge <?= $spo2_class; ?>"><?= $spo2_status; ?></span></td></tr>
                <tr><td><strong>Body Temperature</strong></td><td><?= $temp_disp; ?></td><td><span class="status-badge <?= $temp_class; ?>"><?= $temp_status; ?></span></td></tr>
                <tr><td><strong>Body Weight</strong></td><td><?= $weight_disp; ?></td><td><span class="status-badge badge-secondary"><?= $weight_val !== null ? 'Recorded' : '--'; ?></span></td></tr>
                <tr><td><strong>Height</strong></td><td><?= $height_disp; ?></td><td><span class="status-badge badge-secondary"><?= $height_val !== null ? 'Recorded' : '--'; ?></span></td></tr>
                <tr><td><strong>BMI</strong></td><td><?= $bmi_disp; ?></td><td><span class="status-badge <?= $bmi_class; ?>"><?= $bmi_status; ?></span></td></tr>
            </tbody>
        </table>

        <div class="section-header">
            <i class="bi bi-shield-check text-primary"></i> Family Planning Assessment
        </div>

        <table class="vitals-table">
            <thead><tr><th>Assessment</th><th>Result</th></tr></thead>
            <tbody>
                <tr><td><strong>Pregnancy Status</strong></td><td><?= $pregnancy_status; ?></td></tr>
                <tr><td><strong>Medical Eligibility</strong></td><td><?= $eligibility_status; ?></td></tr>
                <tr><td><strong>Counseling Provided</strong></td><td><?= $counseling_status; ?></td></tr>
            </tbody>
        </table>

        <div class="section-header">
            <i class="bi bi-heart-fill text-primary"></i> Family Planning Method
        </div>

        <div class="fp-method-box">
            <div class="text-muted" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px;">
                Selected Family Planning Method
            </div>
            <div class="fp-method-value mt-1">
                <i class="bi bi-check-circle-fill me-1"></i> <?= $fp_method; ?>
            </div>
        </div>

        <div class="section-header">
            <i class="bi bi-calendar-check text-primary"></i> Service Details & Schedule
        </div>

        <table class="vitals-table">
            <thead><tr><th>Information</th><th>Details</th></tr></thead>
            <tbody>
                <tr><td><strong>Date Started</strong></td><td><?= e($date_started); ?></td></tr>
                <tr><td><strong>Next Follow-Up Schedule</strong></td><td><?= e($next_schedule); ?></td></tr>
                <tr><td><strong>Service Provider</strong></td><td><?= e($provider_name); ?></td></tr>
            </tbody>
        </table>

        <div class="section-header">
            <i class="bi bi-journal-text text-primary"></i> Remarks & Recommendations
        </div>

        <div class="notes-box remarks-content">
            <?= $remarks; ?>
        </div>

        <div class="signature-section">
            <div class="signature-line">
                Client Signature
                <div class="text-muted fw-normal" style="font-size: 0.72rem;">Printed Name: <?= e($patient_name); ?></div>
            </div>
            <div class="signature-line">
                Family Planning Provider
                <div class="text-muted fw-normal" style="font-size: 0.72rem;">Provider: <?= e($provider_name); ?></div>
            </div>
        </div>

        <div class="doc-footer">
            CONFIDENTIAL MEDICAL RECORD • FOR AUTHORIZED PERSONNEL ONLY • VITALCORE HEALTH INFORMATION SYSTEM
        </div>

    </div>

</div>

<!-- INPUT MODAL -->
<div class="modal fade no-print" id="familyPlanningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-clipboard2-pulse text-primary me-2"></i> Family Planning Record
                    </h5>
                    <small class="text-muted">Patient: <?= e($patient_name); ?></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <h6 class="fw-bold text-primary border-bottom pb-2">Reproductive History</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Gravida</label>
                            <input type="number" name="gravida" class="form-control" min="0" value="<?= e($fp['gravida']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Para</label>
                            <input type="number" name="para_count" class="form-control" min="0" value="<?= e($fp['para_count']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Living Children</label>
                            <input type="number" name="living_children" class="form-control" min="0" value="<?= e($fp['living_children']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Partner Name</label>
                            <input type="text" name="partner_name" class="form-control" value="<?= e($fp['partner_name']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="occupation" class="form-control" value="<?= e($fp['occupation']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Last Menstrual Period</label>
                            <input type="date" name="lmp" class="form-control" value="<?= e($fp['lmp']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Menstrual Cycle</label>
                            <select name="menstrual_cycle" class="form-select">
                                <option value="">Select</option>
                                <option value="Regular" <?= $fp['menstrual_cycle'] === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                <option value="Irregular" <?= $fp['menstrual_cycle'] === 'Irregular' ? 'selected' : ''; ?>>Irregular</option>
                            </select>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2">Family Planning Assessment</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Pregnancy Status</label>
                            <select name="pregnancy_status" class="form-select">
                                <option value="">Select</option>
                                <option value="Not Pregnant" <?= $fp['pregnancy_status'] === 'Not Pregnant' ? 'selected' : ''; ?>>Not Pregnant</option>
                                <option value="Pregnant" <?= $fp['pregnancy_status'] === 'Pregnant' ? 'selected' : ''; ?>>Pregnant</option>
                                <option value="Unknown" <?= $fp['pregnancy_status'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Medical Eligibility</label>
                            <select name="eligibility_status" class="form-select">
                                <option value="">Select</option>
                                <option value="Eligible" <?= $fp['eligibility_status'] === 'Eligible' ? 'selected' : ''; ?>>Eligible</option>
                                <option value="Not Eligible" <?= $fp['eligibility_status'] === 'Not Eligible' ? 'selected' : ''; ?>>Not Eligible</option>
                                <option value="Pending Assessment" <?= $fp['eligibility_status'] === 'Pending Assessment' ? 'selected' : ''; ?>>Pending Assessment</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Counseling Provided</label>
                            <select name="counseling_status" class="form-select">
                                <option value="">Select</option>
                                <option value="Yes" <?= $fp['counseling_status'] === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?= $fp['counseling_status'] === 'No' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2">Family Planning Method</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Selected Method</label>
                            <select name="fp_method" class="form-select" required>
                                <option value="">Select Method</option>
                                <option value="DMPA Injectable" <?= $fp['fp_method'] === 'DMPA Injectable' ? 'selected' : ''; ?>>DMPA Injectable</option>
                                <option value="Combined Oral Contraceptive Pills" <?= $fp['fp_method'] === 'Combined Oral Contraceptive Pills' ? 'selected' : ''; ?>>Combined Oral Contraceptive Pills</option>
                                <option value="Progestin-Only Pills" <?= $fp['fp_method'] === 'Progestin-Only Pills' ? 'selected' : ''; ?>>Progestin-Only Pills</option>
                                <option value="Condom" <?= $fp['fp_method'] === 'Condom' ? 'selected' : ''; ?>>Condom</option>
                                <option value="IUD" <?= $fp['fp_method'] === 'IUD' ? 'selected' : ''; ?>>IUD</option>
                                <option value="Implant" <?= $fp['fp_method'] === 'Implant' ? 'selected' : ''; ?>>Implant</option>
                                <option value="Natural Family Planning" <?= $fp['fp_method'] === 'Natural Family Planning' ? 'selected' : ''; ?>>Natural Family Planning</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Date Started</label>
                            <input type="date" name="date_started" class="form-control" value="<?= e($fp['date_started']); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Next Schedule</label>
                            <input type="date" name="next_schedule" class="form-control" value="<?= e($fp['next_schedule']); ?>">
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2">Service Provider</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Provider Name</label>
                            <input type="text" name="provider_name" class="form-control" value="<?= e($fp['provider_name']); ?>" placeholder="e.g. Nurse Maria Santos">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Enter counseling notes, recommendations, or other remarks..."><?= e($fp['remarks']); ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_family_planning" class="btn btn-primary fw-bold">
                        <i class="bi bi-save me-1"></i> Save Family Planning Record
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
const defaultFileName = <?= json_encode($safe_filename); ?>;

const remarksInput = document.querySelector('textarea[name="remarks"]');
if (remarksInput) {
    const adjustHeight = () => {
        remarksInput.style.height = 'auto';
        remarksInput.style.height = Math.max(110, remarksInput.scrollHeight + 4) + 'px';
    };
    remarksInput.addEventListener('input', adjustHeight);
    const modalEl = document.getElementById('familyPlanningModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', adjustHeight);
    }
}

async function saveAsPDF() {
    const element = document.getElementById('document-wrapper');
    try {
        const pdfBlob = await html2pdf()
            .set({
                margin: [6, 6, 6, 6],
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            })
            .from(element)
            .output('blob');

        if ('showSaveFilePicker' in window) {
            try {
                const handle = await window.showSaveFilePicker({
                    suggestedName: defaultFileName + '.pdf',
                    types: [{ description: 'PDF Document (*.pdf)', accept: { 'application/pdf': ['.pdf'] } }]
                });
                const writable = await handle.createWritable();
                await writable.write(pdfBlob);
                await writable.close();
            } catch (err) {
                if (err.name !== 'AbortError') fallbackDownload(pdfBlob, defaultFileName + '.pdf');
            }
        } else {
            fallbackDownload(pdfBlob, defaultFileName + '.pdf');
        }
    } catch (error) {
        console.error(error);
        alert('Unable to generate the PDF. Please try again.');
    }
}

async function saveAsWord() {
    const element = document.getElementById('document-wrapper');
    const clone = element.cloneNode(true);

    const clinicHeader = clone.querySelector('.clinic-header');
    if (clinicHeader) {
        const logoImg = clinicHeader.querySelector('.clinic-logo');
        const logoSrc = logoImg ? logoImg.src : '';
        const textEnd = clinicHeader.querySelector('.text-end');
        const textEndHTML = textEnd ? textEnd.innerHTML : '';

        const headerTable = document.createElement('table');
        headerTable.style.cssText = 'width:100%; border-collapse:collapse; margin-bottom:12px; border-bottom:2px solid #0a49c4; padding-bottom:8px;';
        headerTable.innerHTML = `
            <tr>
                <td style="vertical-align:middle; text-align:left;">
                    <table style="border-collapse:collapse;">
                        <tr>
                            <td style="vertical-align:middle; padding-right:12px;">
                                <table style="border-collapse:collapse; background-color:#f1f5f9; border:1px solid #e2e8f0; border-radius:10px;">
                                    <tr>
                                        <td style="padding:6px; text-align:center; vertical-align:middle;">
                                            <img src="${logoSrc}" width="40" height="40" style="width:40px !important; height:40px !important; display:block; border-radius:6px; object-fit:cover;">
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td style="vertical-align:middle;">
                                <div style="font-size:20px; font-weight:800; color:#0a49c4; line-height:1.1; font-family:'Segoe UI', Tahoma, Arial, sans-serif;">VitalCore</div>
                                <div style="font-size:11px; color:#64748b; margin-top:2px; font-family:'Segoe UI', Tahoma, Arial, sans-serif;">Family Planning Service</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="vertical-align:middle; text-align:right;">${textEndHTML}</td>
            </tr>
        `;
        clinicHeader.parentNode.replaceChild(headerTable, clinicHeader);
    }

    const infoGrids = clone.querySelectorAll('.info-grid');
    infoGrids.forEach(grid => {
        const items = Array.from(grid.querySelectorAll('.info-item'));
        const table = document.createElement('table');
        table.style.cssText = 'width:100%; border-collapse:collapse; background-color:#f8fafc; border:1px solid #dbe3ef; border-radius:6px; margin-bottom:10px; font-size:11px;';
        
        let currentRow = document.createElement('tr');
        let colCount = 0;

        items.forEach(item => {
            const isFullWidth = item.style.gridColumn && item.style.gridColumn.includes('span 2');
            const td = document.createElement('td');
            td.style.cssText = 'padding:5px 8px; vertical-align:top; font-family:"Segoe UI", Tahoma, Arial, sans-serif;';
            td.innerHTML = item.innerHTML;

            if (isFullWidth) {
                if (colCount === 1) {
                    const emptyTd = document.createElement('td');
                    currentRow.appendChild(emptyTd);
                    table.appendChild(currentRow);
                    currentRow = document.createElement('tr');
                    colCount = 0;
                }
                td.setAttribute('colspan', '2');
                td.style.width = '100%';
                const fullRow = document.createElement('tr');
                fullRow.appendChild(td);
                table.appendChild(fullRow);
            } else {
                td.style.width = '50%';
                currentRow.appendChild(td);
                colCount++;
                if (colCount === 2) {
                    table.appendChild(currentRow);
                    currentRow = document.createElement('tr');
                    colCount = 0;
                }
            }
        });

        if (colCount === 1) {
            const emptyTd = document.createElement('td');
            emptyTd.style.width = '50%';
            currentRow.appendChild(emptyTd);
            table.appendChild(currentRow);
        }

        grid.parentNode.replaceChild(table, grid);
    });

    const badges = clone.querySelectorAll('.status-badge, .doc-title-badge');
    badges.forEach(b => {
        b.style.display = 'inline-block';
        b.style.padding = '3px 8px';
        b.style.borderRadius = '10px';
        b.style.fontSize = '10px';
        b.style.fontWeight = 'bold';
        if (b.classList.contains('badge-success')) {
            b.style.backgroundColor = '#d1e7dd'; b.style.color = '#0f5132';
        } else if (b.classList.contains('badge-danger')) {
            b.style.backgroundColor = '#f8d7da'; b.style.color = '#842029';
        } else if (b.classList.contains('badge-warning')) {
            b.style.backgroundColor = '#fff3cd'; b.style.color = '#664d03';
        } else if (b.classList.contains('badge-info')) {
            b.style.backgroundColor = '#cff4fc'; b.style.color = '#055160';
        } else if (b.classList.contains('badge-secondary')) {
            b.style.backgroundColor = '#e2e3e5'; b.style.color = '#41464b';
        } else if (b.classList.contains('doc-title-badge')) {
            b.style.backgroundColor = '#eff6ff'; b.style.color = '#0a49c4'; b.style.border = '1px solid #bfdbfe'; b.style.padding = '4px 10px'; b.style.borderRadius = '6px';
        }
    });

    const sigSection = clone.querySelector('.signature-section');
    if (sigSection) {
        const sigLines = sigSection.querySelectorAll('.signature-line');
        if (sigLines.length >= 2) {
            const sigTable = document.createElement('table');
            sigTable.style.cssText = 'width:100%; border-collapse:collapse; margin-top:20px;';
            sigTable.innerHTML = `
                <tr>
                    <td style="width:45%; text-align:center; vertical-align:top; border-top:1px solid #64748b; padding-top:4px; font-size:11px; font-weight:bold;">${sigLines[0].innerHTML}</td>
                    <td style="width:10%;"></td>
                    <td style="width:45%; text-align:center; vertical-align:top; border-top:1px solid #64748b; padding-top:4px; font-size:11px; font-weight:bold;">${sigLines[1].innerHTML}</td>
                </tr>
            `;
            sigSection.parentNode.replaceChild(sigTable, sigSection);
        }
    }

    const wordStyles = `
        @page WordSection1 {
            size: 595.3pt 841.9pt;
            margin: 0.35in 0.45in 0.35in 0.45in;
            mso-header-margin: 15pt;
            mso-footer-margin: 15pt;
            mso-paper-source: 0;
        }
        div.WordSection1 { page: WordSection1; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important; font-size: 11px !important; color: #1e293b !important; background: #ffffff !important; margin: 0 !important; padding: 0 !important; }
        .document-wrapper { width: 100% !important; background: #ffffff !important; padding: 0 !important; margin: 0 !important; }
        .section-header { color: #0a49c4 !important; font-weight: bold !important; font-size: 11px !important; margin-top: 8px !important; margin-bottom: 4px !important; }
        .info-label { font-weight: bold !important; color: #475569 !important; }
        .info-value { color: #0f172a !important; font-weight: 600 !important; }
        .vitals-table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 8px !important; font-size: 10.5px !important; }
        .vitals-table th, .vitals-table td { border: 1px solid #dbe3ef !important; padding: 4px 7px !important; }
        .vitals-table th { background-color: #f8fafc !important; color: #475569 !important; font-size: 9.5px !important; text-transform: uppercase !important; }
        .fp-method-box { border: 1px solid #dbe3ef !important; background-color: #f8fafc !important; padding: 6px 10px !important; border-radius: 6px !important; margin-bottom: 8px !important; }
        .fp-method-value { font-size: 12px !important; font-weight: bold !important; color: #0a49c4 !important; }
        .notes-box { border: 1px dashed #cbd5e1 !important; background-color: #f8fafc !important; padding: 6px 10px !important; min-height: 35px !important; margin-bottom: 10px !important; font-size: 10.5px !important; }
        .doc-footer { margin-top: 10px !important; text-align: center !important; font-size: 8.5px !important; color: #94a3b8 !important; border-top: 1px solid #e2e8f0 !important; padding-top: 4px !important; }
    `;

    const sourceHTML = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <title>Family Planning Record</title>
            <!--[if gte mso 9]>
            <xml>
                <w:WordDocument>
                    <w:View>Print</w:View>
                    <w:Zoom>100</w:Zoom>
                    <w:DoNotOptimizeForBrowser/>
                </w:WordDocument>
            </xml>
            <![endif]-->
            <style>${wordStyles}</style>
        </head>
        <body>
            <div class="WordSection1">${clone.innerHTML}</div>
        </body>
        </html>
    `;

    const wordBlob = new Blob(['\ufeff' + sourceHTML], { type: 'application/msword' });

    if ('showSaveFilePicker' in window) {
        try {
            const handle = await window.showSaveFilePicker({
                suggestedName: defaultFileName + '.doc',
                types: [{ description: 'Word Document (*.doc)', accept: { 'application/msword': ['.doc'] } }]
            });
            const writable = await handle.createWritable();
            await writable.write(wordBlob);
            await writable.close();
        } catch (err) {
            if (err.name !== 'AbortError') fallbackDownload(wordBlob, defaultFileName + '.doc');
        }
    } else {
        fallbackDownload(wordBlob, defaultFileName + '.doc');
    }
}

function fallbackDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/theme.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const savedTheme = localStorage.getItem('staff_theme');
    if (savedTheme === 'dark') {
        if (document.body) {
            document.body.classList.add('dark-mode');
            document.body.setAttribute('data-bs-theme', 'dark');
        }
    }
});
</script>

</body>
</html>
