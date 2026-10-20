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

// CSRF token for save requests
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Validate & Extract Patient ID (MUST COME BEFORE ANY DATABASE QUERIES)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Patient not found.");
}

$patient_id     = (int)$_GET['id'];
$measurement_id = isset($_GET['measurement_id']) && is_numeric($_GET['measurement_id']) ? (int)$_GET['measurement_id'] : 0;

// 2. Ensure prenatal_records table exists
if ($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `prenatal_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `measurement_id` INT DEFAULT NULL,
        `next_schedule` DATE DEFAULT NULL,
        `remarks` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`next_schedule`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 3. Handle AJAX Save for Next Follow-up Schedule (Tetanus, Pregnancy Visits, Immunization)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_prenatal_schedule') {
    header('Content-Type: application/json');

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        exit();
    }

    $pid = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : $patient_id;
    $mid = isset($_POST['measurement_id']) ? (int)$_POST['measurement_id'] : $measurement_id;
    $raw_schedule = trim($_POST['next_schedule'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $remarks_sql = !empty($remarks) ? "'" . mysqli_real_escape_string($conn, $remarks) . "'" : "NULL";

    if ($pid <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
        exit();
    }

    $formatted_date = null;
    $sql_date = null;

    if ($raw_schedule !== '') {
        $ts = strtotime($raw_schedule);
        if ($ts !== false) {
            $sql_date = date('Y-m-d', $ts);
            $formatted_date = date('M d, Y', $ts);
        }
    }

    $val_clause = ($sql_date !== null) ? "'$sql_date'" : "NULL";

    $check = mysqli_query($conn, "SELECT id FROM prenatal_records WHERE user_id = $pid ORDER BY id DESC LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        $rec = mysqli_fetch_assoc($check);
        $rec_id = (int)$rec['id'];
        mysqli_query($conn, "UPDATE prenatal_records SET next_schedule = $val_clause, remarks = $remarks_sql WHERE id = $rec_id");
    } else {
        mysqli_query($conn, "INSERT INTO prenatal_records (user_id, measurement_id, next_schedule, remarks) VALUES ($pid, " . ($mid > 0 ? $mid : "NULL") . ", $val_clause, $remarks_sql)");
    }

    $is_active = ($sql_date !== null && strtotime($sql_date) >= strtotime('today'));

    echo json_encode([
        'success'        => true,
        'sql_date'       => $sql_date,
        'formatted_date' => $formatted_date,
        'is_active'      => $is_active
    ]);
    exit();
}

// 4. Fetch Patient Info
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = $patient_id LIMIT 1");
$patient = mysqli_fetch_assoc($query);
if (!$patient) { die("Patient not found."); }

// 5. Fetch Existing Next Schedule & Remarks for this Patient
$prenatal_next_schedule = null;
$prenatal_remarks = '';
if ($patient_id > 0) {
    $sched_q = mysqli_query($conn, "SELECT next_schedule, remarks FROM prenatal_records WHERE user_id = $patient_id AND next_schedule IS NOT NULL ORDER BY id DESC LIMIT 1");
    if ($sched_q && mysqli_num_rows($sched_q) > 0) {
        $sched_data = mysqli_fetch_assoc($sched_q);
        $prenatal_next_schedule = $sched_data['next_schedule'];
        $prenatal_remarks = $sched_data['remarks'] ?? '';
    }
}

// 6. Fetch Vital Measurements for This Visit
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

// 7. Data Processing & Formatting
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
    <script>
        if (localStorage.getItem("theme") === "dark" || localStorage.getItem("staff_theme") === "dark") {
            document.documentElement.classList.add("dark-mode");
        }
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

        /* Table Cell Text Styling — force dark text always */
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

        .row-pink { background-color: #f7b0a7 !important; }
        .row-blue { background-color: #b0c6e8 !important; }
        .row-peach { background-color: #fce4b4 !important; }

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
            <img src="../img/logo.jpg" alt="Logo" class="clinic-logo"
                 onerror="this.src='https://via.placeholder.com/48?text=VC'">
            <div>
                <h1 class="clinic-name" style="color:#d9534f !important;">MOMMY'S Pre-Natal Chart</h1>
                <p class="clinic-sub">Clinical Patient Record & Maternal Health Assessment</p>
            </div>
        </div>
        <div class="text-end">
            <span class="doc-title-badge">OFFICIAL MEDICAL REPORT</span>
            <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                <strong>Document Date:</strong> <?= htmlspecialchars($doc_date); ?>
            </div>
            <!-- Header Live Follow-up Badge -->
            <div class="mt-1" style="font-size: 0.75rem;">
                <strong>Next Schedule:</strong>
                <span class="sched-badge-live badge <?= !empty($prenatal_next_schedule) && strtotime($prenatal_next_schedule) >= strtotime('today') ? 'bg-success' : 'bg-secondary' ?>">
                    <?php if (!empty($prenatal_next_schedule)): ?>
                        <?= date('M d, Y', strtotime($prenatal_next_schedule)); ?><?= !empty($prenatal_remarks) ? ' (' . htmlspecialchars($prenatal_remarks) . ')' : '' ?>
                    <?php else: ?>
                        None
                    <?php endif; ?>
                </span>
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

    <!-- SECTION 2 — TETANUS TOXOID (DATE GIVEN AUTO-SYNCS) -->
    <div class="pn-section mt-3">
        <div class="pn-header d-flex justify-content-between align-items-center">
            <span>TETANUS TOXOID</span>
            <span class="badge bg-light text-dark fw-bold no-print" style="font-size: 0.68rem;">
                <i class="bi bi-shield-check text-success me-1"></i> Auto-synced to Follow-up Schedule
            </span>
        </div>
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
                    <td contenteditable="true" class="sched-cell tetanus-cell" data-section="Tetanus Toxoid" data-label="Tetanus Toxoid Dose 1"></td>
                    <td contenteditable="true" class="sched-cell tetanus-cell" data-section="Tetanus Toxoid" data-label="Tetanus Toxoid Dose 2"></td>
                    <td contenteditable="true" class="sched-cell tetanus-cell" data-section="Tetanus Toxoid" data-label="Tetanus Toxoid Dose 3"></td>
                    <td contenteditable="true" class="sched-cell tetanus-cell" data-section="Tetanus Toxoid" data-label="Tetanus Toxoid Dose 4"></td>
                    <td contenteditable="true" class="sched-cell tetanus-cell" data-section="Tetanus Toxoid" data-label="Tetanus Toxoid Dose 5"></td>
                </tr>
                <tr>
                    <td class="text-start"><strong>Age:</strong> <?= htmlspecialchars($patient['age'] ?? ''); ?> yrs</td>
                    <td colspan="2" contenteditable="true">Below 18</td>
                    <td colspan="2" contenteditable="true">18-34</td>
                    <td contenteditable="true">35+</td>
                </tr>
                <tr>
                    <td class="text-start">
                        <strong>Height:</strong> <?= $height_disp ?>
                        <?php if ($height_val !== null): ?>
                            <span style="font-size:0.85em; color:#64748b;">&nbsp;(<?= $height_ft_disp ?>)</span>
                        <?php endif; ?>
                    </td>
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
                        <td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true">Y</td><td contenteditable="true">N</td><td contenteditable="true"><span class="dot-circle"></span>Y</td><td contenteditable="true">N</td><td contenteditable="true"><span class="dot-circle"></span>Y</td><td contenteditable="true">N</td><td contenteditable="true"><span class="dot-circle"></span>Y</td><td contenteditable="true">N</td>
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
                    <td class="row-blue" contenteditable="true"><span class="dot-circle"></span>YES</td>
                </tr>
                <tr>
                    <td class="text-start">Diabetes</td>
                    <td contenteditable="true">NO</td>
                    <td class="row-blue" contenteditable="true"><span class="dot-circle"></span>YES</td>
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
                    <td class="row-blue" contenteditable="true"><span class="dot-circle"></span>YES</td>
                </tr>
            </tbody>
        </table>

        <div class="legend-box px-2 pb-2">
            <div>
                <span class="legend-square" style="background:#b0c6e8;"></span>Refer to Physician/RHU (and follow-up)
                <span class="legend-square" style="background:#f7b0a7;"></span>Close observation or action by midwife/nurse
                <span class="dot-circle" style="width:12px; height:12px; margin-right:4px;"></span>Hospital delivery recommended
            </div>
        </div>
        <div style="font-size:0.65rem; color:#666; padding-left:8px; margin-top:-4px;" class="mb-2">
            <em>*You may wish to consider a permanent method of Family Planning*</em>
        </div>
    </div>

    <!-- SECTION 5 — VITAL SIGNS TELEMETRY (FROM DB) -->
    <div class="pn-section mt-3">
        <div class="pn-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-heart-pulse-fill me-1"></i> Vital Signs Telemetry Measurements</span>
            <?php if ($current_mid > 0): ?>
            <button type="button" class="btn btn-light btn-sm py-0 px-2 fw-bold no-print"
                    data-html2canvas-ignore="true" onclick="openEditVitals()" style="font-size:0.7rem;">
                <i class="bi bi-pencil-square me-1"></i> Edit Vitals
            </button>
            <?php endif; ?>
        </div>
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
                    <td><?= $bp_disp ?></td>
                    <td>120/80 mmHg</td>
                    <td><span class="status-badge <?= $bp_class ?>"><?= $bp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Heart Rate</strong></td>
                    <td><?= $heart_rate_disp ?></td>
                    <td>60 – 100 BPM</td>
                    <td><span class="status-badge <?= $heart_class ?>"><?= $heart_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>SpO2 (Blood Oxygen)</strong></td>
                    <td><?= $spo2_disp ?></td>
                    <td>95% – 100%</td>
                    <td><span class="status-badge <?= $spo2_class ?>"><?= $spo2_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Temperature</strong></td>
                    <td><?= $temp_disp ?></td>
                    <td>36.0 °C – 37.5 °C</td>
                    <td><span class="status-badge <?= $temp_class ?>"><?= $temp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Weight</strong></td>
                    <td><?= $weight_disp ?></td>
                    <td>--</td>
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
                    <td>--</td>
                    <td><span class="status-badge badge-secondary"><?= $height_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>BMI Score</strong></td>
                    <td><?= $bmi_disp ?></td>
                    <td>18.5 – 24.9</td>
                    <td><span class="status-badge <?= $bmi_class ?>"><?= $bmi_status ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SECTION 5 — PRESENT PREGNANCY (DATE OF VISIT AUTO-SYNCS) -->
    <div class="pn-section mt-3">
        <div class="pn-header d-flex justify-content-between align-items-center">
            <span>PRESENT PREGNANCY</span>
            <span class="badge bg-light text-dark fw-bold no-print" style="font-size: 0.68rem;">
                <i class="bi bi-shield-check text-success me-1"></i> Auto-synced to Follow-up Schedule
            </span>
        </div>
        <div style="border:1px solid #aaa; border-top:none; padding:8px; border-radius:0 0 4px 4px;">

            <!-- LMP and EDC Row with Header Badges -->
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
                        <td contenteditable="true" class="sched-cell pregnancy-cell" data-section="Prenatal Visit" data-label="Prenatal Visit 1"></td>
                        <?php for ($i = 2; $i <= 12; $i++): ?>
                        <td class="row-blue sched-cell pregnancy-cell" contenteditable="true" data-section="Prenatal Visit" data-label="Prenatal Visit <?= $i ?>"></td>
                        <?php endfor; ?>
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

    <!-- SECTION 6 — ACTION (DATE OF NEXT VISIT AUTO-SYNCS) -->
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
                foreach ($action_rows as $row): 
                    $isNextVisit = ($row === 'Date of next visit');
                ?>
                <tr>
                    <td class="text-start"><?= $row ?></td>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <td style="height:18px;" contenteditable="true" <?= $isNextVisit ? 'class="sched-cell action-cell" data-section="Next Visit" data-label="Follow-up Visit ' . $i . '"' : '' ?>></td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- SECTION 7 — LABOR AND DELIVERY -->
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

    <!-- SECTION 8 — POST PARTUM -->
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
            <div>MOTHER'S NAME: <span contenteditable="true" class="blank-line blank-line-lg"></span></div> 
            <div>NAME: <span contenteditable="true" class="blank-line blank-line-lg"></span></div>
            <div>BIRTHDATE: <span contenteditable="true" class="blank-line blank-line-lg"></span></div>
        </div>
    </div>

    <!-- SECTION 9 — IMMUNIZATION RECORD (VACCINES AUTO-SYNC) -->
    <div class="pn-section mt-3">
        <div class="pn-header d-flex justify-content-between align-items-center">
            <span>IMMUNIZATION RECORD</span>
            <span class="badge bg-light text-dark fw-bold no-print" style="font-size: 0.68rem;">
                <i class="bi bi-shield-check me-1 text-success"></i> Auto-synced to Follow-up Schedule
            </span>
        </div>
        <div style="border:1px solid #aaa; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            <table class="pn-table mb-2" id="immunizationTable">
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
                        <td style="height:20px;" contenteditable="true" class="sched-cell immu-cell" data-section="Immunization" data-vaccine="<?= $v ?>" data-col="1" data-label="<?= $v ?> Dose 1"></td>
                        <td style="height:20px;" contenteditable="true" class="sched-cell immu-cell" data-section="Immunization" data-vaccine="<?= $v ?>" data-col="2" data-label="<?= $v ?> Dose 2"></td>
                        <td style="height:20px;" contenteditable="true" class="sched-cell immu-cell" data-section="Immunization" data-vaccine="<?= $v ?>" data-col="3" data-label="<?= $v ?> Dose 3"></td>
                        <td style="height:20px;" contenteditable="true" class="sched-cell immu-cell" data-section="Immunization" data-vaccine="<?= $v ?>" data-col="remarks" data-label="<?= $v ?> (Remarks)"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- LIVE SCHEDULE SYNC INDICATOR -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pt-2 border-top" style="font-size: 0.75rem;">
                <div>
                    <strong>Follow-up Schedule:</strong>
                    <span id="immuSchedBadge" class="badge <?= !empty($prenatal_next_schedule) && strtotime($prenatal_next_schedule) >= strtotime('today') ? 'bg-success' : 'bg-secondary' ?> ms-1">
                        <?php if (!empty($prenatal_next_schedule)): ?>
                            <i class="bi bi-calendar-check me-1"></i> Active: <?= date('M d, Y', strtotime($prenatal_next_schedule)); ?><?= !empty($prenatal_remarks) ? ' (' . htmlspecialchars($prenatal_remarks) . ')' : '' ?>
                        <?php else: ?>
                            No date set
                        <?php endif; ?>
                    </span>
                </div>
                <div id="immuSyncStatus" class="text-muted" style="font-size: 0.7rem;">
                    <em>Typing a date (e.g. 8/10/26) in Tetanus, Visit, or Immunization auto-activates Follow-up Schedule.</em>
                </div>
            </div>
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
const storageKey = 'prenatal_chart_draft_v8_<?= $patient_id; ?>_<?= $measurement_id; ?>';
let saveTimeout = null;

// ---- Global JS Constants (available even if measurement_id == 0) ----
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

// ---- Draft auto-save ----
function saveDraft() {
    const fields = {};
    const editables = document.querySelectorAll('[contenteditable="true"]');
    editables.forEach((el, index) => {
        fields[index] = el.innerHTML;
    });
    localStorage.setItem(storageKey, JSON.stringify(fields));
}

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
    if (localStorage.getItem("theme") === "dark" || localStorage.getItem("staff_theme") === "dark") {
        document.body.classList.add("dark-mode");
    }

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

// --- ROBUST DATE PARSER (Supports M/D/YY, M/D/YYYY, M-D-YY, YYYY-MM-DD, Month Words) ---
function parseTypedDate(str) {
    if (!str) return null;
    const clean = str.replace(/<[^>]*>/g, '').trim();
    if (!clean) return null;

    // Matches M/D/YY or M/D/YYYY or M-D-YY (e.g. 8/10/26, 9/10/26, 08/10/2026)
    const m = clean.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
    if (m) {
        let month = parseInt(m[1], 10);
        let day   = parseInt(m[2], 10);
        let year  = parseInt(m[3], 10);
        if (year < 100) year += 2000;

        if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
            const mStr = String(month).padStart(2, '0');
            const dStr = String(day).padStart(2, '0');
            return `${year}-${mStr}-${dStr}`;
        }
    }

    // Matches YYYY-MM-DD
    const m2 = clean.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (m2) {
        let year  = parseInt(m2[1], 10);
        let month = parseInt(m2[2], 10);
        let day   = parseInt(m2[3], 10);
        if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
            return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        }
    }

    // Natural date parsing fallback
    const parsedTs = Date.parse(clean);
    if (!isNaN(parsedTs)) {
        const dObj = new Date(parsedTs);
        const y = dObj.getFullYear();
        if (y >= 2000 && y <= 2100) {
            const m = String(dObj.getMonth() + 1).padStart(2, '0');
            const d = String(dObj.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }
    }

    return null;
}

// --- SCAN ALL CELLS: TETANUS TOXOID, PREGNANCY VISITS, NEXT VISIT, IMMUNIZATION ---
let syncTimeout = null;

function scanAndSyncAllSchedules() {
    clearTimeout(syncTimeout);
    syncTimeout = setTimeout(async () => {
        const cells = document.querySelectorAll('.sched-cell, .immu-cell, .tetanus-cell, .pregnancy-cell, .action-cell');
        const todayStr = new Date().toISOString().split('T')[0];

        let targetSchedule = null;
        let upcomingList = [];
        let allDatesList = [];

        cells.forEach(td => {
            const text = td.innerText.trim();
            const sqlDate = parseTypedDate(text);
            if (sqlDate) {
                let label = td.getAttribute('data-label');
                if (!label) {
                    const vaccine = td.getAttribute('data-vaccine');
                    const col = td.getAttribute('data-col');
                    if (vaccine) {
                        label = col === 'remarks' ? `${vaccine} (Remarks)` : `${vaccine} Dose ${col}`;
                    } else {
                        label = td.getAttribute('data-section') || 'Prenatal Schedule';
                    }
                }
                const entry = { sqlDate, label };

                allDatesList.push(entry);
                if (sqlDate >= todayStr) {
                    upcomingList.push(entry);
                }
            }
        });

        // 1. Prioritize earliest upcoming future date
        if (upcomingList.length > 0) {
            upcomingList.sort((a, b) => a.sqlDate.localeCompare(b.sqlDate));
            targetSchedule = upcomingList[0];
        } else if (allDatesList.length > 0) {
            // 2. Or latest entered date if all are in past
            allDatesList.sort((a, b) => b.sqlDate.localeCompare(a.sqlDate));
            targetSchedule = allDatesList[0];
        }

        const badges = document.querySelectorAll('#immuSchedBadge, .sched-badge-live');
        const statusTexts = document.querySelectorAll('#immuSyncStatus, .sched-status-live');

        const dateVal  = targetSchedule ? targetSchedule.sqlDate : '';
        const labelVal = targetSchedule ? targetSchedule.label   : '';

        statusTexts.forEach(st => {
            st.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-repeat"></i> Saving schedule...</span>';
        });

        const formData = new FormData();
        formData.append('action', 'save_prenatal_schedule');
        formData.append('patient_id', PATIENT_ID);
        formData.append('measurement_id', CURRENT_MID);
        formData.append('next_schedule', dateVal);
        formData.append('remarks', labelVal);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                if (data.sql_date) {
                    badges.forEach(b => {
                        b.className = (b.id === 'immuSchedBadge') 
                            ? (data.is_active ? 'badge bg-success ms-1' : 'badge bg-secondary ms-1')
                            : (data.is_active ? 'sched-badge-live badge bg-success' : 'sched-badge-live badge bg-secondary');
                        b.innerHTML = (b.id === 'immuSchedBadge')
                            ? `<i class="bi bi-calendar-check me-1"></i> Active: ${data.formatted_date} (${labelVal})`
                            : `${data.formatted_date} (${labelVal})`;
                    });
                    statusTexts.forEach(st => {
                        st.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Saved: ${data.formatted_date} (${labelVal}) &mdash; Visible in Dashboard Follow-up Schedule.</span>`;
                    });
                } else {
                    badges.forEach(b => {
                        b.className = (b.id === 'immuSchedBadge') ? 'badge bg-secondary ms-1' : 'sched-badge-live badge bg-secondary';
                        b.textContent = (b.id === 'immuSchedBadge') ? 'No date set' : 'None';
                    });
                    statusTexts.forEach(st => {
                        st.innerHTML = '<em>Typing a date (e.g. 8/10/26) in Tetanus, Visit, or Immunization auto-activates Follow-up Schedule.</em>';
                    });
                }
            }
        } catch (e) {
            console.error('Error syncing schedule:', e);
        }
    }, 400); // 400ms debounce
}

// Attach event listeners to all schedule-eligible cells
document.addEventListener('DOMContentLoaded', () => {
    const schedCells = document.querySelectorAll('.sched-cell, .immu-cell, .tetanus-cell, .pregnancy-cell, .action-cell');
    schedCells.forEach(cell => {
        cell.addEventListener('input', scanAndSyncAllSchedules);
        cell.addEventListener('blur', scanAndSyncAllSchedules);
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
            html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
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

<!-- SYSTEM SETTINGS ENGINE SCRIPT (Applies Theme, NightLight, TextSize, Language) -->
<script>
(function applySystemSettings() {
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

    const savedNightLight = localStorage.getItem("nightLight");
    const nightLightOverlay = document.getElementById("nightLightOverlay");
    if (savedNightLight === "enabled" && nightLightOverlay) {
        nightLightOverlay.style.display = "block";
    }

    const savedTextSize = localStorage.getItem("textSize");
    if (savedTextSize) {
        const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
        document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
    }

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