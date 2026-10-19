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

/* Maps an English status label to its translation key (used for data-i18n) */
function i18n_attr(string $label): string {
    static $map = [
        'Low Temp'          => 'st_low_temp',
        'Normal'            => 'st_normal',
        'Slight Fever'      => 'st_slight_fever',
        'High Fever'        => 'st_high_fever',
        'Underweight'       => 'st_underweight',
        'Overweight'        => 'st_overweight',
        'Obese'             => 'st_obese',
        'Low'               => 'st_low',
        'High'              => 'st_high',
        'Critical'          => 'st_critical',
        'Low (Hypotension)' => 'st_hypotension',
        'Elevated'          => 'st_elevated',
        'High Stage 1'      => 'st_high1',
        'High Stage 2'      => 'st_high2',
        'Crisis'            => 'st_crisis',
    ];

    return isset($map[$label]) ? ' data-i18n="' . $map[$label] . '"' : '';
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

$has_visit    = false;
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
        $has_visit    = true;
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

$chart_bmi_data   = $has_bmi ? [5, 60, 25, 10] : [1];
$chart_bmi_colors = $has_bmi ? ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'] : ['#e2e8f0'];

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
<html lang="en" translate="no">
<head>
<script>
// Apply saved theme + text size before first paint (prevents flicker)
(function () {
    try {
        if (localStorage.getItem("theme") === "dark") {
            document.documentElement.classList.add("dark-mode");
        }
        const savedTextSize = localStorage.getItem("textSize");
        if (savedTextSize) {
            const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
            document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
        }
    } catch (e) { /* localStorage unavailable */ }
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Patient Details - VitalCore</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../css/patient-view.css">
<link rel="stylesheet" href="../css/theme.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Fallback Dark Mode Styles (same approach as setting.php) */
    body.dark-mode, html.dark-mode body {
        background-color: #121824 !important;
        color: #e2e8f0 !important;
    }
    html.dark-mode .bg-white,
    html.dark-mode .navbar,
    html.dark-mode .card-custom,
    html.dark-mode .body-callout-card,
    html.dark-mode .mini-vital-card,
    html.dark-mode .modal-content {
        background-color: #1e293b !important;
        color: #e2e8f0 !important;
        border-color: rgba(255,255,255,0.1) !important;
    }
    html.dark-mode .hr-stat-box,
    html.dark-mode .hr-info {
        background-color: #172033 !important;
        color: #e2e8f0 !important;
    }
    html.dark-mode .text-dark { color: #f8fafc !important; }
    html.dark-mode .text-muted { color: #94a3b8 !important; }
    html.dark-mode .border,
    html.dark-mode .border-end,
    html.dark-mode .border-bottom {
        border-color: rgba(255,255,255,0.12) !important;
    }
    html.dark-mode .form-select,
    html.dark-mode .form-control {
        background-color: #0f172a !important;
        color: #f8fafc !important;
        border-color: #334155 !important;
    }
    html.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    html.dark-mode .sidebar {
        background-color: #0f172a !important;
        border-right-color: rgba(255,255,255,0.1) !important;
    }

    /* Night Light Warm Amber Filter Overlay */
    #nightLightOverlay {
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background-color: rgba(255, 140, 0, 0.18);
        pointer-events: none;
        z-index: 99999;
        display: none;
    }
</style>

</head>

<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

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
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.onerror=null; this.src='https://cdn-icons-png.flaticon.com/512/2966/2966327.png';">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

                <div class="sidebar-menu-wrapper">

                    <!-- SECTION: MAIN -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_main">Main</small>

                    <ul class="nav flex-column mb-3">
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link <?= (isset($current_page) && $current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="bi bi-grid-1x2-fill me-2"></i>
                                <span data-i18n="nav_dashboard">Dashboard</span>
                            </a>
                        </li>
                    </ul>

                    <!-- SECTION: CLINICAL SERVICES & PATIENTS -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_clinical">Clinical Services</small>

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
                                    <span data-i18n="nav_patient_mgmt">Patient Management</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>

                            <div class="collapse show" id="patientsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="patient-list.php" class="sidebar-submenu-link active text-decoration-none">
                                            <i class="bi bi-list-ul me-2"></i>
                                            <span data-i18n="nav_all_patients">All Patients Services</span>
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="admin-dashboard.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-person-plus-fill me-2"></i>
                                            <span data-i18n="nav_add_patient">Add Patient</span>
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
                                    <span data-i18n="nav_patient_records">Patient Records</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>

                            <div class="collapse" id="recordsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="logs/patient-history.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-clock-history me-2"></i>
                                            <span data-i18n="nav_patient_records">Patient Records</span>
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="logs/reports.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                            <span data-i18n="nav_reports">Reports</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>

                    <!-- SECTION: HARDWARE & SYSTEM -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_system">Hardware &amp; System</small>

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
                                    <span data-i18n="nav_admin">Administration</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>

                            <div class="collapse" id="adminMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="staff_accounts.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-person-gear me-2"></i>
                                            <span data-i18n="nav_user_mgmt">User Management</span>
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="setting.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-sliders me-2"></i>
                                            <span data-i18n="nav_settings">Settings</span>
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
                    <span data-i18n="nav_logout">Log out</span>
                </a>
            </div>
        </nav>

        <!-- MAIN CONTENT AREA -->
        <main class="col-md-9 col-lg-10 main-content ms-auto">

            <div class="d-flex align-items-center mb-1">
                <a href="patient-list.php" class="text-dark me-1">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <h4 class="fw-bold mb-1" data-i18n="pv_title">Patients</h4>
                <span class="small text-muted ms-3" id="liveClock"></span>
            </div>

            <!-- PATIENT TOP BANNER CARD -->
            <div class="patient-top-card">
                <div class="patient-top-content">
                    <?php $initials = strtoupper(substr((string)$patient['fullname'], 0, 2)); ?>
                    <div class="patient-avatar-large">
                        <?= htmlspecialchars($initials) ?>
                    </div>

                    <div>
                        <div class="patient-id">PT<?= htmlspecialchars((string)$patient['patient_number']); ?></div>
                        <h2 class="patient-name"><?= htmlspecialchars((string)$patient['fullname']); ?></h2>
                            <div class="patient-meta mt-1">
                            <span class="me-4"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars((string)$patient['address']); ?></span>
                            <span class="me-4"><i class="bi bi-telephone"></i> <span data-i18n="pv_phone">Phone:</span> <?= htmlspecialchars((string)$patient['contact_number']); ?></span>
                        </div>

                        <div class="patient-meta mt-1">
                            <span class="me-3"><i class="bi bi-person"></i> <?= htmlspecialchars((string)$patient['gender']); ?></span>
                            <span class="me-3"><i class="bi bi-calendar"></i> <span data-i18n="pv_age">Age:</span> <?= htmlspecialchars((string)$patient['age']); ?></span>
                            <span>
                                <i class="bi bi-clock-history"></i>
                                <span data-i18n="pv_last_visited">Last Visited:</span>
                                <?php if ($has_visit): ?>
                                    <?= $last_visited; ?>
                                <?php else: ?>
                                    <span data-i18n="no_visits">No visits yet</span>
                                <?php endif; ?>
                            </span>
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
                                                <div class="text-muted fw-semibold" style="font-size: 0.72rem;" data-i18n="lbl_hr">Heart Rate</div>
                                                <div style="font-size:28px;font-weight:700;line-height:1;"><?= $heart_rate_disp ?></div>
                                                <div class="<?= $heart_color ?> fw-semibold" style="font-size:13px;"<?= i18n_attr($heart_status) ?>>
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

                                                <div class="<?= $spo2_color ?> fw-semibold" style="font-size:13px;"<?= i18n_attr($spo2_status) ?>>
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
                                                <div class="text-muted fw-semibold" style="font-size: 0.72rem;" data-i18n="lbl_bp">Blood Pressure</div>
                                                <div style="font-size:24px;font-weight:700;line-height:1;"><?= $bp_disp ?></div>
                                                <div class="<?= $bp_color ?> fw-semibold" style="font-size:13px;"<?= i18n_attr($bp_status) ?>>
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
                                                <div class="text-muted fw-semibold" style="font-size: 0.72rem;" data-i18n="lbl_bmi_status">BMI Status</div>
                                                <div class="fw-bold fs-3" style="line-height:1.1;"><?= $bmi_disp ?></div>
                                                <?php if ($has_bmi): ?>
                                                    <small class="<?= $bmi_badge_class ?> fw-semibold" style="font-size: 0.7rem;"<?= i18n_attr($bmi_status) ?>><?= $bmi_status ?></small>
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
                                                <span class="fw-bold text-dark" style="font-size: 0.88rem;" data-i18n="bmi_category">BMI Category</span>
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
                                                    <span class="bmi-status-pill <?= $bmi_pill_class ?>"<?= i18n_attr($bmi_status) ?>><?= $bmi_status ?></span>
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
                                                    <span style="color:#3b82f6;" data-i18n="zone_under">Under</span>
                                                    <span style="color:#10b981;" data-i18n="zone_normal">Normal</span>
                                                    <span style="color:#f59e0b;" data-i18n="zone_over">Over</span>
                                                    <span style="color:#ef4444;" data-i18n="zone_obese">Obese</span>
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
                                                    <i class="bi bi-dash-circle mb-1 d-block fs-4"></i><span data-i18n="no_bmi">No BMI data</span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Hidden canvas (kept for the BMI donut script below) -->
                                            <canvas id="bmiCategoryChart" style="display:none;"></canvas>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="p-3 border rounded-3 bg-white h-100">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="bi bi-shield-plus text-success fs-6"></i>
                                                <span class="fw-bold text-dark" style="font-size: 0.88rem;" data-i18n="health_score">Health Score</span>
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
                                                <div class="text-muted fw-semibold" style="font-size: 0.75rem;" data-i18n="lbl_temp">Temperature</div>
                                                <div class="fw-bold fs-5" style="line-height:1.1;"><?= $temp_disp ?></div>
                                                <?php if($temp_status !== '--'): ?>
                                                    <span class="badge <?= $temp_badge_class ?> mt-1 px-2 py-1" style="font-size: 0.68rem; font-weight: 600;"<?= i18n_attr($temp_status) ?>>
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
                                                    <span class="badge bg-info-subtle text-info mt-1 px-2 py-1" style="font-size: 0.68rem; font-weight: 600;"<?= i18n_attr($spo2_status) ?>>
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
                                                    <span data-i18n="hr_trend">Heart Rate Trend</span>
                                                </h6>
                                                <small class="text-muted" data-i18n="recent_readings">Recent readings</small>
                                            </div>

                                            <!-- (heart_rate_disp already ends with "BPM") -->
                                            <span class="badge bg-danger-subtle text-danger">
                                                <?= $heart_rate_disp ?>
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

                                            $hrKey = strtolower($hrStatus); // low | normal | high
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
                                                    <span<?= i18n_attr($hrStatus) ?>><?= $hrStatus ?></span>
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
                                                            * (named $barHeight so it no longer overwrites the patient's $height)
                                                            */
                                                            $barHeight = (($value - 40) / 120) * 135 + 25;

                                                            $barHeight = max(25, min(160, $barHeight));

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
                                                                style="height: <?= $barHeight ?>px;"
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
                                                        <small data-i18n="hr_avg">Average</small>
                                                        <strong><?= $hrAverage ?></strong>
                                                        <span>BPM</span>
                                                    </div>
                                                </div>

                                                <div class="col-4">
                                                    <div class="hr-stat-box">
                                                        <small data-i18n="hr_lowest">Lowest</small>
                                                        <strong><?= $hrMin ?></strong>
                                                        <span>BPM</span>
                                                    </div>
                                                </div>

                                                <div class="col-4">
                                                    <div class="hr-stat-box">
                                                        <small data-i18n="hr_highest">Highest</small>
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
                                                        <strong class="<?= $hrStatusClass ?>" data-i18n="hr_<?= $hrKey ?>_title">
                                                            <?= $hrStatus ?> Heart Rate
                                                        </strong>

                                                        <small class="d-block text-muted" data-i18n="hr_<?= $hrKey ?>_desc">
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

                                                <p class="mb-0 mt-2" data-i18n="no_hr">
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
                            <h6 class="fw-bold mb-0" style="font-size: 0.95rem;" data-i18n="vital_signs">Vital Signs</h6>
                        </div>

                        <div>

                            <!-- Blood Pressure -->
                            <div class="vital-item">
                                <div class="vital-item-icon" style="background: #eff6ff; color: #3b82f6;">
                                    <i class="bi bi-droplet"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold small mb-0 text-dark" data-i18n="lbl_bp">Blood Pressure</p>
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
                                    <p class="fw-semibold small mb-0 text-dark" data-i18n="lbl_hr">Heart Rate</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle" style="
                                            width:7px;
                                            height:7px;
                                            background:
                                            <?= $heart_status == 'Normal' ? '#10b981' :
                                            ($heart_status == 'Low' ? '#f59e0b' :
                                            ($heart_status == 'High' ? '#ef4444' : '#cbd5e1')); ?>;
                                            "></span>
                                        <span class="<?= $heart_color ?> small fw-semibold"><?= $heart_rate_disp ?> (<span<?= i18n_attr($heart_status) ?>><?= $heart_status ?></span>)</span>
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
                                        <span class="<?= $spo2_color ?> small fw-semibold"><?= $spo2_disp ?> (<span<?= i18n_attr($spo2_status) ?>><?= $spo2_status ?></span>)</span>
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
                                    <p class="fw-semibold small mb-0 text-dark" data-i18n="lbl_temp">Temperature</p>
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
                                    <p class="fw-semibold small mb-0 text-dark" data-i18n="lbl_weight">Weight</p>
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
                                    <p class="fw-semibold small mb-0 text-dark" data-i18n="lbl_height">Height</p>
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
<script src="../assets/js/theme.js"></script>

<!-- SYSTEM SETTINGS ENGINE (applies the preferences saved in setting.php) -->
<script>
(function applySystemSettings() {

    // Safe localStorage helper
    const store = {
        get(key, fallback = null) {
            try {
                const v = localStorage.getItem(key);
                return v === null ? fallback : v;
            } catch (e) { return fallback; }
        }
    };

    // ---------------------------------------------------------------
    // 1. Theme / Dark Mode
    // ---------------------------------------------------------------
    const isDark = store.get("theme") === "dark";
    document.body.classList.toggle("dark-mode", isDark);
    document.documentElement.classList.toggle("dark-mode", isDark);

    // ---------------------------------------------------------------
    // 2. Night Light
    // ---------------------------------------------------------------
    const nightLightOverlay = document.getElementById("nightLightOverlay");
    if (nightLightOverlay) {
        nightLightOverlay.style.display =
            store.get("nightLight") === "enabled" ? "block" : "none";
    }

    // ---------------------------------------------------------------
    // 3. Text Size
    // ---------------------------------------------------------------
    const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
    document.documentElement.style.fontSize =
        fontSizes[store.get("textSize", "normal")] || "100%";

    // ---------------------------------------------------------------
    // 4. Language (English is the server-rendered default, so only
    //    Filipino / Cebuano need to be applied)
    // ---------------------------------------------------------------
    const i18n = {
        fil: {
            section_main: "Pangunahin",
            nav_dashboard: "Dashboard",
            section_clinical: "Serbisyong Klinikal",
            nav_patient_mgmt: "Pamamahala ng Pasyente",
            nav_all_patients: "Lahat ng Serbisyong Pasyente",
            nav_add_patient: "Magdagdag ng Pasyente",
            nav_patient_records: "Mga Rekord ng Pasyente",
            nav_reports: "Mga Ulat",
            section_system: "Hardware at Sistema",
            nav_admin: "Administrasyon",
            nav_user_mgmt: "Pamamahala ng Gumagamit",
            nav_settings: "Mga Setting",
            nav_logout: "Mag-logout",
            pv_title: "Mga Pasyente",
            pv_phone: "Telepono:",
            pv_age: "Edad:",
            pv_last_visited: "Huling Bisita:",
            no_visits: "Wala pang bisita",
            lbl_hr: "Bilis ng Puso",
            lbl_bp: "Presyon ng Dugo",
            lbl_bmi_status: "Katayuan ng BMI",
            bmi_category: "Kategorya ng BMI",
            zone_under: "Kulang",
            zone_normal: "Normal",
            zone_over: "Sobra",
            zone_obese: "Obese",
            no_bmi: "Walang datos ng BMI",
            health_score: "Marka ng Kalusugan",
            lbl_temp: "Temperatura",
            lbl_weight: "Timbang",
            lbl_height: "Taas",
            hr_trend: "Takbo ng Bilis ng Puso",
            recent_readings: "Mga kamakailang basa",
            hr_avg: "Average",
            hr_lowest: "Pinakamababa",
            hr_highest: "Pinakamataas",
            hr_low_title: "Mababang Bilis ng Puso",
            hr_normal_title: "Normal na Bilis ng Puso",
            hr_high_title: "Mataas na Bilis ng Puso",
            hr_low_desc: "Mas mababa sa karaniwang saklaw habang nagpapahinga.",
            hr_normal_desc: "Nasa karaniwang saklaw ng nasa hustong gulang habang nagpapahinga.",
            hr_high_desc: "Mas mataas sa karaniwang saklaw ng nasa hustong gulang habang nagpapahinga.",
            no_hr: "Walang nakitang basa ng bilis ng puso.",
            vital_signs: "Mga Vital Sign",
            st_low_temp: "Mababang Temp",
            st_normal: "Normal",
            st_slight_fever: "Bahagyang Lagnat",
            st_high_fever: "Mataas na Lagnat",
            st_underweight: "Kulang sa Timbang",
            st_overweight: "Sobra sa Timbang",
            st_obese: "Obese",
            st_low: "Mababa",
            st_high: "Mataas",
            st_critical: "Kritikal",
            st_hypotension: "Mababa (Hypotension)",
            st_elevated: "Medyo Mataas",
            st_high1: "Mataas Yugto 1",
            st_high2: "Mataas Yugto 2",
            st_crisis: "Krisis"
        },
        ceb: {
            section_main: "Pangunahing",
            nav_dashboard: "Dashboard",
            section_clinical: "Mga Serbisyong Klinikal",
            nav_patient_mgmt: "Pagdumala sa Pasyente",
            nav_all_patients: "Tanan nga Serbisyong Pasyente",
            nav_add_patient: "Idugang ang Pasyente",
            nav_patient_records: "Mga Rekord sa Pasyente",
            nav_reports: "Mga Report",
            section_system: "Hardware ug Sistema",
            nav_admin: "Administrasyon",
            nav_user_mgmt: "Pagdumala sa Paggamit",
            nav_settings: "Mga Setting",
            nav_logout: "Mo-logout",
            pv_title: "Mga Pasyente",
            pv_phone: "Telepono:",
            pv_age: "Edad:",
            pv_last_visited: "Katapusang Bisita:",
            no_visits: "Wala pay bisita",
            lbl_hr: "Kusog sa Kasingkasing",
            lbl_bp: "Presyon sa Dugo",
            lbl_bmi_status: "Kahimtang sa BMI",
            bmi_category: "Kategorya sa BMI",
            zone_under: "Kulang",
            zone_normal: "Normal",
            zone_over: "Sobra",
            zone_obese: "Obese",
            no_bmi: "Walay datos sa BMI",
            health_score: "Marka sa Panglawas",
            lbl_temp: "Temperatura",
            lbl_weight: "Timbang",
            lbl_height: "Gitas-on",
            hr_trend: "Lakaw sa Kusog sa Kasingkasing",
            recent_readings: "Bag-ong mga basa",
            hr_avg: "Average",
            hr_lowest: "Pinakaubos",
            hr_highest: "Pinakataas",
            hr_low_title: "Ubos nga Kusog sa Kasingkasing",
            hr_normal_title: "Normal nga Kusog sa Kasingkasing",
            hr_high_title: "Taas nga Kusog sa Kasingkasing",
            hr_low_desc: "Ubos sa kasagarang sakup samtang nagpahulay.",
            hr_normal_desc: "Sulod sa kasagarang sakup sa hamtong samtang nagpahulay.",
            hr_high_desc: "Mas taas sa kasagarang sakup sa hamtong samtang nagpahulay.",
            no_hr: "Walay nakitang basa sa kusog sa kasingkasing.",
            vital_signs: "Mga Vital Sign",
            st_low_temp: "Ubos nga Temp",
            st_normal: "Normal",
            st_slight_fever: "Gamayng Hilanat",
            st_high_fever: "Taas nga Hilanat",
            st_underweight: "Kulang sa Timbang",
            st_overweight: "Sobra sa Timbang",
            st_obese: "Obese",
            st_low: "Ubos",
            st_high: "Taas",
            st_critical: "Kritikal",
            st_hypotension: "Ubos (Hypotension)",
            st_elevated: "Medyo Taas",
            st_high1: "Taas Yugto 1",
            st_high2: "Taas Yugto 2",
            st_crisis: "Krisis"
        }
    };

    const lang = store.get("language", "en");
    const dict = i18n[lang] || null;

    if (dict) {
        document.documentElement.lang = lang;

        document.querySelectorAll("[data-i18n]").forEach(el => {
            const text = dict[el.getAttribute("data-i18n")];
            if (text) el.textContent = text;
        });
    }

    // ---------------------------------------------------------------
    // 5. Date & Time Format (live clock next to the page title)
    // ---------------------------------------------------------------
    const use12h = store.get("dateTimeFormat", "24h") === "12h";
    const clockEl = document.getElementById("liveClock");

    function updateClock() {
        if (!clockEl) return;
        const opts = { hour: "2-digit", minute: "2-digit", second: "2-digit", timeZone: "Asia/Manila" };
        if (use12h) { opts.hour12 = true; } else { opts.hourCycle = "h23"; }
        clockEl.textContent = new Date().toLocaleTimeString("en-US", opts);
    }
    updateClock();
    setInterval(updateClock, 1000);

})();
</script>

<script>
/*
 * NOTE: the old "Heart Rate Trend" line chart was removed. It looked for a
 * <canvas id="heartRateChart"> that no longer exists (the trend is now the
 * bar "building" view), so getContext() threw an error and stopped every
 * chart after it from drawing.
 */

// 1. BMI Category Donut Chart (canvas is hidden, kept for compatibility)
const bmiCanvas = document.getElementById('bmiCategoryChart');

if (bmiCanvas) {
    new Chart(bmiCanvas.getContext('2d'), {
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
}

// 2. Health Score Gauge
const scoreCanvas = document.getElementById('healthScoreChart');

if (scoreCanvas) {
    new Chart(scoreCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [0, 100],
                backgroundColor: ['#10b981', '#e2e8f0'],
                borderWidth: 0
            }]
        }
    });
}
</script>

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
                           value="<?= (int)$patient['id']; ?>">

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