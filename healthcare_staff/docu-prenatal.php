<?php
/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../db_conn.php');

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

$patient_id     = (int)$_GET['id'];
$measurement_id = isset($_GET['measurement_id']) && is_numeric($_GET['measurement_id']) ? (int)$_GET['measurement_id'] : 0;

// 1. Fetch Patient Info
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = $patient_id LIMIT 1");
$patient = mysqli_fetch_assoc($query);
if (!$patient) { die("Patient not found."); }

// 2. Fetch Vital Measurements for This Visit
$temp = $weight = $height = $bmi = $heart_rate = $spo2 = $systolic = $diastolic = '--';
$last_visited = 'No visits recorded';
$doc_date     = date('F d, Y');

if ($measurement_id > 0) {
    $measurementQuery = mysqli_query($conn, "SELECT * FROM measurements WHERE id = $measurement_id LIMIT 1");
} else {
    $measurementQuery = mysqli_query($conn, "SELECT * FROM measurements WHERE user_id = $patient_id ORDER BY created_at DESC LIMIT 1");
}

if ($measurementQuery && mysqli_num_rows($measurementQuery) > 0) {
    $measurement    = mysqli_fetch_assoc($measurementQuery);
    $temp           = $measurement['temperature'] ?? '--';
    $weight         = $measurement['weight']      ?? '--';
    $height         = $measurement['height']      ?? '--';
    $bmi            = $measurement['bmi']         ?? '--';
    $heart_rate     = $measurement['heart_rate']  ?? '--';
    $spo2           = $measurement['spo2']        ?? '--';
    $systolic       = $measurement['systolic']    ?? '--';
    $diastolic      = $measurement['diastolic']   ?? '--';
    if (!empty($measurement['created_at'])) {
        $last_visited = date('F d, Y - h:i A', strtotime($measurement['created_at']));
        $doc_date     = date('F d, Y', strtotime($measurement['created_at']));
    }
}

// 3. Data Processing & Formatting
$temp_val       = (is_numeric($temp)       && (float)$temp > 0)     ? (float)$temp       : null;
$weight_val     = (is_numeric($weight)     && (float)$weight > 0)   ? (float)$weight     : null;
$height_val     = (is_numeric($height)     && (float)$height > 0)   ? (float)$height     : null;
$bmi_val        = (is_numeric($bmi)        && (float)$bmi > 0)      ? (float)$bmi        : null;
$heart_rate_val = (is_numeric($heart_rate) && (int)$heart_rate > 0) ? (int)$heart_rate   : null;
$spo2_val       = (is_numeric($spo2)       && (int)$spo2 > 0)       ? (int)$spo2         : null;
$systolic_val   = (is_numeric($systolic)   && (int)$systolic > 0)   ? (int)$systolic     : null;
$diastolic_val  = (is_numeric($diastolic)  && (int)$diastolic > 0)  ? (int)$diastolic    : null;

// Display strings
$temp_disp       = ($temp_val !== null)   ? number_format($temp_val, 1) . ' °C' : '--';
$weight_disp     = ($weight_val !== null) ? number_format($weight_val, 1) . ' kg' : '--';
$height_disp     = ($height_val !== null) ? number_format($height_val, 1) . ' cm' : '--';
$bmi_disp        = ($bmi_val !== null)    ? number_format($bmi_val, 2) : '--';
$heart_rate_disp = ($heart_rate_val !== null) ? $heart_rate_val . ' BPM' : '--';
$spo2_disp       = ($spo2_val !== null)   ? $spo2_val . '%' : '--';
$bp_disp         = ($systolic_val !== null && $diastolic_val !== null)
                   ? $systolic_val . '/' . $diastolic_val . ' mmHg' : '--';

// Status Evaluations
$temp_status = '--'; $temp_class  = 'badge-secondary';
if ($temp_val !== null) {
    if ($temp_val < 36.0)                           { $temp_status = 'Low Temp';     $temp_class = 'badge-info'; }
    elseif ($temp_val <= 37.5)                      { $temp_status = 'Normal';       $temp_class = 'badge-success'; }
    elseif ($temp_val > 37.5 && $temp_val <= 38.5)  { $temp_status = 'Slight Fever'; $temp_class = 'badge-warning'; }
    else                                            { $temp_status = 'High Fever';   $temp_class = 'badge-danger'; }
}

$has_bmi    = ($bmi_val !== null && $weight_val !== null);
$bmi_status = '--'; $bmi_class  = 'badge-secondary';
if ($has_bmi) {
    if ($bmi_val < 18.5)      { $bmi_status = 'Underweight'; $bmi_class = 'badge-info'; }
    elseif ($bmi_val <= 24.9) { $bmi_status = 'Normal';      $bmi_class = 'badge-success'; }
    elseif ($bmi_val <= 29.9) { $bmi_status = 'Overweight';  $bmi_class = 'badge-warning'; }
    else                      { $bmi_status = 'Obese';        $bmi_class = 'badge-danger'; }
}

$heart_status = '--'; $heart_class  = 'badge-secondary';
if ($heart_rate_val !== null) {
    if ($heart_rate_val < 60)       { $heart_status = 'Low (Bradycardia)';  $heart_class = 'badge-warning'; }
    elseif ($heart_rate_val <= 100) { $heart_status = 'Normal';             $heart_class = 'badge-success'; }
    else                            { $heart_status = 'High (Tachycardia)'; $heart_class = 'badge-danger'; }
}

$spo2_status = '--'; $spo2_class  = 'badge-secondary';
if ($spo2_val !== null) {
    if ($spo2_val < 90)      { $spo2_status = 'Critical Low'; $spo2_class = 'badge-danger'; }
    elseif ($spo2_val < 95)  { $spo2_status = 'Mild Low';     $spo2_class = 'badge-warning'; }
    else                     { $spo2_status = 'Normal';        $spo2_class = 'badge-success'; }
}

$bp_status = '--'; $bp_class  = 'badge-secondary';
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

$weight_status = ($weight_val !== null) ? 'Recorded' : '--';
$height_status = ($height_val !== null) ? 'Recorded' : '--';

$safe_filename = 'Prenatal_PT' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT)
               . '_' . preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', strtolower($patient['fullname'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
    <title>Pre-Natal Chart - #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/document.css">
    <link rel="stylesheet" href="../css/theme.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .pn-section { margin-bottom: 0; }

        /* Dark Mode Override Fix for Clinic Title */
        .clinic-name,
        h1.clinic-name,
        html.dark-mode .clinic-name,
        body.dark-mode .clinic-name,
        .dark-mode h1.clinic-name {
            color: #d9534f !important;
        }

        .pn-header {
            background: #d9534f;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 3px 3px 0 0;
        }

        .pn-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.72rem;
            margin-bottom: 10px;
        }

        .pn-table th,
        .pn-table td {
            border: 1px solid #aaa;
            padding: 4px 5px;
            vertical-align: middle;
            text-align: center;
            color: #1e293b;
        }

        /* ✅ FIXED — document is always white paper, text stays dark */
        html.dark-mode .pn-table th,
        html.dark-mode .pn-table td,
        body.dark-mode .pn-table th,
        body.dark-mode .pn-table td {
            color: #1e293b !important;
        }

        /* Vitals table fix too */
        html.dark-mode .vitals-table th,
        html.dark-mode .vitals-table td,
        body.dark-mode .vitals-table th,
        body.dark-mode .vitals-table td {
            color: #1e293b !important;
        }

        /* Colored rows always black text */
        html.dark-mode .row-pink,
        html.dark-mode .row-pink td,
        html.dark-mode .row-blue,
        html.dark-mode .row-blue td,
        html.dark-mode .row-peach,
        html.dark-mode .row-peach td,
        body.dark-mode .row-pink,
        body.dark-mode .row-pink td,
        body.dark-mode .row-blue,
        body.dark-mode .row-blue td {
            color: #000000 !important;
        }

        .pn-table td.text-start { text-align: left !important; }

        .pn-table thead th {
            background: #fde8e6;
            font-weight: 700;
            font-size: 0.68rem;
            color: #2c3e50;
        }

        /* High Contrast Colored Rows */
        .row-pink { background-color: #f7b0a7 !important; color: #000000 !important; }
        .row-blue { background-color: #b0c6e8 !important; color: #000000 !important; }
        .row-peach { background-color: #fce4b4 !important; color: #000000 !important; }

        .row-pink td, .row-blue td, .row-peach td,
        html.dark-mode .row-pink td, html.dark-mode .row-blue td, html.dark-mode .row-peach td,
        body.dark-mode .row-pink td, body.dark-mode .row-blue td, body.dark-mode .row-peach td {
            color: #000000 !important;
        }

        /* Editable Blank Lines & Table Cells */
        .blank-line {
            display: inline-block;
            border-bottom: 1px solid #333;
            min-width: 80px;
            height: 16px;
            line-height: 16px;
            vertical-align: bottom;
            padding: 0 4px;
            outline: none;
        }
        .blank-line-sm { min-width: 35px; }
        .blank-line-lg { min-width: 130px; }

        [contenteditable="true"] {
            outline: none;
            cursor: pointer;
        }

        .pn-info-row {
            display: flex;
            gap: 18px;
            font-size: 0.75rem;
            margin-bottom: 6px;
            align-items: center;
            flex-wrap: wrap;
        }

        .pn-info-row label {
            font-weight: 600;
            margin-right: 4px;
            white-space: nowrap;
        }

        .legend-box {
            font-size: 0.68rem;
            color: #333;
            margin-top: 4px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        html.dark-mode .legend-box,
        body.dark-mode .legend-box {
            color: #e2e8f0;
        }

        .legend-square {
            display: inline-block;
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 4px;
            border-radius: 2px;
        }

        /* Black Dot Circle Styling */
        .dot-circle {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #333;
            border-radius: 50%;
            margin-left: 4px;
            vertical-align: middle;
        }
        html.dark-mode .dot-circle,
        body.dark-mode .dot-circle {
            background-color: #ffffff !important;
        }

        /* Check Icon Styling */
        .check-icon {
            font-size: 0.85rem;
            color: #198754;
            vertical-align: middle;
            margin-right: 2px;
        }
        html.dark-mode .check-icon,
        body.dark-mode .check-icon {
            color: #2ec4b6 !important;
        }

        .pp-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            font-size: 0.72rem;
        }

        .pp-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pp-item label { font-weight: 600; white-space: nowrap; }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .document-wrapper { box-shadow: none !important; }
        }
    </style>
</head>
<body>

<!-- TOP ACTION TOOLBAR -->
<div class="no-print bg-white border-bottom p-3 mb-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 860px;">
        <a href="patient-history-list.php?user_id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Patient History
        </a>
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

<div class="container" style="max-width: 860px;">
<div class="document-wrapper" id="document-wrapper">

    <!-- CLINIC LETTERHEAD HEADER -->
    <div class="clinic-header" style="border-bottom: 2px solid #d9534f;">
        <div class="clinic-brand">
            <img src="../../img/logo.jpg" alt="Logo" class="clinic-logo"
                 onerror="this.src='https://via.placeholder.com/48?text=VC'">
            <div>
                <h1 class="clinic-name" style="color:#d9534f !important;">MOMMY'S Pre-Natal Chart</h1>
                <p class="clinic-sub">Clinical Patient Record & Maternal Health Assessment</p>
            </div>
        </div>
        <div class="text-end">
            <span class="doc-title-badge" style="background:#fde8e6; color:#d9534f; border:1px solid #f8b4b4;">OFFICIAL PRE-NATAL RECORD</span>
            <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                <strong>Document Date:</strong> <?= $doc_date; ?>
            </div>
        </div>
    </div>

    <!-- SECTION 1 — PERSONAL INFORMATION -->
    <div class="pn-section">
        <div class="pn-header">PERSONAL INFORMATION</div>
        <div class="info-grid" style="margin-top:0; border-top:none; border-radius:0 0 4px 4px;">

            <div class="info-item">
                <span class="info-label">Blood Type:</span>
                <span class="info-value"><?= !empty($patient['blood_type']) ? htmlspecialchars($patient['blood_type']) : '--'; ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">Family Serial No:</span>
                <span class="info-value"><span contenteditable="true" class="blank-line blank-line-lg"></span></span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Name:</span>
                <span class="info-value"><?= htmlspecialchars($patient['fullname']); ?></span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Address:</span>
                <span class="info-value"><?= !empty($patient['address']) ? htmlspecialchars($patient['address']) : '--'; ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">B-Date:</span>
                <span class="info-value">
                    <?= !empty($patient['birth_date']) ? date('F d, Y', strtotime($patient['birth_date'])) : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Contact No:</span>
                <span class="info-value"><?= !empty($patient['contact_number']) ? htmlspecialchars($patient['contact_number']) : '--'; ?></span>
            </div>

        </div>
    </div>

    <!-- SECTION 2 — TETANUS TOXOID -->
    <div class="pn-section mt-3">
        <div class="pn-header">TETANUS TOXOID</div>
        <table class="pn-table" style="border-top:none;">
            <thead>
                <tr>
                    <th style="width:25%; text-align:left;"></th>
                    <th style="width:15%;">1</th>
                    <th style="width:15%;">2</th>
                    <th style="width:15%;">3</th>
                    <th style="width:15%;">4</th>
                    <th style="width:15%;">5</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-start"><strong>Date Given</strong></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start"><strong>Age:</strong> <?= htmlspecialchars($patient['age']); ?> yrs</td>
                    <td colspan="2" contenteditable="true">Below 18</td>
                    <td colspan="2" contenteditable="true">18-34</td>
                    <td contenteditable="true">35+</td>
                </tr>
                <tr>
                    <td class="text-start"><strong>Height:</strong> <?= $height_disp ?></td>
                    <td colspan="2" contenteditable="true">Below 145 cm</td>
                    <td colspan="3" class="text-start ps-3"><strong>BMI:</strong> <?= $bmi_disp ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SECTION 3 — OBSTETRICAL HISTORY -->
    <div class="pn-section mt-3">
        <div class="pn-header">OBSTETRICAL HISTORY</div>
        <div style="border:1px solid #aaa; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            
            <div style="font-size:0.85rem; font-weight:700; margin-bottom:8px; padding-left:4px;">
                G <span contenteditable="true" class="blank-line blank-line-sm"></span> &nbsp;
                L <span contenteditable="true" class="blank-line blank-line-sm"></span> &nbsp;
                P <span contenteditable="true" class="blank-line blank-line-sm"></span> &nbsp;
                O <span contenteditable="true" class="blank-line blank-line-sm"></span> &nbsp;&nbsp;&nbsp;
                ( 
                  <span contenteditable="true" class="blank-line blank-line-sm"></span> &nbsp;&nbsp;&nbsp;
                  <span contenteditable="true" class="blank-line blank-line-sm"></span> &nbsp;&nbsp;&nbsp;
                  <span contenteditable="true" class="blank-line blank-line-sm"></span> &nbsp;&nbsp;&nbsp;
                  <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                )
                <div style="font-size:0.62rem; color:#666; margin-left:145px; margin-top:-2px; letter-spacing: 22px;">
                         &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;T&nbsp;P&nbsp;A&nbsp;L
                </div>
            </div>

            <table class="pn-table mb-1">
                <thead>
                    <tr>
                        <th rowspan="2" class="text-start">Previous Pregnancies</th>
                        <th colspan="2">1</th>
                        <th colspan="2">2</th>
                        <th colspan="2">3</th>
                        <th colspan="2">4</th>
                        <th colspan="2">5</th>
                        <th colspan="2">6</th>
                    </tr>
                    <tr>
                        <th>Y</th><th>N</th>
                        <th>Y</th><th>N</th>
                        <th>Y</th><th>N</th>
                        <th>Y</th><th>N</th>
                        <th>Y</th><th>N</th>
                        <th>Y</th><th>N</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-start">Caesarean Section</td>
                        <td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td>
                    </tr>
                    <tr>
                        <td class="text-start">Stillbirth</td>
                        <td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td>
                    </tr>
                    <tr>
                        <td class="text-start">Post-partum Hemorrhage</td>
                        <td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td>
                    </tr>
                    <tr>
                        <td class="text-start">3 Consecutive Miscarriages</td>
                        <td colspan="6" contenteditable="true" style="text-align:center;">YES <span class="dot-circle"></span></td>
                        <td colspan="6" contenteditable="true" style="text-align:center;">NO</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 4 — PRESENT HEALTH PROBLEMS -->
    <div class="pn-section mt-3">
        <div class="pn-header">PRESENT HEALTH PROBLEMS</div>
        <table class="pn-table" style="border-top:none;">
            <tbody>
                <tr>
                    <td class="text-start">Tuberculosis (14 days + of cough)</td>
                    <td style="width:30%;" contenteditable="true">NO</td>
                    <td class="row-pink" style="width:30%;" contenteditable="true">YES</td>
                </tr>
                <tr>
                    <td class="text-start">Heart Disease</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true">YES</td>
                </tr>
                <tr>
                    <td class="text-start">Diabetes</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true">YES</td>
                </tr>
                <tr>
                    <td class="text-start">Bronchial Asthma</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true">YES</td>
                </tr>
                <tr>
                    <td class="text-start">Goiter</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true">YES</td>
                </tr>
                <tr>
                    <td class="text-start">Hypertension</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true">YES</td>
                </tr>
            </tbody>
        </table>

        <div class="legend-box px-2 pb-2">
            <div>
                <span class="legend-square" style="background:#b0c6e8;"></span>Refer to Physician/RHU (and follow-up)
                
                <span class="legend-square" style="background:#f7b0a7;"></span>Close observation or action by midwife/nurse
                
                <span class="dot-circle" style="width:12px; height:12px; margin-right:4px;"></span>Hospital delivery recommeded
            </div>
        </div>
        <div style="font-size:0.65rem; color:#666; padding-left:8px; margin-top:-4px;" class="mb-2">
            <em>*You may wish to consider a</em><br>
            <em>permanent method of Family Planning*</em>
        </div>
    </div>

    <!-- SECTION 5 — VITAL SIGNS TELEMETRY (FROM DB) -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-heart-pulse-fill me-1"></i> Vital Signs Telemetry Measurements</div>
        <table class="vitals-table" style="border-top:none;">
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Measured Value</th>
                    <th>Target / Normal</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Blood Pressure</strong></td>
                    <td contenteditable="true"><?= $bp_disp ?></td>
                    <td>120/80 mmHg</td>
                    <td><span class="status-badge <?= $bp_class ?>"><?= $bp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Heart Rate</strong></td>
                    <td contenteditable="true"><?= $heart_rate_disp ?></td>
                    <td>60 – 100 BPM</td>
                    <td><span class="status-badge <?= $heart_class ?>"><?= $heart_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>SpO2 (Blood Oxygen)</strong></td>
                    <td contenteditable="true"><?= $spo2_disp ?></td>
                    <td>95% – 100%</td>
                    <td><span class="status-badge <?= $spo2_class ?>"><?= $spo2_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Temperature</strong></td>
                    <td contenteditable="true"><?= $temp_disp ?></td>
                    <td>36.0 °C – 37.5 °C</td>
                    <td><span class="status-badge <?= $temp_class ?>"><?= $temp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Weight</strong></td>
                    <td contenteditable="true"><?= $weight_disp ?></td>
                    <td>--</td>
                    <td><span class="status-badge badge-secondary"><?= $weight_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Height</strong></td>
                    <td contenteditable="true"><?= $height_disp ?></td>
                    <td>--</td>
                    <td><span class="status-badge badge-secondary"><?= $height_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>BMI Score</strong></td>
                    <td contenteditable="true"><?= $bmi_disp ?></td>
                    <td>18.5 – 24.9</td>
                    <td><span class="status-badge <?= $bmi_class ?>"><?= $bmi_status ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SECTION 6 — PRESENT PREGNANCY (LMP & EDC) -->
    <div class="pn-section mt-3">
        <div class="pn-header">PRESENT PREGNANCY</div>
        <div style="border:1px solid #aaa; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            
            <div class="d-flex justify-content-between align-items-center mb-4" style="font-size:0.75rem;">
                <div class="d-flex gap-2">
                    <div>
                        <strong class="text-danger">LMP:</strong> 
                        MONTH <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                        DAY <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                        YEAR <span contenteditable="true" class="blank-line blank-line-sm"></span>
                        <span class="badge" style="background:#b0c6e8; color:#1e3a8a;">Refer to Hospital</span>
                    </div>
                    <div>
                        <strong class="text-danger">EDC:</strong> 
                        MONTH <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                        DAY <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                        YEAR <span contenteditable="true" class="blank-line blank-line-sm"></span>
                        <span class="badge" style="background:#f7b0a7; color:#881337;">Refer to Physician / RHU</span>
                    </div>
                </div>
            </div>

            <table class="pn-table mb-1">
                <thead>
                    <tr>
                        <th rowspan="2" class="text-start" style="width:25%;">TRIMESTER</th>
                        <th colspan="3">1st</th>
                        <th colspan="3">2nd</th>
                        <th colspan="6">3rd</th>
                    </tr>
                    <tr>
                        <th>1</th><th>2</th><th>3</th>
                        <th>4</th><th>5</th><th>6</th>
                        <th>7</th><th>8</th><th colspan="4">9</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-start">AOG in Months</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">Date of Visit</td>
                        <?php for ($i = 1; $i <= 1; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 2; $i <= 12; $i++): ?><td class="row-blue" contenteditable="true"></td><?php endfor; ?> 
                    </tr>
                    <tr>
                        <td class="text-start">Vaginal Bleeding (Y/N)</td>
                        <?php for ($i = 1; $i <= 1; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 2; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">Urinary Tract Infection</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">Weight in Kg</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">Blood Pressure</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">BP 140/90 and above (Y/N)</td>
                        <?php for ($i = 1; $i <= 1; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 2; $i <= 12; $i++): ?><td class="row-blue" contenteditable="true"></td><?php endfor; ?> 
                    </tr>
                    <tr>
                        <td class="text-start">Fever 39 and above (Y/N)</td>
                        <?php for ($i = 1; $i <= 1; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 2; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?> 
                    </tr>
                    <tr>
                        <td class="text-start">Pallor (Y/N)</td>
                        <?php for ($i = 1; $i <= 4; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 5; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td rowspan="2" class="text-start">Abnormal Fundal Height (Y/N)</td>
                        <?php for ($i = 1; $i <= 4; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 5; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <?php for ($i = 1; $i <= 5; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <td style="font-size:0.6rem; font-weight:bold;" contenteditable="true">20<br>cm</td>
                        <td style="font-size:0.6rem; font-weight:bold;" contenteditable="true">21-24<br>cm</td>
                        <td style="font-size:0.6rem; font-weight:bold;" contenteditable="true">25-28<br>cm</td>
                        <td style="font-size:0.6rem; font-weight:bold;" contenteditable="true">28-30<br>cm</td>
                        <td colspan="4" style="font-size:0.6rem; font-weight:bold;" contenteditable="true">30-34<br>cm</td>
                    </tr>
                    <tr>
                        <td class="text-start">Abnormal Presentation (Y/N)</td>
                        <?php for ($i = 1; $i <= 5; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 6; $i <= 12; $i++): ?><td class="row-blue" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">Missing Fetal Heartbeat (Y/N)</td>
                        <?php for ($i = 1; $i <= 5; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 6; $i <= 12; $i++): ?><td class="row-blue" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">Edema (Y/N)</td>
                        <?php for ($i = 1; $i <= 4; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 5; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">Vaginal Infection (Y/N)</td>
                        <?php for ($i = 1; $i <= 4; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 5; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start">Lab Test Results (e.g. HGB, Urine, VDRL)</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 7 — ACTION -->
    <div class="pn-section mt-3">
        <div class="pn-header">ACTION</div>
        <table class="pn-table" style="border-top:none;">
            <thead>
                <tr>
                    <th class="text-start" style="width:35%;">Iron/Folate#/RX</th>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <th><?= $i ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $action_rows = [
                    'Iodine Supplementation in High Risk Areas',
                    'Malaria',
                    'Prophylaxis (Y/N)',
                    'Mother intends to breastfeed? (Y/N)',
                    'Advice on 4 danger signs (Y/N)',
                    'Dental Check-up? (Y/N)',
                    'Emergency plans and place of delivery (Y/N)',
                    'Risk? (Y/N)',
                    'Date of next visit',
                ];
                foreach ($action_rows as $row): ?>
                <tr>
                    <td class="text-start"><?= $row ?></td>
                    <?php for ($i = 1; $i <= 9; $i++): ?><td style="height:18px;" contenteditable="true"></td><?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- SECTION 8 — LABOR AND DELIVERY -->
    <div class="pn-section mt-3">
        <div class="pn-header">LABOR AND DELIVERY</div>
        <div style="border:1px solid #aaa; border-top:none; padding:10px; border-radius:0 0 4px 4px;">
            <div class="pp-grid">
                <div class="pp-item"><label>Immediate breastfeeding (Y/N):</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label>Birth Weight in grams:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label>Type of delivery:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label>Post Partum Hemorrhage 500 CC+ (N/Y):</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label>Date of delivery:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label>Baby Alive:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label>Place of delivery:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label>Baby Healthy (Y/N):</label> <span contenteditable="true" class="blank-line"></span></div>
            </div>
        </div>
    </div>

    <!-- SECTION 9 — POST PARTUM -->
    <div class="pn-section mt-3">
        <div class="pn-header">POST PARTUM</div>
        <table class="pn-table" style="border-top:none;">
            <thead>
                <tr>
                    <th rowspan="3" class="text-start" style="width:45%;">Timing of Post Partum Visit <br><br> Date of Visit</th>
                    <th colspan="3">HOME VISITS</th>
                    <th rowspan="2">CLINIC VISIT</th>
                </tr>
                <tr>
                    <th>24 hrs</th>
                    <th>1 week</th>
                    <th>2-4 weeks</th>
                </tr>
                <tr>
                    <th contenteditable="true"></th>
                    <th contenteditable="true"></th>
                    <th contenteditable="true"></th>
                    <th contenteditable="true"></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-start">Exclusive Breastfeeding (Y/N)</td>
                    <td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start">Intends to use Family Planning (Y/N)</td>
                    <td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start">Fever >39C (Y/N)</td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start">Foul Smelling Vaginal Discharge (Y/N)</td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start">Excessive Bleeding (Y/N)</td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start">Pallor (Y/N)</td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start">Cord OK? (Y/N)</td>
                    <td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td>
                </tr>
            </tbody>
        </table>

        <!-- Post Partum Legend -->
        <div class="legend-box px-2 pb-2 mb-2">
            <strong>LEGEND:</strong>
            <div><span class="legend-square" style="background:#b0c6e8;"></span> Refer to Hospital</div>
            <div class="ms-3"><span class="legend-square" style="background:#f7b0a7;"></span> Refer to Physician / RHU</div>
        </div>

        <!-- Separate Post Partum Table (Vitamin A & Iron/Folate) -->
        <div class="pn-header">POST PARTUM</div>
        <table class="pn-table" style="border-top:none;">
            <tbody>
                <tr>
                    <td class="text-start" style="width:30%;">Vitamin A 200,000 IU (Y/N)</td>
                    <td contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start">Iron / Folate / Date / #</td>
                    <td contenteditable="true"></td>
                </tr>
            </tbody>
        </table>

        <!-- Name, Mother's Name, Birthdate Bar -->
        <div class="d-flex justify-content-between align-items-center py-2 px-1 border-top mt-2" style="font-size:0.75rem; font-weight:bold;">
            <div>NAME: <span class="text-primary"><?= htmlspecialchars($patient['fullname']); ?></span></div>
            <div>MOTHER'S NAME: <span contenteditable="true" class="blank-line blank-line-lg"></span></div>
            <div>BIRTHDATE: <span><?= !empty($patient['birth_date']) ? date('F d, Y', strtotime($patient['birth_date'])) : '--'; ?></span></div>
        </div>
    </div>

    <!-- SECTION 10 — IMMUNIZATION RECORD -->
    <div class="pn-section mt-3">
        <div class="pn-header">IMMUNIZATION RECORD</div>
        <div style="border:1px solid #aaa; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            <table class="pn-table mb-0">
                <thead>
                    <tr>
                        <th class="text-start" style="width:30%;">VACCINES</th>
                        <th style="width:15%;">1</th>
                        <th style="width:15%;">2</th>
                        <th style="width:15%;">3</th>
                        <th>REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $vaccines = ['BCG', 'HEPA@B', 'PENTA', 'OPV', 'IPV', 'PCV', 'MCV'];
                    foreach ($vaccines as $v): ?>
                    <tr>
                        <td class="text-start" style="font-weight:700;"><?= $v ?></td>
                        <td style="height:20px;" contenteditable="true"></td>
                        <td style="height:20px;" contenteditable="true"></td>
                        <td style="height:20px;" contenteditable="true"></td>
                        <td style="height:20px;" contenteditable="true"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CLINICAL OBSERVATIONS & SIGNATURE -->
    <div class="section-header mt-3">
        <i class="bi bi-journal-medical text-primary"></i> Clinical Observations & Diagnosis
    </div>
    <div class="notes-box" contenteditable="true" style="min-height: 60px;">
        <em>Physician / Attending Clinician Notes:</em>
    </div>

    <div class="signature-section">
        <div class="signature-line">
            Attending Physician Signature
            <div class="text-muted fw-normal" style="font-size:0.72rem;">License No: <span contenteditable="true" class="blank-line blank-line-lg"></span></div>
        </div>
        <div class="signature-line">
            Clinic Medical Officer Stamp & Date
            <div class="text-muted fw-normal" style="font-size:0.72rem;">VitalCore Health Information System</div>
        </div>
    </div>

    <div class="doc-footer">
        CONFIDENTIAL MEDICAL RECORD • FOR AUTHORIZED PERSONNEL ONLY • VITALCORE CLINICAL SYSTEM
    </div>

</div>
</div>

<script>
const defaultFileName = '<?= $safe_filename; ?>';
const storageKey = 'prenatal_chart_draft_v6_<?= $patient_id; ?>_<?= $measurement_id; ?>';
let saveTimeout = null;

// Silent background auto-save
function saveDraft() {
    const fields = {};
    const editables = document.querySelectorAll('[contenteditable="true"]');
    editables.forEach((el, index) => {
        fields[index] = el.innerHTML;
    });
    localStorage.setItem(storageKey, JSON.stringify(fields));
}

// Restore saved draft on page load
function loadSavedDraft() {
    const savedData = localStorage.getItem(storageKey);
    if (!savedData) return;
    try {
        const fields = JSON.parse(savedData);
        const editables = document.querySelectorAll('[contenteditable="true"]');
        editables.forEach((el, index) => {
            if (fields[index] !== undefined && fields[index] !== null) {
                el.innerHTML = fields[index];
            }
        });
    } catch(e) {
        console.error('Error restoring draft:', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Theme pre-loader check
    const savedTheme = localStorage.getItem('staff_theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        document.body.setAttribute('data-bs-theme', 'dark');
    }

    loadSavedDraft();

    const editables = document.querySelectorAll('[contenteditable="true"]');
    editables.forEach(el => {
        el.addEventListener('input', () => {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveDraft, 300);
        });
    });

    // Checkmark Toggle Handler — Preserves Black Dots (.dot-circle)
    document.addEventListener('click', (e) => {
        const td = e.target.closest('td[contenteditable="true"]');
        if (!td) return;
        
        // Extract raw text excluding HTML tags
        const textContent = td.textContent.replace(/[\n\r]/g, '').trim();
        
        // Only toggle for choice cells (NO, YES, Y, N) or cells that already have a check icon
        if (['NO', 'YES', 'Y', 'N'].includes(textContent) || td.querySelector('.check-icon')) {
            let existingIcon = td.querySelector('.check-icon');
            if (existingIcon) {
                existingIcon.remove();
            } else {
                const icon = document.createElement('i');
                icon.className = 'bi bi-check-lg check-icon me-1';
                td.insertBefore(icon, td.firstChild);
            }
            saveDraft();
        }
    });
});

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
        headerTable.style.cssText = 'width:100%; border-collapse:collapse; margin-bottom:12px; border-bottom:2px solid #d9534f; padding-bottom:8px;';
        headerTable.innerHTML = `
            <tr>
                <td style="vertical-align:middle; text-align:left;">
                    <table style="border-collapse:collapse;">
                        <tr>
                            <td style="vertical-align:middle; padding-right:12px;">
                                <table style="border-collapse:collapse; background-color:#fde8e6; border:1px solid #f8b4b4; border-radius:10px;">
                                    <tr>
                                        <td style="padding:6px; text-align:center; vertical-align:middle;">
                                            <img src="${logoSrc}" width="40" height="40" style="width:40px !important; height:40px !important; display:block; border-radius:6px; object-fit:cover;">
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td style="vertical-align:middle;">
                                <div style="font-size:20px; font-weight:800; color:#d9534f; line-height:1.1; font-family:'Segoe UI', Tahoma, Arial, sans-serif;">MOMMY'S Pre-Natal Chart</div>
                                <div style="font-size:11px; color:#64748b; margin-top:2px; font-family:'Segoe UI', Tahoma, Arial, sans-serif;">Clinical Patient Record & Maternal Assessment</div>
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
        .pn-header { background-color: #d9534f !important; color: #ffffff !important; font-weight: bold !important; font-size: 11px !important; padding: 4px 8px !important; }
        .row-pink { background-color: #f7b0a7 !important; color: #000000 !important; }
        .row-blue { background-color: #b0c6e8 !important; color: #000000 !important; }
        .row-peach { background-color: #fce4b4 !important; color: #000000 !important; }
        .pn-table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 8px !important; font-size: 10px !important; }
        .pn-table th, .pn-table td { border: 1px solid #aaa !important; padding: 3px 5px !important; text-align: center !important; }
        .notes-box { border: 1px dashed #cbd5e1 !important; background-color: #f8fafc !important; padding: 6px 10px !important; min-height: 35px !important; margin-bottom: 10px !important; font-size: 10.5px !important; }
        .doc-footer { margin-top: 10px !important; text-align: center !important; font-size: 8.5px !important; color: #94a3b8 !important; border-top: 1px solid #e2e8f0 !important; padding-top: 4px !important; }
    `;

    const sourceHTML = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <title>Pre-Natal Chart</title>
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
    const a   = document.createElement('a');
    a.href     = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>


