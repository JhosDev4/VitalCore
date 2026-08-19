<?php

/** @var mysqli $conn */
session_start();
require_once('../db_conn.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Patient not found.");
}

$patient_id = (int)$_GET['id'];

// 1. Fetch Patient Info
$query = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE id = $patient_id"
);
$patient = mysqli_fetch_assoc($query);

if (!$patient) {
    die("Patient not found.");
}

// 2. Fetch Latest Vital Measurements
$temp       = '--';
$weight     = '--';
$height     = '--';
$bmi        = '--';
$heart_rate = '--';
$spo2       = '--';
$systolic   = '--';
$diastolic  = '--';
$last_visited = 'No visits recorded';

$measurementQuery = mysqli_query(
    $conn,
    "SELECT * FROM measurements WHERE user_id = $patient_id ORDER BY created_at DESC LIMIT 1"
);

if ($measurementQuery && mysqli_num_rows($measurementQuery) > 0) {
    $measurement = mysqli_fetch_assoc($measurementQuery);

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

// Display strings
$temp_disp       = ($temp_val !== null)   ? number_format($temp_val, 1) . ' °C' : '--';
$weight_disp     = ($weight_val !== null) ? number_format($weight_val, 1) . ' kg' : '--';
$height_disp     = ($height_val !== null) ? number_format($height_val, 1) . ' cm' : '--';
$bmi_disp        = ($bmi_val !== null)    ? number_format($bmi_val, 2)          : '--';
$heart_rate_disp = ($heart_rate_val !== null) ? $heart_rate_val . ' BPM'        : '--';
$spo2_disp       = ($spo2_val !== null) ? $spo2_val . '%'                      : '--';
$bp_disp         = ($systolic_val !== null && $diastolic_val !== null) ? $systolic_val . '/' . $diastolic_val . ' mmHg' : '--';

// Status Evaluations
$temp_status = 'Normal';
$temp_class  = 'badge-success';
if ($temp_val !== null) {
    if ($temp_val < 36.0) { $temp_status = 'Low Temp'; $temp_class = 'badge-info'; }
    elseif ($temp_val > 37.5 && $temp_val <= 38.5) { $temp_status = 'Slight Fever'; $temp_class = 'badge-warning'; }
    elseif ($temp_val > 38.5) { $temp_status = 'High Fever'; $temp_class = 'badge-danger'; }
}

$has_bmi = ($bmi_val !== null && $weight_val !== null);
$bmi_status = '--';
$bmi_class  = 'badge-secondary';
if ($has_bmi) {
    if ($bmi_val < 18.5) { $bmi_status = 'Underweight'; $bmi_class = 'badge-info'; }
    elseif ($bmi_val <= 24.9) { $bmi_status = 'Normal'; $bmi_class = 'badge-success'; }
    elseif ($bmi_val <= 29.9) { $bmi_status = 'Overweight'; $bmi_class = 'badge-warning'; }
    else { $bmi_status = 'Obese'; $bmi_class = 'badge-danger'; }
}

$heart_status = 'Normal';
$heart_class  = 'badge-success';
if ($heart_rate_val !== null) {
    if ($heart_rate_val < 60) { $heart_status = 'Low (Bradycardia)'; $heart_class = 'badge-warning'; }
    elseif ($heart_rate_val > 100) { $heart_status = 'High (Tachycardia)'; $heart_class = 'badge-danger'; }
}

$spo2_status = 'Normal';
$spo2_class  = 'badge-success';
if ($spo2_val !== null) {
    if ($spo2_val < 90) { $spo2_status = 'Critical Low'; $spo2_class = 'badge-danger'; }
    elseif ($spo2_val < 95) { $spo2_status = 'Mild Low'; $spo2_class = 'badge-warning'; }
}

// Calculate Health Score
$health_score = 100;
if ($temp_status !== 'Normal') $health_score -= 15;
if ($heart_status !== 'Normal') $health_score -= 15;
if ($spo2_status !== 'Normal') $health_score -= 20;
if ($bmi_status === 'Obese' || $bmi_status === 'Underweight') $health_score -= 10;
$health_score = max(40, min(100, $health_score));
if ($temp_val === null && $heart_rate_val === null) $health_score = '--';

$safe_filename = 'Patient_Report_PT' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT) . '_' . preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', strtolower($patient['fullname'])));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Clinical Record - #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/document.css">
    <link rel="stylesheet" href="../css/theme.css">
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>


</head>
<body>

<!-- TOP ACTION TOOLBAR -->
<div class="no-print bg-white border-bottom p-3 mb-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 820px;">
        <a href="patient-view.php?id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Patient View
        </a>

        <!-- ACTION BUTTONS -->
        <div class="d-flex gap-2">
            <button onclick="saveAsPDF()" class="btn btn-success btn-sm px-3 fw-bold">
                <i class="bi bi-folder-plus me-1"></i> Save As PDF...
            </button>
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Print
            </button>
        </div>
    </div>
</div>

<div class="container">
    <div class="document-wrapper" id="document-wrapper">

        <!-- CLINIC LETTERHEAD HEADER -->
        <div class="clinic-header">
            <div class="clinic-brand">
                <img src="../img/logo.jpg" alt="Logo" class="clinic-logo" onerror="this.src='https://via.placeholder.com/48?text=VC'">
                <div>
                    <h1 class="clinic-name">VitalCore Medical Record</h1>
                    <p class="clinic-sub">Clinical Patient Record & Health Assessment</p>
                </div>
            </div>
            <div class="text-end">
                <span class="doc-title-badge">OFFICIAL MEDICAL REPORT</span>
                <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                    <strong>Document Date:</strong> <?= date('F d, Y'); ?>
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
                <span class="info-value">
                    #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Full Name:</span>
                <span class="info-value">
                    <?= htmlspecialchars($patient['fullname']); ?>
                </span>
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
                    <?php
                        if (!empty($patient['birth_date'])) {
                            echo date('F d, Y', strtotime($patient['birth_date']));
                        } else {
                            echo '--';
                        }
                    ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Blood Type:</span>
                <span class="info-value">
                    <?= !empty($patient['blood_type'])
                        ? htmlspecialchars($patient['blood_type'])
                        : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Contact No:</span>
                <span class="info-value">
                    <?= !empty($patient['contact_number'])
                        ? htmlspecialchars($patient['contact_number'])
                        : '--'; ?>
                </span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Address:</span>
                <span class="info-value">
                    <?= !empty($patient['address'])
                        ? htmlspecialchars($patient['address'])
                        : '--'; ?>
                </span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Last Visited:</span>
                <span class="info-value text-primary">
                    <?= $last_visited; ?>
                </span>
            </div>

        </div>
        <!-- HEALTH SCORE SUMMARY (FORCED SOLID BLUE COLOR) -->
        <div class="health-score-box">
            <div>
                <div class="fw-bold" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">OVERALL HEALTH ASSESSMENT SCORE</div>
                <div style="font-size: 0.78rem; opacity: 0.9;" class="mt-1">Calculated based on latest recorded vital parameters</div>
            </div>
            <div class="text-end">
                <div class="score-val"><?= $health_score ?><?= is_numeric($health_score) ? '/100' : '' ?></div>
            </div>
        </div>

        <!-- VITAL SIGNS BREAKDOWN TABLE -->
        <div class="section-header">
            <i class="bi bi-heart-pulse-fill text-danger"></i> Vital Signs & Physical Measurements
        </div>

        <table class="vitals-table">
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Measured Value</th>
                    <th>Reference / Target</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Blood Pressure</strong></td>
                    <td><?= $bp_disp; ?></td>
                    <td>120/80 mmHg</td>
                    <td><span class="status-badge badge-success">Normal</span></td>
                </tr>
                <tr>
                    <td><strong>Heart Rate</strong></td>
                    <td><?= $heart_rate_disp; ?></td>
                    <td>60 - 100 BPM</td>
                    <td><span class="status-badge <?= $heart_class; ?>"><?= $heart_status; ?></span></td>
                </tr>
                <tr>
                    <td><strong>SpO2 (Blood Oxygen)</strong></td>
                    <td><?= $spo2_disp; ?></td>
                    <td>95% - 100%</td>
                    <td><span class="status-badge <?= $spo2_class; ?>"><?= $spo2_status; ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Temperature</strong></td>
                    <td><?= $temp_disp; ?></td>
                    <td>36.5 °C - 37.5 °C</td>
                    <td><span class="status-badge <?= $temp_class; ?>"><?= $temp_status; ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Weight</strong></td>
                    <td><?= $weight_disp; ?></td>
                    <td>--</td>
                    <td><span class="status-badge badge-secondary">Recorded</span></td>
                </tr>
                <tr>
                    <td><strong>Height</strong></td>
                    <td><?= $height_disp; ?></td>
                    <td>--</td>
                    <td><span class="status-badge badge-secondary">Recorded</span></td>
                </tr>
                <tr>
                    <td><strong>BMI Score</strong></td>
                    <td><?= $bmi_disp; ?></td>
                    <td>18.5 - 24.9</td>
                    <td><span class="status-badge <?= $bmi_class; ?>"><?= $bmi_status; ?></span></td>
                </tr>
            </tbody>
        </table>

        <!-- CLINICAL DIAGNOSIS / PHYSICIAN NOTES -->
        <div class="section-header">
            <i class="bi bi-journal-medical text-primary"></i> Clinical Observations & Diagnosis
        </div>

        <div class="notes-box">
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

<script>
const defaultFileName = '<?= $safe_filename; ?>';

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
    const content = document.getElementById('document-wrapper').outerHTML;
    const sourceHTML = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'><title>Patient Medical Report</title></head><body>" + content + "</body></html>";
    const wordBlob = new Blob([sourceHTML], { type: 'application/msword' });

    if ('showSaveFilePicker' in window) {
        try {
            const handle = await window.showSaveFilePicker({
                suggestedName: defaultFileName + '.doc',
                types: [{
                    description: 'Word Document (*.doc)',
                    accept: { 'application/msword': ['.doc', '.docx'] }
                }]
            });
            const writable = await handle.createWritable();
            await writable.write(wordBlob);
            await writable.close();
        } catch (err) {
            if (err.name !== 'AbortError') {
                fallbackDownload(wordBlob, defaultFileName + '.doc');
            }
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