<?php
session_start();

// Set local timezone for Philippines
date_default_timezone_set('Asia/Manila');

/* =========================
   SECURITY CHECK
   (re-enabled - this page shows patient data.
    To bypass it while demoing, comment out this block again.)
========================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login.php");
    exit();
}

/* Helper: JSON-encode template variables for data-vars attributes */
function vars_attr(array $vars): string {
    return htmlspecialchars(json_encode($vars, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

$conn = mysqli_connect("localhost", "root", "", "vitalcore_db");

if (!$conn) {
    // Fail-safe connection
}

/* =========================
   PATIENT STATISTICS & GENDER OVERVIEW
========================= */
$count_q = $conn ? mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='patient'") : false;
$count_r = $count_q ? mysqli_fetch_assoc($count_q) : [];
$patient_count = $count_r['total'] ?? 0;

$today_q = $conn ? mysqli_query($conn, "SELECT COUNT(*) AS today_total FROM users WHERE role='patient' AND DATE(created_at) = CURDATE()") : false;
$today_r = $today_q ? mysqli_fetch_assoc($today_q) : [];
$today_new_patients = $today_r['today_total'] ?? 0;

$yesterday_q = $conn ? mysqli_query($conn, "SELECT COUNT(*) AS yesterday_total FROM users WHERE role='patient' AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY") : false;
$yesterday_r = $yesterday_q ? mysqli_fetch_assoc($yesterday_q) : [];
$yesterday_new_patients = $yesterday_r['yesterday_total'] ?? 0;

$gender_q = $conn ? mysqli_query($conn, "
    SELECT
        SUM(CASE WHEN gender='Male' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN gender='Female' THEN 1 ELSE 0 END) AS female_count,
        COUNT(*) AS total_count
    FROM users
    WHERE role='patient'
") : false;
$gender_r = $gender_q ? mysqli_fetch_assoc($gender_q) : [];

$male_count = $gender_r['male_count'] ?? 0;
$female_count = $gender_r['female_count'] ?? 0;
$total_gender_count = $gender_r['total_count'] ?? 0;

$male_percent = $total_gender_count > 0 ? round(($male_count / $total_gender_count) * 100, 1) : 0;
$female_percent = $total_gender_count > 0 ? round(($female_count / $total_gender_count) * 100, 1) : 0;

$current_date_str = date("M d, Y");
$yesterday_date_str = date("M d, Y", strtotime("-1 day"));

/* =========================
   PATIENT LIST (DYNAMIC QUERY)
========================= */
$sql_patients = "
    SELECT u.*, COALESCE(p.disease, 'Not recorded') AS disease
    FROM users u
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.role='patient'
    ORDER BY u.id DESC
    LIMIT 10
";
$patients = $conn ? mysqli_query($conn, $sql_patients) : false;

/* =========================
   SERVICE COUNTS
========================= */
$vital_count = $conn ? (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM measurements WHERE service_type='vital'"))['total'] ?? 0) : 0;
$prenatal_count = $conn ? (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM measurements WHERE service_type='prenatal'"))['total'] ?? 0) : 0;
$family_count = $conn ? (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM measurements WHERE service_type='family'"))['total'] ?? 0) : 0;

/* =========================
   AVERAGE HEALTH MEASUREMENTS
========================= */
$average_health_q = $conn ? mysqli_query($conn, "
    SELECT
        AVG(height) AS avg_height,
        AVG(weight) AS avg_weight,
        AVG(temperature) AS avg_temperature,
        AVG(heart_rate) AS avg_heart_rate,
        AVG(spo2) AS avg_spo2,
        AVG(systolic) AS avg_systolic,
        AVG(diastolic) AS avg_diastolic
    FROM measurements
") : false;

$average_health = $average_health_q ? mysqli_fetch_assoc($average_health_q) : [];

$avg_height = $average_health['avg_height'] ?? 0;
$avg_weight = $average_health['avg_weight'] ?? 0;
$avg_temperature = $average_health['avg_temperature'] ?? 0;
$avg_heart_rate = $average_health['avg_heart_rate'] ?? 0;
$avg_spo2 = $average_health['avg_spo2'] ?? 0;
$avg_systolic = $average_health['avg_systolic'] ?? 0;
$avg_diastolic = $average_health['avg_diastolic'] ?? 0;

/* =========================
   FOLLOW-UP SCHEDULES
========================= */
$followup_rows = [];
$followup_query = $conn ? mysqli_query($conn, "
    SELECT
        u.id,
        u.fullname,
        'family' AS service_type,
        MIN(f.next_schedule) AS follow_up_date
    FROM family_planning_records f
    INNER JOIN users u ON u.id = f.user_id
    WHERE f.next_schedule IS NOT NULL
      AND f.next_schedule >= CURDATE()
      AND u.role = 'patient'
    GROUP BY f.user_id, u.id, u.fullname
    ORDER BY follow_up_date ASC
    LIMIT 20
") : false;

if ($followup_query) {
    while ($row = mysqli_fetch_assoc($followup_query)) {
        $followup_rows[] = $row;
    }
}

$followup_total = count($followup_rows);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitalCore Dashboard</title>
    <!-- Bootstrap 5.3 (the *-subtle classes used on this page need 5.3) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/theme.css">

    <style>
        /* Fallback Dark Mode Styles */
        body.dark-mode, html.dark-mode body {
            background-color: #121824 !important;
            color: #e2e8f0 !important;
        }
        html.dark-mode .card,
        html.dark-mode .patient-card,
        html.dark-mode .analytics-card,
        html.dark-mode .modal-content {
            background-color: #1e293b !important;
            color: #e2e8f0 !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        html.dark-mode .text-dark {
            color: #f8fafc !important;
        }
        html.dark-mode .text-muted {
            color: #94a3b8 !important;
        }
        html.dark-mode .bg-white,
        html.dark-mode .bg-light {
            background-color: #0f172a !important;
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }
        html.dark-mode .form-control {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        html.dark-mode .table {
            --bs-table-bg: transparent;
            --bs-table-color: #e2e8f0;
            --bs-table-hover-color: #f8fafc;
            --bs-table-hover-bg: rgba(255,255,255,0.05);
            --bs-table-border-color: rgba(255,255,255,0.1);
            color: #e2e8f0 !important;
        }
        html.dark-mode .border-top,
        html.dark-mode .border-bottom,
        html.dark-mode .border-start {
            border-color: rgba(255,255,255,0.12) !important;
        }
        html.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        html.dark-mode .sidebar {
            background-color: #0f172a !important;
            border-right-color: rgba(255,255,255,0.1) !important;
        }

        /* Night Light Filter Overlay */
        #nightLightOverlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(255, 140, 0, 0.18);
            pointer-events: none;
            z-index: 99999;
            display: none;
            mix-blend-mode: multiply;
        }

        .modal {
            z-index: 999999 !important;
        }

        .modal-backdrop {
            z-index: 999998 !important;
        }

        .modal-dialog {
            margin: 1.75rem auto !important;
        }

        .modal-dialog-centered {
            min-height: calc(100vh - 3.5rem) !important;
        }
    </style>
</head>

<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
            <div>
                <!-- LOGO & BRAND -->
                <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.onerror=null; this.src='https://cdn-icons-png.flaticon.com/512/2966/2966327.png';">
                    <span class="sidebar-brand fw-bold fs-5">VitalCore</span>
                </div>

                <div class="sidebar-menu-wrapper">

                    <!-- SECTION: MAIN -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_main">
                        Main
                    </small>

                    <ul class="nav flex-column mb-3">
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-grid-1x2-fill me-2"></i>
                                <span data-i18n="nav_dashboard">Dashboard</span>
                            </a>
                        </li>
                    </ul>

                    <!-- SECTION: CLINICAL SERVICES & PATIENTS -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_clinical">
                        Clinical Services
                    </small>

                    <ul class="nav flex-column mb-3">
                        <!-- Patients / Patient Management -->
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                            data-bs-toggle="collapse"
                            href="#patientsMenu"
                            role="button"
                            aria-expanded="false"
                            aria-controls="patientsMenu">
                                <span>
                                    <i class="bi bi-people-fill me-2 text-primary"></i>
                                    <span data-i18n="nav_patient_mgmt">Patient Management</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>

                            <div class="collapse" id="patientsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="patient-list.php" class="sidebar-submenu-link text-decoration-none">
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
                                            <span data-i18n="nav_records_history">Patient Records</span>
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
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_system">
                        Hardware &amp; System
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
            <div class="pt-3 px-2 border-top border-secondary border-opacity-25">
                <a href="../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left fs-5"></i>
                    <span data-i18n="nav_logout">Log out</span>
                </a>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <!-- TOP HEADER BAR WITH DYNAMIC DATE & QUICK CONTROLS -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 fade-up">
               <div>
                    <h3 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-hospital-fill me-2 text-primary"></i>
                        <span data-i18n="header_greeting">Good Day, Admin</span>
                    </h3>
                    <p class="text-muted small mb-0" data-i18n="header_desc">
                        System status overview and clinical intake telemetry.
                    </p>
                </div>

                <div class="top-actions m-0">
                    <!-- DYNAMIC CURRENT DATE + LIVE CLOCK CHIP -->
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill fw-semibold shadow-sm" style="font-size: 0.85rem;" id="dashboardDateChip">
                        <i class="bi bi-calendar-event me-1 text-primary"></i> <?= date("l, M d, Y"); ?>
                        <span class="ms-1 text-primary" id="liveClock"></span>
                    </span>

                    <a href="#" id="openSensorStatus" class="status-pill warning text-decoration-none">
                        <i class="bi bi-exclamation-triangle-fill warning-icon"></i>
                        <span data-i18n="sensors_status">Sensors Status</span>
                    </a>

                     <button id="darkModeToggle" type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="bi bi-moon-stars-fill me-1"></i>
                        <span data-i18n="dark_mode_title">Dark Mode</span>
                    </button>
                </div>
            </div>

            <!-- Dashboard Overview Row -->
            <div class="row mb-4">
                <!-- HEALTH ANALYTICS -->
                <div class="col-xl-5 col-lg-5 mb-3 mb-lg-0">
                    <div class="card analytics-card h-100 p-4 fade-up fade-delay-1">

                        <!-- HEADER -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-1">
                                    <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                                    <span data-i18n="avg_health_title">Average Health</span>
                                </h5>
                                <small class="text-muted" data-i18n="avg_health_desc">
                                    Average recorded vital measurements
                                </small>
                            </div>

                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                                <i class="bi bi-activity me-1"></i>
                                <span data-i18n="live_data">Live Data</span>
                            </span>
                        </div>

                        <!-- AVERAGE HEALTH TABLE -->
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle average-health-table mb-0">
                                <thead>
                                    <tr>
                                        <th data-i18n="tbl_measurement">Measurement</th>
                                        <th class="text-end" data-i18n="tbl_average">Average</th>
                                        <th class="text-end" data-i18n="tbl_unit">Unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- HEIGHT -->
                                    <tr>
                                        <td>
                                            <div class="health-label">
                                                <div class="health-icon height-icon">
                                                    <i class="bi bi-rulers"></i>
                                                </div>
                                                <div>
                                                    <strong data-i18n="lbl_height">Height</strong>
                                                    <small data-i18n="sub_height">Body height</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <strong class="health-value">
                                                <?= $avg_height > 0 ? number_format($avg_height, 1) : '--' ?>
                                            </strong>
                                        </td>
                                        <td class="text-end">
                                            <span class="health-unit">cm</span>
                                        </td>
                                    </tr>

                                    <!-- WEIGHT -->
                                    <tr>
                                        <td>
                                            <div class="health-label">
                                                <div class="health-icon weight-icon">
                                                    <i class="bi bi-speedometer2"></i>
                                                </div>
                                                <div>
                                                    <strong data-i18n="lbl_weight">Weight</strong>
                                                    <small data-i18n="sub_weight">Body weight</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <strong class="health-value">
                                                <?= $avg_weight > 0 ? number_format($avg_weight, 1) : '--' ?>
                                            </strong>
                                        </td>
                                        <td class="text-end">
                                            <span class="health-unit">kg</span>
                                        </td>
                                    </tr>

                                    <!-- TEMPERATURE -->
                                    <tr>
                                        <td>
                                            <div class="health-label">
                                                <div class="health-icon temp-icon">
                                                    <i class="bi bi-thermometer-half"></i>
                                                </div>
                                                <div>
                                                    <strong data-i18n="lbl_temp">Temperature</strong>
                                                    <small data-i18n="sub_temp">Body temperature</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <strong class="health-value">
                                                <?= $avg_temperature > 0 ? number_format($avg_temperature, 1) : '--' ?>
                                            </strong>
                                        </td>
                                        <td class="text-end">
                                            <span class="health-unit">°C</span>
                                        </td>
                                    </tr>

                                    <!-- HEART RATE -->
                                    <tr>
                                        <td>
                                            <div class="health-label">
                                                <div class="health-icon heart-icon">
                                                    <i class="bi bi-heart-pulse-fill"></i>
                                                </div>
                                                <div>
                                                    <strong data-i18n="lbl_heart">Heart Rate</strong>
                                                    <small data-i18n="sub_heart">Pulse rate</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <strong class="health-value">
                                                <?= $avg_heart_rate > 0 ? number_format($avg_heart_rate, 0) : '--' ?>
                                            </strong>
                                        </td>
                                        <td class="text-end">
                                            <span class="health-unit">BPM</span>
                                        </td>
                                    </tr>

                                    <!-- SPO2 -->
                                    <tr>
                                        <td>
                                            <div class="health-label">
                                                <div class="health-icon spo2-icon">
                                                    <i class="bi bi-lungs-fill"></i>
                                                </div>
                                                <div>
                                                    <strong>SpO₂</strong>
                                                    <small data-i18n="sub_spo2">Oxygen saturation</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <strong class="health-value">
                                                <?= $avg_spo2 > 0 ? number_format($avg_spo2, 1) : '--' ?>
                                            </strong>
                                        </td>
                                        <td class="text-end">
                                            <span class="health-unit">%</span>
                                        </td>
                                    </tr>

                                    <!-- BLOOD PRESSURE -->
                                    <tr>
                                        <td>
                                            <div class="health-label">
                                                <div class="health-icon bp-icon">
                                                    <i class="bi bi-activity"></i>
                                                </div>
                                                <div>
                                                    <strong data-i18n="lbl_bp">Blood Pressure</strong>
                                                    <small data-i18n="sub_bp">Average BP</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <strong class="health-value">
                                                <?php if ($avg_systolic > 0 || $avg_diastolic > 0): ?>
                                                    <?= number_format($avg_systolic, 0) ?> / <?= number_format($avg_diastolic, 0) ?>
                                                <?php else: ?>
                                                    --
                                                <?php endif; ?>
                                            </strong>
                                        </td>
                                        <td class="text-end">
                                            <span class="health-unit">mmHg</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- FOOTER -->
                        <div class="average-health-footer">
                            <div>
                                <i class="bi bi-info-circle me-1"></i>
                                <span data-i18n="based_on">Based on recorded measurements</span>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <!-- New Patients & Patients Overview (Center Side) -->
                <div class="col-xl-4 col-lg-4 mb-3 mb-lg-0">
                    <!-- NEW PATIENTS CARD -->
                   <div class="card analytics-card p-4 mb-3 fade-up fade-delay-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold text-dark m-0" data-i18n="title_new_patients">New Patients</h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fw-semibold" style="font-size: 0.75rem;" data-i18n="badge_today">
                                Today
                            </span>
                        </div>

                        <div style="position: relative; height:150px; width:100%" class="d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-baseline gap-2 mt-1">
                               <span class="display-3 fw-bolder text-primary lh-1 stat-number">
                                    <?= number_format($today_new_patients); ?>
                                </span>
                                <span class="text-muted fw-semibold fs-6" data-i18n="<?= $today_new_patients == 1 ? 'new_added_one' : 'new_added_many'; ?>">new patient<?= $today_new_patients != 1 ? 's' : ''; ?> added today</span>
                            </div>

                            <div class="row g-2 border-top pt-2 mt-auto">
                                <div class="col-6">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;" data-i18n="total_patients_lbl">Total Patients</span>
                                       <span class="fw-bold fs-5 text-dark stat-number"><?= number_format($patient_count); ?></span>
                                        <span class="text-secondary opacity-75" style="font-size: 0.75rem;"><?= $current_date_str; ?></span>
                                    </div>
                                </div>

                                <div class="col-6 border-start ps-3">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;" data-i18n="previous_record">Previous Record</span>
                                        <span class="fw-bold fs-5 text-dark stat-number"><?= number_format($yesterday_new_patients); ?></span>
                                        <span class="text-secondary opacity-75" style="font-size: 0.75rem;"><?= $yesterday_date_str; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PATIENTS OVERVIEW CARD -->
                   <div class="card analytics-card p-4 fade-up fade-delay-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold text-dark m-0" data-i18n="title_patients_overview">Patients Overview</h5>
                            <span class="badge bg-light text-secondary border px-2 py-1 rounded-pill fw-semibold" style="font-size: 0.72rem;" data-i18n="gender_ratio">
                                Gender Ratio
                            </span>
                        </div>

                        <div style="position: relative; height:150px; width:100%" class="d-flex flex-column justify-content-between">
                            <div class="table-responsive mt-1">
                                <table class="table table-borderless table-sm align-middle mb-1" style="font-size: 0.85rem;">
                                    <thead>
                                        <tr class="text-muted border-bottom" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <th class="ps-0 py-1" data-i18n="col_gender">Gender</th>
                                            <th class="text-center py-1" data-i18n="col_count">Count</th>
                                            <th class="text-end py-1" data-i18n="col_ratio">Ratio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="ps-0 py-1 fw-semibold text-dark">
                                                <i class="bi bi-gender-male text-primary me-1"></i> <span data-i18n="male">Male</span>
                                            </td>
                                            <td class="text-center py-1 fw-bold stat-number"><?= number_format($male_count); ?></td>
                                            <td class="text-end py-1">
                                                <span class="badge bg-primary-subtle text-primary fw-semibold"><?= $male_percent; ?>%</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-1 fw-semibold text-dark">
                                                <i class="bi bi-gender-female text-danger me-1"></i> <span data-i18n="female">Female</span>
                                            </td>
                                            <td class="text-center py-1 fw-bold stat-number"><?= number_format($female_count); ?></td>
                                            <td class="text-end py-1">
                                                <span class="badge bg-danger-subtle text-danger fw-semibold"><?= $female_percent; ?>%</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-auto pt-2 border-top">
                                <div class="d-flex justify-content-between text-muted mb-1" style="font-size: 0.72rem;">
                                    <span><i class="bi bi-circle-fill text-primary me-1" style="font-size: 0.5rem;"></i> <span data-i18n="legend_male" data-vars="<?= vars_attr(['p' => (string)$male_percent]); ?>">Male (<?= $male_percent; ?>%)</span></span>
                                    <span><i class="bi bi-circle-fill text-danger me-1" style="font-size: 0.5rem;"></i> <span data-i18n="legend_female" data-vars="<?= vars_attr(['p' => (string)$female_percent]); ?>">Female (<?= $female_percent; ?>%)</span></span>
                                </div>
                               <div class="progress gender-progress">
                                    <div class="progress-bar bg-primary male-bar" style="width: <?= $male_percent; ?>%"></div>
                                    <div class="progress-bar bg-danger female-bar" style="width: <?= $female_percent; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service Categories (Right Side) -->
                <div class="col-xl-3 col-lg-3">
                    <div class="card analytics-card h-100 p-4 fade-up fade-delay-4">
                        <h5 class="fw-bold mb-3" data-i18n="title_services">Service Categories</h5>

                        <div style="height:220px">
                            <canvas id="serviceChart"></canvas>
                        </div>

                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="bi bi-circle-fill text-primary me-1"></i> <span data-i18n="svc_vital">Vital Screening</span></span>
                                <strong class="stat-number"><?= $vital_count ?></strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="bi bi-circle-fill text-danger me-1"></i> <span data-i18n="svc_prenatal">Prenatal</span></span>
                               <strong class="stat-number"><?= $prenatal_count ?></strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span><i class="bi bi-circle-fill text-warning me-1"></i> <span data-i18n="svc_family">Family Planning</span></span>
                                <strong class="stat-number"><?= $family_count ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

           <!-- Patient List + Follow-up Schedule Row -->
            <div class="row mt-4">
                <!-- Recent Patient List -->
                <div class="col-xl-7 col-12 mb-2">
                    <div class="card patient-card p-4 fade-up fade-delay-5">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                            <div>
                                <h5 class="fw-bold text-dark m-0" data-i18n="title_recent_patients">Recent Patient List</h5>
                                <small class="text-muted" data-i18n="recent_sub">Showing latest registered patients</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="position-relative">
                                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                                    <input type="text" id="patientSearchInput" class="form-control rounded-pill ps-5 bg-light border-light-subtle" placeholder="Search patient name..." data-i18n-placeholder="ph_search" style="font-size: 0.85rem; width: 230px;">
                                </div>
                                <a href="admin-dashboard.php" class="btn btn-primary rounded-pill px-3 py-1 fw-semibold d-flex align-items-center gap-1" style="font-size: 0.85rem;">
                                    <i class="bi bi-person-plus-fill"></i> <span data-i18n="nav_add_patient">Add Patient</span>
                                </a>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="patientTable">
                                <thead>
                                    <tr>
                                        <th data-i18n="col_no">No</th>
                                        <th data-i18n="col_name">Name</th>
                                        <th data-i18n="col_checkup">Date Of Checkup</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $no = 1;
                                if ($patients) {
                                    while ($row = mysqli_fetch_assoc($patients)) {
                                        $date = isset($row['created_at']) ? date("Y-m-d", strtotime($row['created_at'])) : date("Y-m-d");
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td class="fw-semibold patient-name">
                                            <?= htmlspecialchars((string)$row['fullname']); ?>
                                        </td>
                                        <td><?= $date; ?></td>
                                    </tr>
                                <?php
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Follow-up Schedule -->
                <div class="col-xl-5 col-12 mb-2">
                    <div class="card analytics-card p-4 fade-up fade-delay-5 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-1">
                                    <i class="bi bi-calendar2-check-fill text-primary me-2"></i>
                                    <span data-i18n="title_followup">Follow-up Schedule</span>
                                    <span class="badge bg-primary ms-2" data-i18n="followup_total" data-vars="<?= vars_attr(['n' => (string)$followup_total]); ?>"> Total:
                                        <?= $followup_total; ?>
                                    </span>
                                </h5>
                                <small class="text-muted" data-i18n="followup_sub">Patients scheduled</small>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fw-semibold" style="font-size: 0.75rem;">
                                <?= date("M d, Y"); ?>
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-borderless table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                <thead>
                                    <tr class="text-muted border-bottom" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <th class="ps-0 py-1" data-i18n="col_patient">Patient</th>
                                        <th class="text-center py-1" data-i18n="col_service">Service</th>
                                        <th class="text-end py-1" data-i18n="col_date">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($followup_rows)): ?>
                                    <?php foreach ($followup_rows as $fu): ?>
                                    <tr>
                                        <td class="ps-0 py-2 fw-semibold text-dark">
                                            <?= htmlspecialchars((string)$fu['fullname']); ?>
                                        </td>
                                        <td class="text-center py-2">
                                            <?php
                                            $svc = $fu['service_type'];
                                            $badge_map = [
                                                'vital'        => ['bg-warning-subtle text-warning', 'Vital',    'badge_vital'],
                                                'prenatal'     => ['bg-danger-subtle text-danger',   'Prenatal', 'badge_prenatal'],
                                                'family'       => ['bg-primary-subtle text-primary', 'Family',   'badge_family'],
                                            ];
                                            [$cls, $label, $label_key] = $badge_map[$svc] ?? ['bg-secondary-subtle text-secondary', ucfirst($svc), ''];
                                            ?>
                                            <span class="badge <?= $cls ?> fw-semibold" style="font-size: 0.7rem;"<?= $label_key ? ' data-i18n="' . $label_key . '"' : ''; ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </span>
                                        </td>
                                        <td class="text-end py-2 text-muted" style="font-size: 0.78rem;">
                                            <?= date("M d, Y", strtotime($fu['follow_up_date'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-x fs-4 d-block mb-1 opacity-50"></i>
                                            <span data-i18n="no_followups">No upcoming follow-ups scheduled</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($followup_rows)): ?>
                        <div class="mt-auto pt-2 border-top">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                <span data-i18n="<?= $followup_total == 1 ? 'followup_footer_one' : 'followup_footer_many'; ?>" data-vars="<?= vars_attr(['n' => (string)$followup_total]); ?>"><?= $followup_total; ?> patient<?= $followup_total != 1 ? 's' : ''; ?> scheduled</span>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Sensor Status Modal -->
<div class="modal fade" id="sensorModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title fw-bold">
                    <i class="bi bi-cpu me-2"></i> <span data-i18n="sensor_title">Sensor Status</span>
                </h4>
                <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div><i class="bi bi-rulers me-2 text-primary"></i> TF-Luna Height Sensor</div>
                    <span class="badge bg-success" data-i18n="status_connected">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div><i class="bi bi-speedometer2 me-2 text-primary"></i> HX711 Weight Sensor</div>
                    <span class="badge bg-success" data-i18n="status_connected">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div><i class="bi bi-thermometer-half me-2 text-primary"></i> MLX90614 Temperature Sensor</div>
                    <span class="badge bg-success" data-i18n="status_connected">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div><i class="bi bi-heart-pulse me-2 text-danger"></i> MAX30102 Heart Rate Sensor</div>
                    <span class="badge bg-danger" data-i18n="status_disconnected">Disconnected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div><i class="bi bi-activity me-2 text-primary"></i> Blood Pressure Monitor</div>
                    <span class="badge bg-success" data-i18n="status_connected">Connected</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        },
        set(key, value) {
            try { localStorage.setItem(key, value); } catch (e) {}
        }
    };

    // ---------------------------------------------------------------
    // 1. Theme / Dark Mode
    // ---------------------------------------------------------------
    function applyTheme(isDark) {
        document.body.classList.toggle("dark-mode", isDark);
        document.documentElement.classList.toggle("dark-mode", isDark);
    }
    applyTheme(store.get("theme") === "dark");

    // Replace the button with a clone so only ONE click handler exists
    // (prevents theme.js and this script from toggling twice and cancelling out)
    let darkBtn = document.getElementById("darkModeToggle");
    if (darkBtn) {
        const fresh = darkBtn.cloneNode(true);
        darkBtn.parentNode.replaceChild(fresh, darkBtn);
        darkBtn = fresh;
        darkBtn.addEventListener("click", function () {
            const isDark = !document.documentElement.classList.contains("dark-mode");
            applyTheme(isDark);
            store.set("theme", isDark ? "dark" : "light");
        });
    }

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
            nav_records_history: "Kasaysayan ng Rekord",
            nav_reports: "Mga Ulat",
            section_system: "Hardware at Sistema",
            nav_admin: "Administrasyon",
            nav_user_mgmt: "Pamamahala ng Gumagamit",
            nav_settings: "Mga Setting",
            nav_logout: "Mag-logout",
            header_greeting: "Magandang Araw, Admin",
            header_desc: "Pangkalahatang-ideya ng estado ng sistema.",
            sensors_status: "Katayuan ng mga Sensor",
            dark_mode_title: "Dark Mode",
            avg_health_title: "Gitarang Kalusugan",
            avg_health_desc: "Karaniwang naitalang sukat ng vital signs",
            live_data: "Live na Datos",
            tbl_measurement: "Sukat",
            tbl_average: "Average",
            tbl_unit: "Yunit",
            lbl_height: "Taas",
            lbl_weight: "Timbang",
            lbl_temp: "Temperatura",
            lbl_heart: "Bilis ng Puso",
            lbl_bp: "Presyon ng Dugo",
            sub_height: "Taas ng katawan",
            sub_weight: "Timbang ng katawan",
            sub_temp: "Temperatura ng katawan",
            sub_heart: "Pulso",
            sub_spo2: "Saturasyon ng oxygen",
            sub_bp: "Karaniwang BP",
            based_on: "Batay sa mga naitalang sukat",
            title_new_patients: "Bagong Pasyente",
            badge_today: "Ngayon",
            new_added_one: "bagong pasyente ang naidagdag ngayon",
            new_added_many: "bagong pasyente ang naidagdag ngayon",
            total_patients_lbl: "Kabuuang Pasyente",
            previous_record: "Nakaraang Rekord",
            title_patients_overview: "Pangkalahatang-ideya ng Pasyente",
            gender_ratio: "Ratio ng Kasarian",
            col_gender: "Kasarian",
            col_count: "Bilang",
            col_ratio: "Ratio",
            male: "Lalaki",
            female: "Babae",
            legend_male: "Lalaki ({p}%)",
            legend_female: "Babae ({p}%)",
            title_services: "Kategorya ng Serbisyo",
            svc_vital: "Vital Screening",
            svc_prenatal: "Prenatal",
            svc_family: "Family Planning",
            title_recent_patients: "Kasalukuyang Listahan ng Pasyente",
            recent_sub: "Ipinapakita ang mga pinakabagong nakarehistrong pasyente",
            ph_search: "Maghanap ng pangalan ng pasyente...",
            col_no: "Blg",
            col_name: "Pangalan",
            col_checkup: "Petsa ng Check-up",
            title_followup: "Iskedyul ng Follow-up",
            followup_total: "Kabuuan: {n}",
            followup_sub: "Mga naka-iskedyul na pasyente",
            col_patient: "Pasyente",
            col_service: "Serbisyo",
            col_date: "Petsa",
            badge_vital: "Vital",
            badge_prenatal: "Prenatal",
            badge_family: "Family",
            no_followups: "Walang paparating na follow-up",
            followup_footer_one: "{n} pasyente ang naka-iskedyul",
            followup_footer_many: "{n} pasyente ang naka-iskedyul",
            sensor_title: "Katayuan ng Sensor",
            status_connected: "Nakakonekta",
            status_disconnected: "Hindi Nakakonekta"
        },
        ceb: {
            section_main: "Pangunahing",
            nav_dashboard: "Dashboard",
            section_clinical: "Mga Serbisyong Klinikal",
            nav_patient_mgmt: "Pagdumala sa Pasyente",
            nav_all_patients: "Tanan nga Serbisyong Pasyente",
            nav_add_patient: "Idugang ang Pasyente",
            nav_patient_records: "Mga Rekord sa Pasyente",
            nav_records_history: "Kasaysayan sa Rekord",
            nav_reports: "Mga Report",
            section_system: "Hardware ug Sistema",
            nav_admin: "Administrasyon",
            nav_user_mgmt: "Pagdumala sa Paggamit",
            nav_settings: "Mga Setting",
            nav_logout: "Mo-logout",
            header_greeting: "Maayong Adlaw, Admin",
            header_desc: "Kinatibuk-ang pagtan-aw sa estado sa sistema.",
            sensors_status: "Kahimtang sa mga Sensor",
            dark_mode_title: "Dark Mode",
            avg_health_title: "Kasagarang Panglawas",
            avg_health_desc: "Kasagarang nahitala nga vital signs",
            live_data: "Live nga Datos",
            tbl_measurement: "Sukat",
            tbl_average: "Average",
            tbl_unit: "Yunit",
            lbl_height: "Gitas-on",
            lbl_weight: "Timbang",
            lbl_temp: "Temperatura",
            lbl_heart: "Kusog sa Kasingkasing",
            lbl_bp: "Presyon sa Dugo",
            sub_height: "Gitas-on sa lawas",
            sub_weight: "Timbang sa lawas",
            sub_temp: "Temperatura sa lawas",
            sub_heart: "Pulso",
            sub_spo2: "Saturasyon sa oxygen",
            sub_bp: "Kasagarang BP",
            based_on: "Gibase sa mga nahitala nga sukat",
            title_new_patients: "Bag-ong Pasyente",
            badge_today: "Karon",
            new_added_one: "bag-ong pasyente ang nadugang karon",
            new_added_many: "bag-ong pasyente ang nadugang karon",
            total_patients_lbl: "Total nga Pasyente",
            previous_record: "Miaging Rekord",
            title_patients_overview: "Kinatibuk-ang Pasyente",
            gender_ratio: "Ratio sa Gender",
            col_gender: "Gender",
            col_count: "Ihap",
            col_ratio: "Ratio",
            male: "Lalaki",
            female: "Babaye",
            legend_male: "Lalaki ({p}%)",
            legend_female: "Babaye ({p}%)",
            title_services: "Mga Kategorya sa Serbisyo",
            svc_vital: "Vital Screening",
            svc_prenatal: "Prenatal",
            svc_family: "Family Planning",
            title_recent_patients: "Bag-ong Listahan sa Pasyente",
            recent_sub: "Gipakita ang pinakabag-ong narehistrong pasyente",
            ph_search: "Pangita og ngalan sa pasyente...",
            col_no: "Blg",
            col_name: "Ngalan",
            col_checkup: "Petsa sa Check-up",
            title_followup: "Iskedyul sa Follow-up",
            followup_total: "Total: {n}",
            followup_sub: "Mga naka-iskedyul nga pasyente",
            col_patient: "Pasyente",
            col_service: "Serbisyo",
            col_date: "Petsa",
            badge_vital: "Vital",
            badge_prenatal: "Prenatal",
            badge_family: "Family",
            no_followups: "Walay moabot nga follow-up",
            followup_footer_one: "{n} ka pasyente ang naka-iskedyul",
            followup_footer_many: "{n} ka pasyente ang naka-iskedyul",
            sensor_title: "Kahimtang sa Sensor",
            status_connected: "Konektado",
            status_disconnected: "Dili Konektado"
        }
    };

    const lang = store.get("language", "en");
    const dict = i18n[lang] || null;

    // Exposed so the chart (below) can translate its labels
    window.vcT = function (key, fallback) {
        return (dict && dict[key]) ? dict[key] : fallback;
    };

    if (dict) {
        document.documentElement.lang = lang;

        document.querySelectorAll("[data-i18n]").forEach(el => {
            let text = dict[el.getAttribute("data-i18n")];
            if (!text) return;

            // Fill {placeholders} from data-vars (counts, percentages)
            if (el.dataset.vars) {
                try {
                    const vars = JSON.parse(el.dataset.vars);
                    text = text.replace(/\{(\w+)\}/g, (m, k) => (k in vars ? vars[k] : m));
                } catch (e) {}
            }
            el.textContent = text;
        });

        document.querySelectorAll("[data-i18n-placeholder]").forEach(el => {
            const t = dict[el.getAttribute("data-i18n-placeholder")];
            if (t) el.placeholder = t;
        });
    }

    // ---------------------------------------------------------------
    // 5. Date & Time Format (live clock in the date chip)
    // ---------------------------------------------------------------
    const use12h = store.get("dateTimeFormat", "24h") === "12h";
    const clockEl = document.getElementById("liveClock");

    function updateClock() {
        if (!clockEl) return;
        const opts = { hour: "2-digit", minute: "2-digit", second: "2-digit", timeZone: "Asia/Manila" };
        if (use12h) { opts.hour12 = true; } else { opts.hourCycle = "h23"; }
        clockEl.textContent = "• " + new Date().toLocaleTimeString("en-US", opts);
    }
    updateClock();
    setInterval(updateClock, 1000);

})();
</script>

<script>
const sensorModal = new bootstrap.Modal(document.getElementById('sensorModal'));

document.getElementById('openSensorStatus').addEventListener('click', function(e){
    e.preventDefault();
    sensorModal.show();
});

// Live Search Filter for Patient List
document.getElementById('patientSearchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#patientTable tbody tr');

    rows.forEach(row => {
        const name = row.querySelector('.patient-name').textContent.toLowerCase();
        row.style.display = name.includes(filter) ? '' : 'none';
    });
});
</script>

<script>
    const ctx = document.getElementById('serviceChart');

    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    window.vcT('svc_vital', 'Vital Screening'),
                    window.vcT('svc_prenatal', 'Prenatal'),
                    window.vcT('svc_family', 'Family Planning')
                ],
                datasets: [{
                    data: [
                        <?= (int)$vital_count ?>,
                        <?= (int)$prenatal_count ?>,
                        <?= (int)$family_count ?>
                    ],
                    backgroundColor: [
                        '#0d6efd',
                        '#dc3545',
                        '#ffc107'
                    ]
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                animation: {
                    duration: 3000,
                    easing: 'easeOutQuart',
                    animateRotate: true,
                    animateScale: true
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
</script>

</body>
</html>
<?php
if ($conn) {
    mysqli_close($conn);
}
?>