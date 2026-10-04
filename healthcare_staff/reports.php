<?php
// Set accurate local Philippine timezone
date_default_timezone_set('Asia/Manila');

/** @var mysqli $conn */
require_once('../db_conn.php');

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
   FILTER SETTINGS
========================= */

$filter_type = $_GET['type'] ?? 'day';
$filter_date = $_GET['date'] ?? date('Y-m-d');

$whereUsers = "1=1";
$whereMeasurements = "1=1";

if($filter_type == "day"){

    $whereUsers = "DATE(created_at) = '$filter_date'";
    $whereMeasurements = "DATE(created_at) = '$filter_date'";
        
    $filter_label = date('F d, Y', strtotime($filter_date));
    $report_scope_title = "Daily Clinical Summary Report";
    $period_type_label = "Day";
}
elseif($filter_type == "month"){

    $month = date('m', strtotime($filter_date));
    $year  = date('Y', strtotime($filter_date));

    $whereUsers = "MONTH(created_at)='$month' AND YEAR(created_at)='$year'";
    $whereMeasurements = "MONTH(created_at)='$month' AND YEAR(created_at)='$year'";
         
    $filter_label = date('F Y', strtotime($filter_date));
    $report_scope_title = "Monthly Clinical Summary Report";
    $period_type_label = "Month";
}
elseif($filter_type == "year"){

    $year = is_numeric($filter_date) ? $filter_date : date('Y', strtotime($filter_date));

    $whereUsers = "YEAR(created_at)='$year'";
    $whereMeasurements = "YEAR(created_at)='$year'";
        
    $filter_label = "Year " . $year;
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
$male_percent = $total_gender_count > 0 ? round(($male_count/$total_gender_count)*100, 1) : 0;
$female_percent = $total_gender_count > 0 ? round(($female_count/$total_gender_count)*100, 1) : 0;

/* ==================================================================
   FIXED ACCURATE SERVICE COUNTS (From measurements for the selected period)
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

/* FOLLOW-UPS */
$schedule_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM family_planning_records WHERE next_schedule IS NOT NULL");
$total_schedules = mysqli_fetch_assoc($schedule_query)['total'] ?? 0;
$upcoming_schedules = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM family_planning_records WHERE next_schedule >= CURDATE()"))['total'] ?? 0;
$overdue_schedules = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM family_planning_records WHERE next_schedule < CURDATE()"))['total'] ?? 0;

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
        city_municipality, 
        service_type, 
        blood_type,
        DATE_FORMAT(created_at, '%b %d, %Y') AS reg_date
     FROM users
     WHERE role='patient'
     AND $whereUsers
     ORDER BY id DESC"
);

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
    <title><?= htmlspecialchars($report_scope_title . " - " . $filter_label); ?> - VitalCore</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
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

        /* When printing or exporting to PDF */
        @media print {
            .sidebar, .dashboard-web-view, .btn-print-action, .date-pill, .no-print {
                display: none !important;
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

<div class="container-fluid">
    <div class="row">

        <!-- ========================================================
             1. SIDEBAR (Hidden in Print/PDF)
        ======================================================== -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between no-print">
            <div>
                <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

                <div class="sidebar-menu-wrapper">
                   
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Clinical Services</small>
                    <ul class="nav flex-column mb-3">
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#patientsMenu" role="button">
                                <span><i class="bi bi-people-fill me-2 text-primary"></i> Patient Management</span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse" id="patientsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1"><a href="patient-list.php" class="sidebar-submenu-link text-decoration-none"><i class="bi bi-list-ul me-2"></i> All Patients</a></li>
                                    <li class="py-1"><a href="admin-dashboard.php" class="sidebar-submenu-link text-decoration-none"><i class="bi bi-person-plus-fill me-2"></i> Add Patient</a></li>
                                </ul>
                            </div>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link active sidebar-collapse-link active-parent d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#recordsMenu" role="button">
                                <span><i class="bi bi-folder2-open me-2 text-warning"></i> Patient Records</span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse show" id="recordsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1"><a href="patient-history.php" class="sidebar-submenu-link text-decoration-none"><i class="bi bi-clock-history me-2"></i> Patient History</a></li>
                                    <li class="py-1"><a href="reports.php" class="sidebar-submenu-link active text-decoration-none"><i class="bi bi-file-earmark-bar-graph me-2"></i> Reports</a></li>
                                </ul>
                            </div>
                        </li>
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

            <div class="logout-section pt-3 px-2 border-top border-secondary border-opacity-25">
                <a href="../../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left fs-5"></i> Log out
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
                        <h1 class="h3 m-0 fw-bold">Clinic Reports Hub</h1>
                        
                        <!-- Print/Export Button -->
                        <button type="button" 
                                onclick="window.print()" 
                                class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 d-inline-flex align-items-center gap-2 shadow-sm btn-print-action">
                            <i class="bi bi-file-earmark-pdf-fill text-danger"></i>
                            <span class="fw-semibold">Export <?= htmlspecialchars($filter_label); ?> as PDF</span>
                        </button>
                    </div>
                    <p class="text-muted m-0 fs-6 mt-1">
                        Viewing clinical data for: <span class="badge bg-primary-subtle text-primary fw-bold px-2 py-1"><?= htmlspecialchars($filter_label); ?></span>
                    </p>
                </div>
                
                <!-- Date Filter Selector Form -->
                <div class="date-pill">
                    <i class="bi bi-calendar3 text-accent-primary"></i>
                    <form method="GET" class="row g-2 align-items-center m-0">
                        <!-- Filter Type Selector -->
                        <div class="col-auto">
                            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="day" <?= $filter_type=='day'?'selected':'' ?>>Day</option>
                                <option value="month" <?= $filter_type=='month'?'selected':'' ?>>Month</option>
                                <option value="year" <?= $filter_type=='year'?'selected':'' ?>>Year</option>
                            </select>
                        </div>

                        <!-- Date / Month / Year Dropdown Selector -->
                        <div class="col-auto">
                            <?php if ($filter_type == 'year'): ?>
                                <!-- Scrollable Year Dropdown -->
                                <select name="date" class="form-select form-select-sm">
                                    <?php 
                                    $selected_year = is_numeric($filter_date) ? (int)$filter_date : (int)date('Y', strtotime($filter_date));
                                    $current_year  = (int)date('Y');
                                    
                                    for ($y = $current_year + 24; $y >= 2025; $y--): 
                                    ?>
                                        <option value="<?= $y; ?>" <?= ($selected_year == $y) ? 'selected' : ''; ?>>
                                            <?= $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>

                            <?php elseif ($filter_type == 'month'): ?>
                                <input type="month" 
                                    name="date" 
                                    value="<?= htmlspecialchars(date('Y-m', strtotime($filter_date))); ?>" 
                                    class="form-control form-control-sm">

                            <?php else: ?>
                                <input type="date" 
                                    name="date" 
                                    value="<?= htmlspecialchars(date('Y-m-d', strtotime($filter_date))); ?>" 
                                    class="form-control form-control-sm">
                            <?php endif; ?>
                        </div>

                        <div class="col-auto">
                            <button class="btn btn-primary btn-sm px-3">Apply Filter</button>
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
                                <span class="stat-label">Patients in Period</span>
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
                                <span class="stat-label">Follow-up Schedules</span>
                                <div class="stat-value mt-1"><?= number_format($total_schedules); ?></div>
                                <small class="text-success fw-semibold"><?= number_format($upcoming_schedules); ?> Upcoming</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card clinic-card kpi-card kpi-measurements h-100">
                        <div class="clinic-card-body d-flex align-items-center gap-3">
                            <div class="metric-pill metric-pill-purple"><i class="bi bi-heart-pulse-fill"></i></div>
                            <div>
                                <span class="stat-label">Vital Measurements</span>
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
                                <span class="stat-label">Gender Ratio</span>
                                <div class="stat-value mt-1" style="font-size: 1.25rem;">
                                    <?= $male_percent; ?>% M / <?= $female_percent; ?>% F
                                </div>
                                <small class="text-muted"><?= number_format($male_count); ?> Male, <?= number_format($female_count); ?> Female</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Services Breakdown Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card clinic-card h-100 p-3">
                        <h5 class="fw-bold mb-3">Clinical Services Access Summary</h5>
                        <div class="service-item-row mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><i class="bi bi-clipboard2-pulse-fill text-warning me-2"></i> Vital Screening</span>
                                <strong><?= number_format($vital_count); ?> Checkups</strong>
                            </div>
                        </div>
                        <div class="service-item-row mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><i class="bi bi-heart-pulse-fill text-danger me-2"></i> Prenatal Check-up</span>
                                <strong><?= number_format($prenatal_count); ?> Checkups</strong>
                            </div>
                        </div>
                        <div class="service-item-row mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><i class="bi bi-shield-check text-success me-2"></i> Child Immunization</span>
                                <strong><?= number_format($immunization_count); ?> Checkups</strong>
                            </div>
                        </div>
                        <div class="service-item-row mb-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><i class="bi bi-people-fill text-primary me-2"></i> Family Planning</span>
                                <strong><?= number_format($family_count); ?> Checkups</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card clinic-card h-100 p-3">
                        <h5 class="fw-bold mb-3">Barangay Patient Distribution</h5>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Barangay</th>
                                        <th class="text-end">Registered</th>
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

            <!-- Registered Patient Roster -->
            <div class="card clinic-card shadow-sm">
                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold">Patient Registry (<?= htmlspecialchars($filter_label); ?>)</h5>
                    <span class="badge bg-primary"><?= mysqli_num_rows($patient_roster_q); ?> Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Patient Name</th>
                                    <th>Age/Sex</th>
                                    <th>Address</th>
                                    <th>Blood Type</th>
                                    <th>Registered Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if(mysqli_num_rows($patient_roster_q) > 0) {
                                    mysqli_data_seek($patient_roster_q, 0);
                                    $idx = 1;
                                    while($p = mysqli_fetch_assoc($patient_roster_q)):
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
                                    <tr><td colspan="6" class="text-center text-muted py-4">No records found for this period.</td></tr>
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
                <div><strong>Date Generated:</strong> <?= date('F d, Y - h:i A'); ?></div>
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

            <!-- Section 4: All 35 Barangays Distribution -->
            <h4 style="font-size: 11pt; font-weight: bold; margin: 15px 0 6px 0; text-transform: uppercase; border-bottom: 1px solid #000;">
                IV. Patient Address / Barangay Distribution (All 35 Barangays)
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
                        <small style="color: #555;">Medical Records & Intake Officer</small>
                    </div>
                </div>
                <div style="width: 45%; text-align: right;">
                    <p style="margin-bottom: 45px;">Noted & Approved by:</p>
                    <div style="border-top: 1px solid #000; width: 90%; margin-left: auto;">
                        <p style="margin: 4px 0 0 0; font-weight: bold;">Municipal Health Officer (MHO)</p>
                        <small style="color: #555;">Rural Health Unit — Villaba, Leyte</small>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Clear browser title during printing so top header is completely clean -->
<script>
window.addEventListener('beforeprint', () => {
    document._origTitle = document.title;
    document.title = "";
});
window.addEventListener('afterprint', () => {
    document.title = document._origTitle || "VitalCore";
});
</script>

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/theme.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const savedTheme = localStorage.getItem('staff_theme');

    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        document.body.setAttribute('data-bs-theme', 'dark');
    }
});
</script>
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

    // 2. Brightness
    const savedBrightness = localStorage.getItem("brightness");
    if (savedBrightness) {
        document.body.style.filter = `brightness(${savedBrightness}%)`;
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
</body>
</html>