<?php
// Set accurate local Philippine timezone
date_default_timezone_set('Asia/Manila');

/** @var mysqli $conn */
require_once('../db_conn.php');

/* Helper: JSON-encode template variables for data-vars attributes */
function vars_attr(array $vars): string {
    return htmlspecialchars(json_encode($vars, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

/* ==========================================================
   OFFICIAL 35 BARANGAYS OF VILLABA, LEYTE
========================================================== */
$all_barangays = [
    "Abijao", "Balite", "Bugabuga", "Cabungahan", "Cabunga-an",
    "Cagnocot", "Cahigan", "Calbugos", "Camporog", "Capinyahan",
    "Casili-on", "Catagbacan", "Fatima", "Hibulangan", "Hinabuyan",
    "Iligay", "Jalas", "Jordan", "Libagong", "New Balanac",
    "Payao", "Poblacion Norte", "Poblacion Sur", "Sambulawan", "San Francisco",
    "Silad", "Sulpa", "Tabunok", "Tagbubunga", "Tinghub",
    "Bangcal", "Canquiason", "San Vicente", "Santa Cruz", "Suba"
];

/* =========================
   FILTER SETTINGS (sanitized - nothing from $_GET reaches SQL raw)
========================= */

$filter_type = $_GET['type'] ?? 'day';
if (!in_array($filter_type, ['day', 'month', 'year'], true)) {
    $filter_type = 'day';
}

$raw_date = trim((string)($_GET['date'] ?? ''));
if (preg_match('/^\d{4}$/', $raw_date)) {
    $raw_date .= '-01-01';            // "2026" from the year dropdown
}
$filter_ts = ($raw_date !== '') ? strtotime($raw_date) : false;
if ($filter_ts === false) {
    $filter_ts = time();
}

// Always a clean Y-m-d, whatever the previous filter type was
$filter_date  = date('Y-m-d', $filter_ts);
$filter_month = (int)date('n', $filter_ts);
$filter_year  = (int)date('Y', $filter_ts);

if ($filter_type == "day") {

    $whereUsers = "DATE(created_at) = '$filter_date'";
    $whereMeasurements = "DATE(created_at) = '$filter_date'";

    $filter_label = date('F d, Y', $filter_ts);
    $report_scope_title = "Daily Clinical Summary Report";
    $period_type_label = "Day";
}
elseif ($filter_type == "month") {

    $whereUsers = "MONTH(created_at) = $filter_month AND YEAR(created_at) = $filter_year";
    $whereMeasurements = "MONTH(created_at) = $filter_month AND YEAR(created_at) = $filter_year";

    $filter_label = date('F Y', $filter_ts);
    $report_scope_title = "Monthly Clinical Summary Report";
    $period_type_label = "Month";
}
else {

    $whereUsers = "YEAR(created_at) = $filter_year";
    $whereMeasurements = "YEAR(created_at) = $filter_year";

    $filter_label = "Year " . $filter_year;
    $report_scope_title = "Annual Clinical Summary Report";
    $period_type_label = "Year";
}

/* TOTAL FILTERED PATIENTS */
$count_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='patient' AND $whereUsers");
$count_r = mysqli_fetch_assoc($count_q);
$filtered_patient_count = $count_r['total'] ?? 0;

/* OVERALL TOTAL PATIENTS (ALL TIME) */
$all_patients_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='patient'");
$all_patients_count = mysqli_fetch_assoc($all_patients_q)['total'] ?? 0;

/* TOTAL MEASUREMENTS */
$measurement_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM measurements WHERE $whereMeasurements");
$measurement_r = mysqli_fetch_assoc($measurement_q);
$totalMeasurements = $measurement_r['total'] ?? 0;

/* GENDER DISTRIBUTION */
$gender_q = mysqli_query(
    $conn,
    "SELECT
        SUM(CASE WHEN gender='Male' THEN 1 ELSE 0 END) male_count,
        SUM(CASE WHEN gender='Female' THEN 1 ELSE 0 END) female_count,
        COUNT(*) total_count
     FROM users
     WHERE role='patient'
     AND $whereUsers"
);
$gender_r = mysqli_fetch_assoc($gender_q);
$male_count = $gender_r['male_count'] ?? 0;
$female_count = $gender_r['female_count'] ?? 0;
$total_gender_count = $gender_r['total_count'] ?? 0;
$male_percent = $total_gender_count > 0 ? round(($male_count / $total_gender_count) * 100, 1) : 0;
$female_percent = $total_gender_count > 0 ? round(($female_count / $total_gender_count) * 100, 1) : 0;

/* AGE GROUP DISTRIBUTION BY GENDER */
$age_groups = [
    '0-4'   => ['label' => '0–4 yrs',   'min' => 0,  'max' => 4],
    '5-14'  => ['label' => '5–14 yrs',  'min' => 5,  'max' => 14],
    '15-24' => ['label' => '15–24 yrs', 'min' => 15, 'max' => 24],
    '25-34' => ['label' => '25–34 yrs', 'min' => 25, 'max' => 34],
    '35-44' => ['label' => '35–44 yrs', 'min' => 35, 'max' => 44],
    '45-54' => ['label' => '45–54 yrs', 'min' => 45, 'max' => 54],
    '55-64' => ['label' => '55–64 yrs', 'min' => 55, 'max' => 64],
    '65+'   => ['label' => '65+ yrs',   'min' => 65, 'max' => 999],
];

$age_ratio_data = [];
foreach ($age_groups as $key => $grp) {
    $aq = mysqli_query($conn,
        "SELECT
            SUM(CASE WHEN gender='Male'   THEN 1 ELSE 0 END) AS male_count,
            SUM(CASE WHEN gender='Female' THEN 1 ELSE 0 END) AS female_count,
            COUNT(*) AS total
         FROM users
         WHERE role='patient'
           AND age >= {$grp['min']} AND age <= {$grp['max']}
           AND $whereUsers"
    );
    $ar = mysqli_fetch_assoc($aq);
    $age_ratio_data[$key] = [
        'label'  => $grp['label'],
        'male'   => (int)($ar['male_count']   ?? 0),
        'female' => (int)($ar['female_count'] ?? 0),
        'total'  => (int)($ar['total']        ?? 0),
    ];
}
$age_ratio_max = max(array_column($age_ratio_data, 'total') ?: [1]);

/* ==================================================================
   SERVICE COUNTS (From measurements for the selected period)
================================================================== */
$vital_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM measurements
     WHERE (service_type LIKE '%vital%')
     AND $whereMeasurements"
))['total'] ?? 0;

$prenatal_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM measurements
     WHERE (service_type LIKE '%prenatal%')
     AND $whereMeasurements"
))['total'] ?? 0;

$immunization_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM measurements
     WHERE (service_type LIKE '%immuniz%')
     AND $whereMeasurements"
))['total'] ?? 0;

$family_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM measurements
     WHERE (service_type LIKE '%family%' OR service_type LIKE '%planning%')
     AND $whereMeasurements"
))['total'] ?? 0;

/* FOLLOW-UPS — deduplicated per patient */
$upcoming_schedules = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM family_planning_records
     WHERE next_schedule IS NOT NULL
       AND next_schedule >= CURDATE()"
))['total'] ?? 0;

$overdue_schedules = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM family_planning_records
     WHERE next_schedule IS NOT NULL
       AND next_schedule < CURDATE()"
))['total'] ?? 0;

// Total = only active (upcoming) schedules, NOT a raw row count
$total_schedules = $upcoming_schedules;

/* PATIENT ROSTER FOR THE FILTERED PERIOD */
$patient_roster_q = mysqli_query(
    $conn,
    "SELECT
        id,
        fullname,
        age,
        gender,
        contact_number,
        barangay,
        address,
        city_municipality,
        service_type,
        blood_type,
        DATE_FORMAT(created_at, '%b %d, %Y') AS reg_date
     FROM users
     WHERE role='patient'
     AND $whereUsers
     ORDER BY id DESC"
);
$roster_count = $patient_roster_q ? mysqli_num_rows($patient_roster_q) : 0;

/* ==========================================================
   INITIALIZE ALL 35 BARANGAYS WITH 0 COUNTS
========================================================== */
$barangay_counts = array_fill_keys($all_barangays, 0);

$brgy_q = mysqli_query($conn, "SELECT
    COALESCE(NULLIF(TRIM(barangay), ''), TRIM(address)) AS loc,
    COUNT(*) AS total
    FROM users
    WHERE role='patient' AND $whereUsers
    GROUP BY loc");

if ($brgy_q) {
    while ($b = mysqli_fetch_assoc($brgy_q)) {
        $loc = trim($b['loc'] ?? '');
        $tot = (int)$b['total'];
        $matched = false;
        foreach ($all_barangays as $brgy) {
            if (strcasecmp($brgy, $loc) === 0 || stripos($loc, $brgy) !== false) {
                $barangay_counts[$brgy] += $tot;
                $matched = true;
                break;
            }
        }
        if (!$matched && !empty($loc)) {
            $barangay_counts[$loc] = ($barangay_counts[$loc] ?? 0) + $tot;
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($report_scope_title . " - " . $filter_label); ?> - VitalCore</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 CSS (the *-subtle classes used on this page need 5.3, matching the JS bundle) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/report.css">
    <link rel="stylesheet" href="../../../css/theme.css">

    <!-- PRINT & DOCUMENT EXPORT STYLING -->
    <style>
        /* Suppress browser automatic headers & footers */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        /* On regular screen, hide the printable document template */
        .simple-print-document {
            display: none;
        }

        /* ---------- Fallback Dark Mode Styles (same approach as setting.php) ---------- */
        body.dark-mode, html.dark-mode body {
            background-color: #121824 !important;
            color: #e2e8f0 !important;
        }
        html.dark-mode .card,
        html.dark-mode .clinic-card {
            background-color: #1e293b !important;
            color: #e2e8f0 !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        html.dark-mode .card-header.bg-light {
            background-color: #172033 !important;
            color: #f8fafc !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        html.dark-mode .text-dark { color: #f8fafc !important; }
        html.dark-mode .text-muted { color: #94a3b8 !important; }
        html.dark-mode .form-select,
        html.dark-mode .form-control {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        html.dark-mode .sidebar {
            background-color: #0f172a !important;
            border-right-color: rgba(255,255,255,0.1) !important;
        }
        html.dark-mode .table {
            --bs-table-bg: transparent;
            --bs-table-color: #e2e8f0;
            --bs-table-hover-color: #f8fafc;
            --bs-table-hover-bg: rgba(255,255,255,0.05);
            --bs-table-border-color: rgba(255,255,255,0.1);
            color: #e2e8f0;
        }
        html.dark-mode .table-light {
            --bs-table-bg: #172033;
            --bs-table-color: #e2e8f0;
            --bs-table-border-color: rgba(255,255,255,0.1);
        }
        html.dark-mode tfoot tr { background: #172033 !important; }
        html.dark-mode .progress { background: #0f172a !important; }

        /* Night Light Warm Amber Filter Overlay */
        #nightLightOverlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(255, 140, 0, 0.18);
            pointer-events: none;
            z-index: 99999;
            display: none;
        }

        /* When printing or exporting to PDF */
        @media print {
            .sidebar, .dashboard-web-view, .btn-print-action, .date-pill, .no-print {
                display: none !important;
            }

            /* Printed reports stay white, regardless of Dark Mode / Night Light */
            #nightLightOverlay { display: none !important; }

            html.dark-mode, html.dark-mode body, body.dark-mode {
                background: #fff !important;
                color: #111 !important;
            }

            .simple-print-document {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 30px 40px !important;
                font-family: Arial, sans-serif !important;
                color: #111 !important;
                background: #fff !important;
            }

            body, .container-fluid, .row {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .table-bordered th, .table-bordered td {
                border: 1px solid #333 !important;
            }

            .summary-box {
                border: 1px solid #444 !important;
                background-color: #f9f9f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-footer {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

<div class="container-fluid">
    <div class="row">

        <!-- ========================================================
             1. SIDEBAR (Hidden in Print/PDF)
        ======================================================== -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between no-print">
            <div>
                <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.onerror=null; this.src='https://cdn-icons-png.flaticon.com/512/2966/2966327.png';">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

                <div class="sidebar-menu-wrapper">
                    
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_clinical">Clinical Services</small>
                    <ul class="nav flex-column mb-3">
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#patientsMenu" role="button">
                                <span>
                                    <i class="bi bi-people-fill me-2 text-primary"></i>
                                    <span data-i18n="nav_patient_mgmt">Patient Management</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse" id="patientsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1"><a href="patient-list.php" class="sidebar-submenu-link text-decoration-none"><i class="bi bi-list-ul me-2"></i> <span data-i18n="nav_all_patients_short">All Patients</span></a></li>
                                    <li class="py-1"><a href="admin-dashboard.php" class="sidebar-submenu-link text-decoration-none"><i class="bi bi-person-plus-fill me-2"></i> <span data-i18n="nav_add_patient">Add Patient</span></a></li>
                                </ul>
                            </div>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link active sidebar-collapse-link active-parent d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#recordsMenu" role="button">
                                <span>
                                    <i class="bi bi-folder2-open me-2 text-warning"></i>
                                    <span data-i18n="nav_patient_records">Patient Records</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse show" id="recordsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1"><a href="patient-history.php" class="sidebar-submenu-link text-decoration-none"><i class="bi bi-clock-history me-2"></i> <span data-i18n="nav_patient_history">Patient History</span></a></li>
                                    <li class="py-1"><a href="reports.php" class="sidebar-submenu-link active text-decoration-none"><i class="bi bi-file-earmark-bar-graph me-2"></i> <span data-i18n="nav_reports">Reports</span></a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>

                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_system">Hardware &amp; System</small>
                    <ul class="nav flex-column mb-3">
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#adminMenu" role="button">
                                <span>
                                    <i class="bi bi-shield-lock-fill me-2 text-danger"></i>
                                    <span data-i18n="nav_admin">Administration</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse" id="adminMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1"><a href="setting.php" class="sidebar-submenu-link text-decoration-none"><i class="bi bi-sliders me-2"></i> <span data-i18n="nav_settings">Settings</span></a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="logout-section pt-3 px-2 border-top border-secondary border-opacity-25">
                <a href="../../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left fs-5"></i>
                    <span data-i18n="nav_logout">Log out</span>
                </a>
            </div>
        </nav>

        <!-- ========================================================
             2. WEB VIEW (Interactive Dashboard)
        ======================================================== -->
        <main class="col-md-9 col-lg-10 p-4 dashboard-web-view">
            <!-- Header with Title & Filter -->
            <div class="dashboard-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-4 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <h1 class="h3 m-0 fw-bold" data-i18n="hub_title">Clinic Reports Hub</h1>

                        <!-- Print/Export Button -->
                        <button type="button"
                                onclick="window.print()"
                                class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 d-inline-flex align-items-center gap-2 shadow-sm btn-print-action">
                            <i class="bi bi-file-earmark-pdf-fill text-danger"></i>
                            <span class="fw-semibold" data-i18n="export_pdf" data-vars="<?= vars_attr(['label' => $filter_label]); ?>">Export <?= htmlspecialchars($filter_label); ?> as PDF</span>
                        </button>
                    </div>
                    <p class="text-muted m-0 fs-6 mt-1">
                        <span data-i18n="viewing_for">Viewing clinical data for:</span>
                        <span class="badge bg-primary-subtle text-primary fw-bold px-2 py-1"><?= htmlspecialchars($filter_label); ?></span>
                        <span class="small ms-2" id="liveClock"></span>
                    </p>
                </div>

                <!-- Date Filter Selector Form -->
                <div class="date-pill">
                    <i class="bi bi-calendar3 text-accent-primary"></i>
                    <form method="GET" class="row g-2 align-items-center m-0">
                        <!-- Filter Type Selector -->
                        <div class="col-auto">
                            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="day" <?= $filter_type=='day'?'selected':'' ?> data-i18n="opt_day">Day</option>
                                <option value="month" <?= $filter_type=='month'?'selected':'' ?> data-i18n="opt_month">Month</option>
                                <option value="year" <?= $filter_type=='year'?'selected':'' ?> data-i18n="opt_year">Year</option>
                            </select>
                        </div>

                        <!-- Date / Month / Year Selector -->
                        <div class="col-auto">
                            <?php if ($filter_type == 'year'): ?>
                                <select name="date" class="form-select form-select-sm">
                                    <?php
                                    $current_year = (int)date('Y');
                                    for ($y = $current_year + 24; $y >= 2025; $y--):
                                    ?>
                                        <option value="<?= $y; ?>" <?= ($filter_year == $y) ? 'selected' : ''; ?>>
                                            <?= $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>

                            <?php elseif ($filter_type == 'month'): ?>
                                <input type="month"
                                    name="date"
                                    value="<?= htmlspecialchars(date('Y-m', $filter_ts)); ?>"
                                    class="form-control form-control-sm">

                            <?php else: ?>
                                <input type="date"
                                    name="date"
                                    value="<?= htmlspecialchars($filter_date); ?>"
                                    class="form-control form-control-sm">
                            <?php endif; ?>
                        </div>

                        <div class="col-auto">
                            <button class="btn btn-primary btn-sm px-3" data-i18n="apply_filter">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Dashboard Summary Metrics -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card clinic-card kpi-card kpi-total-patients h-100">
                        <div class="clinic-card-body d-flex align-items-center gap-3">
                            <div class="metric-pill metric-pill-primary"><i class="bi bi-people-fill"></i></div>
                            <div>
                                <span class="stat-label" data-i18n="kpi_patients_period">Patients in Period</span>
                                <div class="stat-value mt-1"><?= number_format($filtered_patient_count); ?></div>
                                <small class="text-muted"><?= htmlspecialchars($filter_label); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card clinic-card kpi-card kpi-new-today h-100">
                        <div class="clinic-card-body d-flex align-items-center gap-3">
                            <div class="metric-pill metric-pill-success"><i class="bi bi-calendar2-check-fill"></i></div>
                            <div>
                                <span class="stat-label" data-i18n="kpi_followup">Follow-up Schedules</span>
                                <div class="stat-value mt-1"><?= number_format($total_schedules); ?></div>
                                <small class="text-success fw-semibold" data-i18n="upcoming_tpl" data-vars="<?= vars_attr(['n' => number_format($upcoming_schedules)]); ?>"><?= number_format($upcoming_schedules); ?> Upcoming</small> |
                                <small class="text-<?= $overdue_schedules > 0 ? 'danger' : 'success'; ?> fw-semibold" data-i18n="overdue_tpl" data-vars="<?= vars_attr(['n' => number_format($overdue_schedules)]); ?>"><?= number_format($overdue_schedules); ?> Overdue</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card clinic-card kpi-card kpi-measurements h-100">
                        <div class="clinic-card-body d-flex align-items-center gap-3">
                            <div class="metric-pill metric-pill-purple"><i class="bi bi-heart-pulse-fill"></i></div>
                            <div>
                                <span class="stat-label" data-i18n="kpi_vitals">Vital Measurements</span>
                                <div class="stat-value mt-1"><?= number_format($totalMeasurements); ?></div>
                                <small class="text-muted"><?= htmlspecialchars($filter_label); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card clinic-card kpi-card kpi-yesterday h-100">
                        <div class="clinic-card-body d-flex align-items-center gap-3">
                            <div class="metric-pill metric-pill-warning"><i class="bi bi-gender-ambiguous"></i></div>
                            <div>
                                <span class="stat-label" data-i18n="kpi_gender">Gender Ratio</span>
                                <div class="stat-value mt-1" style="font-size: 1.25rem;">
                                    <?= $male_percent; ?>% M / <?= $female_percent; ?>% F
                                </div>
                                <small class="text-muted" data-i18n="gender_counts" data-vars="<?= vars_attr(['m' => number_format($male_count), 'f' => number_format($female_count)]); ?>"><?= number_format($male_count); ?> Male, <?= number_format($female_count); ?> Female</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Services Breakdown Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card clinic-card h-100 p-3">
                        <h5 class="fw-bold mb-3" data-i18n="svc_title">Clinical Services Access Summary</h5>
                        <div class="service-item-row mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><i class="bi bi-clipboard2-pulse-fill text-warning me-2"></i> <span data-i18n="svc_vital">Vital Screening</span></span>
                                <strong data-i18n="checkups_tpl" data-vars="<?= vars_attr(['n' => number_format($vital_count)]); ?>"><?= number_format($vital_count); ?> Checkups</strong>
                            </div>
                        </div>
                        <div class="service-item-row mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><i class="bi bi-heart-pulse-fill text-danger me-2"></i> <span data-i18n="svc_prenatal">Prenatal Check-up</span></span>
                                <strong data-i18n="checkups_tpl" data-vars="<?= vars_attr(['n' => number_format($prenatal_count)]); ?>"><?= number_format($prenatal_count); ?> Checkups</strong>
                            </div>
                        </div>
                        <div class="service-item-row mb-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><i class="bi bi-people-fill text-primary me-2"></i> <span data-i18n="svc_family">Family Planning</span></span>
                                <strong data-i18n="checkups_tpl" data-vars="<?= vars_attr(['n' => number_format($family_count)]); ?>"><?= number_format($family_count); ?> Checkups</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card clinic-card h-100 p-3">
                        <h5 class="fw-bold mb-3" data-i18n="brgy_title">Barangay Patient Distribution</h5>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th data-i18n="col_barangay">Barangay</th>
                                        <th class="text-end" data-i18n="col_registered">Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($barangay_counts as $b_name => $count): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($b_name); ?></td>
                                            <td class="text-end fw-bold"><?= number_format($count); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Age Ratio by Gender Card -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card clinic-card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-bar-chart-fill text-primary me-2"></i>
                                <span data-i18n="age_title">Age Group Distribution by Gender</span>
                            </h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fw-semibold" style="font-size: 0.75rem;">
                                <?= htmlspecialchars($filter_label); ?>
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                <thead>
                                    <tr class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <th style="width: 90px;" data-i18n="col_age_group">Age Group</th>
                                        <th class="text-center" style="width: 60px;" data-i18n="col_male">Male</th>
                                        <th class="text-center" style="width: 60px;" data-i18n="col_female">Female</th>
                                        <th class="text-center" style="width: 60px;" data-i18n="col_total">Total</th>
                                        <th data-i18n="col_distribution">Distribution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($age_ratio_data as $grp): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?= $grp['label']; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary-subtle text-primary fw-semibold">
                                                <?= $grp['male']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger-subtle text-danger fw-semibold">
                                                <?= $grp['female']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold"><?= $grp['total']; ?></td>
                                        <td style="min-width: 200px;">
                                            <?php if ($grp['total'] > 0): ?>
                                            <div class="d-flex gap-1 align-items-center">
                                                <div class="progress flex-grow-1" style="height: 10px; border-radius: 6px; background: #f1f5f9;">
                                                    <?php
                                                        $m_pct = $age_ratio_max > 0 ? round(($grp['male']   / $age_ratio_max) * 100, 1) : 0;
                                                        $f_pct = $age_ratio_max > 0 ? round(($grp['female'] / $age_ratio_max) * 100, 1) : 0;
                                                    ?>
                                                    <div class="progress-bar bg-primary" style="width: <?= $m_pct; ?>%; border-radius: 6px 0 0 6px;" title="Male: <?= $grp['male']; ?>"></div>
                                                    <div class="progress-bar bg-danger"  style="width: <?= $f_pct; ?>%; border-radius: 0 6px 6px 0;" title="Female: <?= $grp['female']; ?>"></div>
                                                </div>
                                                <small class="text-muted" style="font-size: 0.7rem; white-space: nowrap;">
                                                    <?= $grp['male']; ?>M / <?= $grp['female']; ?>F
                                                </small>
                                            </div>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 0.75rem;" data-i18n="no_data">No data</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold border-top" style="font-size: 0.8rem; background: #f8fafc;">
                                        <td data-i18n="total_upper">TOTAL</td>
                                        <td class="text-center text-primary"><?= number_format($male_count); ?></td>
                                        <td class="text-center text-danger"><?= number_format($female_count); ?></td>
                                        <td class="text-center"><?= number_format($total_gender_count); ?></td>
                                        <td>
                                            <div class="progress" style="height: 10px; border-radius: 6px;">
                                                <div class="progress-bar bg-primary" style="width: <?= $male_percent; ?>%;" title="Male <?= $male_percent; ?>%"></div>
                                                <div class="progress-bar bg-danger"  style="width: <?= $female_percent; ?>%;" title="Female <?= $female_percent; ?>%"></div>
                                            </div>
                                            <small class="text-muted" style="font-size: 0.68rem;"><?= $male_percent; ?>% <span data-i18n="col_male">Male</span> &nbsp;|&nbsp; <?= $female_percent; ?>% <span data-i18n="col_female">Female</span></small>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <!-- Legend -->
                        <div class="mt-2 d-flex gap-3" style="font-size: 0.75rem;">
                            <span><i class="bi bi-circle-fill text-primary me-1" style="font-size: 0.5rem;"></i> <span data-i18n="legend_male">Male</span></span>
                            <span><i class="bi bi-circle-fill text-danger me-1" style="font-size: 0.5rem;"></i> <span data-i18n="legend_female">Female</span></span>
                            <span class="text-muted ms-auto"><i class="bi bi-info-circle me-1"></i><span data-i18n="legend_note">Bar width relative to highest age group total</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registered Patient Roster -->
            <div class="card clinic-card shadow-sm">
                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold" data-i18n="registry_title" data-vars="<?= vars_attr(['label' => $filter_label]); ?>">Patient Registry (<?= htmlspecialchars($filter_label); ?>)</h5>
                    <span class="badge bg-primary" data-i18n="records_tpl" data-vars="<?= vars_attr(['n' => (string)$roster_count]); ?>"><?= $roster_count; ?> Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th data-i18n="col_patient_name">Patient Name</th>
                                    <th data-i18n="col_agesex">Age/Sex</th>
                                    <th data-i18n="col_address">Address</th>
                                    <th data-i18n="col_bloodtype">Blood Type</th>
                                    <th data-i18n="col_regdate">Registered Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($roster_count > 0) {
                                    mysqli_data_seek($patient_roster_q, 0);
                                    $idx = 1;
                                    while ($p = mysqli_fetch_assoc($patient_roster_q)):
                                ?>
                                    <tr>
                                        <td class="ps-3 text-muted"><?= $idx++; ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($p['fullname']); ?></td>
                                        <td><?= htmlspecialchars($p['age']); ?> / <?= htmlspecialchars($p['gender']); ?></td>
                                        <td><?= htmlspecialchars(!empty($p['barangay']) ? $p['barangay'] : (!empty($p['address']) ? $p['address'] : $p['city_municipality'])); ?></td>
                                        <td><?= !empty($p['blood_type']) ? htmlspecialchars($p['blood_type']) : 'N/A'; ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars($p['reg_date']); ?></td>
                                    </tr>
                                <?php
                                    endwhile;
                                } else {
                                ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4" data-i18n="no_records">No records found for this period.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <!-- ========================================================
             3. SIMPLE PRINT / PDF DOCUMENT TEMPLATE
             (Visible ONLY when printing / converting to PDF)
             Official clinic record: always printed in English.
        ======================================================== -->
        <div class="simple-print-document">

            <!-- Clinic Document Letterhead -->
            <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 20px;">
                <h2 style="margin: 0; font-size: 20pt; font-weight: bold; text-transform: uppercase;">RHU Villaba Clinic</h2>
                <p style="margin: 2px 0; font-size: 10pt; color: #444;">Rural Health Unit • Municipality of Villaba, Leyte</p>
                <h3 style="margin: 8px 0 0 0; font-size: 13pt; font-weight: bold; text-decoration: underline;">
                    <?= strtoupper(htmlspecialchars($report_scope_title)); ?>
                </h3>
                <p style="margin: 3px 0 0 0; font-size: 10pt; font-weight: bold;">
                    Coverage Period: <?= htmlspecialchars($filter_label); ?>
                </p>
            </div>

            <!-- Document Metadata Info -->
            <div style="display: flex; justify-content: space-between; font-size: 9pt; margin-bottom: 15px; border-bottom: 1px dashed #777; padding-bottom: 6px;">
                <div><strong>Report Type:</strong> <?= ucfirst($filter_type); ?> Filter</div>
                <div><strong>Date Generated:</strong> <span id="printGenerated"><?= date('F d, Y - h:i A'); ?></span></div>
                <div><strong>Status:</strong> Official Clinic Record</div>
            </div>

            <!-- Section 1: Executive Summary Metrics -->
            <h4 style="font-size: 11pt; font-weight: bold; margin: 15px 0 6px 0; text-transform: uppercase; border-bottom: 1px solid #000;">
                I. Clinical Executive Summary
            </h4>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9.5pt;">
                <tbody>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333; width: 50%;">
                            <strong>Total Patients Registered (In Period):</strong> <?= number_format($filtered_patient_count); ?>
                        </td>
                        <td style="padding: 6px; border: 1px solid #333; width: 50%;">
                            <strong>Total Vital Measurements:</strong> <?= number_format($totalMeasurements); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333;">
                            <strong>Gender Demographics:</strong> <?= number_format($male_count); ?> Male (<?= $male_percent; ?>%) &nbsp;|&nbsp; <?= number_format($female_count); ?> Female (<?= $female_percent; ?>%)
                        </td>
                        <td style="padding: 6px; border: 1px solid #333;">
                            <strong>Follow-up Schedules:</strong> <?= number_format($upcoming_schedules); ?> Upcoming &nbsp;|&nbsp; <?= number_format($overdue_schedules); ?> Overdue
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Section 2: Clinical Program Breakdown -->
            <h4 style="font-size: 11pt; font-weight: bold; margin: 15px 0 6px 0; text-transform: uppercase; border-bottom: 1px solid #000;">
                II. Clinical Services Program Intake
            </h4>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9.5pt;">
                <thead>
                    <tr style="background-color: #eee;">
                        <th style="padding: 6px; border: 1px solid #333; text-align: left;">Clinical Program / Service</th>
                        <th style="padding: 6px; border: 1px solid #333; text-align: right; width: 30%;">Total Completed Checkups</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333;">Vital Signs Screening</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= number_format($vital_count); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333;">Prenatal Check-up</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= number_format($prenatal_count); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333;">Child Immunization</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= number_format($immunization_count); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333;">Family Planning</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= number_format($family_count); ?></td>
                    </tr>
                    <tr style="background-color: #fafafa;">
                        <td style="padding: 6px; border: 1px solid #333; font-weight: bold;">TOTAL CLINICAL ENCOUNTERS</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= number_format($totalMeasurements); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Section 3: Overall Total of Patients -->
            <h4 style="font-size: 11pt; font-weight: bold; margin: 15px 0 6px 0; text-transform: uppercase; border-bottom: 1px solid #000;">
                III. Overall Total of Patients
            </h4>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9.5pt;">
                <thead>
                    <tr style="background-color: #eee;">
                        <th style="padding: 6px; border: 1px solid #333; text-align: left;">Category / Demographic</th>
                        <th style="padding: 6px; border: 1px solid #333; text-align: right; width: 30%;">Total Count</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333;">Male Patients Registered (In Coverage Period)</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right;"><?= number_format($male_count); ?> (<?= $male_percent; ?>%)</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333;">Female Patients Registered (In Coverage Period)</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right;"><?= number_format($female_count); ?> (<?= $female_percent; ?>%)</td>
                    </tr>
                    <tr style="background-color: #f2f2f2; font-weight: bold;">
                        <td style="padding: 6px; border: 1px solid #333;">TOTAL PATIENTS REGISTERED IN PERIOD</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= number_format($filtered_patient_count); ?></td>
                    </tr>
                    <tr style="background-color: #e6e6e6; font-weight: bold;">
                        <td style="padding: 6px; border: 1px solid #333;">OVERALL CUMULATIVE PATIENTS (ALL TIME)</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= number_format($all_patients_count); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Section 4: Age Group by Gender (Print) -->
            <h4 style="font-size: 11pt; font-weight: bold; margin: 15px 0 6px 0; text-transform: uppercase; border-bottom: 1px solid #000;">
                IV. Age Group Distribution by Gender
            </h4>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9.5pt;">
                <thead>
                    <tr style="background-color: #eee;">
                        <th style="padding: 6px; border: 1px solid #333; text-align: left;">Age Group</th>
                        <th style="padding: 6px; border: 1px solid #333; text-align: right; width: 18%;">Male</th>
                        <th style="padding: 6px; border: 1px solid #333; text-align: right; width: 18%;">Female</th>
                        <th style="padding: 6px; border: 1px solid #333; text-align: right; width: 18%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($age_ratio_data as $grp): ?>
                    <tr>
                        <td style="padding: 6px; border: 1px solid #333;"><?= $grp['label']; ?></td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right;"><?= $grp['male']; ?></td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right;"><?= $grp['female']; ?></td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= $grp['total']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: #f2f2f2; font-weight: bold;">
                        <td style="padding: 6px; border: 1px solid #333;">TOTAL</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right;"><?= number_format($male_count); ?> (<?= $male_percent; ?>%)</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right;"><?= number_format($female_count); ?> (<?= $female_percent; ?>%)</td>
                        <td style="padding: 6px; border: 1px solid #333; text-align: right;"><?= number_format($total_gender_count); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Section 5: All 35 Barangays Distribution -->
            <h4 style="font-size: 11pt; font-weight: bold; margin: 15px 0 6px 0; text-transform: uppercase; border-bottom: 1px solid #000;">
                V. Patient Address / Barangay Distribution (All 35 Barangays)
            </h4>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 9pt;">
                <thead>
                    <tr style="background-color: #eee;">
                        <th style="padding: 5px; border: 1px solid #333; width: 8%; text-align: center;">#</th>
                        <th style="padding: 5px; border: 1px solid #333; text-align: left;">Address / Barangay Location</th>
                        <th style="padding: 5px; border: 1px solid #333; width: 25%; text-align: right;">Total Patients</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $brgy_num = 1;
                    foreach ($barangay_counts as $b_name => $count):
                    ?>
                        <tr>
                            <td style="padding: 5px; border: 1px solid #333; text-align: center;"><?= $brgy_num++; ?></td>
                            <td style="padding: 5px; border: 1px solid #333;"><?= htmlspecialchars($b_name); ?></td>
                            <td style="padding: 5px; border: 1px solid #333; text-align: right; font-weight: bold;"><?= number_format($count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Document Sign-off Footer -->
            <div class="print-footer" style="margin-top: 40px; display: flex; justify-content: space-between; font-size: 9.5pt;">
                <div style="width: 45%;">
                    <p style="margin-bottom: 45px;">Prepared by:</p>
                    <div style="border-top: 1px solid #000; width: 90%;">
                        <p style="margin: 4px 0 0 0; font-weight: bold;">VitalCore System Administrator</p>
                        <small style="color: #555;">Medical Records &amp; Intake Officer</small>
                    </div>
                </div>
                <div style="width: 45%; text-align: right;">
                    <p style="margin-bottom: 45px;">Noted &amp; Approved by:</p>
                    <div style="border-top: 1px solid #000; width: 90%; margin-left: auto;">
                        <p style="margin: 4px 0 0 0; font-weight: bold;">Municipal Health Officer (MHO)</p>
                        <small style="color: #555;">Rural Health Unit — Villaba, Leyte</small>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/theme.js"></script>

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
            nav_all_patients_short: "Lahat ng Pasyente",
            nav_add_patient: "Magdagdag ng Pasyente",
            nav_patient_records: "Mga Rekord ng Pasyente",
            nav_patient_history: "Kasaysayan ng Pasyente",
            nav_reports: "Mga Ulat",
            section_system: "Hardware at Sistema",
            nav_admin: "Administrasyon",
            nav_user_mgmt: "Pamamahala ng Gumagamit",
            nav_settings: "Mga Setting",
            nav_logout: "Mag-logout",
            hub_title: "Sentro ng Ulat ng Klinika",
            export_pdf: "I-export ang {label} bilang PDF",
            viewing_for: "Tinitingnan ang datos klinikal para sa:",
            opt_day: "Araw",
            opt_month: "Buwan",
            opt_year: "Taon",
            apply_filter: "I-apply ang Filter",
            kpi_patients_period: "Mga Pasyente sa Panahon",
            kpi_followup: "Mga Iskedyul ng Follow-up",
            upcoming_tpl: "{n} Paparating",
            overdue_tpl: "{n} Lampas na",
            kpi_vitals: "Mga Sukat ng Vital",
            kpi_gender: "Ratio ng Kasarian",
            gender_counts: "{m} Lalaki, {f} Babae",
            svc_title: "Buod ng Pag-access sa Serbisyong Klinikal",
            svc_vital: "Vital Screening",
            svc_prenatal: "Prenatal Check-up",
            svc_family: "Family Planning",
            checkups_tpl: "{n} Check-up",
            brgy_title: "Distribusyon ng Pasyente kada Barangay",
            col_barangay: "Barangay",
            col_registered: "Nakarehistro",
            age_title: "Distribusyon ng Pangkat ng Edad ayon sa Kasarian",
            col_age_group: "Pangkat ng Edad",
            col_male: "Lalaki",
            col_female: "Babae",
            col_total: "Kabuuan",
            col_distribution: "Distribusyon",
            no_data: "Walang datos",
            total_upper: "KABUUAN",
            legend_male: "Lalaki",
            legend_female: "Babae",
            legend_note: "Ang lapad ng bar ay kaugnay ng pinakamataas na kabuuan ng pangkat ng edad",
            registry_title: "Rehistro ng Pasyente ({label})",
            records_tpl: "{n} Rekord",
            col_patient_name: "Pangalan ng Pasyente",
            col_agesex: "Edad/Kasarian",
            col_address: "Tirahan",
            col_bloodtype: "Uri ng Dugo",
            col_regdate: "Petsa ng Pagpaparehistro",
            no_records: "Walang nakitang rekord sa panahong ito."
        },
        ceb: {
            section_main: "Pangunahing",
            nav_dashboard: "Dashboard",
            section_clinical: "Mga Serbisyong Klinikal",
            nav_patient_mgmt: "Pagdumala sa Pasyente",
            nav_all_patients_short: "Tanan nga Pasyente",
            nav_add_patient: "Idugang ang Pasyente",
            nav_patient_records: "Mga Rekord sa Pasyente",
            nav_patient_history: "Kasaysayan sa Pasyente",
            nav_reports: "Mga Report",
            section_system: "Hardware ug Sistema",
            nav_admin: "Administrasyon",
            nav_user_mgmt: "Pagdumala sa Paggamit",
            nav_settings: "Mga Setting",
            nav_logout: "Mo-logout",
            hub_title: "Sentro sa Report sa Klinika",
            export_pdf: "I-export ang {label} isip PDF",
            viewing_for: "Gitan-aw ang datos klinikal alang sa:",
            opt_day: "Adlaw",
            opt_month: "Bulan",
            opt_year: "Tuig",
            apply_filter: "I-apply ang Filter",
            kpi_patients_period: "Mga Pasyente sa Panahon",
            kpi_followup: "Mga Iskedyul sa Follow-up",
            upcoming_tpl: "{n} Moabot",
            overdue_tpl: "{n} Milapas na",
            kpi_vitals: "Mga Sukat sa Vital",
            kpi_gender: "Ratio sa Gender",
            gender_counts: "{m} Lalaki, {f} Babaye",
            svc_title: "Kinatibuk-ang Pag-access sa Serbisyong Klinikal",
            svc_vital: "Vital Screening",
            svc_prenatal: "Prenatal Check-up",
            svc_family: "Family Planning",
            checkups_tpl: "{n} Check-up",
            brgy_title: "Pag-apod-apod sa Pasyente matag Barangay",
            col_barangay: "Barangay",
            col_registered: "Narehistro",
            age_title: "Pag-apod-apod sa Grupo sa Edad suno sa Gender",
            col_age_group: "Grupo sa Edad",
            col_male: "Lalaki",
            col_female: "Babaye",
            col_total: "Total",
            col_distribution: "Pag-apod-apod",
            no_data: "Walay datos",
            total_upper: "TOTAL",
            legend_male: "Lalaki",
            legend_female: "Babaye",
            legend_note: "Ang gilapdon sa bar nalambigit sa pinakataas nga total sa grupo sa edad",
            registry_title: "Rehistro sa Pasyente ({label})",
            records_tpl: "{n} ka Rekord",
            col_patient_name: "Ngalan sa Pasyente",
            col_agesex: "Edad/Gender",
            col_address: "Adres",
            col_bloodtype: "Tipo sa Dugo",
            col_regdate: "Petsa sa Pagparehistro",
            no_records: "Walay nakitang rekord niining panahona."
        }
    };

    const lang = store.get("language", "en");
    if (i18n[lang]) {
        const dict = i18n[lang];
        document.documentElement.lang = lang;

        document.querySelectorAll("[data-i18n]").forEach(el => {
            let text = dict[el.getAttribute("data-i18n")];
            if (!text) return;

            // Fill {placeholders} from data-vars (counts, period label, ...)
            if (el.dataset.vars) {
                try {
                    const vars = JSON.parse(el.dataset.vars);
                    text = text.replace(/\{(\w+)\}/g, (m, k) => (k in vars ? vars[k] : m));
                } catch (e) {}
            }
            el.textContent = text;
        });
    }

    // ---------------------------------------------------------------
    // 5. Date & Time Format (live clock + "Date Generated" on the printout)
    // ---------------------------------------------------------------
    const TZ = "Asia/Manila";
    function timeString(withSeconds) {
        const opts = { hour: "2-digit", minute: "2-digit", timeZone: TZ };
        if (withSeconds) opts.second = "2-digit";
        if (store.get("dateTimeFormat", "24h") === "12h") {
            opts.hour12 = true;
        } else {
            opts.hourCycle = "h23";
        }
        return new Date().toLocaleTimeString("en-US", opts);
    }

    const clockEl = document.getElementById("liveClock");
    function updateClock() {
        if (clockEl) clockEl.textContent = "• " + timeString(true);
    }
    updateClock();
    setInterval(updateClock, 1000);

    function updatePrintStamp() {
        const el = document.getElementById("printGenerated");
        if (!el) return;
        const d = new Date().toLocaleDateString("en-US", {
            month: "long", day: "2-digit", year: "numeric", timeZone: TZ
        });
        el.textContent = d + " - " + timeString(false);
    }
    updatePrintStamp();

    // ---------------------------------------------------------------
    // 6. Print: clean title + fresh timestamp
    // ---------------------------------------------------------------
    window.addEventListener("beforeprint", () => {
        document._origTitle = document.title;
        document.title = "";
        updatePrintStamp();
    });
    window.addEventListener("afterprint", () => {
        document.title = document._origTitle || "VitalCore";
    });

})();
</script>
</body>
</html>