<?php
session_start();

// Set local timezone for Philippines (Leyte/Local time)
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "vitalcore_db");

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

/* =========================
   PATIENT STATISTICS & GENDER OVERVIEW
========================= */
// 1. Total Patients Count
$count_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='patient'");
$count_r = mysqli_fetch_assoc($count_q);
$patient_count = $count_r['total'] ?? 0;

// 2. New Patients Added Today
$today_q = mysqli_query($conn, "SELECT COUNT(*) AS today_total FROM users WHERE role='patient' AND DATE(created_at) = CURDATE()");
$today_r = mysqli_fetch_assoc($today_q);
$today_new_patients = $today_r['today_total'] ?? 0;

// 3. Yesterday's New Patients (Previous Record)
$yesterday_q = mysqli_query($conn, "SELECT COUNT(*) AS yesterday_total FROM users WHERE role='patient' AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY");
$yesterday_r = mysqli_fetch_assoc($yesterday_q);
$yesterday_new_patients = $yesterday_r['yesterday_total'] ?? 0;

// 4. Male vs Female Gender Ratio Breakdown
$gender_q = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN gender='Male' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN gender='Female' THEN 1 ELSE 0 END) AS female_count,
        COUNT(*) AS total_count
    FROM users 
    WHERE role='patient'
");
$gender_r = mysqli_fetch_assoc($gender_q);

$male_count = $gender_r['male_count'] ?? 0;
$female_count = $gender_r['female_count'] ?? 0;
$total_gender_count = $gender_r['total_count'] ?? 0;

// Percentage calculations
$male_percent = $total_gender_count > 0 ? round(($male_count / $total_gender_count) * 100, 1) : 0;
$female_percent = $total_gender_count > 0 ? round(($female_count / $total_gender_count) * 100, 1) : 0;

// Formatted Date Strings
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
$patients = mysqli_query($conn, $sql_patients);

if (!$patients) {
    die("Query Failed: " . mysqli_error($conn));
}

/* SERVICE COUNTS */

$vital_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM measurements
     WHERE service_type='vital'"
))['total'];

$prenatal_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM measurements
     WHERE service_type='prenatal'"
))['total'];

$immunization_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM measurements
     WHERE service_type='immunization'"
))['total'];

$family_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM measurements
     WHERE service_type='family'"
))['total'];

/* =========================
   AVERAGE HEALTH MEASUREMENTS
========================= */

$average_health_q = mysqli_query($conn, "
    SELECT
        AVG(height) AS avg_height,
        AVG(weight) AS avg_weight,
        AVG(temperature) AS avg_temperature,
        AVG(heart_rate) AS avg_heart_rate,
        AVG(spo2) AS avg_spo2,
        AVG(systolic) AS avg_systolic,
        AVG(diastolic) AS avg_diastolic
    FROM measurements
");

$average_health = mysqli_fetch_assoc($average_health_q);

$avg_height = $average_health['avg_height'] ?? 0;
$avg_weight = $average_health['avg_weight'] ?? 0;
$avg_temperature = $average_health['avg_temperature'] ?? 0;
$avg_heart_rate = $average_health['avg_heart_rate'] ?? 0;
$avg_spo2 = $average_health['avg_spo2'] ?? 0;
$avg_systolic = $average_health['avg_systolic'] ?? 0;
$avg_diastolic = $average_health['avg_diastolic'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en" translate="no">
<head>
    <script>
    if (localStorage.getItem("theme") === "dark") {
        document.documentElement.classList.add("dark-mode");
    }
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitalCore Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/theme.css">
</head>

<body>

<div class="container-fluid">
    <div class="row ">
        <!-- Sidebar Navigation -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
            <div>
                <div class="logo-section">
                    <img src="../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

                <ul class="nav flex-column">

                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-grid-1x2-fill me-2"></i>
                            Dashboard
                        </a>
                    </li>

                    <!-- Patients -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                           data-bs-toggle="collapse"
                           href="#patientsMenu"
                           role="button"
                           aria-expanded="false"
                           aria-controls="patientsMenu">

                            <span>
                                <i class="bi bi-people-fill me-2"></i>
                                Patients
                            </span>

                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="patientsMenu">
                            <ul class="sidebar-submenu">

                                <li>
                                    <a href="patient-list.php" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        All Patients
                                    </a>
                                </li>

                                <li>
                                    <a href="admin-dashboard.php" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Add Patient
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>

                </ul>

                <!-- CLINIC SERVICES <div class="sidebar-heading">CLINIC</div> -->
                <ul class="nav flex-column">

                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                           data-bs-toggle="collapse"
                           href="#clinicServicesMenu"
                           role="button"
                           aria-expanded="false"
                           aria-controls="clinicServicesMenu">

                            <span>
                                <i class="bi bi-hospital-fill me-2"></i>
                                Clinic Services
                            </span>

                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="clinicServicesMenu">
                            <ul class="sidebar-submenu">

                                <li>
                                    <a href="Service/vital-screening.php" class="sidebar-submenu-link">
                                        <i class="bi bi-clipboard2-pulse-fill text-warning me-2"></i>
                                        <span class="submenu-dot"></span>
                                        Vital Screening
                                    </a>
                                </li>

                                <li>
                                    <a href="Service/prenatal.php" class="sidebar-submenu-link">
                                        <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                                        <span class="submenu-dot"></span>
                                        Prenatal Check-up
                                    </a>
                                </li>

                                <li>
                                    <a href="Service/child-immunization.php" class="sidebar-submenu-link">
                                        <i class="bi bi-shield-check text-success me-2"></i>
                                        <span class="submenu-dot"></span>
                                        Child Immunization
                                    </a>
                                </li>

                                <li>
                                    <a href="Service/family-planning.php" class="sidebar-submenu-link">
                                         <i class="bi bi-people-fill text-primary me-2"></i>
                                        <span class="submenu-dot"></span>
                                        Family Planning
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>

                </ul>

                <!-- RECORDS -->
                <ul class="nav flex-column">

                    <li class="nav-item">

                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                        data-bs-toggle="collapse"
                        href="#recordsMenu"
                        role="button">

                            <span>
                                <i class="bi bi-folder2-open me-2"></i>
                                Patient Records
                            </span>

                            <i class="bi bi-chevron-down collapse-chevron"></i>

                        </a>

                        <div class="collapse" id="recordsMenu">

                            <ul class="sidebar-submenu">

                                <li>
                                    <a href="logs/checkup-records.php"
                                    class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Checkup Records
                                    </a>
                                </li>

                                <li>
                                    <a href="logs/patient-history.php"
                                    class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Patient History
                                    </a>
                                </li>

                                <li>
                                    <a href="logs/service-records.php"
                                    class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Service Records
                                    </a>
                                </li>

                                <li>
                                    <a href="logs/reports.php"
                                    class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Reports
                                    </a>
                                </li>

                            </ul>

                        </div>

                    </li>

                </ul>

                <!-- KIOSK & DEVICES <div class="sidebar-heading">KIOSK &amp; DEVICES</div> -->
                <ul class="nav flex-column">

                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                           data-bs-toggle="collapse"
                           href="#kioskMenu"
                           role="button"
                           aria-expanded="false"
                           aria-controls="kioskMenu">

                            <span>
                                <i class="bi bi-display me-2"></i>
                                KioskManagement
                            </span>

                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="kioskMenu">
                            <ul class="sidebar-submenu">

                                <li>
                                    <a href="#" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Kiosk Monitor
                                    </a>
                                </li>

                                <li>
                                    <a href="#" id="sidebarSensorStatus" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Sensor Status
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>

                </ul>

                <!-- ADMINISTRATION -->
                <ul class="nav flex-column">

                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                           data-bs-toggle="collapse"
                           href="#adminMenu"
                           role="button"
                           aria-expanded="false"
                           aria-controls="adminMenu">

                            <span>
                                <i class="bi bi-shield-lock-fill me-2"></i>
                                Administration
                            </span>

                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="adminMenu">
                            <ul class="sidebar-submenu">

                                <li>
                                    <a href="#" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        User Management
                                    </a>
                                </li>

                                <li>
                                    <a href="#" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Settings
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>

                </ul>

            </div>

            <!-- Logout -->
            <div class="pt-4 px-2 border-top">
                <a href="../login.php"
                   class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left"></i>
                    Log out
                </a>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <!-- TOP HEADER BAR WITH DYNAMIC DATE -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 fade-up">
               <div>
                    <h3 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-hospital-fill me-2 text-primary"></i>
                        Good Day, Admin
                    </h3>
                    <p class="text-muted small mb-0">
                        System status overview and clinical intake telemetry.
                    </p>
                </div>

                <div class="top-actions m-0">
                    <!-- DYNAMIC CURRENT DATE CHIP -->
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill fw-semibold shadow-sm" style="font-size: 0.85rem;">
                        <i class="bi bi-calendar-event me-1 text-primary"></i> <?= date("l, M d, Y"); ?>
                    </span>
                    <a href="dashboard.php" class="status-pill active text-decoration-none">
                        <span class="status-dot green"></span>
                        <span>Kiosk Online</span>
                    </a>
                    <a href="#" id="openSensorStatus" class="status-pill warning text-decoration-none">
                        <i class="bi bi-exclamation-triangle-fill warning-icon"></i>
                        <span>Sensors Status</span>
                    </a>
                     <button id="darkModeToggle" class="darkmode-btn">
                        <i class="bi bi-moon-stars-fill"></i>
                        <span>Dark Mode</span>
                    </button>
                </div>
            </div>

            <!-- Dashboard Overview Row -->
            <div class="row mb-4">
                <!-- Health Analytics (Left Side) -->
                <!-- HEALTH ANALYTICS -->
<div class="col-xl-5 col-lg-5 mb-3 mb-lg-0">

    <div class="card analytics-card h-100 p-4 fade-up fade-delay-1">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-3">

            <div>
                <h5 class="card-title fw-bold text-dark mb-1">
                    <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                    Average Health
                </h5>

                <small class="text-muted">
                    Average recorded vital measurements
                </small>
            </div>

            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                <i class="bi bi-activity me-1"></i>
                Live Data
            </span>

        </div>


        <!-- AVERAGE HEALTH TABLE -->
        <div class="table-responsive">

            <table class="table table-borderless align-middle average-health-table mb-0">

                <thead>
                    <tr>
                        <th>Measurement</th>
                        <th class="text-end">Average</th>
                        <th class="text-end">Unit</th>
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
                                    <strong>Height</strong>
                                    <small>Body height</small>
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
                                    <strong>Weight</strong>
                                    <small>Body weight</small>
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
                                    <strong>Temperature</strong>
                                    <small>Body temperature</small>
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
                                    <strong>Heart Rate</strong>
                                    <small>Pulse rate</small>
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
                                    <small>Oxygen saturation</small>
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
                                    <strong>Blood Pressure</strong>
                                    <small>Average BP</small>
                                </div>
                            </div>
                        </td>

                        <td class="text-end">
                            <strong class="health-value">
                                <?php if ($avg_systolic > 0 || $avg_diastolic > 0): ?>
                                    <?= number_format($avg_systolic, 0) ?>
                                    /
                                    <?= number_format($avg_diastolic, 0) ?>
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
                Based on recorded measurements
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
                            <h5 class="card-title fw-bold text-dark m-0">New Patients</h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fw-semibold" style="font-size: 0.75rem;">
                                Today
                            </span>
                        </div>
                        
                        <div style="position: relative; height:150px; width:100%" class="d-flex flex-column justify-content-between">
                            <!-- Big Highlight Number -->
                            <div class="d-flex align-items-baseline gap-2 mt-1">
                               <span class="display-3 fw-bolder text-primary lh-1 stat-number">
                                    <?= number_format($today_new_patients); ?>
                                </span>
                                <span class="text-muted fw-semibold fs-6">new patient<?= $today_new_patients != 1 ? 's' : ''; ?> added today</span>
                            </div>

                            <!-- Sub-Metrics Grid -->
                            <div class="row g-2 border-top pt-2 mt-auto">
                                <div class="col-6">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;">Total Patients</span>
                                       <span class="fw-bold fs-5 text-dark stat-number"><?= number_format($patient_count); ?></span>
                                        <span class="text-secondary opacity-75" style="font-size: 0.75rem;"><?= $current_date_str; ?></span>
                                    </div>
                                </div>

                                <div class="col-6 border-start ps-3">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;">Previous Record</span>
                                        <span class="fw-bold fs-5 text-dark stat-number"><?= number_format($yesterday_new_patients); ?></span>
                                        <span class="text-secondary opacity-75" style="font-size: 0.75rem;"><?= $yesterday_date_str; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PATIENTS OVERVIEW CARD (MALE VS FEMALE DATA TABLE) -->
                   <div class="card analytics-card p-4 fade-up fade-delay-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold text-dark m-0">Patients Overview</h5>
                            <span class="badge bg-light text-secondary border px-2 py-1 rounded-pill fw-semibold" style="font-size: 0.72rem;">
                                Gender Ratio
                            </span>
                        </div>
                        
                        <div style="position: relative; height:150px; width:100%" class="d-flex flex-column justify-content-between">
                            <!-- Male vs Female Data Table -->
                            <div class="table-responsive mt-1">
                                <table class="table table-borderless table-sm align-middle mb-1" style="font-size: 0.85rem;">
                                    <thead>
                                        <tr class="text-muted border-bottom" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <th class="ps-0 py-1">Gender</th>
                                            <th class="text-center py-1">Count</th>
                                            <th class="text-end py-1">Ratio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="ps-0 py-1 fw-semibold text-dark">
                                                <i class="bi bi-gender-male text-primary me-1"></i> Male
                                            </td>
                                            <td class="text-center py-1 fw-bold stat-number"><?= number_format($male_count); ?></td>
                                            <td class="text-end py-1">
                                                <span class="badge bg-primary-subtle text-primary fw-semibold"><?= $male_percent; ?>%</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-1 fw-semibold text-dark">
                                                <i class="bi bi-gender-female text-danger me-1"></i> Female
                                            </td>
                                            <td class="text-center py-1 fw-bold stat-number"><?= number_format($female_count); ?></td>
                                            <td class="text-end py-1">
                                                <span class="badge bg-danger-subtle text-danger fw-semibold"><?= $female_percent; ?>%</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Combined Gender Distribution Bar -->
                            <div class="mt-auto pt-2 border-top">
                                <div class="d-flex justify-content-between text-muted mb-1" style="font-size: 0.72rem;">
                                    <span><i class="bi bi-circle-fill text-primary me-1" style="font-size: 0.5rem;"></i> Male (<?= $male_percent; ?>%)</span>
                                    <span><i class="bi bi-circle-fill text-danger me-1" style="font-size: 0.5rem;"></i> Female (<?= $female_percent; ?>%)</span>
                                </div>
                               <div class="progress gender-progress">
                                    <div class="progress-bar bg-primary male-bar"
                                        style="width: <?= $male_percent; ?>%">
                                    </div>

                                    <div class="progress-bar bg-danger female-bar"
                                        style="width: <?= $female_percent; ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>

                <!--(Right Side) -->
          <div class="col-xl-3 col-lg-3">
                <div class="card analytics-card h-100 p-4 fade-up fade-delay-4">

                    <h5 class="fw-bold mb-3">
                        Service Categories
                    </h5>

                    <div style="height:220px">
                        <canvas id="serviceChart"></canvas>
                    </div>

                    <div class="mt-3">

                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-circle-fill text-primary me-1"></i> Vital Screening</span>
                            <strong class="stat-number"><?= $vital_count ?></strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-circle-fill text-danger me-1"></i> Prenatal</span>
                           <strong class="stat-number"><?= $prenatal_count ?></strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-circle-fill text-success me-1"></i> Immunization</span>
                            <strong class="stat-number"><?= $immunization_count ?></strong>
                        </div>

                        <div class="d-flex justify-content-between">
                            <span><i class="bi bi-circle-fill text-warning me-1"></i> Family Planning</span>
                            <strong class="stat-number"><?= $family_count ?></strong>
                        </div>

                    </div>

                </div>
            </div>        
            </div>
            
            <!-- Patient List Row -->
            <div class="row">
                <div class="col-12">
                   <div class="card patient-card p-4 fade-up fade-delay-5">
                        <!-- Header with Search & Add Patient Button -->
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                            <div>
                                <h5 class="fw-bold text-dark m-0">Recent Patient List</h5>
                                <small class="text-muted">Showing latest registered patients</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="position-relative">
                                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                                    <input type="text" id="patientSearchInput" class="form-control rounded-pill ps-5 bg-light border-light-subtle" placeholder="Search patient name..." style="font-size: 0.85rem; width: 230px;">
                                </div>
                                <a href="admin-dashboard.php" class="btn btn-primary rounded-pill px-3 py-1 fw-semibold d-flex align-items-center gap-1" style="font-size: 0.85rem;">
                                    <i class="bi bi-person-plus-fill"></i> Add Patient
                                </a>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="patientTable">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Name</th>
                                        <th>Date Of Checkup</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $no = 1;
                                while ($row = mysqli_fetch_assoc($patients)) {
                                    $disease = htmlspecialchars($row['disease']);
                                    $date = isset($row['created_at']) ? date("Y-m-d", strtotime($row['created_at'])) : date("Y-m-d");
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td class="fw-semibold patient-name">
                                            <?= htmlspecialchars($row['fullname']); ?>
                                        </td>
                                        <td><?= $date; ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
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
                    <i class="bi bi-cpu me-2"></i> Sensor Status
                </h4>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div><i class="bi bi-rulers me-2 text-primary"></i> TF-Luna Height Sensor</div>
                    <span class="badge bg-success">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div><i class="bi bi-speedometer2 me-2 text-primary"></i> HX711 Weight Sensor</div>
                    <span class="badge bg-success">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div><i class="bi bi-thermometer-half me-2 text-primary"></i> MLX90614 Temperature Sensor</div>
                    <span class="badge bg-success">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div><i class="bi bi-heart-pulse me-2 text-danger"></i> MAX30102 Heart Rate Sensor</div>
                    <span class="badge bg-danger">Disconnected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div><i class="bi bi-activity me-2 text-primary"></i> Blood Pressure Monitor</div>
                    <span class="badge bg-success">Connected</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sensorModal = new bootstrap.Modal(document.getElementById('sensorModal'));

document.getElementById('openSensorStatus').addEventListener('click', function(e){
    e.preventDefault();
    sensorModal.show();
});

const sidebarSensorStatus = document.getElementById('sidebarSensorStatus');

if (sidebarSensorStatus) {
    sidebarSensorStatus.addEventListener('click', function(e){
        e.preventDefault();
        sensorModal.show();
    });
}

// Live Search Filter for Patient List
document.getElementById('patientSearchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#patientTable tbody tr');
    
    rows.forEach(row => {
        let name = row.querySelector('.patient-name').textContent.toLowerCase();
        row.style.display = name.includes(filter) ? '' : 'none';
    });
});
</script>

<script src="../assets/js/theme.js"></script>

<script>
    const ctx = document.getElementById('serviceChart');

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                'Vital Screening',
                'Prenatal',
                'Immunization',
                'Family Planning'
            ],
            datasets: [{
                data: [
                    <?= $vital_count ?>,
                    <?= $prenatal_count ?>,
                    <?= $immunization_count ?>,
                    <?= $family_count ?>
                ],
                backgroundColor: [
                    '#0d6efd',
                    '#dc3545',
                    '#198754',
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
</script>

</body>
</html>
<?php mysqli_close($conn); ?>