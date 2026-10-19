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
if (!$patient) { die("Patient not found."); }

// 2. Fetch Vital Measurements for This Visit
$temp = $weight = $height = $bmi = $heart_rate = $spo2 = $systolic = $diastolic = '--';
$last_visited = 'No visits recorded';
$doc_date     = date('F d, Y');
$current_mid  = 0;

if ($measurement_id > 0) {
    $measurementQuery = mysqli_query($conn, "SELECT * FROM measurements WHERE id = $measurement_id LIMIT 1");
} else {
    $measurementQuery = mysqli_query($conn, "SELECT * FROM measurements WHERE user_id = $patient_id ORDER BY created_at DESC LIMIT 1");
}

if ($measurementQuery && mysqli_num_rows($measurementQuery) > 0) {
    $measurement    = mysqli_fetch_assoc($measurementQuery);
    $current_mid    = (int)$measurement['id'];
    $temp           = $measurement['temperature'] ?? '--';
    $weight         = $measurement['weight']      ?? '--';
    $height         = $measurement['height']      ?? '--';
    $bmi            = $measurement['bmi']         ?? '--';
    $heart_rate     = $measurement['heart_rate']  ?? '--';
    $spo2           = $measurement['spo2']        ?? '--';
    $systolic       = $measurement['systolic']    ?? '--';
    $diastolic      = $measurement['diastolic']   ?? '--';
    if (!empty($measurement['created_at'])) {
        $doc_date     = date('F d, Y', strtotime($measurement['created_at']));
        $last_visited = date('F d, Y - h:i A', strtotime($measurement['created_at']));
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
    <!-- PRE-LOAD DARK MODE & TEXT SIZE (Prevents UI Flicker) -->
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem("theme") || localStorage.getItem("staff_theme");
                if (savedTheme === "dark") {
                    document.documentElement.classList.add("dark-mode");
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
    <title>Pre-Natal Chart - #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></title>
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
            color: #ffffff !important;
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

        /* Table Cell Text Styling — force dark text always (document is white paper) */
        .pn-table th,
        .pn-table td {
            border: 1px solid #aaa;
            padding: 4px 5px;
            vertical-align: middle;
            text-align: center;
            color: #0f172a !important;
        }

        /* Override theme.css dark mode for ALL table cells inside the document */
        html.dark-mode #document-wrapper .pn-table th,
        html.dark-mode #document-wrapper .pn-table td,
        body.dark-mode #document-wrapper .pn-table th,
        body.dark-mode #document-wrapper .pn-table td,
        html.dark-mode #document-wrapper .vitals-table th,
        html.dark-mode #document-wrapper .vitals-table td,
        body.dark-mode #document-wrapper .vitals-table th,
        body.dark-mode #document-wrapper .vitals-table td {
            color: #0f172a !important;
            background-color: inherit;
        }

        /* Colored rows — always pure black text */
        html.dark-mode #document-wrapper .row-pink,
        html.dark-mode #document-wrapper .row-pink td,
        html.dark-mode #document-wrapper .row-blue,
        html.dark-mode #document-wrapper .row-blue td,
        body.dark-mode #document-wrapper .row-pink,
        body.dark-mode #document-wrapper .row-pink td,
        body.dark-mode #document-wrapper .row-blue,
        body.dark-mode #document-wrapper .row-blue td {
            color: #000000 !important;
        }

        .pn-table td.text-start { text-align: left !important; }

        .pn-table thead th {
            background: #fde8e6 !important;
            font-weight: 700;
            font-size: 0.68rem;
            color: #2c3e50 !important;
        }

        /* Force Pure Dark Text on Pink, Blue & Peach Highlight Rows */
        .row-pink,
        .row-pink td,
        .row-pink th,
        .row-blue,
        .row-blue td,
        .row-blue th,
        .row-peach,
        .row-peach td,
        .row-peach th {
            color: #000000 !important;
        }

        .row-pink { background-color: #f7b0a7 !important; }
        .row-blue { background-color: #b0c6e8 !important; }
        .row-peach { background-color: #fce4b4 !important; }

        /* Black Dot Circle */
        .dot-circle {
            display: inline-block !important;
            width: 10px !important;
            height: 10px !important;
            background-color: #000000 !important;
            border-radius: 50% !important;
            margin-right: 5px !important;
            margin-left: 2px !important;
            vertical-align: middle !important;
        }

        /* Check Icon Styling */
        .check-icon {
            display: inline-block !important;
            font-size: 0.85rem !important;
            font-weight: 900 !important;
            margin-right: 3px !important;
            color: #000000 !important;
            vertical-align: middle !important;
        }

        .row-pink .check-icon { color: #842029 !important; }
        .row-blue .check-icon { color: #0a49c4 !important; }

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
            color: inherit !important;
        }
        .blank-line-sm { min-width: 35px; }
        .blank-line-lg { min-width: 130px; }

        [contenteditable="true"] {
            outline: none;
            color: inherit;
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
            color: #e2e8f0 !important;
        }

        .legend-square {
            display: inline-block;
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 4px;
            border-radius: 2px;
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

        /* Prevent individual table rows and signatures from slicing horizontally mid-text */
        tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .signature-section {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0 !important; padding: 0 !important; }
            .document-wrapper {
                box-shadow: none !important;
                border: none !important;
                margin: 0 auto !important;
                padding: 10px 20px !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

<!-- TOP ACTION TOOLBAR -->
<div class="no-print bg-white border-bottom p-3 mb-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 860px;">
        <a href="patient-history-list.php?user_id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> <span data-i18n="back_to_history">Back to Patient History</span>
        </a>
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

<div class="container" style="max-width: 860px;">
<div class="document-wrapper" id="document-wrapper">

    <!-- CLINIC LETTERHEAD HEADER -->
    <div class="clinic-header" style="border-bottom: 2px solid #d9534f;">
        <div class="clinic-brand">
            <img src="../../img/logo.jpg" alt="Logo" class="clinic-logo"
                 onerror="this.src='https://via.placeholder.com/48?text=VC'">
            <div>
                <h1 class="clinic-name" style="color:#d9534f !important;" data-i18n="doc_title">MOMMY'S Pre-Natal Chart</h1>
                <p class="clinic-sub" data-i18n="doc_subtitle">Clinical Patient Record & Maternal Health Assessment</p>
            </div>
        </div>
        <div class="text-end">
            <span class="doc-title-badge" data-i18n="badge_official_report">OFFICIAL MEDICAL REPORT</span>
            <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                <strong><span data-i18n="lbl_document_date">Document Date:</span></strong> <?= htmlspecialchars($doc_date); ?>
            </div>
        </div>
    </div>

    <!-- SECTION 1 — PERSONAL INFORMATION -->
    <div class="pn-section">
        <div class="pn-header" data-i18n="sec_personal_info">PERSONAL INFORMATION</div>
        <div class="info-grid" style="margin-top:0; border-top:none; border-radius:0 0 4px 4px;">

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_bloodtype">Blood Type:</span>
                <span class="info-value"><?= !empty($patient['blood_type']) ? htmlspecialchars($patient['blood_type']) : '--'; ?></span>
            </div>

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_family_serial">Family Serial No:</span>
                <span class="info-value"><span contenteditable="true" class="blank-line blank-line-lg"></span></span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label" data-i18n="lbl_name">Name:</span>
                <span class="info-value"><?= htmlspecialchars($patient['fullname']); ?></span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label" data-i18n="lbl_address">Address:</span>
                <span class="info-value"><?= !empty($patient['address']) ? htmlspecialchars($patient['address']) : '--'; ?></span>
            </div>

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_birthdate">B-Date:</span>
                <span class="info-value">
                    <?= !empty($patient['birth_date']) ? date('F d, Y', strtotime($patient['birth_date'])) : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label" data-i18n="lbl_contact">Contact No:</span>
                <span class="info-value"><?= !empty($patient['contact_number']) ? htmlspecialchars($patient['contact_number']) : '--'; ?></span>
            </div>

        </div>
    </div>

    <!-- SECTION 2 — TETANUS TOXOID -->
    <div class="pn-section mt-3">
        <div class="pn-header" data-i18n="sec_tetanus">TETANUS TOXOID</div>
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
                    <td class="text-start"><strong data-i18n="lbl_date_given">Date Given</strong></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start"><strong data-i18n="lbl_age">Age:</strong> <?= htmlspecialchars($patient['age']); ?> yrs</td>
                    <td colspan="2" contenteditable="true" data-i18n="lbl_below_18">Below 18</td>
                    <td colspan="2" contenteditable="true">18-34</td>
                    <td contenteditable="true">35+</td>
                </tr>
                <tr>
                    <td class="text-start">
                        <strong data-i18n="lbl_height">Height:</strong> <?= $height_disp ?>
                        <?php if ($height_val !== null): ?>
                            <span style="font-size:0.85em; color:#64748b;">&nbsp;(<?= $height_ft_disp ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td colspan="2" contenteditable="true" data-i18n="lbl_below_145">Below 145 cm</td>
                    <td colspan="3" class="text-start ps-3"><strong data-i18n="lbl_bmi">BMI:</strong> <?= $bmi_disp ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SECTION 3 — OBSTETRICAL HISTORY -->
    <div class="pn-section mt-3">
        <div class="pn-header" data-i18n="sec_obstetrical">OBSTETRICAL HISTORY</div>
        <div style="border:1px solid #aaa; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            
            <!-- G_ L_ P_ O_ ( _ _ _ _ ) with TPAL underneath -->
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
                        <th rowspan="2" class="text-start" data-i18n="lbl_prev_pregnancies">Previous Pregnancies</th>
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
                        <td class="text-start" data-i18n="lbl_csection">Caesarean Section</td>
                        <td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true"><span class="dot-circle"></span>Y</td><td contenteditable="true">N</td><td contenteditable="true"><span class="dot-circle"></span>Y</td><td contenteditable="true">N</td><td contenteditable="true"><span class="dot-circle"></span>Y</td><td contenteditable="true">N</td>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="lbl_stillbirth">Stillbirth</td>
                        <td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="lbl_pp_hemorrhage">Post-partum Hemorrhage</td>
                        <td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="lbl_miscarriages">3 Consecutive Miscarriages</td>
                        <td colspan="6" contenteditable="true" style="text-align:center;">YES <span class="dot-circle"></span></td>
                        <td colspan="6" contenteditable="true" style="text-align:center;">NO</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 4 — PRESENT HEALTH PROBLEMS -->
    <div class="pn-section mt-3">
        <div class="pn-header" data-i18n="sec_health_problems">PRESENT HEALTH PROBLEMS</div>
        <table class="pn-table" style="border-top:none;">
            <tbody>
                <tr>
                    <td class="text-start" data-i18n="lbl_tb">Tuberculosis (14 days + of cough)</td>
                    <td style="width:30%;" contenteditable="true">NO</td>
                    <td class="row-pink" style="width:30%;" contenteditable="true">YES</td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="lbl_heart_disease">Heart Disease</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true"><span class="dot-circle"></span>YES</td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="lbl_diabetes">Diabetes</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true"><span class="dot-circle"></span>YES</td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="lbl_asthma">Bronchial Asthma</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true">YES</td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="lbl_goiter">Goiter</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true">YES</td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="lbl_hypertension">Hypertension</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true"><span class="dot-circle"></span>YES</td>
                </tr>
            </tbody>
        </table>

        <div class="legend-box px-2 pb-2">
            <div>
                <span class="legend-square" style="background:#b0c6e8;"></span><span data-i18n="legend_physician">Refer to Physician/RHU (and follow-up)</span>
                
                <span class="legend-square" style="background:#f7b0a7;"></span><span data-i18n="legend_midwife">Close observation or action by midwife/nurse</span>
                
                <span class="dot-circle" style="width:12px; height:12px; margin-right:4px;"></span><span data-i18n="legend_hospital">Hospital delivery recommended</span>
            </div>
        </div>
        <div style="font-size:0.65rem; color:#666; padding-left:8px; margin-top:-4px;" class="mb-2">
            <em data-i18n="legend_fp_note">*You may wish to consider a permanent method of Family Planning*</em>
        </div>
    </div>

    <!-- SECTION 5 — VITAL SIGNS TELEMETRY (FROM DB) -->
    <div class="pn-section mt-3">
        <div class="pn-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-heart-pulse-fill me-1"></i> <span data-i18n="sec_telemetry">Vital Signs Telemetry Measurements</span></span>
            <?php if ($current_mid > 0): ?>
            <button type="button" class="btn btn-light btn-sm py-0 px-2 fw-bold no-print"
                    data-html2canvas-ignore="true" onclick="openEditVitals()" style="font-size:0.7rem;">
                <i class="bi bi-pencil-square me-1"></i> <span data-i18n="btn_edit_vitals">Edit Vitals</span>
            </button>
            <?php endif; ?>
        </div>
        <table class="vitals-table" style="border-top:none;">
            <thead>
                <tr>
                    <th data-i18n="tbl_parameter">Parameter</th>
                    <th data-i18n="tbl_measured_value">Measured Value</th>
                    <th data-i18n="tbl_target_normal">Target / Normal</th>
                    <th data-i18n="tbl_status">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong data-i18n="lbl_bp">Blood Pressure</strong></td>
                    <td><?= $bp_disp ?></td>
                    <td>120/80 mmHg</td>
                    <td><span class="status-badge <?= $bp_class ?>"><?= $bp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_heart">Heart Rate</strong></td>
                    <td><?= $heart_rate_disp ?></td>
                    <td>60 – 100 BPM</td>
                    <td><span class="status-badge <?= $heart_class ?>"><?= $heart_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_spo2">SpO2 (Blood Oxygen)</strong></td>
                    <td><?= $spo2_disp ?></td>
                    <td>95% – 100%</td>
                    <td><span class="status-badge <?= $spo2_class ?>"><?= $spo2_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_temp">Body Temperature</strong></td>
                    <td><?= $temp_disp ?></td>
                    <td>36.0 °C – 37.5 °C</td>
                    <td><span class="status-badge <?= $temp_class ?>"><?= $temp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_weight">Body Weight</strong></td>
                    <td><?= $weight_disp ?></td>
                    <td>--</td>
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
                    <td>--</td>
                    <td><span class="status-badge badge-secondary"><?= $height_status ?></span></td>
                </tr>
                <tr>
                    <td><strong data-i18n="lbl_bmi">BMI Score</strong></td>
                    <td><?= $bmi_disp ?></td>
                    <td>18.5 – 24.9</td>
                    <td><span class="status-badge <?= $bmi_class ?>"><?= $bmi_status ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SECTION 5 — PRESENT PREGNANCY (LMP & EDC) -->
    <div class="pn-section mt-3">
        <div class="pn-header" data-i18n="sec_present_pregnancy">PRESENT PREGNANCY</div>
        <div style="border:1px solid #aaa; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            
            <!-- LMP and EDC Row with Header Badges -->
            <div class="d-flex justify-content-between align-items-center mb-4" style="font-size:0.75rem;">
                <div class="d-flex gap-2">
                    <div>
                        <strong class="text-danger" data-i18n="lbl_lmp">LMP:</strong> 
                        MONTH <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                        DAY <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                        YEAR <span contenteditable="true" class="blank-line blank-line-sm"></span>
                        <span class="badge" style="background:#b0c6e8; color:#1e3a8a;" data-i18n="badge_refer_hospital">Refer to Hospital</span>
                    </div>
                    <div>
                        <strong class="text-danger" data-i18n="lbl_edc">EDC:</strong> 
                        MONTH <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                        DAY <span contenteditable="true" class="blank-line blank-line-sm"></span> 
                        YEAR <span contenteditable="true" class="blank-line blank-line-sm"></span>
                        <span class="badge" style="background:#f7b0a7; color:#881337;" data-i18n="badge_refer_physician">Refer to Physician / RHU</span>
                    </div>
                </div>
            </div>

            <table class="pn-table mb-1">
                <thead>
                    <tr>
                        <th rowspan="2" class="text-start" style="width:25%;" data-i18n="tbl_trimester">TRIMESTER</th>
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
                        <td class="text-start" data-i18n="row_aog">AOG in Months</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_date_visit">Date of Visit</td>
                        <?php for ($i = 1; $i <= 1; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 2; $i <= 12; $i++): ?><td class="row-blue" contenteditable="true"></td><?php endfor; ?> 
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_bleeding">Vaginal Bleeding (Y/N)</td>
                        <?php for ($i = 1; $i <= 1; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 2; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_uti">Urinary Tract Infection</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_weight_kg">Weight in Kg</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="lbl_bp">Blood Pressure</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_bp_high">BP 140/90 and above (Y/N)</td>
                        <?php for ($i = 1; $i <= 1; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 2; $i <= 12; $i++): ?><td class="row-blue" contenteditable="true"></td><?php endfor; ?> 
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_fever_high">Fever 39 and above (Y/N)</td>
                        <?php for ($i = 1; $i <= 1; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 2; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?> 
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_pallor">Pallor (Y/N)</td>
                        <?php for ($i = 1; $i <= 4; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 5; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td rowspan="2" class="text-start" data-i18n="row_fundal">Abnormal Fundal Height (Y/N)</td>
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
                        <td class="text-start" data-i18n="row_abnormal_pres">Abnormal Presentation (Y/N)</td>
                        <?php for ($i = 1; $i <= 5; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 6; $i <= 12; $i++): ?><td class="row-blue" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_missing_fetal_hb">Missing Fetal Heartbeat (Y/N)</td>
                        <?php for ($i = 1; $i <= 5; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 6; $i <= 12; $i++): ?><td class="row-blue" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_edema">Edema (Y/N)</td>
                        <?php for ($i = 1; $i <= 4; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 5; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_vag_infection">Vaginal Infection (Y/N)</td>
                        <?php for ($i = 1; $i <= 4; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                        <?php for ($i = 5; $i <= 12; $i++): ?><td class="row-pink" contenteditable="true"></td><?php endfor; ?>
                    </tr>
                    <tr>
                        <td class="text-start" data-i18n="row_lab_tests">Lab Test Results (e.g. HGB, Urine, VDRL)</td>
                        <?php for ($i = 1; $i <= 12; $i++): ?><td contenteditable="true"></td><?php endfor; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 6 — ACTION -->
    <div class="pn-section mt-3">
        <div class="pn-header" data-i18n="sec_action">ACTION</div>
        <table class="pn-table" style="border-top:none;">
            <thead>
                <tr>
                    <th class="text-start" style="width:35%;" data-i18n="col_iron_folate">Iron/Folate#/RX</th>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <th><?= $i ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $action_rows = [
                    'act_iodine'        => 'Iodine Supplementation in High Risk Areas',
                    'act_malaria'       => 'Malaria',
                    'act_prophylaxis'   => 'Prophylaxis (Y/N)',
                    'act_breastfeed'    => 'Mother intends to breastfeed? (Y/N)',
                    'act_danger_signs'  => 'Advice on 4 danger signs (Y/N)',
                    'act_dental'        => 'Dental Check-up? (Y/N)',
                    'act_delivery_plan' => 'Emergency plans and place of delivery (Y/N)',
                    'act_risk'          => 'Risk? (Y/N)',
                    'act_next_visit'    => 'Date of next visit',
                ];
                foreach ($action_rows as $key => $row): ?>
                <tr>
                    <td class="text-start" data-i18n="<?= $key ?>"><?= $row ?></td>
                    <?php for ($i = 1; $i <= 9; $i++): ?><td style="height:18px;" contenteditable="true"></td><?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- SECTION 7 — LABOR AND DELIVERY -->
    <div class="pn-section mt-3">
        <div class="pn-header" data-i18n="sec_labor_delivery">LABOR AND DELIVERY</div>
        <div style="border:1px solid #aaa; border-top:none; padding:10px; border-radius:0 0 4px 4px;">
            <div class="pp-grid">
                <div class="pp-item"><label data-i18n="lbl_imm_breastfeed">Immediate breastfeeding (Y/N):</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label data-i18n="lbl_birth_weight">Birth Weight in grams:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label data-i18n="lbl_delivery_type">Type of delivery:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label data-i18n="lbl_pp_hem_500">Post Partum Hemorrhage 500 CC+ (N/Y):</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label data-i18n="lbl_delivery_date">Date of delivery:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label data-i18n="lbl_baby_alive">Baby Alive:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label data-i18n="lbl_delivery_place">Place of delivery:</label> <span contenteditable="true" class="blank-line"></span></div>
                <div class="pp-item"><label data-i18n="lbl_baby_healthy">Baby Healthy (Y/N):</label> <span contenteditable="true" class="blank-line"></span></div>
            </div>
        </div>
    </div>

    <!-- SECTION 8 — POST PARTUM -->
    <div class="pn-section mt-3">
        <div class="pn-header" data-i18n="sec_post_partum">POST PARTUM</div>
        <table class="pn-table" style="border-top:none;">
            <thead>
                <tr>
                    <th rowspan="3" class="text-start" style="width:45%;" data-i18n="tbl_timing_pp">Timing of Post Partum Visit <br><br> Date of Visit</th>
                    <th colspan="3" data-i18n="tbl_home_visits">HOME VISITS</th>
                    <th rowspan="2" data-i18n="tbl_clinic_visit">CLINIC VISIT</th>
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
                    <td class="text-start" data-i18n="row_excl_breastfeed">Exclusive Breastfeeding (Y/N)</td>
                    <td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="row_intend_fp">Intends to use Family Planning (Y/N)</td>
                    <td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="row_fever_39">Fever >39C (Y/N)</td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="row_foul_discharge">Foul Smelling Vaginal Discharge (Y/N)</td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="row_excess_bleeding">Excessive Bleeding (Y/N)</td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                    <td class="row-blue" contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="row_pallor">Pallor (Y/N)</td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                    <td class="row-pink" contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="row_cord_ok">Cord OK? (Y/N)</td>
                    <td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td>
                </tr>
            </tbody>
        </table>

        <!-- Post Partum Legend -->
        <div class="legend-box px-2 pb-2 mb-2">
            <strong>LEGEND:</strong>
            <div><span class="legend-square" style="background:#b0c6e8;"></span> <span data-i18n="badge_refer_hospital">Refer to Hospital</span></div>
            <div class="ms-3"><span class="legend-square" style="background:#f7b0a7;"></span> <span data-i18n="badge_refer_physician">Refer to Physician / RHU</span></div>
        </div>

        <!-- Separate Post Partum Table (Vitamin A & Iron/Folate) -->
        <div class="pn-header" data-i18n="sec_post_partum">POST PARTUM</div>
        <table class="pn-table" style="border-top:none;">
            <tbody>
                <tr>
                    <td class="text-start" style="width:30%;" data-i18n="row_vit_a">Vitamin A 200,000 IU (Y/N)</td>
                    <td contenteditable="true"></td>
                </tr>
                <tr>
                    <td class="text-start" data-i18n="row_iron_folate_num">Iron / Folate / Date / #</td>
                    <td contenteditable="true"></td>
                </tr>
            </tbody>
        </table>

        <!-- Name, Mother's Name, Birthdate Bar -->
        <div class="d-flex justify-content-between align-items-center py-2 px-1 border-top mt-2" style="font-size:0.75rem; font-weight:bold;">
            <div><span data-i18n="lbl_name_upper">NAME:</span> <span class="text-primary"><?= htmlspecialchars($patient['fullname']); ?></span></div>
            <div><span data-i18n="lbl_mother_name">MOTHER'S NAME:</span> <span contenteditable="true" class="blank-line blank-line-lg"></span></div>
            <div><span data-i18n="lbl_birthdate_upper">BIRTHDATE:</span> <span><?= !empty($patient['birth_date']) ? date('F d, Y', strtotime($patient['birth_date'])) : '--'; ?></span></div>
        </div>
    </div>

    <!-- SECTION 9 — IMMUNIZATION RECORD -->
    <div class="pn-section mt-3">
        <div class="pn-header" data-i18n="sec_immunization">IMMUNIZATION RECORD</div>
        <div style="border:1px solid #aaa; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            <table class="pn-table mb-0">
                <thead>
                    <tr>
                        <th class="text-start" style="width:30%;" data-i18n="tbl_vaccines">VACCINES</th>
                        <th style="width:15%;">1</th>
                        <th style="width:15%;">2</th>
                        <th style="width:15%;">3</th>
                        <th data-i18n="tbl_remarks">REMARKS</th>
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
        <i class="bi bi-journal-medical text-primary"></i> <span data-i18n="sec_diagnosis">Clinical Observations & Diagnosis</span>
    </div>
    <div class="notes-box" contenteditable="true" style="min-height: 60px;">
        <em data-i18n="lbl_notes_placeholder">Physician / Attending Clinician Notes:</em>
    </div>

    <div class="signature-section">
        <div class="signature-line">
            <span data-i18n="lbl_physician_sig">Attending Physician Signature</span>
            <div class="text-muted fw-normal" style="font-size:0.72rem;"><span data-i18n="lbl_license_no">License No:</span> <span contenteditable="true" class="blank-line blank-line-lg"></span></div>
        </div>
        <div class="signature-line">
            <span data-i18n="lbl_officer_stamp">Clinic Medical Officer Stamp & Date</span>
            <div class="text-muted fw-normal" style="font-size:0.72rem;">VitalCore Health Information System</div>
        </div>
    </div>

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<script>
const defaultFileName = '<?= $safe_filename; ?>';
const storageKey = 'prenatal_chart_draft_v7_<?= $patient_id; ?>_<?= $measurement_id; ?>';
let saveTimeout = null;

// ---- Edit Vitals ----
<?php if ($current_mid > 0): ?>
const CURRENT_MID = <?= (int)$current_mid; ?>;
const PATIENT_ID  = <?= (int)$patient_id; ?>;
const CSRF_TOKEN  = '<?= $_SESSION['csrf_token']; ?>';
const VITALS      = <?= $vitals_js; ?>;
const evFields    = ['temperature','weight','height','heart_rate','spo2','systolic','diastolic'];

function openEditVitals() {
    evFields.forEach(f => {
        document.getElementById('ev_' + f).value = VITALS[f] ?? '';
    });
    document.getElementById('evError').classList.add('d-none');
    updateBmiPreview();
    new bootstrap.Modal(document.getElementById('editVitalsModal')).show();
}

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
        const data = await res.json();
        if (data.success) {
            location.reload();
            return;
        }
        err.textContent = data.message || 'Unable to save.';
        err.classList.remove('d-none');
    } catch (e) {
        err.textContent = 'Network error. Please try again.';
        err.classList.remove('d-none');
    }
    btn.disabled = false;
}
<?php endif; ?>

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
    // Live BMI preview in modal
    ['ev_weight', 'ev_height'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateBmiPreview);
    });

    loadSavedDraft();

    const editables = document.querySelectorAll('[contenteditable="true"]');
    editables.forEach(el => {
        el.addEventListener('input', () => {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveDraft, 300);
        });

        el.addEventListener('click', (e) => {
            const rawText = el.innerText.replace(/[^A-Za-z]/g, '').trim().toUpperCase();
            if (['YES', 'NO', 'Y', 'N'].includes(rawText)) {
                const existingCheck = el.querySelector('.check-icon');
                if (existingCheck) {
                    existingCheck.remove();
                } else {
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-check-lg check-icon';
                    el.prepend(icon);
                }
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(saveDraft, 100);
            }
        });
    });
});

async function saveAsPDF() {
    window.scrollTo(0, 0);
    const element = document.getElementById('document-wrapper');

    try {
        const opt = {
            margin: [6, 6, 6, 6],
            filename: defaultFileName + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                scrollY: 0
            },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: 'css' }
        };

        const pdfBlob = await html2pdf().set(opt).from(element).output('blob');

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
        console.error('Error generating PDF:', error);
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
        .row-pink { background-color: #f7b0a7 !important; }
        .row-blue { background-color: #b0c6e8 !important; }
        .pn-table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 8px !important; font-size: 10px !important; }
        .pn-table th, .pn-table td { border: 1px solid #aaa !important; padding: 3px 5px !important; text-align: center !important; color: #000000 !important; }
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

<!-- SYSTEM SETTINGS ENGINE (Connected directly to setting.php) -->
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

    // 4. Language Dictionary & Translations
    const i18n = {
        en: {
            back_to_history: "Back to Patient History",
            btn_save_pdf: "Save As PDF...",
            btn_save_word: "Save As Word...",
            btn_print: "Print",
            doc_title: "MOMMY'S Pre-Natal Chart",
            doc_subtitle: "Clinical Patient Record & Maternal Health Assessment",
            badge_official_report: "OFFICIAL MEDICAL REPORT",
            lbl_document_date: "Document Date:",
            sec_personal_info: "PERSONAL INFORMATION",
            lbl_bloodtype: "Blood Type:",
            lbl_family_serial: "Family Serial No:",
            lbl_name: "Name:",
            lbl_address: "Address:",
            lbl_birthdate: "B-Date:",
            lbl_contact: "Contact No:",
            sec_tetanus: "TETANUS TOXOID",
            lbl_date_given: "Date Given",
            lbl_age: "Age:",
            lbl_height: "Height:",
            lbl_bmi: "BMI:",
            lbl_below_18: "Below 18",
            lbl_below_145: "Below 145 cm",
            sec_obstetrical: "OBSTETRICAL HISTORY",
            lbl_prev_pregnancies: "Previous Pregnancies",
            lbl_csection: "Caesarean Section",
            lbl_stillbirth: "Stillbirth",
            lbl_pp_hemorrhage: "Post-partum Hemorrhage",
            lbl_miscarriages: "3 Consecutive Miscarriages",
            sec_health_problems: "PRESENT HEALTH PROBLEMS",
            lbl_tb: "Tuberculosis (14 days + of cough)",
            lbl_heart_disease: "Heart Disease",
            lbl_diabetes: "Diabetes",
            lbl_asthma: "Bronchial Asthma",
            lbl_goiter: "Goiter",
            lbl_hypertension: "Hypertension",
            legend_physician: "Refer to Physician/RHU (and follow-up)",
            legend_midwife: "Close observation or action by midwife/nurse",
            legend_hospital: "Hospital delivery recommended",
            legend_fp_note: "*You may wish to consider a permanent method of Family Planning*",
            sec_telemetry: "Vital Signs Telemetry Measurements",
            btn_edit_vitals: "Edit Vitals",
            tbl_parameter: "Parameter",
            tbl_measured_value: "Measured Value",
            tbl_target_normal: "Target / Normal",
            tbl_status: "Status",
            lbl_bp: "Blood Pressure",
            lbl_heart: "Heart Rate",
            lbl_spo2: "SpO2 (Blood Oxygen)",
            lbl_temp: "Body Temperature",
            lbl_weight: "Body Weight",
            sec_present_pregnancy: "PRESENT PREGNANCY",
            lbl_lmp: "LMP:",
            lbl_edc: "EDC:",
            badge_refer_hospital: "Refer to Hospital",
            badge_refer_physician: "Refer to Physician / RHU",
            tbl_trimester: "TRIMESTER",
            row_aog: "AOG in Months",
            row_date_visit: "Date of Visit",
            row_bleeding: "Vaginal Bleeding (Y/N)",
            row_uti: "Urinary Tract Infection",
            row_weight_kg: "Weight in Kg",
            row_bp_high: "BP 140/90 and above (Y/N)",
            row_fever_high: "Fever 39 and above (Y/N)",
            row_pallor: "Pallor (Y/N)",
            row_fundal: "Abnormal Fundal Height (Y/N)",
            row_abnormal_pres: "Abnormal Presentation (Y/N)",
            row_missing_fetal_hb: "Missing Fetal Heartbeat (Y/N)",
            row_edema: "Edema (Y/N)",
            row_vag_infection: "Vaginal Infection (Y/N)",
            row_lab_tests: "Lab Test Results (e.g. HGB, Urine, VDRL)",
            sec_action: "ACTION",
            col_iron_folate: "Iron/Folate#/RX",
            act_iodine: "Iodine Supplementation in High Risk Areas",
            act_malaria: "Malaria",
            act_prophylaxis: "Prophylaxis (Y/N)",
            act_breastfeed: "Mother intends to breastfeed? (Y/N)",
            act_danger_signs: "Advice on 4 danger signs (Y/N)",
            act_dental: "Dental Check-up? (Y/N)",
            act_delivery_plan: "Emergency plans and place of delivery (Y/N)",
            act_risk: "Risk? (Y/N)",
            act_next_visit: "Date of next visit",
            sec_labor_delivery: "LABOR AND DELIVERY",
            lbl_imm_breastfeed: "Immediate breastfeeding (Y/N):",
            lbl_birth_weight: "Birth Weight in grams:",
            lbl_delivery_type: "Type of delivery:",
            lbl_pp_hem_500: "Post Partum Hemorrhage 500 CC+ (N/Y):",
            lbl_delivery_date: "Date of delivery:",
            lbl_baby_alive: "Baby Alive:",
            lbl_delivery_place: "Place of delivery:",
            lbl_baby_healthy: "Baby Healthy (Y/N):",
            sec_post_partum: "POST PARTUM",
            tbl_timing_pp: "Timing of Post Partum Visit / Date of Visit",
            tbl_home_visits: "HOME VISITS",
            tbl_clinic_visit: "CLINIC VISIT",
            row_excl_breastfeed: "Exclusive Breastfeeding (Y/N)",
            row_intend_fp: "Intends to use Family Planning (Y/N)",
            row_fever_39: "Fever >39C (Y/N)",
            row_foul_discharge: "Foul Smelling Vaginal Discharge (Y/N)",
            row_excess_bleeding: "Excessive Bleeding (Y/N)",
            row_cord_ok: "Cord OK? (Y/N)",
            row_vit_a: "Vitamin A 200,000 IU (Y/N)",
            row_iron_folate_num: "Iron / Folate / Date / #",
            lbl_name_upper: "NAME:",
            lbl_mother_name: "MOTHER'S NAME:",
            lbl_birthdate_upper: "BIRTHDATE:",
            sec_immunization: "IMMUNIZATION RECORD",
            tbl_vaccines: "VACCINES",
            tbl_remarks: "REMARKS",
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
            doc_title: "Pre-Natal Chart ni Nanay",
            doc_subtitle: "Klinikal na Rekord ng Pasyente at Pagsusuri ng Kalusugan ng Ina",
            badge_official_report: "OPISYAL NA ULAT MEDIKAL",
            lbl_document_date: "Petsa ng Dokumento:",
            sec_personal_info: "IMPORMASYONG PERSONAL",
            lbl_bloodtype: "Uri ng Dugo:",
            lbl_family_serial: "Serial No. ng Pamilya:",
            lbl_name: "Pangalan:",
            lbl_address: "Tirahan:",
            lbl_birthdate: "Araw ng Kapanganakan:",
            lbl_contact: "Numero ng Telepono:",
            sec_tetanus: "TETANUS TOXOID",
            lbl_date_given: "Petsa ng Pagbigay",
            lbl_age: "Edad:",
            lbl_height: "Taas:",
            lbl_bmi: "BMI:",
            lbl_below_18: "Mababa sa 18",
            lbl_below_145: "Mababa sa 145 cm",
            sec_obstetrical: "KASAYSAYAN SA OBSTETRIKO",
            lbl_prev_pregnancies: "Mga Nakaraang Pagbubuntis",
            lbl_csection: "Caesarean Section",
            lbl_stillbirth: "Panganganak ng Patay",
            lbl_pp_hemorrhage: "Pagdurugo Pagkatapos Manganak",
            lbl_miscarriages: "3 Sunod-sunod na Pagkalaglag",
            sec_health_problems: "KASALUKUYANG MGA PROBLEMA SA KALUSUGAN",
            lbl_tb: "Tuberkulosis (14+ araw ng ubo)",
            lbl_heart_disease: "Sakit sa Puso",
            lbl_diabetes: "Diyabetis",
            lbl_asthma: "Hika (Asthma)",
            lbl_goiter: "Bosyo (Goiter)",
            lbl_hypertension: "Altapresyon",
            legend_physician: "Sumangguni sa Doktor/RHU (at follow-up)",
            legend_midwife: "Mahigpit na pagmamasid o aksyon ng komadrona/nars",
            legend_hospital: "Inirerekomenda ang panganganak sa ospital",
            legend_fp_note: "*Maaari mong isaalang-alang ang permanenteng paraan ng Pagpaplano ng Pamilya*",
            sec_telemetry: "Mga Sukat ng Vital Signs Telemetry",
            btn_edit_vitals: "I-edit ang Vitals",
            tbl_parameter: "Sukat",
            tbl_measured_value: "Naitalang Halaga",
            tbl_target_normal: "Target / Normal",
            tbl_status: "Katayuan",
            lbl_bp: "Presyon ng Dugo",
            lbl_heart: "Bilis ng Puso",
            lbl_spo2: "SpO2 (Oksiheno sa Dugo)",
            lbl_temp: "Temperatura ng Katawan",
            lbl_weight: "Timbang ng Katawan",
            sec_present_pregnancy: "KASALUKUYANG PAGBUBUNTIS",
            lbl_lmp: "LMP:",
            lbl_edc: "EDC:",
            badge_refer_hospital: "Sumangguni sa Ospital",
            badge_refer_physician: "Sumangguni sa Doktor / RHU",
            tbl_trimester: "TRIMESTER",
            row_aog: "AOG sa Buwan",
            row_date_visit: "Petsa ng Pagbisita",
            row_bleeding: "Pagdurugo sa Puwerta (Y/N)",
            row_uti: "Impeksyon sa Daaran ng Ihi (UTI)",
            row_weight_kg: "Timbang sa Kg",
            row_bp_high: "BP 140/90 pataas (Y/N)",
            row_fever_high: "Lagnat 39 pataas (Y/N)",
            row_pallor: "Pamumutla (Y/N)",
            row_fundal: "Abnormal na Taas ng Sinapupunan (Y/N)",
            row_abnormal_pres: "Abnormal na Posisyon ng Sanggol (Y/N)",
            row_missing_fetal_hb: "Nawawalang Tibok ng Puso ng Sanggol (Y/N)",
            row_edema: "Manas (Y/N)",
            row_vag_infection: "Impeksyon sa Puwerta (Y/N)",
            row_lab_tests: "Resulta ng Lab Test (hal. HGB, Ihi, VDRL)",
            sec_action: "AKSYON",
            col_iron_folate: "Iron/Folate#/RX",
            act_iodine: "Pagbibigay ng Iodine sa Mataas na Panganib",
            act_malaria: "Malaria",
            act_prophylaxis: "Prophylaxis (Y/N)",
            act_breastfeed: "Balak ba ng ina na magpasuso? (Y/N)",
            act_danger_signs: "Payo sa 4 na senyales ng panganib (Y/N)",
            act_dental: "Pagsusuri sa Ngipin? (Y/N)",
            act_delivery_plan: "Plano sa emerhensiya at lugar ng panganganak (Y/N)",
            act_risk: "Panganib? (Y/N)",
            act_next_visit: "Petsa ng susunod na pagbisita",
            sec_labor_delivery: "PANGANGANAK AT PAGLUWAL",
            lbl_imm_breastfeed: "Mabilisang pagpapasuso (Y/N):",
            lbl_birth_weight: "Timbang pagkapanganak sa gramo:",
            lbl_delivery_type: "Uri ng panganganak:",
            lbl_pp_hem_500: "Pagdurugo pagkapanganak 500 CC+ (N/Y):",
            lbl_delivery_date: "Petsa ng panganganak:",
            lbl_baby_alive: "Buhay ang Sanggol:",
            lbl_delivery_place: "Lugar ng panganganak:",
            lbl_baby_healthy: "Malusog ang Sanggol (Y/N):",
            sec_post_partum: "PAGKATAPOS MANGANAK",
            tbl_timing_pp: "Oras ng Pagbisita / Petsa ng Pagbisita",
            tbl_home_visits: "PAGBISITA SA BAHAY",
            tbl_clinic_visit: "PAGBISITA SA KLINIKA",
            row_excl_breastfeed: "Eksklusibong Pagpapasuso (Y/N)",
            row_intend_fp: "Balak gumamit ng Family Planning (Y/N)",
            row_fever_39: "Lagnat >39C (Y/N)",
            row_foul_discharge: "Mabahong Katas sa Puwerta (Y/N)",
            row_excess_bleeding: "Labis na Pagdurugo (Y/N)",
            row_cord_ok: "Maayos ang Pusod? (Y/N)",
            row_vit_a: "Bitamina A 200,000 IU (Y/N)",
            row_iron_folate_num: "Iron / Folate / Petsa / #",
            lbl_name_upper: "PANGALAN:",
            lbl_mother_name: "PANGALAN NG INA:",
            lbl_birthdate_upper: "ARAW NG KAPANGANAKAN:",
            sec_immunization: "REKORD NG BAKUNA",
            tbl_vaccines: "MGA BAKUNA",
            tbl_remarks: "MGA TALA",
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
            doc_title: "Pre-Natal Chart sa Inahan",
            doc_subtitle: "Klinikal nga Rekord sa Pasyente ug Pagsusi sa Panglawas sa Inahan",
            badge_official_report: "OPISYAL NGA TALAAN SA MEDIKAL",
            lbl_document_date: "Petsa sa Dokumento:",
            sec_personal_info: "PERSONAL NGA IMPORMASYON",
            lbl_bloodtype: "Klase sa Dugo:",
            lbl_family_serial: "Serial No. sa Pamilya:",
            lbl_name: "Ngalan:",
            lbl_address: "Puy-anan:",
            lbl_birthdate: "Adlawng Natawhan:",
            lbl_contact: "Numero sa Telepono:",
            sec_tetanus: "TETANUS TOXOID",
            lbl_date_given: "Petsa sa Paghatag",
            lbl_age: "Pangidaron:",
            lbl_height: "Gitas-on:",
            lbl_bmi: "BMI:",
            lbl_below_18: "Ubos sa 18",
            lbl_below_145: "Ubos sa 145 cm",
            sec_obstetrical: "KASAYSAYAN SA PAGBUROD",
            lbl_prev_pregnancies: "Nangaging Pagbuntis",
            lbl_csection: "Caesarean Section",
            lbl_stillbirth: "Patay Natawo",
            lbl_pp_hemorrhage: "Pagdugo Human Nanganak",
            lbl_miscarriages: "3 Sunod-sunod nga Pagkakuha",
            sec_health_problems: "MGA PROBLEMA SA PANGLAWAS KARON",
            lbl_tb: "Tuberkulosis (14+ ka adlaw nga ubo)",
            lbl_heart_disease: "Sakit sa Kasingkasing",
            lbl_diabetes: "Diyabetes",
            lbl_asthma: "Hubak (Asthma)",
            lbl_goiter: "Bolyaw (Goiter)",
            lbl_hypertension: "Alta Presyon",
            legend_physician: "Pakigkita sa Doktor/RHU (ug follow-up)",
            legend_midwife: "Hugot nga pagbantay o aksyon sa komadrona/nars",
            legend_hospital: "Girekomenda ang pagpanganak sa ospital",
            legend_fp_note: "*Mahimo nimong ikonsiderar ang permanente nga pamaagi sa Pagplano sa Pamilya*",
            sec_telemetry: "Mga Sukat sa Vital Signs Telemetry",
            btn_edit_vitals: "Usba ang Vitals",
            tbl_parameter: "Sukat",
            tbl_measured_value: "Nahitalang Sukod",
            tbl_target_normal: "Target / Normal",
            tbl_status: "Kahimtang",
            lbl_bp: "Presyon sa Dugo",
            lbl_heart: "Kusog sa Kasingkasing",
            lbl_spo2: "SpO2 (Oksiheno sa Dugo)",
            lbl_temp: "Temperatura sa Lawas",
            lbl_weight: "Timbang sa Lawas",
            sec_present_pregnancy: "KASAMTANGANG PAGBUNTIS",
            lbl_lmp: "LMP:",
            lbl_edc: "EDC:",
            badge_refer_hospital: "Pakigkita sa Ospital",
            badge_refer_physician: "Pakigkita sa Doktor / RHU",
            tbl_trimester: "TRIMESTER",
            row_aog: "AOG sa Buwan",
            row_date_visit: "Petsa sa Pagbisita",
            row_bleeding: "Pagdugo sa Puwerta (Y/N)",
            row_uti: "Impeksyon sa Agianan sa Ihi (UTI)",
            row_weight_kg: "Timbang sa Kg",
            row_bp_high: "BP 140/90 pataas (Y/N)",
            row_fever_high: "Hilanat 39 pataas (Y/N)",
            row_pallor: "Pangluspad (Y/N)",
            row_fundal: "Abnormal nga Gitas-on sa Sabakan (Y/N)",
            row_abnormal_pres: "Abnormal nga Posisyon sa Bata (Y/N)",
            row_missing_fetal_hb: "Nawala nga Pitik sa Kasingkasing sa Bata (Y/N)",
            row_edema: "Panghupong (Y/N)",
            row_vag_infection: "Impeksyon sa Puwerta (Y/N)",
            row_lab_tests: "Resulta sa Lab Test (pananglitan HGB, Ihi, VDRL)",
            sec_action: "AKSYON",
            col_iron_folate: "Iron/Folate#/RX",
            act_iodine: "Paghatag og Iodine sa Taas og Peligro",
            act_malaria: "Malaria",
            act_prophylaxis: "Prophylaxis (Y/N)",
            act_breastfeed: "Plano ba sa inahan nga magpasuso? (Y/N)",
            act_danger_signs: "Tambag sa 4 ka peligro nga timailhan (Y/N)",
            act_dental: "Pagsusi sa Ngipon? (Y/N)",
            act_delivery_plan: "Plano sa emerhensiya ug lugar nga panganakan (Y/N)",
            act_risk: "Peligro? (Y/N)",
            act_next_visit: "Petsa sa sunod nga pagbisita",
            sec_labor_delivery: "PAGPANGARTE UG PAGPANGATAWO",
            lbl_imm_breastfeed: "Dihadiha nga pagpasuso (Y/N):",
            lbl_birth_weight: "Timbang pagkahimugso sa gramo:",
            lbl_delivery_type: "Klase sa pagpanganak:",
            lbl_pp_hem_500: "Pagdugo human manganak 500 CC+ (N/Y):",
            lbl_delivery_date: "Petsa sa pagpanganak:",
            lbl_baby_alive: "Buhi ang Bata:",
            lbl_delivery_place: "Lugar sa pagpanganak:",
            lbl_baby_healthy: "Himsog ang Bata (Y/N):",
            sec_post_partum: "HUMAN NANGANAK",
            tbl_timing_pp: "Oras sa Pagbisita / Petsa sa Pagbisita",
            tbl_home_visits: "PAGBISITA SA BALAY",
            tbl_clinic_visit: "PAGBISITA SA KLINIKA",
            row_excl_breastfeed: "Eksklusibo nga Pagpasuso (Y/N)",
            row_intend_fp: "Plano mogamit og Family Planning (Y/N)",
            row_fever_39: "Hilanat >39C (Y/N)",
            row_foul_discharge: "Bahong Puga sa Puwerta (Y/N)",
            row_excess_bleeding: "Sobra nga Pagdugo (Y/N)",
            row_cord_ok: "Maayo ba ang Pusod? (Y/N)",
            row_vit_a: "Bitamina A 200,000 IU (Y/N)",
            row_iron_folate_num: "Iron / Folate / Petsa / #",
            lbl_name_upper: "NGALAN:",
            lbl_mother_name: "NGALAN SA INAHAN:",
            lbl_birthdate_upper: "ADLAWNG NATAWHAN:",
            sec_immunization: "REKORD SA BAKUNA",
            tbl_vaccines: "MGA BAKUNA",
            tbl_remarks: "MGA PAHINUMDOM",
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
    document.querySelectorAll("[data-i18n]").forEach(el => {
        const key = el.getAttribute("data-i18n");
        if (dict[key]) el.innerText = dict[key];
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
<script src="../../assets/js/theme.js"></script>
</body>
</html>
