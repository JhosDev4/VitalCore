<?php
/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../db_conn.php');

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
$temp       = '--';
$weight     = '--';
$height     = '--';
$bmi        = '--';
$heart_rate = '--';
$spo2       = '--';
$systolic   = '--';
$diastolic  = '--';
$last_visited = 'No visits recorded';
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
        $doc_date     = date('F d, Y', strtotime($measurement['created_at']));
        $last_visited = date('F d, Y - h:i A', strtotime($measurement['created_at']));
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

// Status Evaluations (show "--" when there is no value, instead of a false "Normal")
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

    <script>
        if (localStorage.getItem("theme") === "dark" || localStorage.getItem("staff_theme") === "dark") {
            document.documentElement.classList.add("dark-mode");
        }
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Clinical Record - #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/document.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        html.dark-mode .clinic-name {
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

<!-- TOP ACTION TOOLBAR -->
<div class="no-print bg-white border-bottom p-3 mb-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 820px;">
        <a href="../logs/patient-history-list.php?user_id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Patient History
        </a>

        <!-- ACTION BUTTONS -->
        <div class="d-flex gap-2">
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
    <div class="document-wrapper" id="document-wrapper">

        <!-- CLINIC LETTERHEAD HEADER -->
        <div class="clinic-header">
            <div class="clinic-brand">
                <img src="../../img/logo.jpg" alt="Logo" class="clinic-logo" onerror="this.src='https://via.placeholder.com/48?text=VC'">
                <div>
                    <h1 class="clinic-name">Vital Screening</h1>
                    <p class="clinic-sub">Clinical Patient Record & Health Assessment</p>
                </div>
            </div>
            <div class="text-end">
                <span class="doc-title-badge">OFFICIAL MEDICAL REPORT</span>
                <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                    <strong>Document Date:</strong> <?= htmlspecialchars($doc_date); ?>
                </div>
            </div>
        </div>

        <!-- PATIENT DEMOGRAPHICS -->
        <div class="section-header">
            <i class="bi bi-person-lines-fill text-primary"></i>
            Patient Demographics
        </div>

        <div class="info-grid">

            <div class="info-item">
                <span class="info-label">Patient ID:</span>
                <span class="info-value">#PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">Full Name:</span>
                <span class="info-value"><?= htmlspecialchars($patient['fullname']); ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">Gender / Age:</span>
                <span class="info-value">
                    <?= htmlspecialchars($patient['gender']); ?>
                    /
                    <?= htmlspecialchars($patient['age']); ?> yrs
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Birth Date:</span>
                <span class="info-value">
                    <?= !empty($patient['birth_date']) ? date('F d, Y', strtotime($patient['birth_date'])) : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Blood Type:</span>
                <span class="info-value">
                    <?= !empty($patient['blood_type']) ? htmlspecialchars($patient['blood_type']) : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Contact No:</span>
                <span class="info-value">
                    <?= !empty($patient['contact_number']) ? htmlspecialchars($patient['contact_number']) : '--'; ?>
                </span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Address:</span>
                <span class="info-value">
                    <?= !empty($patient['address']) ? htmlspecialchars($patient['address']) : '--'; ?>
                </span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Visit Date/Time:</span>
                <span class="info-value text-primary"><?= htmlspecialchars($last_visited); ?></span>
            </div>
        </div>

        <!-- VITAL SIGNS BREAKDOWN TABLE -->
        <div class="section-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-heart-pulse-fill text-danger"></i> Vital Signs & Physical Measurements</span>
            <?php if ($current_mid > 0): ?>
            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 fw-bold no-print"
                    data-html2canvas-ignore="true" onclick="openEditVitals()" style="font-size:0.7rem;">
                <i class="bi bi-pencil-square me-1"></i> Edit Vitals
            </button>
            <?php endif; ?>
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
                <tr>
                    <td><strong>Blood Pressure</strong></td>
                    <td><?= $bp_disp ?></td>
                    <td><span class="status-badge <?= $bp_class ?>"><?= $bp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Heart Rate</strong></td>
                    <td><?= $heart_rate_disp ?></td>
                    <td><span class="status-badge <?= $heart_class ?>"><?= $heart_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>SpO2 (Blood Oxygen)</strong></td>
                    <td><?= $spo2_disp ?></td>
                    <td><span class="status-badge <?= $spo2_class ?>"><?= $spo2_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Temperature</strong></td>
                    <td><?= $temp_disp ?></td>
                    <td><span class="status-badge <?= $temp_class ?>"><?= $temp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Weight</strong></td>
                    <td><?= $weight_disp ?></td>
                    <td><span class="status-badge badge-secondary"><?= $weight_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Height</strong></td>
                    <td>
                        <?= $height_disp ?>
                        <?php if ($height_val !== null): ?>
                            <span class="text-muted" style="font-size:0.85em;">&nbsp;(<?= $height_ft_disp ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge badge-secondary"><?= $height_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>BMI Score</strong></td>
                    <td><?= $bmi_disp ?></td>
                    <td><span class="status-badge <?= $bmi_class ?>"><?= $bmi_status ?></span></td>
                </tr>
            </tbody>
        </table>

        <!-- CLINICAL DIAGNOSIS / PHYSICIAN NOTES -->
        <div class="section-header">
            <i class="bi bi-journal-medical text-primary"></i> Clinical Observations & Diagnosis
        </div>

        <div class="notes-box" style="min-height: 60px;">
            <em>Physician / Attending Clinician Notes:</em>
        </div>

        <!-- SIGNATURE BLOCK -->
        <div class="signature-section">
            <div class="signature-line">
                Attending Physician Signature
                <div class="text-muted fw-normal" style="font-size: 0.72rem;">License No: __________________</div>
            </div>
            <div class="signature-line">
                Clinic Medical Officer Stamp & Date
                <div class="text-muted fw-normal" style="font-size: 0.72rem;">VitalCore Health Information System</div>
            </div>
        </div>

        <!-- CONFIDENTIALITY FOOTER -->
        <div class="doc-footer">
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
        <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1"></i> Edit Vital Signs</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small mb-3">
          Use this only to correct sensor errors or inaccurate readings. Leave a field blank to clear it.
        </div>
        <div id="evError" class="alert alert-danger py-2 small d-none"></div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label small fw-bold">Systolic (mmHg)</label>
            <input type="number" id="ev_systolic" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold">Diastolic (mmHg)</label>
            <input type="number" id="ev_diastolic" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold">Heart Rate (BPM)</label>
            <input type="number" id="ev_heart_rate" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold">SpO2 (%)</label>
            <input type="number" id="ev_spo2" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold">Temperature (°C)</label>
            <input type="number" step="0.1" id="ev_temperature" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold">Weight (kg)</label>
            <input type="number" step="0.1" id="ev_weight" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold">Height (cm)</label>
            <input type="number" step="0.1" id="ev_height" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-bold">BMI (automatic)</label>
            <input type="text" id="ev_bmi" class="form-control form-control-sm bg-light" readonly></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success btn-sm fw-bold" id="evSaveBtn" onclick="saveVitals()">
          <i class="bi bi-check-lg me-1"></i> Save Changes
        </button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<script>
const defaultFileName = '<?= $safe_filename; ?>';

// ---- Edit Vitals ----
const CURRENT_MID = <?= (int)$current_mid; ?>;
const PATIENT_ID  = <?= (int)$patient_id; ?>;
const CSRF_TOKEN  = '<?= $_SESSION['csrf_token']; ?>';
const VITALS      = <?= $vitals_js; ?>;
const evFields = ['temperature','weight','height','heart_rate','spo2','systolic','diastolic'];

function openEditVitals() {
    evFields.forEach(f => {
        document.getElementById('ev_' + f).value = VITALS[f] ?? '';
    });
    document.getElementById('evError').classList.add('d-none');
    updateBmiPreview();
    new bootstrap.Modal(document.getElementById('editVitalsModal')).show();
}

// Live automatic BMI
function updateBmiPreview() {
    const w = parseFloat(document.getElementById('ev_weight').value);
    const h = parseFloat(document.getElementById('ev_height').value);
    const out = document.getElementById('ev_bmi');
    if (w > 0 && h > 0) {
        const m = h / 100;
        out.value = (w / (m * m)).toFixed(2);
    } else {
        out.value = '--';
    }
}

async function saveVitals() {
    const btn = document.getElementById('evSaveBtn');
    const err = document.getElementById('evError');
    err.classList.add('d-none');

    const payload = { measurement_id: CURRENT_MID, patient_id: PATIENT_ID, csrf: CSRF_TOKEN };
    evFields.forEach(f => payload[f] = document.getElementById('ev_' + f).value.trim());

    btn.disabled = true;
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
            err.textContent = 'Server replied (HTTP ' + res.status + '): ' + text.substring(0, 300);
            err.classList.remove('d-none');
            btn.disabled = false;
            return;
        }
        if (data.success) {
            location.reload();   // reloads values, BMI and all status badges from the database
            return;
        }
        err.textContent = data.message || 'Unable to save.';
        err.classList.remove('d-none');
    } catch (e) {
        err.textContent = 'Could not reach update_vital.php: ' + e.message;
        err.classList.remove('d-none');
    }
    btn.disabled = false;
}

document.addEventListener("DOMContentLoaded", function() {
    if (localStorage.getItem("theme") === "dark" || localStorage.getItem("staff_theme") === "dark") {
        document.body.classList.add("dark-mode");
    }
    ['ev_weight', 'ev_height'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateBmiPreview);
    });
});

// 1. Opens Windows "Save As" File Picker Dialog for PDF
async function saveAsPDF() {
    const element = document.getElementById('document-wrapper');

    const pdfBlob = await html2pdf().set({
        margin: [6, 6, 6, 6],
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
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
}

// 2. Opens Windows "Save As" File Picker Dialog for Word (.doc)
async function saveAsWord() {
    const element = document.getElementById('document-wrapper');
    const clone = element.cloneNode(true);

    // Remove the Edit Vitals button from the Word export
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
<!-- SYSTEM SETTINGS ENGINE SCRIPT (Applies Theme, Brightness, NightLight, TextSize, Language) -->
<script>
(function applySystemSettings() {
    // 1. Theme / Dark Mode
    const darkModeToggleBtn = document.getElementById("darkModeToggle");
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme === "dark") {
        document.body.classList.add("dark-mode");
        document.documentElement.classList.add("dark-mode");
    }
    if (darkModeToggleBtn) {
        darkModeToggleBtn.addEventListener("click", function() {
            const isDark = document.body.classList.toggle("dark-mode");
            document.documentElement.classList.toggle("dark-mode", isDark);
            localStorage.setItem("theme", isDark ? "dark" : "light");
        });
    }

    // 2. Brightness
    const savedBrightness = localStorage.getItem("brightness");
    if (savedBrightness) {
        document.body.style.filter = `brightness(${savedBrightness}%)`;
    }

    // 3. Night Light
    const savedNightLight = localStorage.getItem("nightLight");
    const nightLightOverlay = document.getElementById("nightLightOverlay");
    if (savedNightLight === "enabled" && nightLightOverlay) {
        nightLightOverlay.style.display = "block";
    }

    // 4. Text Size
    const savedTextSize = localStorage.getItem("textSize");
    if (savedTextSize) {
        const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
        document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
    }

    // 5. Language Dictionary
    const i18n = {
        en: {
            section_main: "Main", nav_dashboard: "Dashboard", section_clinical: "Clinical Services",
            nav_patient_mgmt: "Patient Management", nav_all_patients: "All Patients Services", nav_add_patient: "Add Patient",
            nav_patient_records: "Patient Records", nav_records_history: "Patient Records", nav_reports: "Reports",
            section_system: "Hardware & System", nav_admin: "Administration", nav_user_mgmt: "User Management",
            nav_settings: "Settings", nav_logout: "Log out", header_greeting: "Good Day, Admin",
            header_desc: "System status overview and clinical intake telemetry.", avg_health_title: "Average Health",
            avg_health_desc: "Average recorded vital measurements", tbl_measurement: "Measurement", tbl_average: "Average",
            tbl_unit: "Unit", lbl_height: "Height", lbl_weight: "Weight", lbl_temp: "Temperature",
            lbl_heart: "Heart Rate", lbl_bp: "Blood Pressure", title_new_patients: "New Patients",
            title_patients_overview: "Patients Overview", title_services: "Service Categories",
            title_recent_patients: "Recent Patient List", title_followup: "Follow-up Schedule", dark_mode_title: "Dark Mode"
        },
        fil: {
            section_main: "Pangunahin", nav_dashboard: "Dashboard", section_clinical: "Serbisyong Klinikal",
            nav_patient_mgmt: "Pamamahala ng Pasyente", nav_all_patients: "Lahat ng Serbisyong Pasyente", nav_add_patient: "Magdagdag ng Pasyente",
            nav_patient_records: "Mga Rekord ng Pasyente", nav_records_history: "Kasaysayan ng Rekord", nav_reports: "Mga Ulat",
            section_system: "Hardware at Sistema", nav_admin: "Administrasyon", nav_user_mgmt: "Pamamahala ng Gumagamit",
            nav_settings: "Mga Setting", nav_logout: "Mag-logout", header_greeting: "Magandang Araw, Admin",
            header_desc: "Pangkalahatang-ideya ng estado ng sistema.", avg_health_title: "Gitarang Kalusugan",
            avg_health_desc: "Karaniwang naitalang sukat ng vital signs", tbl_measurement: "Sukat", tbl_average: "Average",
            tbl_unit: "Yunit", lbl_height: "Taas", lbl_weight: "Timbang", lbl_temp: "Temperatura",
            lbl_heart: "Bilis ng Puso", lbl_bp: "Presyon ng Dugo", title_new_patients: "Bagong Pasyente",
            title_patients_overview: "Pangkalahatang-ideya ng Pasyente", title_services: "Kategorya ng Serbisyo",
            title_recent_patients: "Kasalukuyang Listahan ng Pasyente", title_followup: "Iskedyul ng Follow-up", dark_mode_title: "Dark Mode"
        },
        ceb: {
            section_main: "Pangunahing", nav_dashboard: "Dashboard", section_clinical: "Mga Serbisyong Klinikal",
            nav_patient_mgmt: "Pagdumala sa Pasyente", nav_all_patients: "Tanan nga Serbisyong Pasyente", nav_add_patient: "Idugang ang Pasyente",
            nav_patient_records: "Mga Rekord sa Pasyente", nav_records_history: "Kasaysayan sa Rekord", nav_reports: "Mga Report",
            section_system: "Hardware ug Sistema", nav_admin: "Administrasyon", nav_user_mgmt: "Pagdumala sa Paggamit",
            nav_settings: "Mga Setting", nav_logout: "Mo-logout", header_greeting: "Maayong Adlaw, Admin",
            header_desc: "Kinatibuk-ang pagtan-aw sa estado sa sistema.", avg_health_title: "Kasagarang Panglawas",
            avg_health_desc: "Kasagarang nahitala nga vital signs", tbl_measurement: "Sukat", tbl_average: "Average",
            tbl_unit: "Yunit", lbl_height: "Gitas-on", lbl_weight: "Timbang", lbl_temp: "Temperatura",
            lbl_heart: "Kusog sa Kasingkasing", lbl_bp: "Presyon sa Dugo", title_new_patients: "Bag-ong Pasyente",
            title_patients_overview: "Kinatibuk-ang Pasyente", title_services: "Mga Kategorya sa Serbisyo",
            title_recent_patients: "Bag-ong Listahan sa Pasyente", title_followup: "Iskedyul sa Follow-up", dark_mode_title: "Dark Mode"
        }
    };
    const savedLang = localStorage.getItem("language");
    if (savedLang && i18n[savedLang]) {
        const dict = i18n[savedLang];
        document.querySelectorAll("[data-i18n]").forEach(el => {
            const key = el.getAttribute("data-i18n");
            if (dict[key]) el.innerText = dict[key];
        });
    }
})();
</script>
<script src="../../assets/js/theme.js"></script>
</body>
</html>