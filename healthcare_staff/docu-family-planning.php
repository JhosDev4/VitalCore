<?php

/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../db_conn.php');

/* =========================================================
   ACCESS CONTROL & CSRF INITIALIZATION
========================================================= */

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Staff'], true)
) {
    header("Location: ../login.php");
    exit();
}

// CSRF token for requests
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Patient not found.");
}

$patient_id     = (int) $_GET['id'];
$measurement_id = isset($_GET['measurement_id']) && is_numeric($_GET['measurement_id']) ? (int) $_GET['measurement_id'] : 0;

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

$temp        = null;
$weight      = null;
$height      = null;
$bmi         = null;
$heart_rate  = null;
$spo2        = null;
$systolic    = null;
$diastolic   = null;
$current_mid = 0;

$last_visited = 'No visits recorded';
$doc_date     = date('F d, Y');
$visit_date   = '';
$visit_time12 = '';
$visit_time24 = '';

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
    $current_mid = (int) $measurement['id'];

    $temp       = $measurement['temperature'] ?? null;
    $weight     = $measurement['weight'] ?? null;
    $height     = $measurement['height'] ?? null;
    $bmi        = $measurement['bmi'] ?? null;
    $heart_rate = $measurement['heart_rate'] ?? null;
    $spo2       = $measurement['spo2'] ?? null;
    $systolic   = $measurement['systolic'] ?? null;
    $diastolic  = $measurement['diastolic'] ?? null;

    if (!empty($measurement['created_at'])) {
        $ts           = strtotime($measurement['created_at']);
        $last_visited = date('F d, Y - h:i A', $ts);
        $doc_date     = date('F d, Y', $ts);
        $visit_date   = date('F d, Y', $ts);
        $visit_time12 = date('h:i A', $ts);
        $visit_time24 = date('H:i', $ts);
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

// JSON array for the Edit Vitals modal
$vitals_js = json_encode([
    'temperature' => $temp_val,
    'weight'      => $weight_val,
    'height'      => $height_val,
    'heart_rate'  => $heart_rate_val,
    'spo2'        => $spo2_val,
    'systolic'    => $systolic_val,
    'diastolic'   => $diastolic_val,
]);

/* =========================================================
   4. DISPLAY VALUES
========================================================= */

$temp_disp       = ($temp_val !== null) ? number_format($temp_val, 1) . ' °C' : '--';
$weight_disp     = ($weight_val !== null) ? number_format($weight_val, 1) . ' kg' : '--';
$height_disp     = ($height_val !== null) ? number_format($height_val, 1) . ' cm' : '--';

if ($height_val !== null) {
    $total_inches  = $height_val / 2.54;
    $feet          = (int) floor($total_inches / 12);
    $inches        = (int) round(fmod($total_inches, 12));
    if ($inches === 12) { $feet++; $inches = 0; }
    $height_ft_disp = "{$feet}'{$inches}\"";
} else {
    $height_ft_disp = '--';
}

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

$weight_status = ($weight_val !== null) ? 'Recorded' : '--';
$height_status = ($height_val !== null) ? 'Recorded' : '--';

/* =========================================================
   6. FAMILY PLANNING FORM PROCESSING
========================================================= */

$success_message = '';
$error_message   = '';

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

    if ($fp_method === '') {
        $error_message = 'Please select a family planning method.';
    } else {
        $effective_mid = ($measurement_id > 0) ? $measurement_id : $current_mid;

        /* Check if record already exists */
        if ($effective_mid > 0) {
            $checkStmt = mysqli_prepare(
                $conn,
                "SELECT id FROM family_planning_records WHERE user_id = ? AND measurement_id = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($checkStmt, "ii", $patient_id, $effective_mid);
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
            /* UPDATE EXISTING RECORD */
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
            /* INSERT NEW RECORD */
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

            $m_id_param = $effective_mid > 0 ? $effective_mid : null;

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
   7. FETCH FAMILY PLANNING RECORD FOR DISPLAY
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
    } elseif ($current_mid > 0) {
        $fpQuery = mysqli_query(
            $conn,
            "SELECT * FROM family_planning_records WHERE user_id = $patient_id AND (measurement_id = $current_mid OR measurement_id IS NULL) ORDER BY id DESC LIMIT 1"
        );
    } else {
        $fpQuery = mysqli_query(
            $conn,
            "SELECT * FROM family_planning_records WHERE user_id = $patient_id ORDER BY id DESC LIMIT 1"
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
   8. DISPLAY VALUES & PATIENT DATA
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
$remarks            = !empty($fp['remarks']) ? nl2br(e($fp['remarks'])) : '<span data-i18n="val_no_remarks">No remarks recorded.</span>';

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
    <!-- PRE-LOAD DARK MODE, TEXT SIZE & NIGHT LIGHT (Prevents UI flicker on page load) -->
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('theme') || localStorage.getItem('staff_theme');
                if (savedTheme === 'dark') {
                    document.documentElement.classList.add('dark-mode');
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark-mode');
                    document.documentElement.setAttribute('data-bs-theme', 'light');
                }
                const savedTextSize = localStorage.getItem("textSize");
                if (savedTextSize) {
                    const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
                    document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
                }
                const nlVal = localStorage.getItem("nightLight") || localStorage.getItem("staff_night_light") || localStorage.getItem("night_light");
                if (nlVal === "enabled" || nlVal === "true" || nlVal === "1" || nlVal === "on") {
                    document.documentElement.classList.add("night-light-active");
                }
            } catch (e) { /* localStorage unavailable */ }
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
        /* Night Light Warm Amber Filter Overlay */
        #nightLightOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(255, 140, 0, 0.18) !important;
            pointer-events: none;
            z-index: 999999;
            display: none;
        }

        html.night-light-active #nightLightOverlay {
            display: block !important;
        }

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
            .no-print,
            #nightLightOverlay {
                display: none !important;
            }
            body { background: #fff !important; }
            .document-wrapper { box-shadow: none !important; margin: 0 !important; width: 100% !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>

<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay" data-html2canvas-ignore="true"></div>

<div class="no-print bg-white border-bottom p-3 mb-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 820px;">

        <a href="patient-history-list.php?user_id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> <span data-i18n="btn_back_history">Back to Patient History</span>
        </a>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-warning btn-sm px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#familyPlanningModal">
                <i class="bi bi-pencil-square me-1"></i>
                <?= !empty($fp['fp_method']) ? '<span data-i18n="btn_edit_record">Edit Record</span>' : '<span data-i18n="btn_add_record">Add Record</span>'; ?>
            </button>

            <button onclick="saveAsPDF()" class="btn btn-success btn-sm px-3 fw-bold">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> <span data-i18n="btn_save_pdf">Save As PDF...</span>
            </button>

            <button onclick="saveAsWord()" class="btn btn-info text-white btn-sm px-3 fw-bold">
                <i class="bi bi-file-earmark-word-fill me-1"></i> <span data-i18n="btn_save_word">Save As Word...</span>
            </button>

            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
                <i class="bi bi-printer-fill me-1"></i> <span data-i18n="btn_print">Print</span>
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
                    <h1 class="clinic-name" style="color:#0a49c4 !important;" data-i18n="clinic_header_title">Family Planning Chart</h1>
                    <p class="clinic-sub" data-i18n="clinic_header_sub">Clinical Patient Record & Health Assessment</p>
                </div>
            </div>

            <div class="text-end">
                <span class="doc-title-badge" data-i18n="doc_title_badge">FAMILY PLANNING RECORD</span>
                <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                    <strong data-i18n="lbl_doc_date">Document Date:</strong> <?= $doc_date; ?>
                </div>
            </div>
        </div>

        <div class="section-header">
            <i class="bi bi-person-lines-fill text-primary"></i> <span data-i18n="sec_client_info">Client Information</span>
        </div>

        <div class="info-grid">
            <div class="info-item"><span class="info-label" data-i18n="lbl_patient_id">Patient ID:</span><span class="info-value">#PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_full_name">Full Name:</span><span class="info-value"><?= e($patient_name); ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_gender">Gender:</span><span class="info-value"><?= e($gender); ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_age">Age:</span><span class="info-value"><?= e($age); ?> <span data-i18n="lbl_years">years</span></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_birth_date">Birth Date:</span><span class="info-value"><?= e($birth_date); ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_civil_status">Civil Status:</span><span class="info-value"><?= e($civil_status); ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_blood_type">Blood Type:</span><span class="info-value"><?= e($blood_type); ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_contact_no">Contact No:</span><span class="info-value"><?= e($contact_number); ?></span></div>
            <div class="info-item" style="grid-column: span 2;"><span class="info-label" data-i18n="lbl_address">Address:</span><span class="info-value"><?= e($address); ?></span></div>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label" data-i18n="lbl_last_visit">Last Visit:</span>
                <span class="info-value text-primary">
                    <?php if (!empty($visit_date)): ?>
                        <span class="visit-date-display"><?= e($visit_date); ?></span> - 
                        <span class="visit-time-display" data-time12="<?= e($visit_time12); ?>" data-time24="<?= e($visit_time24); ?>"><?= e($visit_time12); ?></span>
                    <?php else: ?>
                        <span data-i18n="val_no_visits">No visits recorded</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="section-header">
            <i class="bi bi-clipboard2-pulse text-primary"></i> <span data-i18n="sec_repro_history">Reproductive History</span>
        </div>

        <div class="info-grid">
            <div class="info-item"><span class="info-label" data-i18n="lbl_gravida">Gravida:</span><span class="info-value"><?= $gravida; ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_para">Para:</span><span class="info-value"><?= $para_count; ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_living_children">Living Children:</span><span class="info-value"><?= $living_children; ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_lmp">Last Menstrual Period:</span><span class="info-value"><?= e($lmp); ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_menstrual_cycle">Menstrual Cycle:</span><span class="info-value"><?= $menstrual_cycle; ?></span></div>
            <div class="info-item"><span class="info-label" data-i18n="lbl_partner_name">Partner Name:</span><span class="info-value"><?= $partner_name; ?></span></div>
            <div class="info-item" style="grid-column: span 2;"><span class="info-label" data-i18n="lbl_occupation">Occupation:</span><span class="info-value"><?= $occupation; ?></span></div>
        </div>

        <div class="section-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-heart-pulse-fill text-danger"></i> <span data-i18n="sec_vitals_telemetry">Vital Signs & Physical Measurements</span></span>
            <?php if ($current_mid > 0): ?>
            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 fw-bold no-print"
                    data-html2canvas-ignore="true" onclick="openEditVitals()" style="font-size:0.7rem;">
                <i class="bi bi-pencil-square me-1"></i> <span data-i18n="btn_edit_vitals">Edit Vitals</span>
            </button>
            <?php endif; ?>
        </div>

        <div class="fp-section-note" data-i18n="note_vitals_kiosk">
            Latest measurements recorded by the VitalCore kiosk.
        </div>

        <table class="vitals-table">
            <thead>
                <tr>
                    <th data-i18n="tbl_param">Parameter</th>
                    <th data-i18n="tbl_value">Measured Value</th>
                    <th data-i18n="tbl_status">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong data-i18n="param_bp">Blood Pressure</strong></td><td><?= $bp_disp; ?></td><td><span class="status-badge <?= $bp_class; ?>"><?= $bp_status; ?></span></td></tr>
                <tr><td><strong data-i18n="param_heart_rate">Heart Rate</strong></td><td><?= $heart_rate_disp; ?></td><td><span class="status-badge <?= $heart_class; ?>"><?= $heart_status; ?></span></td></tr>
                <tr><td><strong data-i18n="param_spo2">SpO₂</strong></td><td><?= $spo2_disp; ?></td><td><span class="status-badge <?= $spo2_class; ?>"><?= $spo2_status; ?></span></td></tr>
                <tr><td><strong data-i18n="param_temp">Body Temperature</strong></td><td><?= $temp_disp; ?></td><td><span class="status-badge <?= $temp_class; ?>"><?= $temp_status; ?></span></td></tr>
                <tr><td><strong data-i18n="param_weight">Body Weight</strong></td><td><?= $weight_disp; ?></td><td><span class="status-badge badge-secondary"><?= $weight_status; ?></span></td></tr>
                <tr>
                    <td><strong data-i18n="param_height">Height</strong></td>
                    <td>
                        <?= $height_disp ?>
                        <?php if ($height_val !== null): ?>
                            <span class="text-muted" style="font-size:0.85em;">&nbsp;(<?= $height_ft_disp ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge badge-secondary"><?= $height_status ?></span></td>
                </tr>
                <tr><td><strong data-i18n="param_bmi">BMI</strong></td><td><?= $bmi_disp; ?></td><td><span class="status-badge <?= $bmi_class; ?>"><?= $bmi_status; ?></span></td></tr>
            </tbody>
        </table>

        <div class="section-header">
            <i class="bi bi-shield-check text-primary"></i> <span data-i18n="sec_fp_assessment">Family Planning Assessment</span>
        </div>

        <table class="vitals-table">
            <thead><tr><th data-i18n="tbl_assessment">Assessment</th><th data-i18n="tbl_result">Result</th></tr></thead>
            <tbody>
                <tr><td><strong data-i18n="param_pregnancy_status">Pregnancy Status</strong></td><td><?= $pregnancy_status; ?></td></tr>
                <tr><td><strong data-i18n="param_eligibility_status">Medical Eligibility</strong></td><td><?= $eligibility_status; ?></td></tr>
                <tr><td><strong data-i18n="param_counseling_status">Counseling Provided</strong></td><td><?= $counseling_status; ?></td></tr>
            </tbody>
        </table>

        <div class="section-header">
            <i class="bi bi-heart-fill text-primary"></i> <span data-i18n="sec_fp_method">Family Planning Method</span>
        </div>

        <div class="fp-method-box">
            <div class="text-muted" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px;" data-i18n="lbl_selected_method">
                Selected Family Planning Method
            </div>
            <div class="fp-method-value mt-1">
                <i class="bi bi-check-circle-fill me-1"></i> <?= $fp_method; ?>
            </div>
        </div>

        <div class="section-header">
            <i class="bi bi-calendar-check text-primary"></i> <span data-i18n="sec_service_details">Service Details & Schedule</span>
        </div>

        <table class="vitals-table">
            <thead><tr><th data-i18n="tbl_information">Information</th><th data-i18n="tbl_details">Details</th></tr></thead>
            <tbody>
                <tr><td><strong data-i18n="param_date_started">Date Started</strong></td><td><?= e($date_started); ?></td></tr>
                <tr><td><strong data-i18n="param_next_schedule">Next Follow-Up Schedule</strong></td><td><?= e($next_schedule); ?></td></tr>
                <tr><td><strong data-i18n="param_provider">Service Provider</strong></td><td><?= e($provider_name); ?></td></tr>
            </tbody>
        </table>

        <div class="section-header">
            <i class="bi bi-journal-text text-primary"></i> <span data-i18n="sec_remarks">Remarks & Recommendations</span>
        </div>

        <div class="notes-box remarks-content">
            <?= $remarks; ?>
        </div>

        <div class="signature-section">
            <div class="signature-line">
                <span data-i18n="lbl_client_sig">Client Signature</span>
                <div class="text-muted fw-normal" style="font-size: 0.72rem;"><span data-i18n="lbl_printed_name">Printed Name:</span> <?= e($patient_name); ?></div>
            </div>
            <div class="signature-line">
                <span data-i18n="lbl_fp_provider_sig">Family Planning Provider</span>
                <div class="text-muted fw-normal" style="font-size: 0.72rem;"><span data-i18n="lbl_provider_colon">Provider:</span> <?= e($provider_name); ?></div>
            </div>
        </div>

        <div class="doc-footer" data-i18n="doc_footer">
            CONFIDENTIAL MEDICAL RECORD • FOR AUTHORIZED PERSONNEL ONLY • VITALCORE HEALTH INFORMATION SYSTEM
        </div>

    </div>

</div>

<!-- FAMILY PLANNING INPUT MODAL -->
<div class="modal fade no-print" id="familyPlanningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-clipboard2-pulse text-primary me-2"></i> <span data-i18n="modal_fp_title">Family Planning Record</span>
                    </h5>
                    <small class="text-muted"><span data-i18n="lbl_patient_prefix">Patient:</span> <?= e($patient_name); ?></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <h6 class="fw-bold text-primary border-bottom pb-2" data-i18n="sec_repro_history">Reproductive History</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" data-i18n="lbl_gravida">Gravida</label>
                            <input type="number" name="gravida" class="form-control" min="0" value="<?= e($fp['gravida']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" data-i18n="lbl_para">Para</label>
                            <input type="number" name="para_count" class="form-control" min="0" value="<?= e($fp['para_count']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" data-i18n="lbl_living_children">Living Children</label>
                            <input type="number" name="living_children" class="form-control" min="0" value="<?= e($fp['living_children']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" data-i18n="lbl_partner_name">Partner Name</label>
                            <input type="text" name="partner_name" class="form-control" value="<?= e($fp['partner_name']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" data-i18n="lbl_occupation">Occupation</label>
                            <input type="text" name="occupation" class="form-control" value="<?= e($fp['occupation']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" data-i18n="lbl_lmp">Last Menstrual Period</label>
                            <input type="date" name="lmp" class="form-control" value="<?= e($fp['lmp']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" data-i18n="lbl_menstrual_cycle">Menstrual Cycle</label>
                            <select name="menstrual_cycle" class="form-select">
                                <option value="">Select</option>
                                <option value="Regular" <?= $fp['menstrual_cycle'] === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                <option value="Irregular" <?= $fp['menstrual_cycle'] === 'Irregular' ? 'selected' : ''; ?>>Irregular</option>
                            </select>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2" data-i18n="sec_fp_assessment">Family Planning Assessment</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" data-i18n="param_pregnancy_status">Pregnancy Status</label>
                            <select name="pregnancy_status" class="form-select">
                                <option value="">Select</option>
                                <option value="Not Pregnant" <?= $fp['pregnancy_status'] === 'Not Pregnant' ? 'selected' : ''; ?>>Not Pregnant</option>
                                <option value="Pregnant" <?= $fp['pregnancy_status'] === 'Pregnant' ? 'selected' : ''; ?>>Pregnant</option>
                                <option value="Unknown" <?= $fp['pregnancy_status'] === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" data-i18n="param_eligibility_status">Medical Eligibility</label>
                            <select name="eligibility_status" class="form-select">
                                <option value="">Select</option>
                                <option value="Eligible" <?= $fp['eligibility_status'] === 'Eligible' ? 'selected' : ''; ?>>Eligible</option>
                                <option value="Not Eligible" <?= $fp['eligibility_status'] === 'Not Eligible' ? 'selected' : ''; ?>>Not Eligible</option>
                                <option value="Pending Assessment" <?= $fp['eligibility_status'] === 'Pending Assessment' ? 'selected' : ''; ?>>Pending Assessment</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" data-i18n="param_counseling_status">Counseling Provided</label>
                            <select name="counseling_status" class="form-select">
                                <option value="">Select</option>
                                <option value="Yes" <?= $fp['counseling_status'] === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?= $fp['counseling_status'] === 'No' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2" data-i18n="sec_fp_method">Family Planning Method</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" data-i18n="lbl_selected_method">Selected Method</label>
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
                            <label class="form-label" data-i18n="param_date_started">Date Started</label>
                            <input type="date" name="date_started" class="form-control" value="<?= e($fp['date_started']); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" data-i18n="param_next_schedule">Next Schedule</label>
                            <input type="date" name="next_schedule" class="form-control" value="<?= e($fp['next_schedule']); ?>">
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2" data-i18n="sec_service_provider">Service Provider</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" data-i18n="lbl_provider_name">Provider Name</label>
                            <input type="text" name="provider_name" class="form-control" value="<?= e($fp['provider_name']); ?>" placeholder="e.g. Nurse Maria Santos">
                        </div>

                        <div class="col-12">
                            <label class="form-label" data-i18n="lbl_remarks">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Enter counseling notes, recommendations, or other remarks..."><?= e($fp['remarks']); ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-i18n="btn_cancel">Cancel</button>
                    <button type="submit" name="save_family_planning" class="btn btn-primary fw-bold">
                        <i class="bi bi-save me-1"></i> <span data-i18n="btn_save_fp_record">Save Family Planning Record</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php if ($current_mid > 0): ?>
<!-- EDIT VITALS MODAL -->
<div class="modal fade no-print" id="editVitalsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1"></i> <span data-i18n="modal_title">Edit Vital Signs</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small mb-3" data-i18n="modal_alert">
          Use this only to correct sensor errors or inaccurate readings. Leave a field blank to clear it.
        </div>
        <div id="evError" class="alert alert-danger py-2 small d-none"></div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label small fw-bold" data-i18n="modal_systolic">Systolic (mmHg)</label>
            <input type="number" id="ev_systolic" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold" data-i18n="modal_diastolic">Diastolic (mmHg)</label>
            <input type="number" id="ev_diastolic" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold" data-i18n="modal_heart_rate">Heart Rate (BPM)</label>
            <input type="number" id="ev_heart_rate" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold" data-i18n="modal_spo2">SpO2 (%)</label>
            <input type="number" id="ev_spo2" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold" data-i18n="modal_temp">Temperature (°C)</label>
            <input type="number" step="0.1" id="ev_temperature" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold" data-i18n="modal_weight">Weight (kg)</label>
            <input type="number" step="0.1" id="ev_weight" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold" data-i18n="modal_height">Height (cm)</label>
            <input type="number" step="0.1" id="ev_height" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold" data-i18n="modal_bmi">BMI (automatic)</label>
            <input type="text" id="ev_bmi" class="form-control form-control-sm bg-light" readonly></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" data-i18n="btn_cancel">Cancel</button>
        <button type="button" class="btn btn-success btn-sm fw-bold" id="evSaveBtn" onclick="saveVitals()">
          <i class="bi bi-check-lg me-1"></i> <span data-i18n="btn_save_changes">Save Changes</span>
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const defaultFileName = <?= json_encode($safe_filename); ?>;

// ---- System Settings Integration (i18n, Theme, Night Light, Text Size, Time Format) ----
function applySystemSettings() {
    // 1. Theme (Dark Mode)
    const savedTheme = localStorage.getItem("theme") || localStorage.getItem("staff_theme");
    if (savedTheme === "dark") {
        document.documentElement.classList.add("dark-mode");
        document.documentElement.setAttribute("data-bs-theme", "dark");
        if (document.body) {
            document.body.classList.add("dark-mode");
            document.body.setAttribute("data-bs-theme", "dark");
        }
    } else {
        document.documentElement.classList.remove("dark-mode");
        document.documentElement.setAttribute("data-bs-theme", "light");
        if (document.body) {
            document.body.classList.remove("dark-mode");
            document.body.setAttribute("data-bs-theme", "light");
        }
    }

    // 2. Night Light (Amber Overlay) - supports 'enabled', 'true', '1', 'on'
    const nlVal = localStorage.getItem("nightLight") || localStorage.getItem("staff_night_light") || localStorage.getItem("night_light");
    const nightLightOn = nlVal === "enabled" || nlVal === "true" || nlVal === "1" || nlVal === "on";

    if (nightLightOn) {
        document.documentElement.classList.add("night-light-active");
    } else {
        document.documentElement.classList.remove("night-light-active");
    }

    const overlay = document.getElementById("nightLightOverlay");
    if (overlay) {
        overlay.style.display = nightLightOn ? "block" : "none";
    }

    // 3. Text Size Scaling
    const savedTextSize = localStorage.getItem("textSize");
    if (savedTextSize) {
        const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
        document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
    }

    // 4. Date & Time Format (12h vs 24h)
    const timeFormat = localStorage.getItem("dateTimeFormat");
    document.querySelectorAll(".visit-time-display").forEach(function(el) {
        if (timeFormat === "24h" || timeFormat === "24") {
            el.textContent = el.getAttribute("data-time24") || el.textContent;
        } else {
            el.textContent = el.getAttribute("data-time12") || el.textContent;
        }
    });

    // 5. Language Translations (i18n)
    const i18n = {
        en: {
            btn_back_history: "Back to Patient History",
            btn_edit_record: "Edit Record",
            btn_add_record: "Add Record",
            btn_save_pdf: "Save As PDF...",
            btn_save_word: "Save As Word...",
            btn_print: "Print",
            clinic_header_title: "Family Planning Chart",
            clinic_header_sub: "Clinical Patient Record & Health Assessment",
            doc_title_badge: "FAMILY PLANNING RECORD",
            lbl_doc_date: "Document Date:",
            sec_client_info: "Client Information",
            lbl_patient_id: "Patient ID:",
            lbl_full_name: "Full Name:",
            lbl_gender: "Gender:",
            lbl_age: "Age:",
            lbl_years: "years",
            lbl_birth_date: "Birth Date:",
            lbl_civil_status: "Civil Status:",
            lbl_blood_type: "Blood Type:",
            lbl_contact_no: "Contact No:",
            lbl_address: "Address:",
            lbl_last_visit: "Last Visit:",
            val_no_visits: "No visits recorded",
            sec_repro_history: "Reproductive History",
            lbl_gravida: "Gravida",
            lbl_para: "Para",
            lbl_living_children: "Living Children",
            lbl_lmp: "Last Menstrual Period",
            lbl_menstrual_cycle: "Menstrual Cycle",
            lbl_partner_name: "Partner Name",
            lbl_occupation: "Occupation",
            sec_vitals_telemetry: "Vital Signs & Physical Measurements",
            btn_edit_vitals: "Edit Vitals",
            note_vitals_kiosk: "Latest measurements recorded by the VitalCore kiosk.",
            tbl_param: "Parameter",
            tbl_value: "Measured Value",
            tbl_status: "Status",
            param_bp: "Blood Pressure",
            param_heart_rate: "Heart Rate",
            param_spo2: "SpO₂",
            param_temp: "Body Temperature",
            param_weight: "Body Weight",
            param_height: "Height",
            param_bmi: "BMI",
            sec_fp_assessment: "Family Planning Assessment",
            tbl_assessment: "Assessment",
            tbl_result: "Result",
            param_pregnancy_status: "Pregnancy Status",
            param_eligibility_status: "Medical Eligibility",
            param_counseling_status: "Counseling Provided",
            sec_fp_method: "Family Planning Method",
            lbl_selected_method: "Selected Family Planning Method",
            sec_service_details: "Service Details & Schedule",
            tbl_information: "Information",
            tbl_details: "Details",
            param_date_started: "Date Started",
            param_next_schedule: "Next Follow-Up Schedule",
            param_provider: "Service Provider",
            sec_remarks: "Remarks & Recommendations",
            val_no_remarks: "No remarks recorded.",
            lbl_client_sig: "Client Signature",
            lbl_printed_name: "Printed Name:",
            lbl_fp_provider_sig: "Family Planning Provider",
            lbl_provider_colon: "Provider:",
            doc_footer: "CONFIDENTIAL MEDICAL RECORD • FOR AUTHORIZED PERSONNEL ONLY • VITALCORE HEALTH INFORMATION SYSTEM",
            modal_fp_title: "Family Planning Record",
            lbl_patient_prefix: "Patient:",
            sec_service_provider: "Service Provider",
            lbl_provider_name: "Provider Name",
            lbl_remarks: "Remarks",
            btn_cancel: "Cancel",
            btn_save_fp_record: "Save Family Planning Record",
            modal_title: "Edit Vital Signs",
            modal_alert: "Use this only to correct sensor errors or inaccurate readings. Leave a field blank to clear it.",
            modal_systolic: "Systolic (mmHg)",
            modal_diastolic: "Diastolic (mmHg)",
            modal_heart_rate: "Heart Rate (BPM)",
            modal_spo2: "SpO2 (%)",
            modal_temp: "Temperature (°C)",
            modal_weight: "Weight (kg)",
            modal_height: "Height (cm)",
            modal_bmi: "BMI (automatic)",
            btn_save_changes: "Save Changes"
        },
        fil: {
            btn_back_history: "Bumalik sa Kasaysayan ng Pasyente",
            btn_edit_record: "I-edit ang Rekord",
            btn_add_record: "Magdagdag ng Rekord",
            btn_save_pdf: "I-save bilang PDF...",
            btn_save_word: "I-save bilang Word...",
            btn_print: "I-print",
            clinic_header_title: "Tsart ng Pagpaplano ng Pamilya",
            clinic_header_sub: "Klinikal na Rekord ng Pasyente at Pagsusuri sa Kalusugan",
            doc_title_badge: "REKORD NG PAGPAPLANO NG PAMILYA",
            lbl_doc_date: "Petsa ng Dokumento:",
            sec_client_info: "Impormasyon ng Kliyente",
            lbl_patient_id: "ID ng Pasyente:",
            lbl_full_name: "Buong Pangalan:",
            lbl_gender: "Kasarian:",
            lbl_age: "Edad:",
            lbl_years: "taon",
            lbl_birth_date: "Petsa ng Kapanganakan:",
            lbl_civil_status: "Katayuang Sibil:",
            lbl_blood_type: "Uri ng Dugo:",
            lbl_contact_no: "Numero ng Kontak:",
            lbl_address: "Tirahan:",
            lbl_last_visit: "Huling Pagbisita:",
            val_no_visits: "Walang naitalang pagbisita",
            sec_repro_history: "Kasaysayan sa Reproduksyon",
            lbl_gravida: "Gravida (Bilang ng Pagbubuntis)",
            lbl_para: "Para (Bilang ng Panganganak)",
            lbl_living_children: "Mga Nabubuhay na Anak",
            lbl_lmp: "Huling Regla (LMP)",
            lbl_menstrual_cycle: "Siklo ng Regla",
            lbl_partner_name: "Pangalan ng Asawa / Kapareha",
            lbl_occupation: "Trabaho / Propesyon",
            sec_vitals_telemetry: "Vital Signs at Pisikal na Sukat",
            btn_edit_vitals: "I-edit ang Vitals",
            note_vitals_kiosk: "Pinakahuling sukat na naitala ng VitalCore kiosk.",
            tbl_param: "Sukat / Parameter",
            tbl_value: "Nasukat na Halaga",
            tbl_status: "Katayuan",
            param_bp: "Presyon ng Dugo",
            param_heart_rate: "Bilis ng Puso",
            param_spo2: "SpO₂ (Oxygen ng Dugo)",
            param_temp: "Temperatura ng Katawan",
            param_weight: "Timbang ng Katawan",
            param_height: "Taas",
            param_bmi: "BMI Score",
            sec_fp_assessment: "Pagsusuri sa Pagpaplano ng Pamilya",
            tbl_assessment: "Pagsusuri",
            tbl_result: "Resulta",
            param_pregnancy_status: "Katayuan sa Pagbubuntis",
            param_eligibility_status: "Medikal na Kwalipikasyon",
            param_counseling_status: "Naipagkaloob na Pagpapayo",
            sec_fp_method: "Paraan ng Pagpaplano ng Pamilya",
            lbl_selected_method: "Napiling Paraan ng Family Planning",
            sec_service_details: "Detalye ng Serbisyo at Iskedyul",
            tbl_information: "Impormasyon",
            tbl_details: "Mga Detalye",
            param_date_started: "Petsa ng Pagsisimula",
            param_next_schedule: "Susunod na Iskedyul ng Follow-Up",
            param_provider: "Tagapagbigay ng Serbisyo",
            sec_remarks: "Mga Tala at Rekomendasyon",
            val_no_remarks: "Walang naitalang mga tala.",
            lbl_client_sig: "Lagda ng Kliyente",
            lbl_printed_name: "Limbag na Pangalan:",
            lbl_fp_provider_sig: "Tagapagbigay ng Family Planning",
            lbl_provider_colon: "Tagapagbigay:",
            doc_footer: "KUMPIDENSIYAL NA REKORD MEDIKAL • PARA LAMANG SA AWTORISADONG PERSONAHE • VITALCORE HEALTH INFORMATION SYSTEM",
            modal_fp_title: "Rekord ng Pagpaplano ng Pamilya",
            lbl_patient_prefix: "Pasyente:",
            sec_service_provider: "Tagapagbigay ng Serbisyo",
            lbl_provider_name: "Pangalan ng Tagapagbigay",
            lbl_remarks: "Mga Tala / Remarks",
            btn_cancel: "Kanselahin",
            btn_save_fp_record: "I-save ang Rekord ng Family Planning",
            modal_title: "I-edit ang Vital Signs",
            modal_alert: "Gamitin lamang ito upang iwasto ang error sa sensor o maling sukat. Iwanang blangko upang burahin.",
            modal_systolic: "Systolic (mmHg)",
            modal_diastolic: "Diastolic (mmHg)",
            modal_heart_rate: "Bilis ng Puso (BPM)",
            modal_spo2: "SpO2 (%)",
            modal_temp: "Temperatura (°C)",
            modal_weight: "Timbang (kg)",
            modal_height: "Taas (cm)",
            modal_bmi: "BMI (awtomatiko)",
            btn_save_changes: "I-save ang mga Pagbabago"
        },
        ceb: {
            btn_back_history: "Balik sa Kasaysayan sa Pasyente",
            btn_edit_record: "Usba ang Rekord",
            btn_add_record: "Idugang ang Rekord",
            btn_save_pdf: "I-save isip PDF...",
            btn_save_word: "I-save isip Word...",
            btn_print: "I-print",
            clinic_header_title: "Tsart sa Pagplano sa Pamilya",
            clinic_header_sub: "Klinikal nga Rekord sa Pasyente ug Pagsusi sa Panglawas",
            doc_title_badge: "REKORD SA PAGPLANO SA PAMILYA",
            lbl_doc_date: "Petsa sa Dokumento:",
            sec_client_info: "Impormasyon sa Kliyente",
            lbl_patient_id: "ID sa Pasyente:",
            lbl_full_name: "Tibuok Ngalan:",
            lbl_gender: "Kasarian:",
            lbl_age: "Pangidaron:",
            lbl_years: "ka tuig",
            lbl_birth_date: "Adlawng Natawhan:",
            lbl_civil_status: "Kahimtang Sibil:",
            lbl_blood_type: "Matang sa Dugo:",
            lbl_contact_no: "Numero sa Kontak:",
            lbl_address: "Puy-anan:",
            lbl_last_visit: "Katapusang Pagbisita:",
            val_no_visits: "Walay natala nga pagbisita",
            sec_repro_history: "Kasaysayan sa Reproduksyon",
            lbl_gravida: "Gravida (Gidaghanon sa Pagmabdos)",
            lbl_para: "Para (Gidaghanon sa Pagpanganak)",
            lbl_living_children: "Buhi nga mga Anak",
            lbl_lmp: "Katapusang Regla (LMP)",
            lbl_menstrual_cycle: "Siklo sa Regla",
            lbl_partner_name: "Ngalan sa Kauban / Asawa",
            lbl_occupation: "Trabaho / Panginabuhi",
            sec_vitals_telemetry: "Vital Signs ug Pisikal nga Sukat",
            btn_edit_vitals: "Usba ang Vitals",
            note_vitals_kiosk: "Pinakabag-ong mga sukat nga narekord sa VitalCore kiosk.",
            tbl_param: "Sukat / Parameter",
            tbl_value: "Nasukod nga Halaga",
            tbl_status: "Kahimtang",
            param_bp: "Presyon sa Dugo",
            param_heart_rate: "Kusog sa Kasingkasing",
            param_spo2: "SpO₂ (Oksiheno sa Dugo)",
            param_temp: "Temperatura sa Lawas",
            param_weight: "Timbang sa Lawas",
            param_height: "Gitas-on",
            param_bmi: "BMI Score",
            sec_fp_assessment: "Pagsusi sa Pagplano sa Pamilya",
            tbl_assessment: "Pagsusi",
            tbl_result: "Resulta",
            param_pregnancy_status: "Kahimtang sa Pagmabdos",
            param_eligibility_status: "Medikal nga Kwalipikasyon",
            param_counseling_status: "Gihatag nga Pagtambag",
            sec_fp_method: "Pamaagi sa Pagplano sa Pamilya",
            lbl_selected_method: "Gipili nga Pamaagi sa Family Planning",
            sec_service_details: "Detalye sa Serbisyo ug Iskedyul",
            tbl_information: "Impormasyon",
            tbl_details: "Mga Detalye",
            param_date_started: "Petsa sa Pagsugod",
            param_next_schedule: "Sunod nga Iskedyul sa Follow-Up",
            param_provider: "Tighatag sa Serbisyo",
            sec_remarks: "Mga Pahinumdom ug Rekomendasyon",
            val_no_remarks: "Walay natala nga mga pahinumdom.",
            lbl_client_sig: "Pirma sa Kliyente",
            lbl_printed_name: "Limbag nga Ngalan:",
            lbl_fp_provider_sig: "Tighatag sa Family Planning",
            lbl_provider_colon: "Tighatag:",
            doc_footer: "KOMPIDENSYAL NGA REKORD MEDIKAL • PARA LAMANG SA AWTORISADONG PERSONAHE • VITALCORE HEALTH INFORMATION SYSTEM",
            modal_fp_title: "Rekord sa Pagplano sa Pamilya",
            lbl_patient_prefix: "Pasyente:",
            sec_service_provider: "Tighatag sa Serbisyo",
            lbl_provider_name: "Ngalan sa Tighatag",
            lbl_remarks: "Mga Pahinumdom / Remarks",
            btn_cancel: "Kanselahon",
            btn_save_fp_record: "I-save ang Rekord sa Family Planning",
            modal_title: "Usba ang Vital Signs",
            modal_alert: "Gamita lamang kini aron matul-id ang sayop sa sensor o dili husto nga sukat. Pasagdi nga blangko aron mapapas.",
            modal_systolic: "Systolic (mmHg)",
            modal_diastolic: "Diastolic (mmHg)",
            modal_heart_rate: "Kusog sa Kasingkasing (BPM)",
            modal_spo2: "SpO2 (%)",
            modal_temp: "Temperatura (°C)",
            modal_weight: "Timbang (kg)",
            modal_height: "Gitas-on (cm)",
            modal_bmi: "BMI (awtomatiko)",
            btn_save_changes: "I-save ang mga Kausaban"
        }
    };

    const savedLang = localStorage.getItem("language") || "en";
    const dict = i18n[savedLang] || i18n.en;
    document.querySelectorAll("[data-i18n]").forEach(function(el) {
        const key = el.getAttribute("data-i18n");
        if (dict[key]) {
            el.textContent = dict[key];
        }
    });
}

// ---- Edit Vitals Logic ----
<?php if ($current_mid > 0): ?>
const CURRENT_MID = <?= (int)$current_mid; ?>;
const PATIENT_ID  = <?= (int)$patient_id; ?>;
const CSRF_TOKEN  = '<?= $_SESSION['csrf_token']; ?>';
const VITALS      = <?= $vitals_js; ?>;
const evFields    = ['temperature','weight','height','heart_rate','spo2','systolic','diastolic'];

function openEditVitals() {
    evFields.forEach(f => {
        const el = document.getElementById('ev_' + f);
        if (el) el.value = VITALS[f] ?? '';
    });
    const err = document.getElementById('evError');
    if (err) err.classList.add('d-none');
    updateBmiPreview();
    new bootstrap.Modal(document.getElementById('editVitalsModal')).show();
}

function updateBmiPreview() {
    const w = parseFloat(document.getElementById('ev_weight').value);
    const h = parseFloat(document.getElementById('ev_height').value);
    const out = document.getElementById('ev_bmi');
    if (out) {
        if (w > 0 && h > 0) {
            const m = h / 100;
            out.value = (w / (m * m)).toFixed(2);
        } else {
            out.value = '--';
        }
    }
}

async function saveVitals() {
    const btn = document.getElementById('evSaveBtn');
    const err = document.getElementById('evError');
    if (err) err.classList.add('d-none');

    const payload = { measurement_id: CURRENT_MID, patient_id: PATIENT_ID, csrf: CSRF_TOKEN };
    evFields.forEach(f => {
        const el = document.getElementById('ev_' + f);
        payload[f] = el ? el.value.trim() : '';
    });

    if (btn) btn.disabled = true;
    try {
        const res = await fetch('update_vital.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            location.reload();
            return;
        }
        if (err) {
            err.textContent = data.message || 'Unable to save.';
            err.classList.remove('d-none');
        }
    } catch (e) {
        if (err) {
            err.textContent = 'Network error. Please try again.';
            err.classList.remove('d-none');
        }
    }
    if (btn) btn.disabled = false;
}
<?php endif; ?>

document.addEventListener("DOMContentLoaded", function () {
    applySystemSettings();

    // Auto-expand remarks textarea
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

    // Live BMI preview in modal
    ['ev_weight', 'ev_height'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateBmiPreview);
    });
});

// Run immediately on page load
applySystemSettings();

// Live listener for real-time synchronization across browser tabs
window.addEventListener("storage", function (e) {
    if (["theme", "staff_theme", "nightLight", "staff_night_light", "night_light", "textSize", "language", "dateTimeFormat"].includes(e.key)) {
        applySystemSettings();
    }
});

async function saveAsPDF() {
    window.scrollTo(0, 0);
    const element = document.getElementById('document-wrapper');
    try {
        const pdfBlob = await html2pdf()
            .set({
                margin: [6, 6, 6, 6],
                filename: defaultFileName + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
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

    // Remove buttons marked with ignore attribute from Word document
    clone.querySelectorAll('[data-html2canvas-ignore]').forEach(e => e.remove());

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
                types: [{ description: 'Word Document (*.doc)', accept: { 'application/msword': ['.doc', '.docx'] } }]
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

<script src="../assets/js/theme.js"></script>

</body>
</html>
