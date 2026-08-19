<?php
/** @var mysqli $conn */
require_once('../../db_conn.php');

/* =========================
   FILTER SETTINGS
========================= */

$filter_type = $_GET['type'] ?? 'day';
$filter_date = $_GET['date'] ?? date('Y-m-d');

$whereUsers = "1=1";
$whereMeasurements = "1=1";

if($filter_type == "day"){

    $whereUsers =
        "DATE(created_at) = '$filter_date'";

    $whereMeasurements =
        "DATE(created_at) = '$filter_date'";
}
elseif($filter_type == "month"){

    $month = date('m', strtotime($filter_date));
    $year  = date('Y', strtotime($filter_date));

    $whereUsers =
        "MONTH(created_at)='$month'
         AND YEAR(created_at)='$year'";

    $whereMeasurements =
        "MONTH(created_at)='$month'
         AND YEAR(created_at)='$year'";
}
elseif($filter_type == "year"){

    $year = date('Y', strtotime($filter_date));

    $whereUsers =
        "YEAR(created_at)='$year'";

    $whereMeasurements =
        "YEAR(created_at)='$year'";
}

$totalPatientsQuery =
mysqli_query(
    $conn,
    "SELECT COUNT(*) total
     FROM users
     WHERE role='patient'
     AND $whereUsers"
);

$totalPatients =
mysqli_fetch_assoc(
    $totalPatientsQuery
)['total'];

$measurementQuery =
mysqli_query(
    $conn,
    "SELECT COUNT(*) total
     FROM measurements
     WHERE $whereMeasurements"
);

$totalMeasurements =
mysqli_fetch_assoc(
    $measurementQuery
)['total'];

$serviceQuery =
mysqli_query(
    $conn,
    "SELECT
        service_type,
        COUNT(*) total
     FROM users
     WHERE role='patient'
     AND $whereUsers
     GROUP BY service_type"
);

/* =========================
   PATIENT STATISTICS & GENDER OVERVIEW
========================= */
// 1. Total Patients Count
$count_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='patient'");
$count_r = mysqli_fetch_assoc($count_q);
$patient_count = $count_r['total'] ?? 0;

if($filter_type == "day"){

    $today_condition =
        "DATE(created_at) = '$filter_date'";
}
elseif($filter_type == "month"){

    $month = date('m', strtotime($filter_date));
    $year  = date('Y', strtotime($filter_date));

    $today_condition =
        "MONTH(created_at)='$month'
         AND YEAR(created_at)='$year'";
}
else{

    $year = date('Y', strtotime($filter_date));

    $today_condition =
        "YEAR(created_at)='$year'";
}

$today_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS today_total
     FROM users
     WHERE role='patient'
     AND $today_condition"
);

$today_r = mysqli_fetch_assoc($today_q);
$today_new_patients = $today_r['today_total'] ?? 0;

/* =========================
   FILTERED STATISTICS
========================= */

$count_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role='patient'
     AND $whereUsers"
);

$count_r = mysqli_fetch_assoc($count_q);
$patient_count = $count_r['total'] ?? 0;


/* TOTAL MEASUREMENTS */

$measurement_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM measurements
     WHERE $whereMeasurements"
);

$measurement_r = mysqli_fetch_assoc($measurement_q);
$totalMeasurements = $measurement_r['total'] ?? 0;


/* PREVIOUS PERIOD */

$today = date('Y-m-d');

if($filter_type == "day"){

    if($filter_date > $today){

        $yesterday_new_patients = 0;

    }else{

        $previous_date =
            date(
                'Y-m-d',
                strtotime($filter_date . ' -1 day')
            );

        $previous_q = mysqli_query(
            $conn,
            "SELECT COUNT(*) total
             FROM users
             WHERE role='patient'
             AND DATE(created_at)='$previous_date'"
        );

        $previous_r = mysqli_fetch_assoc($previous_q);
        $yesterday_new_patients = $previous_r['total'] ?? 0;
    }
}
elseif($filter_type == "month"){

    $selectedMonth = date('Y-m', strtotime($filter_date));
    $currentMonth  = date('Y-m');

    if($selectedMonth > $currentMonth){

        $yesterday_new_patients = 0;

    }else{

        $previousMonth =
            date(
                'Y-m',
                strtotime($filter_date . ' -1 month')
            );

        $previous_q = mysqli_query(
            $conn,
            "SELECT COUNT(*) total
             FROM users
             WHERE role='patient'
             AND DATE_FORMAT(created_at,'%Y-%m')='$previousMonth'"
        );

        $previous_r = mysqli_fetch_assoc($previous_q);
        $yesterday_new_patients = $previous_r['total'] ?? 0;
    }
}
else{ // year

    $selectedYear = date('Y', strtotime($filter_date));
    $currentYear  = date('Y');

    if($selectedYear > $currentYear){

        $yesterday_new_patients = 0;

    }else{

        $previousYear = $selectedYear - 1;

        $previous_q = mysqli_query(
            $conn,
            "SELECT COUNT(*) total
             FROM users
             WHERE role='patient'
             AND YEAR(created_at)='$previousYear'"
        );

        $previous_r = mysqli_fetch_assoc($previous_q);
        $yesterday_new_patients = $previous_r['total'] ?? 0;
    }
}

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

$male_count =
    $gender_r['male_count'] ?? 0;

$female_count =
    $gender_r['female_count'] ?? 0;

$total_gender_count =
    $gender_r['total_count'] ?? 0;

$male_percent =
    $total_gender_count > 0
    ? round(($male_count/$total_gender_count)*100,1)
    : 0;

$female_percent =
    $total_gender_count > 0
    ? round(($female_count/$total_gender_count)*100,1)
    : 0;

// Formatted Date Strings
$current_date_str = date("M d, Y");
$yesterday_date_str = date("M d, Y", strtotime("-1 day"));


/* SERVICE COUNTS */

$dateFilter = str_replace("created_at", "u.created_at", $whereUsers);

$vital_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT u.id) total
     FROM users u
     INNER JOIN measurements m ON u.id = m.user_id
     WHERE u.role='patient'
     AND m.service_type='vital'
     AND $dateFilter"
))['total'];

$prenatal_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT u.id) total
     FROM users u
     INNER JOIN measurements m ON u.id = m.user_id
     WHERE u.role='patient'
     AND m.service_type='prenatal'
     AND $dateFilter"
))['total'];

$immunization_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT u.id) total
     FROM users u
     INNER JOIN measurements m ON u.id = m.user_id
     WHERE u.role='patient'
     AND m.service_type='immunization'
     AND $dateFilter"
))['total'];

$family_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT u.id) total
     FROM users u
     INNER JOIN measurements m ON u.id = m.user_id
     WHERE u.role='patient'
     AND m.service_type='family_planning'
     AND $dateFilter"
))['total'];

/*Address Summary*/
$address_q = mysqli_query(
    $conn,
    "SELECT
        address,
        COUNT(*) AS total
     FROM users
     WHERE role='patient'
     AND $whereUsers
     GROUP BY address
     ORDER BY total DESC"
);

$chartData = [];

$address_chart_q = mysqli_query(
    $conn,
    "SELECT
        address,
        COUNT(*) total
     FROM users
     WHERE role='patient'
     AND $whereUsers
     GROUP BY address
     ORDER BY total DESC
     LIMIT 10"
);

while($row = mysqli_fetch_assoc($address_chart_q)){
    $chartData[] = [
        "address" => $row['address'],
        "patients" => (int)$row['total']
    ];
}

// Dynamic service percentages relative to total patients
$vital_pct = $patient_count > 0 ? round(($vital_count / $patient_count) * 100) : 0;
$prenatal_pct = $patient_count > 0 ? round(($prenatal_count / $patient_count) * 100) : 0;
$immunization_pct = $patient_count > 0 ? round(($immunization_count / $patient_count) * 100) : 0;
$family_pct = $patient_count > 0 ? round(($family_count / $patient_count) * 100) : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Reports Dashboard</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/report.css">
    <link rel="stylesheet" href="../../../css/theme.css">
</head>
<body>

<div class="container py-0">
    <!-- Dashboard Header -->
    <div class="dashboard-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-5">
        <div>
            <h1 class="h3 m-0">Clinic Reports Hub</h1>
            <p class="text-muted m-0 fs-6">Comprehensive insights on patient growth, demographics, and clinical services.</p>
        </div>
           
        <div class="date-pill">
            <i class="bi bi-calendar3 text-accent-primary"></i>
            <?php
            $filter_type = $_GET['type'] ?? 'day';
            $filter_date = $_GET['date'] ?? date('Y-m-d');
            ?>

            <form method="GET" class="row g-2 mb-6">

                <div class="col-auto">
                    <select name="type" class="form-select">

                        <option value="day"
                            <?= $filter_type=='day'?'selected':'' ?>>
                            Day
                        </option>

                        <option value="month"
                            <?= $filter_type=='month'?'selected':'' ?>>
                            Month
                        </option>

                        <option value="year"
                            <?= $filter_type=='year'?'selected':'' ?>>
                            Year
                        </option>

                    </select>
                </div>

                <div class="col-auto">
                    <input type="date"
                        name="date"
                        value="<?= $filter_date ?>"
                        class="form-control">
                </div>

                <div class="col-auto">
                    <button class="btn btn-primary">
                        Apply Filter
                    </button>
                </div>

                

            </form>
        </div>
        <button
            type="button"
            onclick="history.back()"
            class="back-button">

            <i class="bi bi-arrow-left"></i>
            <span>Back</span>

        </button>
    </div>

    <!-- Top Row: Quick Summary Metrics -->
    <div class="row g-4 mb-4">
        <!-- Metric Card 1: Total Patients -->
        <div class="col-sm-6 col-lg-3">
            <div class="card clinic-card kpi-card kpi-total-patients">
                <div class="clinic-card-body d-flex align-items-center gap-3">
                    <div class="metric-pill metric-pill-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <span class="stat-label">Total Patients</span>
                        <div class="stat-value mt-1"><?= number_format($patient_count); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric Card 2: Today's Registrations -->
        <div class="col-sm-6 col-lg-3">
            <div class="card clinic-card kpi-card kpi-new-today">
                <div class="clinic-card-body d-flex align-items-center gap-3">
                    <div class="metric-pill metric-pill-success">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <div>
                        <span class="stat-label">New Today</span>
                        <div class="stat-value mt-1"><?= number_format($today_new_patients); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric Card 3: Total Vital Measurements -->
        <div class="col-sm-6 col-lg-3">
            <div class="card clinic-card kpi-card kpi-measurements">
                <div class="clinic-card-body d-flex align-items-center gap-3">
                    <div class="metric-pill metric-pill-purple">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                    <div>
                        <span class="stat-label">Total Measurements</span>
                        <div class="stat-value mt-1"><?= number_format($totalMeasurements); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric Card 4: Previous Day's Record -->
        <div class="col-sm-6 col-lg-3">
            <div class="card clinic-card kpi-card kpi-yesterday">
                <div class="clinic-card-body d-flex align-items-center gap-3">
                    <div class="metric-pill metric-pill-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <span class="text-uppercase fw-bold text-muted small d-block">
                            <?= $filter_type == 'day'
                                ? "Previous Day"
                                : ($filter_type == 'month'
                                    ? "Previous Month"
                                    : "Previous Year"); ?>
                        </span>
                        <div class="stat-value mt-1"><?= number_format($yesterday_new_patients); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="row g-4">
        
        <!-- Left Section: Patient Registration & Demographics -->
        <div class="col-lg-7">
            <div class="card clinic-card h-100">
                <div class="clinic-card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fs-5">Patient Demographics & Growth</h5>
                    <span class="badge rounded-pill bg-light text-dark border px-2 py-1" style="font-size: 0.75rem;">Live Analytics</span>
                </div>
                
                <div class="clinic-card-body d-flex flex-column justify-content-between gap-4">
                    
                    <!-- New Patients Highlight Box -->
                    <div class="p-3 bg-light rounded-3 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.68rem; letter-spacing: 0.5px;">Today's Patient Cohort</span>
                            <div class="d-flex align-items-baseline gap-2 mt-1">
                                <span class="fs-1 fw-bold text-accent-primary lh-1"><?= number_format($today_new_patients); ?></span>
                                <span class="text-secondary fw-semibold">new patient<?= $today_new_patients != 1 ? 's' : ''; ?> registered today</span>
                            </div>
                        </div>
                        
                        <div class="text-sm-end">
                            <span class="text-uppercase fw-bold text-muted small d-block" style="font-size: 0.68rem; letter-spacing: 0.5px;">Yesterday's Cohort</span>
                            <span class="fs-5 fw-bold text-dark d-block mt-1"><?= number_format($yesterday_new_patients); ?> <span class="fs-6 text-muted fw-normal">patients</span></span>
                            <span class="text-muted small" style="font-size: 0.75rem;"><?= $yesterday_date_str; ?></span>
                        </div>
                    </div>

                    <!-- Gender Ratio Breakdown -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-bold text-dark">Patient Gender Distribution</span>
                            <span class="badge rounded-pill bg-light text-secondary border px-2 py-1" style="font-size: 0.72rem;">Gender Ratio</span>
                        </div>
                        
                        <!-- Gender Data Table -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border mb-4 rounded-3 overflow-hidden">
                                <thead class="table-light text-muted">
                                    <tr style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <th class="ps-3 py-2">Gender</th>
                                        <th class="text-center py-2">Total Patients</th>
                                        <th class="text-end pe-3 py-2">Proportion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ps-3 py-3 fw-semibold text-dark">
                                            <i class="bi bi-gender-male text-primary fs-5 me-2"></i> Male Patients
                                        </td>
                                        <td class="text-center py-3 fw-bold text-dark"><?= number_format($male_count); ?></td>
                                        <td class="text-end pe-3 py-3">
                                            <span class="badge rounded-pill gender-badge-male px-3 py-2"><?= $male_percent; ?>%</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3 py-3 fw-semibold text-dark">
                                            <i class="bi bi-gender-female text-danger fs-5 me-2"></i> Female Patients
                                        </td>
                                        <td class="text-center py-3 fw-bold text-dark"><?= number_format($female_count); ?></td>
                                        <td class="text-end pe-3 py-3">
                                            <span class="badge rounded-pill gender-badge-female px-3 py-2"><?= $female_percent; ?>%</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Combined Gender Distribution Bar -->
                        <div class="pt-2">
                            <div class="d-flex justify-content-between text-muted mb-2" style="font-size: 0.8rem;">
                                <span><i class="bi bi-circle-fill text-primary me-2" style="font-size: 0.55rem;"></i> Male (<?= $male_percent; ?>%)</span>
                                <span><i class="bi bi-circle-fill text-danger me-2" style="font-size: 0.55rem;"></i> Female (<?= $female_percent; ?>%)</span>
                            </div>
                            <div class="progress gender-progress">
                                <div class="progress-bar gender-progress-male" style="width: <?= $male_percent; ?>%"></div>
                                <div class="progress-bar gender-progress-female" style="width: <?= $female_percent; ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card clinic-card mt-4">
                        <div class="clinic-card-header">
                            <h5 class="m-0">Patient Address Distribution</h5>
                        </div>

                        <div class="clinic-card-body">

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>
                                              <span class="stat-label">Brgy, City/Municipality</span></th>
                                            <th class="text-end stat-label">Patients</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                    <?php while($row = mysqli_fetch_assoc($address_q)): ?>

                                        <tr>
                                            <td><?= htmlspecialchars($row['address']) ?></td>
                                            <td class="text-end fw-bold">
                                                <?= number_format($row['total']) ?>
                                            </td>
                                        </tr>

                                    <?php endwhile; ?>

                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Right Section: Service Categories -->
        <div class="col-lg-5">
            <div class="card clinic-card h-100">
                <div class="clinic-card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fs-5">Active Services Breakdown</h5>
                    <span class="badge rounded-pill bg-light text-dark border px-2 py-1" style="font-size: 0.75rem;">Departmental</span>
                </div>
                
                <div class="clinic-card-body">
                    <p class="text-muted small mb-4">Total breakdown of registered patients grouped by their active service program enrollment.</p>

                    <div class="service-list">
                        
                        <!-- Vital Screening -->
                        <div class="service-item-row">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="service-icon-wrapper service-vital">
                                        <i class="bi bi-heart-pulse-fill"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark d-block" style="font-size: 0.95rem;">Vital Screening</span>
                                        <span class="text-muted small" style="font-size: 0.75rem;">General wellness tracking</span>
                                    </div>
                                </div>
                                <span class="fs-5 fw-bold text-dark"><?= number_format($vital_count) ?></span>
                            </div>
                            <!-- Dynamic Progress Bar -->
                            <div class="progress service-progress">
                                <div class="progress-bar bg-info" style="width: <?= $vital_pct; ?>%"></div>
                            </div>
                        </div>

                        <!-- Prenatal Check-up -->
                        <div class="service-item-row">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="service-icon-wrapper service-prenatal">
                                        <i class="bi bi-gender-female"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark d-block" style="font-size: 0.95rem;">Prenatal Check-up</span>
                                        <span class="text-muted small" style="font-size: 0.75rem;">Maternity care & wellness</span>
                                    </div>
                                </div>
                                <span class="fs-5 fw-bold text-dark"><?= number_format($prenatal_count) ?></span>
                            </div>
                            <!-- Dynamic Progress Bar -->
                            <div class="progress service-progress">
                                <div class="progress-bar bg-danger" style="width: <?= $prenatal_pct; ?>%"></div>
                            </div>
                        </div>

                        <!-- Child Immunization -->
                        <div class="service-item-row">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="service-icon-wrapper service-immunization">
                                        <i class="bi bi-shield-fill-check"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark d-block" style="font-size: 0.95rem;">Child Immunization</span>
                                        <span class="text-muted small" style="font-size: 0.75rem;">Pediatric vaccine plans</span>
                                    </div>
                                </div>
                                <span class="fs-5 fw-bold text-dark"><?= number_format($immunization_count) ?></span>
                            </div>
                            <!-- Dynamic Progress Bar -->
                            <div class="progress service-progress">
                                <div class="progress-bar bg-success" style="width: <?= $immunization_pct; ?>%"></div>
                            </div>
                        </div>

                        <!-- Family Planning -->
                        <div class="service-item-row">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="service-icon-wrapper service-family">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark d-block" style="font-size: 0.95rem;">Family Planning</span>
                                        <span class="text-muted small" style="font-size: 0.75rem;">Reproductive consultation</span>
                                    </div>
                                </div>
                                <span class="fs-5 fw-bold text-dark"><?= number_format($family_count) ?></span>
                            </div>
                            <!-- Dynamic Progress Bar -->
                            <div class="progress service-progress">
                                <div class="progress-bar bg-warning" style="width: <?= $family_pct; ?>%"></div>
                            </div>
                        </div>

                    </div>

                    <!-- Total Clinic Summary Helper -->
                    <div class="mt-4 pt-3 border-top text-center text-muted small" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle me-1"></i> Data automatically compiled from registered medical charts.
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>
 <script src="../../assets/js/theme.js"></script>
 <script>
    function goBack() {
        window.history.back();
    }
</script>
</body>
</html>