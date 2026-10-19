<?php
/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../db_conn.php');

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Staff'], true)
) {
    header("Location: ../login.php");
    exit();
}

// CSRF token for the Edit Vitals save request
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Patient not found.");
}

$patient_id     = (int)$_GET['id'];
$measurement_id = isset($_GET['measurement_id']) && is_numeric($_GET['measurement_id']) ? (int)$_GET['measurement_id'] : 0;

// 1. Fetch Patient Info
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = $patient_id LIMIT 1");
$patient = mysqli_fetch_assoc($query);

if (!$patient) {
    die("Patient not found.");
}

// 2. Fetch Vital Measurements for This Specific Visit
$temp         = '--';
$weight       = '--';
$height       = '--';
$bmi          = '--';
$heart_rate   = '--';
$spo2         = '--';
$systolic     = '--';
$diastolic    = '--';
$last_visited = 'No visits recorded';
$visit_time12 = 'No visits recorded';
$visit_time24 = 'No visits recorded';
$doc_date     = date('F d, Y');
$current_mid  = 0;

if ($measurement_id > 0) {
    $measurementQuery = mysqli_query($conn, "SELECT * FROM measurements WHERE id = $measurement_id LIMIT 1");
} else {
    $measurementQuery = mysqli_query($conn, "SELECT * FROM measurements WHERE user_id = $patient_id ORDER BY created_at DESC LIMIT 1");
}

if ($measurementQuery && mysqli_num_rows($measurementQuery) > 0) {
    $measurement = mysqli_fetch_assoc($measurementQuery);

    $current_mid = (int)$measurement['id'];
    $temp       = $measurement['temperature'] ?? '--';
    $weight     = $measurement['weight'] ?? '--';
    $height     = $measurement['height'] ?? '--';
    $bmi        = $measurement['bmi'] ?? '--';
    $heart_rate = $measurement['heart_rate'] ?? '--';
    $spo2       = $measurement['spo2'] ?? '--';
    $systolic   = $measurement['systolic'] ?? '--';
    $diastolic  = $measurement['diastolic'] ?? '--';

    if (!empty($measurement['created_at'])) {
        $last_visited = date('F d, Y - h:i A', strtotime($measurement['created_at']));
        $visit_time12 = date('F d, Y - h:i A', strtotime($measurement['created_at']));
        $visit_time24 = date('F d, Y - H:i', strtotime($measurement['created_at']));
        $doc_date     = date('F d, Y', strtotime($measurement['created_at']));
    }
}

// 3. Data Processing & Formatting
$temp_val       = (is_numeric($temp)       && (float)$temp > 0)       ? (float)$temp       : null;
$weight_val     = (is_numeric($weight)     && (float)$weight > 0)     ? (float)$weight     : null;
$height_val     = (is_numeric($height)     && (float)$height > 0)     ? (float)$height     : null;
$bmi_val        = (is_numeric($bmi)        && (float)$bmi > 0)        ? (float)$bmi        : null;
$heart_rate_val = (is_numeric($heart_rate) && (int)$heart_rate > 0)   ? (int)$heart_rate   : null;
$spo2_val       = (is_numeric($spo2)       && (int)$spo2 > 0)         ? (int)$spo2         : null;
$systolic_val   = (is_numeric($systolic)   && (int)$systolic > 0)     ? (int)$systolic     : null;
$diastolic_val  = (is_numeric($diastolic)  && (int)$diastolic > 0)    ? (int)$diastolic    : null;

// Values passed to the Edit Vitals modal
$vitals_js = json_encode([
    'temperature' => $temp_val,
    'weight'      => $weight_val,
    'height'      => $height_val,
    'heart_rate'  => $heart_rate_val,
    'spo2'        => $spo2_val,
    'systolic'    => $systolic_val,
    'diastolic'   => $diastolic_val,
]);

// Display strings
$temp_disp       = ($temp_val !== null)   ? number_format($temp_val, 1) . ' °C' : '--';
$weight_disp     = ($weight_val !== null) ? number_format($weight_val, 1) . ' kg' : '--';
$height_disp     = ($height_val !== null) ? number_format($height_val, 1) . ' cm' : '--';

// cm → feet & inches conversion
if ($height_val !== null) {
    $total_inches  = $height_val / 2.54;
    $feet          = (int) floor($total_inches / 12);
    $inches        = (int) round(fmod($total_inches, 12));
    if ($inches === 12) { $feet++; $inches = 0; }
    $height_ft_disp = "{$feet}'{$inches}\"";
} else {
    $height_ft_disp = '--';
}

$bmi_disp        = ($bmi_val !== null)    ? number_format($bmi_val, 2) : '--';
$heart_rate_disp = ($heart_rate_val !== null) ? $heart_rate_val . ' BPM' : '--';
$spo2_disp       = ($spo2_val !== null)   ? $spo2_val . '%' : '--';
$bp_disp         = ($systolic_val !== null && $diastolic_val !== null) ? $systolic_val . '/' . $diastolic_val . ' mmHg' : '--';

// Status Evaluations
$temp_status = '--';
$temp_class  = 'badge-secondary';
if ($temp_val !== null) {
    if ($temp_val < 36.0)                          { $temp_status = 'Low Temp';     $temp_class = 'badge-info'; }
    elseif ($temp_val <= 37.5)                     { $temp_status = 'Normal';       $temp_class = 'badge-success'; }
    elseif ($temp_val > 37.5 && $temp_val <= 38.5) { $temp_status = 'Slight Fever'; $temp_class = 'badge-warning'; }
    else                                           { $temp_status = 'High Fever';   $temp_class = 'badge-danger'; }
}

$has_bmi = ($bmi_val !== null && $weight_val !== null);
$bmi_status = '--';
$bmi_class  = 'badge-secondary';
if ($has_bmi) {
    if ($bmi_val < 18.5)      { $bmi_status = 'Underweight'; $bmi_class = 'badge-info'; }
    elseif ($bmi_val <= 24.9) { $bmi_status = 'Normal';      $bmi_class = 'badge-success'; }
    elseif ($bmi_val <= 29.9) { $bmi_status = 'Overweight';  $bmi_class = 'badge-warning'; }
    else                      { $bmi_status = 'Obese';       $bmi_class = 'badge-danger'; }
}

$heart_status = '--';
$heart_class  = 'badge-secondary';
if ($heart_rate_val !== null) {
    if ($heart_rate_val < 60)       { $heart_status = 'Low (Bradycardia)';  $heart_class = 'badge-warning'; }
    elseif ($heart_rate_val <= 100) { $heart_status = 'Normal';             $heart_class = 'badge-success'; }
    else                            { $heart_status = 'High (Tachycardia)'; $heart_class = 'badge-danger'; }
}

$spo2_status = '--';
$spo2_class  = 'badge-secondary';
if ($spo2_val !== null) {
    if ($spo2_val < 90)     { $spo2_status = 'Critical Low'; $spo2_class = 'badge-danger'; }
    elseif ($spo2_val < 95) { $spo2_status = 'Mild Low';     $spo2_class = 'badge-warning'; }
    else                    { $spo2_status = 'Normal';       $spo2_class = 'badge-success'; }
}

// Blood Pressure Status
$bp_status = '--';
$bp_class  = 'badge-secondary';
if ($systolic_val !== null && $diastolic_val !== null) {
    if ($systolic_val > 180 || $diastolic_val > 120) {
        $bp_status = 'Hypertensive Crisis'; $bp_class = 'badge-danger';
    } elseif ($systolic_val >= 140 || $diastolic_val >= 90) {
        $bp_status = 'High Stage 2';        $bp_class = 'badge-danger';
    } elseif (($systolic_val >= 130 && $systolic_val <= 139) || ($diastolic_val >= 80 && $diastolic_val <= 89)) {
        $bp_status = 'High Stage 1';        $bp_class = 'badge-warning';
    } elseif ($systolic_val >= 120 && $systolic_val <= 129 && $diastolic_val < 80) {
        $bp_status = 'Elevated';            $bp_class = 'badge-warning';
    } elseif ($systolic_val < 90 || $diastolic_val < 60) {
        $bp_status = 'Low (Hypotension)';   $bp_class = 'badge-info';
    } else {
        $bp_status = 'Normal';              $bp_class = 'badge-success';
    }
}

// Weight & Height Status
$weight_status = ($weight_val !== null) ? 'Recorded' : '--';
$height_status = ($height_val !== null) ? 'Recorded' : '--';

$safe_filename = 'Patient_Report_PT' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT) . '_' . preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', strtolower($patient['fullname'])));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- PRE-LOAD DARK MODE & TEXT SIZE (Prevents UI Flicker) -->
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('theme') || localStorage.getItem('staff_theme');
                if (savedTheme === 'dark') {
                    document.documentElement.classList.add('dark-mode');
                }
                const savedTextSize = localStorage.getItem("textSize");
                if (savedTextSize) {
                    const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
                    document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
                }
            } catch(e) {}
        })();
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Clinical Record - #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/document.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Night Light Filter Overlay */
        #nightLightOverlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(255, 140, 0, 0.18);
            pointer-events: none;
            z-index: 99999;
            display: none;
        }

        /* Dark Mode Override Fix for Clinic Title */
        .clinic-name,
        h1.clinic-name,
        html.dark-mode .clinic-name,
        body.dark-mode .clinic-name,
        .dark-mode h1.clinic-name {
            color: #0a49c4 !important;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .document-wrapper { box-shadow: none !important; margin: 0 !important; width: 100% !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

<!-- TOP ACTION TOOLBAR -->
<div class="no-print bg-white border-bottom p-3 mb-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 820px;">
        <a href="patient-history-list.php?user_id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> <span data-i18n="back_to_history">Back to Patient History</span>
        </a>

        <!-- ACTION BUTTONS -->
        <div class="d-flex gap-2">
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
    <div class="document-wrapper" id="document-wrapper">

        <!-- CLINIC LETTERHEAD HEADER -->
        <div class="clinic-header">
            <div class="clinic-brand">
                <img src="../../img/logo.jpg" alt="Logo" class="clinic-logo" onerror="this.src='https://via.placeholder.com/48?text=VC'">
                <div>
                    <h1 class="clinic-name" style="color:#0a49c4 !important;" data-i18n="doc_title">Vital Screening</h1>
                    <p class="clinic-sub" data-i18n="doc_subtitle">Clinical Patient Record & Health Assessment</p>
                </div>
            </div>
        </div>

        <!-- PATIENT DEMOGRAPHICS -->
        <div class="section-header">
            <i class="bi bi-person-lines-fill text-primary"></i>
            <span data-i18n="sec_demographics">Patient Demographics</span>
        </div>

        <div class="info-grid">

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_patient_id">Patient ID:</span>
                <span class="info-value">#PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_fullname">Full Name:</span>
                <span class="info-value"><?= htmlspecialchars($patient['fullname']); ?></span>
            </div>

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_gender_age">Gender / Age:</span>
                <span class="info-value">
                    <?= htmlspecialchars($patient['gender']); ?>
                    /
                    <?= htmlspecialchars($patient['age']); ?> yrs
                </span>
            </div>

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_birthdate">Birth Date:</span>
                <span class="info-value">
                    <?= !empty($patient['birth_date']) ? date('F d, Y', strtotime($patient['birth_date'])) : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_bloodtype">Blood Type:</span>
                <span class="info-value">
                    <?= !empty($patient['blood_type']) ? htmlspecialchars($patient['blood_type']) : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_contact">Contact No:</span>
                <span class="info-value">
                    <?= !empty($patient['contact_number']) ? htmlspecialchars($patient['contact_number']) : '--'; ?>
                </span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label" data-i18n="lbl_address">Address:</span>
                <span class="info-value">
                    <?= !empty($patient['address']) ? htmlspecialchars($patient['address']) : '--'; ?>
                </span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label" data-i18n="lbl_visit_datetime">Visit Date/Time:</span>
                <span class="info-value text-primary visit-time-display" data-time12="<?= htmlspecialchars($visit_time12); ?>" data-time24="<?= htmlspecialchars($visit_time24); ?>">
                    <?= htmlspecialchars($visit_time12); ?>
                </span>
            </div>
        </div>

        <!-- VITAL SIGNS BREAKDOWN TABLE -->
        <div class="section-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-heart-pulse-fill text-danger"></i> <span data-i18n="sec_vitals">Vital Signs & Physical Measurements</span></span>
            <?php if ($current_mid > 0): ?>
            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 fw-bold no-print"
                    data-html2canvas-ignore="true" onclick="openEditVitals()" style="font-size:0.7rem;">
                <i class="bi bi-pencil-square me-1"></i> <span data-i18n="btn_edit_vitals">Edit Vitals</span>
            </button>
            <?php endif; ?>
        </div>

        <table class="vitals-table">
            <thead>
                <tr>
                    <th data-i18n="tbl_parameter">Parameter</th>
                    <th data-i18n="tbl_measured_value">Measured Value</th>
                    <th data-i18n="tbl_status">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong data-i18n="lbl_bp">Blood Pressure</strong></td>
                    <td><?= $bp_disp ?></td>
                    <td><span class="status-badge <?= $bp_class ?>"><?= $bp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_heart">Heart Rate</strong></td>
                    <td><?= $heart_rate_disp ?></td>
                    <td><span class="status-badge <?= $heart_class ?>"><?= $heart_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_spo2">SpO2 (Blood Oxygen)</strong></td>
                    <td><?= $spo2_disp ?></td>
                    <td><span class="status-badge <?= $spo2_class ?>"><?= $spo2_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_temp">Body Temperature</strong></td>
                    <td><?= $temp_disp ?></td>
                    <td><span class="status-badge <?= $temp_class ?>"><?= $temp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_weight">Body Weight</strong></td>
                    <td><?= $weight_disp ?></td>
                    <td><span class="status-badge badge-secondary"><?= $weight_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_height">Height</strong></td>
                    <td>
                        <?= $height_disp ?>
                        <?php if ($height_val !== null): ?>
                            <span class="text-muted" style="font-size:0.85em;">&nbsp;(<?= $height_ft_disp ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge badge-secondary"><?= $height_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_bmi">BMI Score</strong></td>
                    <td><?= $bmi_disp ?></td>
                    <td><span class="status-badge <?= $bmi_class ?>"><?= $bmi_status ?></span></td>
                </tr>
            </tbody>
        </table>

        <!-- CLINICAL DIAGNOSIS / PHYSICIAN NOTES -->
        <div class="section-header">
            <i class="bi bi-journal-medical text-primary"></i> <span data-i18n="sec_diagnosis">Clinical Observations & Diagnosis</span>
        </div>

        <div class="notes-box" style="min-height: 60px;">
            <em data-i18n="lbl_notes_placeholder">Physician / Attending Clinician Notes:</em>
        </div>

        <!-- SIGNATURE BLOCK -->
        <div class="signature-section">
            <div class="signature-line">
                <span data-i18n="lbl_physician_sig">Attending Physician Signature</span>
                <div class="text-muted fw-normal" style="font-size: 0.72rem;"><span data-i18n="lbl_license_no">License No:</span> __________________</div>
            </div>
            <div class="signature-line">
                <span data-i18n="lbl_officer_stamp">Clinic Medical Officer Stamp & Date</span>
                <div class="text-muted fw-normal" style="font-size: 0.72rem;">VitalCore Health Information System</div>
            </div>
        </div>

        <!-- CONFIDENTIALITY FOOTER -->
        <div class="doc-footer" data-i18n="doc_footer">
            CONFIDENTIAL MEDICAL RECORD • FOR AUTHORIZED PERSONNEL ONLY • VITALCORE CLINICAL SYSTEM
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
const defaultFileName = '<?= $safe_filename; ?>';

// ---- Edit Vitals ----
<?php if ($current_mid > 0): ?>
const CURRENT_MID = <?= (int)$current_mid; ?>;
const PATIENT_ID  = <?= (int)$patient_id; ?>;
const CSRF_TOKEN  = <?= json_encode($_SESSION['csrf_token']); ?>;
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
        const res  = await fetch('update_vital.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            if (err) {
                err.textContent = 'Server replied (HTTP ' + res.status + '): ' + text.substring(0, 300);
                err.classList.remove('d-none');
            }
            if (btn) btn.disabled = false;
            return;
        }
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
            err.textContent = 'Could not reach update_vital.php: ' + e.message;
            err.classList.remove('d-none');
        }
    }
    if (btn) btn.disabled = false;
}
<?php endif; ?>

document.addEventListener("DOMContentLoaded", function() {
    ['ev_weight', 'ev_height'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateBmiPreview);
    });
});

async function saveAsPDF() {
    window.scrollTo(0, 0);
    const element = document.getElementById('document-wrapper');

    try {
        const pdfBlob = await html2pdf().set({
            margin: [6, 6, 6, 6],
            filename: defaultFileName + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).from(element).output('blob');

        if ('showSaveFilePicker' in window) {
            try {
                const handle = await window.showSaveFilePicker({
                    suggestedName: defaultFileName + '.pdf',
                    types: [{
                        description: 'PDF Document (*.pdf)',
                        accept: { 'application/pdf': ['.pdf'] }
                    }]
                });
                const writable = await handle.createWritable();
                await writable.write(pdfBlob);
                await writable.close();
            } catch (err) {
                if (err.name !== 'AbortError') {
                    fallbackDownload(pdfBlob, defaultFileName + '.pdf');
                }
            }
        } else {
            fallbackDownload(pdfBlob, defaultFileName + '.pdf');
        }
    } catch (e) {
        console.error(e);
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
                                <div style="font-size:20px; font-weight:800; color:#0a49c4; line-height:1.1; font-family:'Segoe UI', Tahoma, Arial, sans-serif;">Vital Screening</div>
                                <div style="font-size:11px; color:#64748b; margin-top:2px; font-family:'Segoe UI', Tahoma, Arial, sans-serif;">Clinical Patient Record & Health Assessment</div>
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
        .notes-box { border: 1px dashed #cbd5e1 !important; background-color: #f8fafc !important; padding: 6px 10px !important; min-height: 35px !important; margin-bottom: 10px !important; font-size: 10.5px !important; }
        .doc-footer { margin-top: 10px !important; text-align: center !important; font-size: 8.5px !important; color: #94a3b8 !important; border-top: 1px solid #e2e8f0 !important; padding-top: 4px !important; }
    `;

    const sourceHTML = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <title>Vital Screening Record</title>
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

<!-- SYSTEM SETTINGS ENGINE (Connected to setting.php) -->
<script>
function applySystemSettings() {
    // 1. Dark Mode
    const savedTheme = localStorage.getItem("theme") || localStorage.getItem("staff_theme");
    const isDark = (savedTheme === "dark");
    document.body.classList.toggle("dark-mode", isDark);
    document.documentElement.classList.toggle("dark-mode", isDark);

    // 2. Night Light
    const savedNightLight = localStorage.getItem("nightLight");
    const nightLightOverlay = document.getElementById("nightLightOverlay");
    if (nightLightOverlay) {
        nightLightOverlay.style.display = (savedNightLight === "enabled") ? "block" : "none";
    }

    // 3. Text Size
    const savedTextSize = localStorage.getItem("textSize");
    if (savedTextSize) {
        const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
        document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
    }

    // 4. Date & Time Format (12h vs 24h)
    const savedFmt = localStorage.getItem("dateTimeFormat") || "24h";
    document.querySelectorAll(".visit-time-display").forEach(function (el) {
        if (savedFmt === "24h" && el.dataset.time24) {
            el.textContent = el.dataset.time24;
        } else if (el.dataset.time12) {
            el.textContent = el.dataset.time12;
        }
    });

    // 5. Language Dictionary & Translations
    const i18n = {
        en: {
            back_to_history: "Back to Patient History",
            btn_save_pdf: "Save As PDF...",
            btn_save_word: "Save As Word...",
            btn_print: "Print",
            doc_title: "Vital Screening",
            doc_subtitle: "Clinical Patient Record & Health Assessment",
            sec_demographics: "Patient Demographics",
            lbl_patient_id: "Patient ID:",
            lbl_fullname: "Full Name:",
            lbl_gender_age: "Gender / Age:",
            lbl_birthdate: "Birth Date:",
            lbl_bloodtype: "Blood Type:",
            lbl_contact: "Contact No:",
            lbl_address: "Address:",
            lbl_visit_datetime: "Visit Date/Time:",
            sec_vitals: "Vital Signs & Physical Measurements",
            btn_edit_vitals: "Edit Vitals",
            tbl_parameter: "Parameter",
            tbl_measured_value: "Measured Value",
            tbl_status: "Status",
            lbl_bp: "Blood Pressure",
            lbl_heart: "Heart Rate",
            lbl_spo2: "SpO2 (Blood Oxygen)",
            lbl_temp: "Body Temperature",
            lbl_weight: "Body Weight",
            lbl_height: "Height",
            lbl_bmi: "BMI Score",
            sec_diagnosis: "Clinical Observations & Diagnosis",
            lbl_notes_placeholder: "Physician / Attending Clinician Notes:",
            lbl_physician_sig: "Attending Physician Signature",
            lbl_license_no: "License No:",
            lbl_officer_stamp: "Clinic Medical Officer Stamp & Date",
            doc_footer: "CONFIDENTIAL MEDICAL RECORD • FOR AUTHORIZED PERSONNEL ONLY • VITALCORE CLINICAL SYSTEM",
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
            btn_cancel: "Cancel",
            btn_save_changes: "Save Changes"
        },
        fil: {
            back_to_history: "Bumalik sa Kasaysayan ng Pasyente",
            btn_save_pdf: "I-save Bilang PDF...",
            btn_save_word: "I-save Bilang Word...",
            btn_print: "I-print",
            doc_title: "Pagsusuri sa Vitals",
            doc_subtitle: "Klinikal na Rekord ng Pasyente at Pagsusuri ng Kalusugan",
            sec_demographics: "Demograpiko ng Pasyente",
            lbl_patient_id: "ID ng Pasyente:",
            lbl_fullname: "Buong Pangalan:",
            lbl_gender_age: "Kasarian / Edad:",
            lbl_birthdate: "Araw ng Kapanganakan:",
            lbl_bloodtype: "Uri ng Dugo:",
            lbl_contact: "Numero ng Telepono:",
            lbl_address: "Tirahan:",
            lbl_visit_datetime: "Petsa/Oras ng Pagbisita:",
            sec_vitals: "Vital Signs at Pisikal na Sukat",
            btn_edit_vitals: "I-edit ang Vitals",
            tbl_parameter: "Sukat",
            tbl_measured_value: "Naitalang Halaga",
            tbl_status: "Katayuan",
            lbl_bp: "Presyon ng Dugo",
            lbl_heart: "Bilis ng Puso",
            lbl_spo2: "SpO2 (Oksiheno sa Dugo)",
            lbl_temp: "Temperatura ng Katawan",
            lbl_weight: "Timbang ng Katawan",
            lbl_height: "Taas",
            lbl_bmi: "BMI Score",
            sec_diagnosis: "Klinikal na Obserbasyon at Diagnosis",
            lbl_notes_placeholder: "Mga Tala ng Doktor / Dumadalong Nars:",
            lbl_physician_sig: "Lagda ng Dumadalong Doktor",
            lbl_license_no: "Numero ng Lisensya:",
            lbl_officer_stamp: "Tatak at Petsa ng Opisyal Medikal ng Klinika",
            doc_footer: "KUMPIDENSIYAL NA REKORD MEDIKAL • PARA LAMANG SA AWTORISADONG TAUHAN • VITALCORE CLINICAL SYSTEM",
            modal_title: "I-edit ang Vital Signs",
            modal_alert: "Gamitin lamang ito upang itama ang mga mali sa sensor. Iwang blangko ang field upang burahin ito.",
            modal_systolic: "Systolic (mmHg)",
            modal_diastolic: "Diastolic (mmHg)",
            modal_heart_rate: "Bilis ng Puso (BPM)",
            modal_spo2: "SpO2 (%)",
            modal_temp: "Temperatura (°C)",
            modal_weight: "Timbang (kg)",
            modal_height: "Taas (cm)",
            modal_bmi: "BMI (awtomatiko)",
            btn_cancel: "Kanselahin",
            btn_save_changes: "I-save ang mga Pagbabago"
        },
        ceb: {
            back_to_history: "Balik sa Kasaysayan sa Pasyente",
            btn_save_pdf: "I-save Isip PDF...",
            btn_save_word: "I-save Isip Word...",
            btn_print: "I-print",
            doc_title: "Pagsusi sa Vitals",
            doc_subtitle: "Klinikal nga Rekord sa Pasyente ug Pagsusi sa Panglawas",
            sec_demographics: "Demograpiko sa Pasyente",
            lbl_patient_id: "ID sa Pasyente:",
            lbl_fullname: "Tibuok Ngalan:",
            lbl_gender_age: "Kasarian / Pangidaron:",
            lbl_birthdate: "Adlawng Natawhan:",
            lbl_bloodtype: "Klase sa Dugo:",
            lbl_contact: "Numero sa Telepono:",
            lbl_address: "Puy-anan:",
            lbl_visit_datetime: "Petsa/Oras sa Pagbisita:",
            sec_vitals: "Vital Signs ug Pisikal nga Sukat",
            btn_edit_vitals: "Usba ang Vitals",
            tbl_parameter: "Sukat",
            tbl_measured_value: "Nahitalang Sukod",
            tbl_status: "Kahimtang",
            lbl_bp: "Presyon sa Dugo",
            lbl_heart: "Kusog sa Kasingkasing",
            lbl_spo2: "SpO2 (Oksiheno sa Dugo)",
            lbl_temp: "Temperatura sa Lawas",
            lbl_weight: "Timbang sa Lawas",
            lbl_height: "Gitas-on",
            lbl_bmi: "BMI Score",
            sec_diagnosis: "Klinikal nga Obserbasyon ug Diagnosis",
            lbl_notes_placeholder: "Mga Pahinumdom sa Doktor / Nars:",
            lbl_physician_sig: "Pirma sa Nag-atiman nga Doktor",
            lbl_license_no: "Numero sa Lisensya:",
            lbl_officer_stamp: "Selyo ug Petsa sa Opisyal Medikal sa Klinika",
            doc_footer: "KOMPIDENSYAL NGA REKORD MEDIKAL • PARA LAMANG SA AWTORISADONG PERSONAHE • VITALCORE CLINICAL SYSTEM",
            modal_title: "Usba ang Vital Signs",
            modal_alert: "Gamita lamang kini aron matul-id ang sayop sa sensor. Pasagdi nga blangko aron mapapas.",
            modal_systolic: "Systolic (mmHg)",
            modal_diastolic: "Diastolic (mmHg)",
            modal_heart_rate: "Kusog sa Kasingkasing (BPM)",
            modal_spo2: "SpO2 (%)",
            modal_temp: "Temperatura (°C)",
            modal_weight: "Timbang (kg)",
            modal_height: "Gitas-on (cm)",
            modal_bmi: "BMI (awtomatiko)",
            btn_cancel: "Kanselahon",
            btn_save_changes: "I-save ang mga Kausaban"
        }
    };

    const savedLang = localStorage.getItem("language") || "en";
    const dict = i18n[savedLang] || i18n.en;
    document.querySelectorAll("[data-i18n]").forEach(function (el) {
        const key = el.getAttribute("data-i18n");
        if (dict[key]) {
            el.textContent = dict[key];
        }
    });
}

// Run immediately on page load
applySystemSettings();

// Live listener for real-time synchronization across browser tabs
window.addEventListener("storage", function (e) {
    if (["theme", "staff_theme", "nightLight", "textSize", "language", "dateTimeFormat"].includes(e.key)) {
        applySystemSettings();
    }
});
</script>

</body>
</html>
