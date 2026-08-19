<?php
/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../db_conn.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Patient not found.");
}

$patient_id = (int)$_GET['id'];

// 1. Fetch Patient Info
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = $patient_id");
$patient = mysqli_fetch_assoc($query);
if (!$patient) { die("Patient not found."); }

// 2. Fetch Latest Vital Measurements
$temp = $weight = $height = $bmi = $heart_rate = $spo2 = $systolic = $diastolic = '--';
$last_visited = 'No visits recorded';

$measurementQuery = mysqli_query($conn,
    "SELECT * FROM measurements WHERE user_id = $patient_id ORDER BY created_at DESC LIMIT 1"
);

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

// ── Status Evaluations (default '--' when no data) ──────────────────────────

$temp_status = '--';
$temp_class  = 'badge-secondary';
if ($temp_val !== null) {
    if ($temp_val < 36.0)                           { $temp_status = 'Low Temp';     $temp_class = 'badge-info'; }
    elseif ($temp_val <= 37.5)                      { $temp_status = 'Normal';       $temp_class = 'badge-success'; }
    elseif ($temp_val > 37.5 && $temp_val <= 38.5)  { $temp_status = 'Slight Fever'; $temp_class = 'badge-warning'; }
    else                                            { $temp_status = 'High Fever';   $temp_class = 'badge-danger'; }
}

$has_bmi    = ($bmi_val !== null && $weight_val !== null);
$bmi_status = '--';
$bmi_class  = 'badge-secondary';
if ($has_bmi) {
    if ($bmi_val < 18.5)      { $bmi_status = 'Underweight'; $bmi_class = 'badge-info'; }
    elseif ($bmi_val <= 24.9) { $bmi_status = 'Normal';      $bmi_class = 'badge-success'; }
    elseif ($bmi_val <= 29.9) { $bmi_status = 'Overweight';  $bmi_class = 'badge-warning'; }
    else                      { $bmi_status = 'Obese';        $bmi_class = 'badge-danger'; }
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
    if ($spo2_val < 90)      { $spo2_status = 'Critical Low'; $spo2_class = 'badge-danger'; }
    elseif ($spo2_val < 95)  { $spo2_status = 'Mild Low';     $spo2_class = 'badge-warning'; }
    else                     { $spo2_status = 'Normal';        $spo2_class = 'badge-success'; }
}

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

$weight_status = ($weight_val !== null) ? 'Recorded' : '--';
$height_status = ($height_val !== null) ? 'Recorded' : '--';

// Health Score
$has_any_vital = ($temp_val || $heart_rate_val || $spo2_val || ($systolic_val && $diastolic_val));
$health_score  = '--';
if ($has_any_vital) {
    $health_score = 100;
    if ($temp_status  !== 'Normal' && $temp_status  !== '--') $health_score -= 15;
    if ($heart_status !== 'Normal' && $heart_status !== '--') $health_score -= 15;
    if ($spo2_status  !== 'Normal' && $spo2_status  !== '--') $health_score -= 20;
    if ($bp_status    !== 'Normal' && $bp_status    !== '--') $health_score -= 10;
    if (in_array($bmi_status, ['Obese', 'Underweight']))       $health_score -= 10;
    $health_score = max(40, min(100, $health_score));
}

$safe_filename = 'Prenatal_PT' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT)
               . '_' . preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', strtolower($patient['fullname'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Natal Chart - #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/document.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* ── Pre-Natal Specific Styles ─────────────────────────── */
        .pn-section {
            margin-bottom: 0;
        }

        .pn-header {
            background: #c0392b;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 2px 2px 0 0;
        }

        .pn-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.72rem;
            margin-bottom: 10px;
        }

        .pn-table th,
        .pn-table td {
            border: 1px solid #bbb;
            padding: 4px 6px;
            vertical-align: middle;
            text-align: center;
        }

        .pn-table td:first-child {
            text-align: left;
            font-weight: 500;
            background: #fafafa;
            white-space: nowrap;
        }

        .pn-table thead th {
            background: #fde8e6;
            font-weight: 700;
            font-size: 0.68rem;
        }

        .pn-table .col-label {
            background: #fde8e6;
            font-weight: 700;
            text-align: left;
        }

        .blank-line {
            display: inline-block;
            border-bottom: 1px solid #555;
            min-width: 80px;
            height: 14px;
            vertical-align: bottom;
        }

        .blank-line-sm { min-width: 40px; }
        .blank-line-lg { min-width: 130px; }

        .pn-info-row {
            display: flex;
            gap: 20px;
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

        .pn-yn-row {
            display: flex;
            gap: 30px;
            font-size: 0.72rem;
            align-items: center;
        }

        .yn-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .yn-item label { font-weight: 500; }

        .legend-box {
            font-size: 0.65rem;
            color: #555;
            margin-top: 4px;
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .legend-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 4px;
            vertical-align: middle;
        }

        .gpal-box {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .gpal-box span {
            border-bottom: 1px solid #555;
            min-width: 24px;
            height: 16px;
            display: inline-block;
        }

        .gpal-labels {
            display: flex;
            gap: 2px;
            font-size: 0.65rem;
            color: #888;
            margin-left: 28px;
            margin-bottom: 8px;
        }

        .gpal-labels span {
            min-width: 24px;
            text-align: center;
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

        .immu-table th { background: #f0f0f0; font-weight: 700; font-size: 0.7rem; }

        .db-value {
            background: #eff6ff;
            border-radius: 3px;
            padding: 1px 6px;
            font-weight: 600;
            color: #1d4ed8;
            font-size: 0.72rem;
        }

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
        <a href="../patient-view.php?id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Patient View
        </a>
        <div class="d-flex gap-2">
            <button onclick="saveAsPDF()" class="btn btn-success btn-sm px-3 fw-bold">
                <i class="bi bi-folder-plus me-1"></i> Save As PDF
            </button>
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Print
            </button>
        </div>
    </div>
</div>

<div class="container">
<div class="document-wrapper" id="document-wrapper">

    <!-- ══════════════════════════════════════════════════
         LETTERHEAD
    ══════════════════════════════════════════════════ -->
    <div class="clinic-header">
        <div class="clinic-brand">
            <img src="../../img/logo.jpg" alt="Logo" class="clinic-logo"
                 onerror="this.src='https://via.placeholder.com/48?text=VC'">
            <div>
                <h1 class="clinic-name">MOMMY'S Pre-Natal Chart</h1>
                <p class="clinic-sub">Clinical Patient Record & Health Assessment — VitalCore</p>
            </div>
        </div>
        <div class="text-end">
            <span class="doc-title-badge">OFFICIAL MEDICAL REPORT</span>
            <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                <strong>Document Date:</strong> <?= date('F d, Y'); ?>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 1 — PERSONAL INFORMATION
    ══════════════════════════════════════════════════ -->
    <div class="pn-section">
        <div class="pn-header"><i class="bi bi-person-lines-fill me-1"></i> Personal Information</div>
        <div class="info-grid" style="margin-top:0; border-top:none; border-radius:0 0 6px 6px;">

            <div class="info-item">
                <span class="info-label">Patient ID:</span>
                <span class="info-value">#PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">Full Name:</span>
                <span class="info-value"><?= htmlspecialchars($patient['fullname']); ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">Blood Type:</span>
                <span class="info-value"><?= !empty($patient['blood_type']) ? htmlspecialchars($patient['blood_type']) : '--'; ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">Family Serial No:</span>
                <span class="info-value"><span class="blank-line blank-line-lg"></span></span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Address:</span>
                <span class="info-value"><?= !empty($patient['address']) ? htmlspecialchars($patient['address']) : '--'; ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">Birth Date:</span>
                <span class="info-value">
                    <?= !empty($patient['birth_date']) ? date('F d, Y', strtotime($patient['birth_date'])) : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Age:</span>
                <span class="info-value"><?= htmlspecialchars($patient['age']); ?> yrs</span>
            </div>

            <div class="info-item">
                <span class="info-label">Contact No:</span>
                <span class="info-value"><?= !empty($patient['contact_number']) ? htmlspecialchars($patient['contact_number']) : '--'; ?></span>
            </div>

            <div class="info-item">
                <span class="info-label">Last Visited:</span>
                <span class="info-value text-primary"><?= $last_visited; ?></span>
            </div>

        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 2 — TETANUS TOXOID
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-shield-plus me-1"></i> Tetanus Toxoid</div>
        <table class="pn-table" style="border-top:none;">
           
            <tbody>
                <tr>
                    <td>Date Given</td>
                    <td><span class=""></span></td>
                    <td><span class=""></span></td>
                    <td><span class="">3</span></td>
                    <td><span class="">4</span></td>
                    <td><span class="">5</span></td>
                </tr>
                <tr>
                    <td>
                        <span>Age:&nbsp;&nbsp;</span>
                        <span class="info-value"><?= htmlspecialchars($patient['age']); ?></span>
                    </td>
                    <td colspan="2" style="text-align:center; font-size:0.65rem; color:#888;">Below 18</td>
                    <td colspan="2" style="text-align:center; font-size:0.65rem; color:#888;">18 – 34</td>
                    <td style="text-align:center; font-size:0.65rem; color:#888;">35+</td>
                </tr>
                <tr>
                    <td>Height</td>
                    <td colspan="2">
                        <?php if ($height_val !== null): ?>
                            <span class="db-value"><?= $height_disp ?></span>
                        <?php else: ?>
                            <span class="blank-line"></span>
                        <?php endif; ?>
                    </td>
                    <td colspan="3">
                        BMI: <?php if ($bmi_val !== null): ?>
                            <span class="db-value"><?= $bmi_disp ?> (<?= $bmi_status ?>)</span>
                        <?php else: ?>
                            <span class=""></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 3 — OBSTETRICAL HISTORY
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-journal-medical me-1"></i> Obstetrical History</div>
        <div style="border:1px solid #bbb; border-top:none; padding:10px; border-radius:0 0 4px 4px;">
            <!-- G/L/P/O -->
            <div style="font-size:0.78rem; font-weight:700; margin-bottom:2px;">
                G <span class="blank-line blank-line-sm"></span> &nbsp;
                L <span class="blank-line blank-line-sm"></span> &nbsp;
                P <span class="blank-line blank-line-sm"></span> &nbsp;
                O <span class="blank-line blank-line-sm"></span>
                &nbsp;&nbsp;
                ( <span class="blank-line blank-line-sm"></span>
                  <span class="blank-line blank-line-sm"></span>
                  <span class="blank-line blank-line-sm"></span>
                  <span class="blank-line blank-line-sm"></span> )
            </div>
            <div style="font-size:0.6rem; color:#888; margin-bottom:10px; padding-left:200px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                T &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                P &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                A &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; L
            </div>

            <table class="pn-table">
                <tbody>
                    <tr>
                        <td>Previous Pregnancies</td>
                        <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th>
                    </tr>
                    <tr>
                        <td>Caesarean Section</td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                    </tr>
                    <tr>
                        <td>Stillbirth</td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                    </tr>
                    <tr>
                        <td>Post-partum Hemorrhage</td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                        <td><span style="font-size:0.6rem;">Y &nbsp; N</span></td>
                    </tr>
                    <tr>
                        <td>3 Consecutive Miscarriages</td>
                        <td colspan="3" style="text-align:center; font-size:0.68rem;">☐ YES &nbsp;&nbsp; ☐ NO</td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 4 — PRESENT HEALTH PROBLEMS
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-clipboard2-pulse me-1"></i> Present Health Problems</div>
        <table class="pn-table" style="border-top:none;">
            <tbody>
                <?php
                $health_problems = [
                    'Tuberculosis (14 days + of cough)',
                    'Heart Disease',
                    'Diabetes',
                    'Bronchial Asthma',
                    'Goiter',
                    'Hypertension',
                ];
                foreach ($health_problems as $prob): ?>
                <tr>
                    <td><?= $prob ?></td>
                    <td style="text-align:center;">☐ No</td>
                    <td style="text-align:center;">☐ Yes</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="legend-box px-2 pb-2" style="font-size:0.65rem; color:#666;">
            <span><span class="legend-dot" style="background:#c0392b;"></span> Hospital delivery recommended</span>
            <span><span class="legend-dot" style="background:#e8a09a;"></span> Close observation / action by midwife/nurse</span>
            <span><span class="legend-dot" style="background:#aaa;"></span> Refer to Physician/RHU (and follow-up)</span>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 5 — VITAL SIGNS (from DB)
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-heart-pulse-fill me-1"></i> Vital Signs & Physical Measurements</div>

        <!-- Health Score -->
     

        <table class="vitals-table" style="border-top:none;">
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
                    <td><?= $height_disp ?></td>
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

    <!-- ══════════════════════════════════════════════════
         SECTION 6 — PRESENT PREGNANCY
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-calendar-heart me-1"></i> Present Pregnancy</div>
        <div style="border:1px solid #bbb; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            <div class="pn-info-row mb-2">
                <span>
                    <label>LMP:</label>
                    Month <span class="blank-line blank-line-sm"></span>
                    Day <span class="blank-line blank-line-sm"></span>
                    Year <span class="blank-line blank-line-sm"></span>
                </span>
                <span style="font-size:0.65rem; background:#fde8e6; padding:2px 8px; border-radius:10px; color:#c0392b;">▣ Refer to Hospital</span>
                <span style="font-size:0.65rem; background:#fce4b4; padding:2px 8px; border-radius:10px; color:#9c6f00;">▣ Refer to Physician / RHU</span>
            </div>
            <div class="pn-info-row mb-3">
                <span>
                    <label>EDC:</label>
                    Month <span class="blank-line blank-line-sm"></span>
                    Day <span class="blank-line blank-line-sm"></span>
                    Year <span class="blank-line blank-line-sm"></span>
                </span>
            </div>

        <!-- Trimester tracking table -->
            <div class="pregnancy-table-wrapper">

                <table class="pn-table pregnancy-table">
                    <colgroup>
                        <!-- Parameter -->
                        <col class="parameter-col">

                        <!-- 9 months -->
                        <?php for ($i = 1; $i <= 9; $i++): ?>
                            <col class="month-col">
                        <?php endfor; ?>
                    </colgroup>

                    <thead>

                        <!-- TRIMESTER HEADER -->
                        <tr class="trimester-header">

                            <th rowspan="2" class="parameter-header">
                                Parameter
                            </th>

                            <th colspan="3" class="first-trimester">
                                1st Trimester
                            </th>

                            <th colspan="3" class="second-trimester">
                                2nd Trimester
                            </th>

                            <th colspan="3" class="third-trimester">
                                3rd Trimester
                            </th>

                        </tr>

                        <!-- MONTH NUMBERS -->
                        <tr class="month-header">

                            <?php for ($i = 1; $i <= 9; $i++): ?>

                                <th class="
                                    <?= $i <= 3
                                        ? 'first-month'
                                        : ($i <= 6
                                            ? 'second-month'
                                            : 'third-month') ?>
                                ">
                                    <?= $i ?>
                                </th>

                            <?php endfor; ?>

                        </tr>

                    </thead>

                    <tbody>

                        <!-- AOG -->
                        <tr>
                            <td>AOG in Months</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>

                        <!-- DATE OF VISIT -->
                        <tr>
                            <td>Date of Visit</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- VAGINAL BLEEDING -->
                        <tr class="paper-red-row">
                            <td>Vaginal Bleeding (Y/N)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- UTI -->
                        <tr class="paper-red-row">
                            <td>Urinary Tract Infection</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- WEIGHT -->
                        <tr>
                            <td>Weight in Kg</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- BLOOD PRESSURE -->
                        <tr>
                            <td>Blood Pressure</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- BP 140/90 -->
                        <tr class="paper-blue-row">
                            <td>BP 140/90 and above (Y/N)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- FEVER -->
                        <tr class="paper-red-row">
                            <td>Fever 39 and above (Y/N)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- PALLOR -->
                        <tr>
                            <td>Pallor (Y/N)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- ABNORMAL FUNDAL HEIGHT -->
                        <tr class="fundal-height-row">

                            <td>
                                Abnormal Fundal Height (Y/N)
                            </td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>

                                <?php
                                /*
                                * Paper form:
                                * Months 1–5 = normal/blank area
                                * Month 6 = 20 cm
                                * Month 7 = 21–24 cm
                                * Month 8 = 25–28 / 28–30 cm
                                * Month 9 = 30–34 cm
                                */
                                ?>

                                <?php if ($i <= 5): ?>

                                    <td></td>

                                <?php elseif ($i == 6): ?>

                                    <td class="fundal-range">
                                        <span>20<br>cm</span>
                                    </td>

                                <?php elseif ($i == 7): ?>

                                    <td class="fundal-range">
                                        <span>21-24<br>cm</span>
                                    </td>

                                <?php elseif ($i == 8): ?>

                                    <td class="fundal-range">
                                        <span>25-28<br>cm</span>
                                    </td>

                                <?php elseif ($i == 9): ?>

                                    <td class="fundal-range">
                                        <span>28-30<br>cm</span>
                                    </td>

                                <?php endif; ?>

                            <?php endfor; ?>

                        </tr>


                        <!-- ABNORMAL PRESENTATION -->
                        <tr>
                            <td>Abnormal Presentation (Y/N)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- MISSING FETAL HEARTBEAT -->
                        <tr>
                            <td>Missing Fetal Heartbeat (Y/N)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- EDEMA -->
                        <tr class="paper-red-row">
                            <td>Edema (Y/N)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- VAGINAL INFECTION -->
                        <tr class="paper-red-row">
                            <td>Vaginal Infection (Y/N)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>


                        <!-- LAB RESULTS -->
                        <tr>
                            <td>Lab Test Results (e.g. HGB, Urine, VDRL)</td>

                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>
                        </tr>

                    </tbody>

                </table>

            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 7 — ACTION TABLE
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-check2-square me-1"></i> Action</div>
        <table class="pn-table" style="border-top:none;">
            <thead>
                <tr>
                    <th style="text-align:left; min-width:200px;">Action Item</th>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <th><?= $i ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $action_rows = [
                    'Iron / Folate # / RX',
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
                    <td><?= $row ?></td>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <td style="height:18px;"></td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 8 — LABOR AND DELIVERY
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-hospital me-1"></i> Labor and Delivery</div>
        <div style="border:1px solid #bbb; border-top:none; padding:10px; border-radius:0 0 4px 4px;">
            <div class="pp-grid">
                <div class="pp-item"><label>Immediate breastfeeding (Y/N):</label> <span class="blank-line"></span></div>
                <div class="pp-item"><label>Birth Weight in grams:</label> <span class="blank-line"></span></div>
                <div class="pp-item"><label>Type of delivery:</label> <span class="blank-line"></span></div>
                <div class="pp-item"><label>Post Partum Hemorrhage 500 CC+ (N/Y):</label> <span class="blank-line"></span></div>
                <div class="pp-item"><label>Date of delivery:</label> <span class="blank-line"></span></div>
                <div class="pp-item"><label>Baby Alive:</label> <span class="blank-line"></span></div>
                <div class="pp-item"><label>Place of delivery:</label> <span class="blank-line"></span></div>
                <div class="pp-item"><label>Baby Healthy (Y/N):</label> <span class="blank-line"></span></div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 9 — POST PARTUM
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-house-heart me-1"></i> Post Partum</div>
        <table class="pn-table" style="border-top:none;">
            <thead>
                <tr>
                    <th rowspan="2" style="text-align:left; min-width:200px;">Timing of Post Partum Visit</th>
                    <th colspan="3" style="background:#fde8e6;">Home Visits</th>
                    <th rowspan="2" style="background:#d6eaf8;">Clinic Visit</th>
                </tr>
                <tr>
                    <th style="background:#fdf2f2; font-size:0.62rem;">24 hrs</th>
                    <th style="background:#fdf2f2; font-size:0.62rem;">1 week</th>
                    <th style="background:#fdf2f2; font-size:0.62rem;">2-4 weeks</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $pp_rows = [
                    'Date of Visit',
                    'Exclusive Breastfeeding (Y/N)',
                    'Intends to use Family Planning (Y/N)',
                    'Fever >39°C (Y/N)',
                    'Foul Smelling Vaginal Discharge (Y/N)',
                    'Excessive Bleeding (Y/N)',
                    'Pallor (Y/N)',
                    'Cord OK? (Y/N)',
                ];
                foreach ($pp_rows as $row): ?>
                <tr>
                    <td><?= $row ?></td>
                    <td style="height:18px;"></td>
                    <td style="height:18px;"></td>
                    <td style="height:18px;"></td>
                    <td style="height:18px;"></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="legend-box px-2 pb-2" style="font-size:0.65rem; color:#666; margin-top:4px;">
            <span><span class="legend-dot" style="background:#c0392b;"></span> Refer to Hospital</span>
            <span><span class="legend-dot" style="background:#e8a09a;"></span> Refer to Physician / RHU</span>
        </div>

        <!-- Post Partum Supplements -->
        <div style="border:1px solid #bbb; border-top:none; padding:8px;">
            <div class="pp-grid">
                <div class="pp-item"><label>Vitamin A 200,000 IU (Y/N):</label> <span class="blank-line"></span></div>
                <div class="pp-item"><label>Iron / Folate / Date / #:</label> <span class="blank-line blank-line-lg"></span></div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 10 — IMMUNIZATION RECORD
    ══════════════════════════════════════════════════ -->
    <div class="pn-section mt-3">
        <div class="pn-header"><i class="bi bi-clipboard2-check me-1"></i> Immunization Record</div>
        <div style="border:1px solid #bbb; border-top:none; padding:8px; border-radius:0 0 4px 4px;">
            <div class="pn-info-row mb-2">
                <span><label>Name:</label> <?= htmlspecialchars($patient['fullname']); ?></span>
                <span><label>Mother's Name:</label> <span class="blank-line blank-line-lg"></span></span>
                <span><label>Birthdate:</label>
                    <?= !empty($patient['birth_date']) ? date('F d, Y', strtotime($patient['birth_date'])) : '<span class="blank-line"></span>'; ?>
                </span>
            </div>
            <table class="pn-table immu-table">
                <thead>
                    <tr>
                        <th style="text-align:left; min-width:80px;">Vaccines</th>
                        <th>1</th>
                        <th>2</th>
                        <th>3</th>
                        <th style="min-width:120px;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $vaccines = ['BCG', 'HEPA@B', 'PENTA', 'OPV', 'IPV', 'PCV', 'MCV'];
                    foreach ($vaccines as $v): ?>
                    <tr>
                        <td style="font-weight:700;"><?= $v ?></td>
                        <td style="height:18px;"></td>
                        <td style="height:18px;"></td>
                        <td style="height:18px;"></td>
                        <td style="height:18px;"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SECTION 11 — CLINICAL NOTES & SIGNATURE
    ══════════════════════════════════════════════════ -->
    <div class="section-header mt-3">
        <i class="bi bi-journal-medical text-primary"></i> Clinical Observations & Diagnosis
    </div>
    <div class="notes-box">
        <em>Physician / Attending Clinician Notes:</em>
    </div>

    <div class="signature-section">
        <div class="signature-line">
            Attending Physician Signature
            <div class="text-muted fw-normal" style="font-size:0.72rem;">License No: __________________</div>
        </div>
        <div class="signature-line">
            Clinic Medical Officer Stamp & Date
            <div class="text-muted fw-normal" style="font-size:0.72rem;">VitalCore Health Information System</div>
        </div>
    </div>

    <div class="doc-footer">
        CONFIDENTIAL MEDICAL RECORD • FOR AUTHORIZED PERSONNEL ONLY • VITALCORE CLINICAL SYSTEM
    </div>

</div><!-- /document-wrapper -->
</div><!-- /container -->

<script>
const defaultFileName = '<?= $safe_filename; ?>';

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
<script src="../../assets/js/theme.js"></script>
</body>
</html>