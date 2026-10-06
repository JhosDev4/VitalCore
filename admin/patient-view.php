<?php
session_start();
require_once('../db_conn.php');

/** @var mysqli $conn */

/* =========================
   SECURITY CHECK
========================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Patient not found.");
}

$patient_id = (int)$_GET['id'];

$query = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE id = $patient_id"
);

$patient = mysqli_fetch_assoc($query);

if (!$patient) {
    die("Patient not found.");
}


/* PREVIOUS PATIENT */
$prevQuery = mysqli_query(
    $conn,
    "SELECT id
     FROM users
     WHERE role='patient'
     AND id < $patient_id
     ORDER BY id DESC
     LIMIT 1"
);

$prevPatient = mysqli_fetch_assoc($prevQuery);
$prev_id = $prevPatient['id'] ?? null;

/* NEXT PATIENT */
$nextQuery = mysqli_query(
    $conn,
    "SELECT id
     FROM users
     WHERE role='patient'
     AND id > $patient_id
     ORDER BY id ASC
     LIMIT 1"
);

$nextPatient = mysqli_fetch_assoc($nextQuery);
$next_id = $nextPatient['id'] ?? null;

$temp       = '--';
$weight     = '--';
$height     = '--';
$bmi        = '--';
$heart_rate = '--';
$spo2       = '--';
$systolic   = '--';
$diastolic  = '--';

$measurementQuery = mysqli_query(
    $conn,
    "SELECT *
     FROM measurements
     WHERE user_id = $patient_id
     ORDER BY created_at DESC
     LIMIT 1"
);

$last_visited = 'No visits yet';

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
        $last_visited = date(
            'F d, Y',
            strtotime($measurement['created_at'])
        );
    }
}

/* FETCH ALL HISTORICAL HEART RATE RECORDS FOR TABLE */
$heartRateHistoryQuery = mysqli_query(
    $conn,
    "SELECT heart_rate, created_at 
     FROM measurements 
     WHERE user_id = $patient_id AND heart_rate IS NOT NULL AND heart_rate > 0 
     ORDER BY created_at DESC"
);

// Strict Numeric Validation
$temp_val       = (is_numeric($temp)       && (float)$temp > 0)       ? (float)$temp       : null;
$weight_val     = (is_numeric($weight)     && (float)$weight > 0)     ? (float)$weight     : null;
$height_val     = (is_numeric($height)     && (float)$height > 0)     ? (float)$height     : null;
$bmi_val        = (is_numeric($bmi)        && (float)$bmi > 0)        ? (float)$bmi        : null;
$heart_rate_val = (is_numeric($heart_rate) && (int)$heart_rate > 0)   ? (int)$heart_rate   : null;
$spo2_val       = (is_numeric($spo2)       && (int)$spo2 > 0)         ? (int)$spo2         : null;
$systolic_val   = (is_numeric($systolic)   && (int)$systolic > 0)     ? (int)$systolic     : null;
$diastolic_val  = (is_numeric($diastolic)  && (int)$diastolic > 0)    ? (int)$diastolic    : null;

// Display strings
$temp_disp       = ($temp_val !== null)   ? number_format($temp_val, 2) . ' °C' : '--';
$weight_disp     = ($weight_val !== null) ? number_format($weight_val, 2) . ' kg' : '--';
$height_disp     = ($height_val !== null) ? number_format($height_val, 2) . ' cm' : '--';
$bmi_disp        = ($bmi_val !== null)    ? number_format($bmi_val, 2)          : '--';
$heart_rate_disp = ($heart_rate_val !== null) ? $heart_rate_val . ' BPM'        : '--';
$spo2_disp       = ($spo2_val !== null) ? $spo2_val . '%'                      : '--';
$bp_disp         = ($systolic_val !== null && $diastolic_val !== null) ? $systolic_val . '/' . $diastolic_val . ' mmHg' : '--';

// Evaluate Temperature Status Label
$temp_status = '--';
$temp_badge_class = 'bg-secondary-subtle text-secondary';
if ($temp_val !== null) {
    if ($temp_val < 36.0) {
        $temp_status = 'Low Temp';
        $temp_badge_class = 'bg-info-subtle text-info';
    } elseif ($temp_val >= 36.0 && $temp_val <= 37.5) {
        $temp_status = 'Normal';
        $temp_badge_class = 'bg-success-subtle text-success';
    } elseif ($temp_val > 37.5 && $temp_val <= 38.5) {
        $temp_status = 'Slight Fever';
        $temp_badge_class = 'bg-warning-subtle text-warning';
    } else {
        $temp_status = 'High Fever';
        $temp_badge_class = 'bg-danger-subtle text-danger';
    }
}

// Evaluate BMI Status Label
$has_bmi = ($bmi_val !== null && $weight_val !== null);
$bmi_status = '--';
$bmi_badge_class = 'text-secondary';

if ($has_bmi) {
    if ($bmi_val < 18.5) {
        $bmi_status = 'Underweight';
        $bmi_badge_class = 'text-primary';
    } elseif ($bmi_val >= 18.5 && $bmi_val <= 24.9) {
        $bmi_status = 'Normal';
        $bmi_badge_class = 'text-success';
    } elseif ($bmi_val >= 25 && $bmi_val <= 29.9) {
        $bmi_status = 'Overweight';
        $bmi_badge_class = 'text-warning';
    } else {
        $bmi_status = 'Obese';
        $bmi_badge_class = 'text-danger';
    }
}

$heart_status = '--';
$heart_color = 'text-secondary';

if ($heart_rate_val !== null) {
    if ($heart_rate_val < 60) {
        $heart_status = 'Low';
        $heart_color = 'text-warning';
    } elseif ($heart_rate_val <= 100) {
        $heart_status = 'Normal';
        $heart_color = 'text-success';
    } else {
        $heart_status = 'High';
        $heart_color = 'text-danger';
    }
}

$spo2_status = '--';
$spo2_color = 'text-secondary';

if ($spo2_val !== null) {
    if ($spo2_val >= 95) {
        $spo2_status = 'Normal';
        $spo2_color = 'text-success';
    } elseif ($spo2_val >= 90) {
        $spo2_status = 'Low';
        $spo2_color = 'text-warning';
    } else {
        $spo2_status = 'Critical';
        $spo2_color = 'text-danger';
    }
}

$underweight_pct = $has_bmi ? '5%'  : '--';
$normal_pct      = $has_bmi ? '60%' : '--';
$overweight_pct  = $has_bmi ? '25%' : '--';
$obese_pct       = $has_bmi ? '10%' : '--';

$dot_underweight = $has_bmi ? '#3b82f6' : '#cbd5e1';
$dot_normal      = $has_bmi ? '#10b981' : '#cbd5e1';
$dot_overweight  = $has_bmi ? '#f59e0b' : '#cbd5e1';
$dot_obese       = $has_bmi ? '#ef4444' : '#cbd5e1';

$chart_bmi_data   = $has_bmi ? [5, 60, 25, 10] : [1];
$chart_bmi_colors = $has_bmi ? ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'] : ['#e2e8f0'];
$chart_trend_data = ($temp_val !== null || $has_bmi) ? [65, 80, 90, 55, 82, 75, 74] : [0, 0, 0, 0, 0, 0, 0];

// Dynamic active state variable
$current_page = basename($_SERVER['PHP_SELF']);

// Evaluate Blood Pressure Status
$bp_status = '--';
$bp_color  = 'text-secondary';

if ($systolic_val !== null && $diastolic_val !== null) {
    if ($systolic_val < 90 || $diastolic_val < 60) {
        $bp_status = 'Low (Hypotension)';
        $bp_color  = 'text-info';
    } elseif ($systolic_val < 120 && $diastolic_val < 80) {
        $bp_status = 'Normal';
        $bp_color  = 'text-success';
    } elseif ($systolic_val <= 129 && $diastolic_val < 80) {
        $bp_status = 'Elevated';
        $bp_color  = 'text-warning';
    } elseif (($systolic_val >= 130 && $systolic_val <= 139) || ($diastolic_val >= 80 && $diastolic_val <= 89)) {
        $bp_status = 'High Stage 1';
        $bp_color  = 'text-warning';
    } elseif ($systolic_val >= 140 || $diastolic_val >= 90) {
        $bp_status = 'High Stage 2';
        $bp_color  = 'text-danger';
    }
    if ($systolic_val > 180 || $diastolic_val > 120) {
        $bp_status = 'Crisis';
        $bp_color  = 'text-danger';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Patient Details - VitalCore</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../css/patient-view.css">
<link rel="stylesheet" href="../css/theme.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


</head>

<body>

<!-- MOBILE TOP NAVBAR -->
<nav class="navbar navbar-light bg-white shadow-sm d-md-none">
    <div class="container-fluid">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list"></i>
        </button>
        <span class="fw-bold text-primary">VitalCore</span>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <nav class="sidebar offcanvas-md offcanvas-start col-md-3 col-lg-2 p-3 d-flex flex-column justify-content-between" tabindex="-1" id="mobileSidebar">
            <div>
                <!-- LOGO & BRAND -->
                <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

                <div class="sidebar-menu-wrapper">

                    <!-- SECTION: MAIN -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                        Main
                    </small>

                    <ul class="nav flex-column mb-3">
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link <?= (isset($current_page) && $current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="bi bi-grid-1x2-fill me-2"></i>
                                Dashboard
                            </a>
                        </li>
                    </ul>

                    <!-- SECTION: CLINICAL SERVICES & PATIENTS -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                        Clinical Services
                    </small>

                    <ul class="nav flex-column mb-3">
                        <!-- Patient Management -->
                        <li class="nav-item">
                            <a class="nav-link active sidebar-collapse-link d-flex justify-content-between align-items-center"
                               data-bs-toggle="collapse"
                               href="#patientsMenu"
                               role="button"
                               aria-expanded="true"
                               aria-controls="patientsMenu">
                                <span>
                                    <i class="bi bi-people-fill me-2 text-primary"></i>
                                    Patient Management
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>

                            <div class="collapse show" id="patientsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="patient-list.php" class="sidebar-submenu-link active text-decoration-none">
                                            <i class="bi bi-list-ul me-2"></i>
                                            All Patients Services
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="admin-dashboard.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-person-plus-fill me-2"></i>
                                            Add Patient
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <!-- Patient Records -->
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                               data-bs-toggle="collapse"
                               href="#recordsMenu"
                               role="button"
                               aria-expanded="false"
                               aria-controls="recordsMenu">
                                <span>
                                    <i class="bi bi-folder2-open me-2 text-warning"></i>
                                    Patient Records
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>

                            <div class="collapse" id="recordsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="logs/patient-history.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-clock-history me-2"></i>
                                            Patient Records
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="logs/reports.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                            Reports
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>

                    <!-- SECTION: HARDWARE & SYSTEM -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                        Hardware & System
                    </small>

                    <ul class="nav flex-column mb-3">
                        <!-- Administration -->
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                               data-bs-toggle="collapse"
                               href="#adminMenu"
                               role="button"
                               aria-expanded="false"
                               aria-controls="adminMenu">
                                <span>
                                    <i class="bi bi-shield-lock-fill me-2 text-danger"></i>
                                    Administration
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>

                            <div class="collapse" id="adminMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="staff_accounts.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-person-gear me-2"></i>
                                            User Management
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="setting.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-sliders me-2"></i>
                                            Settings
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>

                </div>
            </div>

            <!-- LOGOUT FOOTER -->
            <div class="logout-section pt-3 px-2 border-top border-secondary border-opacity-25">
                <a href="../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left fs-5"></i>
                    Log out
                </a>
            </div>
        </nav>

        <!-- MAIN CONTENT AREA -->
        <main class="col-md-9 col-lg-10 main-content ms-auto">

            <div class="d-flex align-items-center mb-1">
                <a href="patient-list.php" class="text-dark me-1">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <h4 class="fw-bold mb-1">Patients</h4>

            </div>

            <!-- PATIENT TOP BANNER CARD -->
            <div class="patient-top-card">
                <div class="patient-top-content">
                    <?php $initials = strtoupper(substr($patient['fullname'], 0, 2)); ?>
                    <div class="patient-avatar-large">
                        <?= $initials ?>
                    </div>

                    <div>
                        <div class="patient-id">PT<?= $patient['patient_number']; ?></div>
                        <h2 class="patient-name"><?= htmlspecialchars($patient['fullname']); ?></h2>
                            <div class="patient-meta mt-1">
                            <span class="me-4"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($patient['address']); ?></span>
                            <span class="me-4"><i class="bi bi-telephone"></i> Phone: <?= htmlspecialchars($patient['contact_number']); ?></span>
                        </div>

                        <div class="patient-meta mt-1">
                            <span class="me-3"><i class="bi bi-person"></i> <?= htmlspecialchars($patient['gender']); ?></span>
                            <span class="me-3"><i class="bi bi-calendar"></i> Age: <?= htmlspecialchars($patient['age']); ?></span>
                            <span><i class="bi bi-clock-history"></i> Last Visited: <?= $last_visited; ?></span>
                        </div>
                    </div>
                </div>
                <div class="patient-banner-design"></div>
            </div>

            <!-- TWO MAIN TABLES/BLOCKS -->
            <div class="row g-4">
                <!-- TABLE 1: MAIN BODY DIAGRAM & CHARTS (LEFT SIDE) -->
                <div class="col-12 col-xl-9">
                    <div class="card-custom h-60">
                        <div class="row g-5">

                            <!-- Body Diagram Column -->
                            <div class="col-12 col-lg-6 border-end pe-lg-4">
                                <div class="body-figure-container">
                                    <svg viewBox="0 0 200 420" style="height: 400px; width: 280px; overflow: visible; filter: drop-shadow(0px 8px 20px rgba(59,130,246,0.08));" xmlns="http://www.w3.org/2000/svg">
                                        <g fill="rgba(37,99,235,0.02)" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.75">
                                            <!-- Detailed Head & Neck -->
                                            <path d="M 100 12 
                                                    C 112 12, 119 18, 119 32 
                                                    C 119 45, 114 54, 107 56 
                                                    L 106 63 
                                                    L 94 63 
                                                    L 93 56 
                                                    C 86 54, 81 45, 81 32 
                                                    C 81 18, 88 12, 100 12 Z" />
                                                    
                                            <!-- Detailed Torso, Silhouette, Arms & Legs -->
                                            <path d="M 94 63 
                                                    C 82 65, 71 70, 64 78 
                                                    C 60 83, 58 92, 57 105 
                                                    C 55 125, 52 155, 49 175 
                                                    C 47 190, 48 200, 52 210 
                                                    C 56 220, 60 220, 63 212 
                                                    C 65 205, 68 180, 69 160 
                                                    C 71 138, 73 115, 75 110 
                                                    C 76 112, 77 125, 77 140 
                                                    L 76 215 
                                                    C 76 225, 78 232, 83 236 
                                                    L 81 300 
                                                    C 80 320, 77 345, 77 365 
                                                    C 77 385, 80 395, 83 400 
                                                    C 85 403, 89 405, 93 405 
                                                    C 97 405, 98 400, 98 395 
                                                    L 97 235 
                                                    L 103 235 
                                                    L 102 395 
                                                    C 102 400, 103 405, 107 405 
                                                    C 111 405, 115 403, 117 400 
                                                    C 120 395, 123 385, 123 365 
                                                    C 123 345, 120 320, 119 300 
                                                    L 117 236 
                                                    C 122 232, 124 225, 124 215 
                                                    L 123 140 
                                                    C 123 125, 124 112, 125 110 
                                                    C 127 115, 129 138, 131 160 
                                                    C 132 180, 135 205, 137 212 
                                                    C 140 220, 144 220, 148 210 
                                                    C 152 200, 153 190, 151 175 
                                                    C 148 155, 145 125, 143 105 
                                                    C 142 92, 140 83, 136 78 
                                                    C 129 70, 118 65, 106 63 
                                                    Z" />
                                                    
                                            <!-- Anatomy Detail Lines -->
                                            <path d="M 82 72 Q 100 80 118 72" stroke="#93c5fd" stroke-width="1.2" opacity="0.75" />
                                            <path d="M 80 102 Q 100 112 120 102" stroke="#93c5fd" stroke-width="1.2" opacity="0.6" />
                                            <path d="M 100 80 L 100 150" stroke="#93c5fd" stroke-width="1.2" opacity="0.4" />
                                            <path d="M 80 305 Q 85 308 90 305" stroke="#93c5fd" stroke-width="1" opacity="0.5" />
                                            <path d="M 110 305 Q 115 308 120 305" stroke="#93c5fd" stroke-width="1" opacity="0.5" />
                                        </g>

                                        <circle cx="106" cy="98" r="4.5" fill="#10b981" class="pulse-node-green" />
                                        <circle cx="94" cy="115" r="4.5" fill="#10b981" class="pulse-node-green" />
                                        <circle cx="108" cy="155" r="4.5" fill="#ef4444" class="pulse-node-red" />
                                        <circle cx="100" cy="205" r="4.5" fill="#3b82f6" class="pulse-node-blue" />

                                        <polyline points="106,98 140,55 185,55" fill="none" stroke="#94a3b8" stroke-width="1.2" stroke-dasharray="3 3"/>
                                        <polyline points="94,115 140,125 185,150" fill="none" stroke="#94a3b8" stroke-width="1.2" stroke-dasharray="3 3"/>
                                        <polyline points="108,155 140,195 185,240" fill="none" stroke="#94a3b8" stroke-width="1.2" stroke-dasharray="3 3"/>
                                        <polyline points="100,205 140,265 185,320" fill="none" stroke="#94a3b8" stroke-width="1.2" stroke-dasharray="3 3"/>
                                    </svg>

                                    <!-- Overlaid Callout Boxes -->
                                  <div class="position-absolute"
                                        style="left:180px; top:20px; display:flex; flex-direction:column; gap:12px;">

                                        <!-- Heart Rate -->
                                        <div class="body-callout-card d-flex align-items-center">
                                            <div class="body-callout-icon bg-success-subtle text-success">
                                                <i class="bi bi-heart-pulse-fill"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted fw-semibold" style="font-size: 0.72rem;">Heart Rate</div>
                                                <div style="font-size:28px;font-weight:700;line-height:1;"><?= $heart_rate_disp ?></div>
                                                <div class="<?= $heart_color ?> fw-semibold" style="font-size:13px;">
                                                    <?= $heart_status ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SpO₂ -->
                                        <div class="body-callout-card d-flex align-items-center">
                                            <div class="body-callout-icon bg-info-subtle text-info">
                                                <i class="bi bi-droplet-half"></i>
                                            </div>

                                            <div>
                                                <div class="text-muted fw-semibold" style="font-size: 0.72rem;">
                                                    SpO₂
                                                </div>

                                                <div style="font-size:28px;font-weight:700;line-height:1;">
                                                    <?= $spo2_disp ?>
                                                </div>

                                                <div class="<?= $spo2_color ?> fw-semibold" style="font-size:13px;">
                                                    <?= $spo2_status ?>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Blood Pressure (fully dynamic) -->
                                        <div class="body-callout-card d-flex align-items-center">
                                            <div class="body-callout-icon bg-danger-subtle text-danger">
                                                <i class="bi bi-activity"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted fw-semibold" style="font-size: 0.72rem;">Blood Pressure</div>
                                                <div style="font-size:24px;font-weight:700;line-height:1;"><?= $bp_disp ?></div>
                                                <div class="<?= $bp_color ?> fw-semibold" style="font-size:13px;">
                                                    <?= $bp_status ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- BMI Status (fully dynamic) -->
                                        <div class="body-callout-card d-flex align-items-center">
                                            <div class="body-callout-icon bg-primary-subtle text-primary">
                                                <i class="bi bi-person-standing"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted fw-semibold" style="font-size: 0.72rem;">BMI Status</div>
                                                <div class="fw-bold fs-3" style="line-height:1.1;"><?= $bmi_disp ?></div>
                                                <?php if ($has_bmi): ?>
                                                    <small class="<?= $bmi_badge_class ?> fw-semibold" style="font-size: 0.7rem;"><?= $bmi_status ?></small>
                                                <?php else: ?>
                                                    <small class="text-secondary fw-semibold" style="font-size: 0.7rem;">--</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                 <!-- BMI Category & Health Score -->
                                <div class="row g-3">
                                   <div class="col-6">
                                        <div class="p-3 border rounded-3 bg-white h-100 bmi-category-card">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="bi bi-grid text-primary fs-6"></i>
                                                <span class="fw-bold text-dark" style="font-size: 0.88rem;">BMI Category</span>
                                            </div>

                                            <?php if ($has_bmi): ?>
                                                <!-- Current BMI badge -->
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <span class="bmi-current-val"><?= $bmi_disp ?></span>
                                                    <?php
                                                        $bmi_pill_class = ($bmi_val < 18.5)  ? 'bmi-pill-underweight' :
                                                                        (($bmi_val <= 24.9) ? 'bmi-pill-normal'      :
                                                                        (($bmi_val <= 29.9) ? 'bmi-pill-overweight'  : 'bmi-pill-obese'));
                                                    ?>
                                                    <span class="bmi-status-pill <?= $bmi_pill_class ?>"><?= $bmi_status ?></span>
                                                </div>

                                                <!-- Gradient bar -->
                                                <div class="bmi-bar-track">
                                                    <div class="bmi-bar-gradient"></div>
                                                    <?php
                                                        // Map BMI to percentage (10→0%, 40→100%)
                                                        $bmi_pct = max(0, min(100, (($bmi_val - 10) / 30) * 100));
                                                    ?>
                                                    <div class="bmi-bar-pin" style="left: <?= $bmi_pct ?>%;">
                                                        <div class="bmi-pin-dot"></div>
                                                        <div class="bmi-pin-label"><?= number_format($bmi_val,1) ?></div>
                                                    </div>
                                                </div>

                                                <!-- Zone labels -->
                                                <div class="bmi-zone-labels">
                                                    <span style="color:#3b82f6;">Under</span>
                                                    <span style="color:#10b981;">Normal</span>
                                                    <span style="color:#f59e0b;">Over</span>
                                                    <span style="color:#ef4444;">Obese</span>
                                                </div>

                                                <!-- Range markers -->
                                                <div class="bmi-range-markers">
                                                    <span>10</span>
                                                    <span>18.5</span>
                                                    <span>25</span>
                                                    <span>30</span>
                                                    <span>40</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center text-muted py-3" style="font-size:0.78rem;">
                                                    <i class="bi bi-dash-circle mb-1 d-block fs-4"></i>No BMI data
                                                </div>
                                                <!-- Keep the hidden canvas so JS doesn't error -->
                                                <canvas id="bmiCategoryChart" style="display:none;"></canvas>
                                            <?php endif; ?>

                                            <?php if ($has_bmi): ?>
                                                <!-- Hidden canvas (JS still references it) -->
                                                <canvas id="bmiCategoryChart" style="display:none;"></canvas>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="p-3 border rounded-3 bg-white h-100">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="bi bi-shield-plus text-success fs-6"></i>
                                                <span class="fw-bold text-dark" style="font-size: 0.88rem;">Health Score</span>
                                            </div>
                                            <div class="relative-chart-box text-center" style="height: 110px;">
                                                <canvas id="healthScoreChart"></canvas>
                                                <div class="chart-center-val">
                                                    <div class="fw-bold text-dark" style="font-size:1.25rem; line-height:1;">--</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Section: Mini Cards & Charts -->
                            <div class="col-12 col-lg-6 d-flex flex-column gap-4">

                                <!-- Mini Cards -->
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="mini-vital-card">
                                            <div class="mini-vital-icon bg-warning-subtle text-warning">
                                                <i class="bi bi-thermometer-half"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted fw-semibold" style="font-size: 0.75rem;">Temperature</div>
                                                <div class="fw-bold fs-5" style="line-height:1.1;"><?= $temp_disp ?></div>
                                                <?php if($temp_status !== '--'): ?>
                                                    <span class="badge <?= $temp_badge_class ?> mt-1 px-2 py-1" style="font-size: 0.68rem; font-weight: 600;">
                                                        <?= $temp_status ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="mini-vital-card">
                                            <div class="mini-vital-icon bg-primary-subtle text-primary">
                                                <i class="bi bi-droplet-half"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted fw-semibold" style="font-size: 0.75rem;">SpO2</div>
                                                <div class="fw-bold fs-5" style="line-height:1.1;"><?= $spo2_disp ?></div>
                                                <?php if($spo2_status !== '--'): ?>
                                                    <span class="badge bg-info-subtle text-info mt-1 px-2 py-1" style="font-size: 0.68rem; font-weight: 600;">
                                                        <?= $spo2_status ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- RIGHT COLUMN: HEART RATE TREND -->
                                <div class="col-12 col-xl-13">
                                    <div class="card border-0 shadow-sm rounded-3 p-2 bg-white">

                                        <!-- HEADER -->
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <h6 class="fw-bold mb-4 text-dark">
                                                    <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                                                    Heart Rate Trend
                                                </h6>
                                                <small class="text-muted">Recent readings</small>
                                            </div>

                                            <span class="badge bg-danger-subtle text-danger">
                                                <?= $heart_rate_disp ?> BPM
                                            </span>
                                        </div>

                                        <?php
                                        /* =========================================
                                        HEART RATE STATISTICS
                                        ========================================= */

                                        $hrValues = [];
                                        $hrLabels = [];

                                        if ($heartRateHistoryQuery && mysqli_num_rows($heartRateHistoryQuery) > 0) {

                                            // Reset pointer so we can read the query again
                                            mysqli_data_seek($heartRateHistoryQuery, 0);

                                            while ($hrRow = mysqli_fetch_assoc($heartRateHistoryQuery)) {

                                                $value = (int)$hrRow['heart_rate'];

                                                $hrValues[] = $value;
                                                $hrLabels[] = date('m/d/Y', strtotime($hrRow['created_at']));
                                            }

                                            // Reverse so oldest → newest
                                            $hrValues = array_reverse($hrValues);
                                            $hrLabels = array_reverse($hrLabels);
                                        }

                                        $hrCount = count($hrValues);

                                        if ($hrCount > 0) {

                                            $hrAverage = round(array_sum($hrValues) / $hrCount);
                                            $hrMin = min($hrValues);
                                            $hrMax = max($hrValues);

                                            $latestHR = end($hrValues);

                                            if ($latestHR < 60) {
                                                $hrStatus = "Low";
                                                $hrStatusClass = "text-warning";
                                                $hrStatusBg = "bg-warning-subtle";
                                            } elseif ($latestHR <= 100) {
                                                $hrStatus = "Normal";
                                                $hrStatusClass = "text-success";
                                                $hrStatusBg = "bg-success-subtle";
                                            } else {
                                                $hrStatus = "High";
                                                $hrStatusClass = "text-danger";
                                                $hrStatusBg = "bg-danger-subtle";
                                            }
                                        }
                                        ?>

                                        <?php if ($hrCount > 0): ?>

                                            <!-- CURRENT HEART RATE -->
                                            <div class="text-center py-3">

                                                <div class="heart-rate-number">
                                                    <?= $latestHR ?>
                                                    <span>BPM</span>
                                                </div>

                                                <span class="badge <?= $hrStatusBg ?> <?= $hrStatusClass ?> px-3 py-2">
                                                    <i class="bi bi-heart-fill me-1"></i>
                                                    <?= $hrStatus ?>
                                                </span>

                                            </div>


                                            <!-- BUILDING STYLE TREND -->
                                            <div class="hr-building-container mt-5">

                                                <div class="hr-building">

                                                    <?php foreach ($hrValues as $index => $value): ?>

                                                        <?php
                                                            /*
                                                            * Convert BPM into building height.
                                                            * Minimum building height = 25px
                                                            * Maximum = 160px
                                                            */
                                                            $height = (($value - 40) / 120) * 135 + 25;

                                                            $height = max(25, min(160, $height));

                                                            if ($value < 60) {
                                                                $barClass = "hr-low";
                                                            } elseif ($value <= 100) {
                                                                $barClass = "hr-normal";
                                                            } else {
                                                                $barClass = "hr-high";
                                                            }
                                                        ?>

                                                        <div class="hr-building-column">

                                                            <div
                                                                class="hr-building-bar <?= $barClass ?>"
                                                                style="height: <?= $height ?>px;"
                                                                title="<?= $value ?> BPM"
                                                            >
                                                                <span class="hr-value">
                                                                    <?= $value ?>
                                                                </span>
                                                            </div>

                                                            <small class="hr-time">
                                                                <?= $hrLabels[$index] ?>
                                                            </small>

                                                        </div>

                                                    <?php endforeach; ?>

                                                </div>

                                            </div>


                                            <!-- STATISTICS -->
                                            <div class="row g-2 mt-2">

                                                <div class="col-4">
                                                    <div class="hr-stat-box">
                                                        <small>Average</small>
                                                        <strong><?= $hrAverage ?></strong>
                                                        <span>BPM</span>
                                                    </div>
                                                </div>

                                                <div class="col-4">
                                                    <div class="hr-stat-box">
                                                        <small>Lowest</small>
                                                        <strong><?= $hrMin ?></strong>
                                                        <span>BPM</span>
                                                    </div>
                                                </div>

                                                <div class="col-4">
                                                    <div class="hr-stat-box">
                                                        <small>Highest</small>
                                                        <strong><?= $hrMax ?></strong>
                                                        <span>BPM</span>
                                                    </div>
                                                </div>

                                            </div>


                                            <!-- INTERPRETATION -->
                                            <div class="hr-info mt-3">

                                                <div class="d-flex align-items-center">

                                                    <i class="bi bi-info-circle-fill me-2 <?= $hrStatusClass ?>"></i>

                                                    <div>
                                                        <strong class="<?= $hrStatusClass ?>">
                                                            <?= $hrStatus ?> Heart Rate
                                                        </strong>

                                                        <small class="d-block text-muted">
                                                            <?php if ($latestHR < 60): ?>

                                                                Below the typical resting range.

                                                            <?php elseif ($latestHR <= 100): ?>

                                                                Within the typical adult resting range.

                                                            <?php else: ?>

                                                                Above the typical adult resting range.

                                                            <?php endif; ?>
                                                        </small>
                                                    </div>

                                                </div>

                                            </div>

                                        <?php else: ?>

                                            <!-- NO DATA -->
                                            <div class="text-center text-muted py-5">

                                                <i class="bi bi-heart-pulse fs-1"></i>

                                                <p class="mb-0 mt-2">
                                                    No heart rate readings found.
                                                </p>

                                            </div>

                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE 2: VITAL SIGNS SIDEBAR PANEL (RIGHT SIDE) -->
                <div class="col-12 col-xl-3">
                    <div class="bg-white rounded-4 border overflow-hidden p-4 shadow-sm h-100">
                        <div class="pb-3 border-bottom d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-activity text-primary fs-5"></i>
                            <h6 class="fw-bold mb-0" style="font-size: 0.95rem;">Vital Signs</h6>
                        </div>

                        <div>

                            <!-- Blood Pressure -->
                            <div class="vital-item">
                                <div class="vital-item-icon" style="background: #eff6ff; color: #3b82f6;">
                                    <i class="bi bi-droplet"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0 text-dark">Blood Pressure</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle" style="width: 7px; height: 7px; background: <?= ($bp_disp !== '--') ? '#3b82f6' : '#cbd5e1' ?>;"></span>
                                        <span class="text-muted small"><?= $bp_disp ?></span>
                                    </div>
                                </div>
                            </div>
                            <br>

                            <!-- Heart Rate -->
                            <div class="vital-item">
                                <div class="vital-item-icon" style="background: #fff1f2; color: #f43f5e;">
                                    <i class="bi bi-heart-pulse"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0 text-dark">Heart Rate</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle" style="
                                            width:7px;
                                            height:7px;
                                            background:
                                            <?= $heart_status == 'Normal' ? '#10b981' :
                                            ($heart_status == 'Low' ? '#f59e0b' :
                                            ($heart_status == 'High' ? '#ef4444' : '#cbd5e1')); ?>;
                                            "></span>
                                        <span class="<?= $heart_color ?> small fw-semibold"><?= $heart_rate_disp ?> (<?= $heart_status ?>)</span>
                                    </div>
                                </div>
                            </div>
                            <br>

                            <!-- SPO2 -->
                            <div class="vital-item">
                                <div class="vital-item-icon" style="background: #f0f9ff; color: #0ea5e9;">
                                    <i class="bi bi-droplet"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0 text-dark">SPO2</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle" style="
                                            width:7px;
                                            height:7px;
                                            background:
                                            <?= $spo2_status == 'Normal' ? '#10b981' :
                                            ($spo2_status == 'Low' ? '#f59e0b' :
                                            ($spo2_status == 'Critical' ? '#ef4444' : '#cbd5e1')); ?>;
                                            "></span>
                                        <span class="<?= $spo2_color ?> small fw-semibold"><?= $spo2_disp ?> (<?= $spo2_status ?>)</span>
                                    </div>
                                </div>
                            </div>
                            <br>

                            <!-- Temperature -->
                            <div class="vital-item">
                                <div class="vital-item-icon" style="background: #fff7ed; color: #f97316;">
                                    <i class="bi bi-thermometer-half"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0 text-dark">Temperature</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle" style="width: 7px; height: 7px; background: <?= ($temp_val !== null) ? '#f97316' : '#cbd5e1' ?>;"></span>
                                        <span class="text-muted small"><?= $temp_disp ?></span>
                                    </div>
                                </div>
                            </div>
                            <br>

                            <!-- Weight -->
                            <div class="vital-item">
                                <div class="vital-item-icon" style="background: #eff6ff; color: #3b82f6;">
                                    <i class="bi bi-person"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0 text-dark">Weight</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle" style="width: 7px; height: 7px; background: <?= ($weight_val !== null) ? '#10b981' : '#cbd5e1' ?>;"></span>
                                        <span class="text-muted small"><?= $weight_disp ?></span>
                                    </div>
                                </div>
                            </div>
                            <br>

                            <!-- BMI -->
                            <div class="vital-item">
                                <div class="vital-item-icon" style="background: #ecfdf5; color: #10b981;">
                                    <i class="bi bi-person-standing"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0 text-dark">BMI</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle" style="width: 7px; height: 7px; background: <?= ($has_bmi) ? '#10b981' : '#cbd5e1' ?>;"></span>
                                        <span class="text-muted small"><?= $bmi_disp ?></span>
                                    </div>
                                </div>
                            </div>
                            <br>

                            <!-- Height -->
                            <div class="vital-item">
                                <div class="vital-item-icon" style="background: #fff1f2; color: #f43f5e;">
                                    <i class="bi bi-rulers"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0 text-dark">Height</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle" style="width: 7px; height: 7px; background: <?= ($height_val !== null) ? '#10b981' : '#cbd5e1' ?>;"></span>
                                        <span class="text-muted small"><?= $height_disp ?></span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
   
})();
</script>

<script>
// 1. Heart Rate Trend Chart (Glowing Area)
const ctxTrend = document.getElementById('heartRateChart').getContext('2d');
const hrGradient = ctxTrend.createLinearGradient(0, 0, 0, 130);
hrGradient.addColorStop(0,   'rgba(239, 68, 68, 0.25)');
hrGradient.addColorStop(0.6, 'rgba(239, 68, 68, 0.06)');
hrGradient.addColorStop(1,   'rgba(239, 68, 68, 0.00)');

new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            data: <?= json_encode($chart_trend_data) ?>,
            borderColor: '#ef4444',
            backgroundColor: hrGradient,
            borderWidth: 2.5,
            pointBackgroundColor: '#ef4444',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.45,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.parsed.y} BPM`
                },
                backgroundColor: '#1e293b',
                titleColor: '#94a3b8',
                bodyColor: '#f1f5f9',
                padding: 8,
                cornerRadius: 8
            }
        },
        scales: {
            y: {
                min: 0,
                max: 120,
                ticks: { stepSize: 40, color: '#94a3b8', font: { size: 9 } },
                grid: { color: 'rgba(241,245,249,0.8)', drawBorder: false }
            },
            x: {
                ticks: { color: '#94a3b8', font: { size: 9 } },
                grid: { display: false }
            }
        }
    }
});

// 2. BMI Category Donut Chart
const ctxBMI = document.getElementById('bmiCategoryChart').getContext('2d');
new Chart(ctxBMI, {
    type: 'doughnut',
    data: {
        labels: ['Underweight', 'Normal', 'Overweight', 'Obese'],
        datasets: [{
            data: <?= json_encode($chart_bmi_data) ?>,
            backgroundColor: <?= json_encode($chart_bmi_colors) ?>,
            borderWidth: 0
        }]
    }
});

// 3. Health Score Gauge
const ctxScore = document.getElementById('healthScoreChart').getContext('2d');
new Chart(ctxScore, {
    type: 'doughnut',
    data: {
        datasets: [{
            data: [0, 100],
            backgroundColor: ['#10b981', '#e2e8f0'],
            borderWidth: 0
        }]
    }
});
</script>
<script src="../assets/js/theme.js"></script>

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <form action="save-service.php" method="POST">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Add Service
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">

                    <input type="hidden"
                           name="user_id"
                           value="<?= $patient['id']; ?>">

                    <label class="form-label">
                        Select Service
                    </label>

                    <select name="service_type"
                            class="form-select"
                            required>

                        <option value="">
                            Choose Service
                        </option>

                        <option value="Vital Screening">
                            Vital Screening
                        </option>

                        <option value="Prenatal Check-up">
                            Prenatal Check-up
                        </option>

                        <option value="Child Immunization">
                            Child Immunization
                        </option>

                        <option value="Family Planning">
                            Family Planning
                        </option>

                    </select>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-success">
                        Save Service
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>
</body>
</html>