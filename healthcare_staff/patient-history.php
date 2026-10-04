<?php
/** @var mysqli $conn */
session_start();

if (
    !isset($_SESSION['staff_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Staff'
) {
    header("Location: ../login.php");
    exit();
}
require_once('../db_conn.php');

$query = mysqli_query(
    $conn,
    "SELECT
        u.id AS user_id,
        u.fullname,
        u.code_number,
        MAX(m.created_at) AS last_visit
    FROM users u
    LEFT JOIN measurements m
        ON m.user_id = u.id
    WHERE u.role = 'patient'
    GROUP BY u.id
    ORDER BY u.fullname ASC"
);

$total_patients = mysqli_num_rows($query);

/* TOTAL CHECKUPS */
$checkup_query = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM measurements"
);

$checkup_row = mysqli_fetch_assoc($checkup_query);

$total_checkups = $checkup_row['total'];

?>

<!DOCTYPE html>
<html lang="en" translate="no">

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
<meta name="google" content="notranslate">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Records | VitalCore</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"rel="stylesheet">
<link rel="stylesheet"href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../../css/patient-history.css">
<link rel="stylesheet" href="../../css/theme.css">
</head>

<body class="dark-mode" data-theme="dark">
<div class="container-fluid">
    <div class="row">

        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
        <div>
            <!-- LOGO & BRAND -->
             <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

            <div class="sidebar-menu-wrapper">

                <!-- SECTION: CLINICAL SERVICES & PATIENTS -->
                <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                    Clinical Services
                </small>

                <ul class="nav flex-column mb-3">
                    <!-- Patient Management -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                        data-bs-toggle="collapse"
                        href="#patientsMenu"
                        role="button"
                        aria-expanded="false"
                        aria-controls="patientsMenu">
                            <span>
                                <i class="bi bi-people-fill me-2 text-primary"></i>
                                Patient Management
                            </span>
                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="patientsMenu">
                            <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                <li class="py-1">
                                    <a href="patient-list.php" class="sidebar-submenu-link text-decoration-none">
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
                    
                    <!-- Patient Records (Active on Patient History) -->
                    <li class="nav-item">
                        <a class="nav-link active sidebar-collapse-link active-parent d-flex justify-content-between align-items-center"
                        data-bs-toggle="collapse"
                        href="#recordsMenu"
                        role="button"
                        aria-expanded="true"
                        aria-controls="recordsMenu">
                            <span>
                                <i class="bi bi-folder2-open me-2 text-warning"></i>
                                Patient Records
                            </span>
                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse show" id="recordsMenu">
                            <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                <li class="py-1">
                                    <a href="patient-history.php" class="sidebar-submenu-link active text-decoration-none">
                                        <i class="bi bi-clock-history me-2"></i>
                                       Patient Records
                                    </a>
                                </li>
                                <li class="py-1">
                                    <a href="reports.php" class="sidebar-submenu-link text-decoration-none">
                                        <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                        Reports
                                    </a>
                                </li>
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

        <!-- LOGOUT FOOTER -->
        <div class="logout-section pt-3 px-2 border-top border-secondary border-opacity-25">
            <a href="../../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-left fs-5"></i>
                Log out
            </a>
        </div>
    </nav>

    <main class="col-md-9 col-lg-10 p-2 main-content">
        <div class="page-container">
        <!-- TOP NAVIGATION -->
        <div class="top-navigation fade-up fade-delay-1">
            <div class="breadcrumb-area">
                <i class="bi bi-house-door-fill"></i>
                <span>Admin</span>
                <i class="bi bi-chevron-right"></i>
                <span>Patient Records</span>
            </div>
        </div>   
            <!-- HERO -->
            <div class="hero-section fade-up fade-delay-2">
                <div class="hero-content">
                    <!-- Title with Icon -->
                    <div class="hero-title">
                        <div class="hero-icon">
                            <i class="bi bi-folder2-open"></i>
                        </div>
                        <h1>Patient Records</h1>
                    </div>
                    <p>View patient medical history and previous checkup records.</p>
                    <div class="hero-date">
                        <i class="bi bi-calendar3"></i>
                        <?= date('l, F d, Y') ?>
                    </div>
                </div>
            </div>
            <!-- STATISTICS -->
            <div class="row g-3">

                <!-- TOTAL PATIENTS -->
                <div class="col-12 col-md-4">
                    <div class="stat-card fade-up fade-delay-2">

                        <div class="stat-header">

                            <span class="stat-title">
                                TOTAL PATIENTS
                            </span>

                            <div class="stat-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>

                        </div>

                        <div class="stat-number">
                            <?= number_format($total_patients) ?>
                        </div>

                        <div class="stat-description">
                            Registered patients
                        </div>

                    </div>
                </div>


                 <!-- TOTAL CHECKUPS -->
                <div class="col-12 col-md-4">
                    <div class="stat-card fade-up fade-delay-3">
                        <div class="stat-header">
                            <span class="stat-title">
                                TOTAL CHECKUPS
                            </span>
                            <div class="stat-icon">
                                <i class="bi bi-clipboard2-pulse"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= number_format($total_checkups) ?></div>
                        <div class="stat-description">
                            Completed checkups
                        </div>
                    </div>
                </div>


                <!-- CURRENT DATE -->
                <div class="col-12 col-md-4">
                    <div class="stat-card fade-up fade-delay-4">

                        <div class="stat-header">

                            <span class="stat-title">
                                CURRENT DATE
                            </span>

                            <div class="stat-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>

                        </div>

                        <div class="stat-number" style="font-size:20px;">
                            <?= date('M d, Y') ?>
                        </div>

                        <div class="stat-description">
                            Philippines local time
                        </div>

                    </div>
                </div>

            </div>
            <!-- RECORDS -->
            <div class="records-card fade-up fade-delay-4">
                <!-- HEADER -->
                <div class="records-header">
                    <div class="records-heading">
                        <div class="records-icon">
                            <i class="bi bi-person-vcard"></i>
                        </div>
                        <div>
                            <h5>
                                Patient History
                            </h5>
                            <p>
                                Browse registered patient records
                            </p>
                        </div>
                    </div>
                    <!-- SEARCH -->
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input
                            type="text"
                            id="recordSearch"
                            class="search-input"
                            placeholder="Search patient..."
                            autocomplete="off"
                        >
                    </div>
                </div>
                <!-- TABLE -->
                <div class="table-wrapper">
                    <table class="patient-table"id="recordTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Code Number</th>
                                <th>Last Visit</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php mysqli_data_seek($query, 0);?>
                        <?php if ($total_patients > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($query)): ?>
                                <?php
                                $fullname = trim($row['fullname']);
                                $parts = preg_split(
                                    '/\s+/',
                                    $fullname
                                );

                                $initials = '';

                                if (!empty($parts[0])) {

                                    $initials .= strtoupper(
                                        substr(
                                            $parts[0],
                                            0,
                                            1
                                        )
                                    );

                                }

                                if (count($parts) > 1) {

                                    $initials .= strtoupper(
                                        substr(
                                            $parts[count($parts)-1],
                                            0,
                                            1
                                        )
                                    );

                                }

                                $has_record =
                                    !empty($row['last_visit']);

                                ?>
                                <tr>
                                    <!-- PATIENT -->
                                    <td>
                                        <div class="patient-profile">
                                            <div class="patient-avatar">
                                                <i class="bi bi-person">&nbsp;</i>
                                                <?= $initials ?: 'P' ?>
                                            </div>
                                            <div>
                                                <div class="patient-name">
                                                    <?= htmlspecialchars(
                                                        $row['fullname']
                                                    ) ?>
                                                </div>
                                                <div class="patient-id">
                                                    Patient ID:
                                                    #<?= $row['user_id'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- CODE -->
                                    <td data-label="Code Number">
                                        <span class="code-badge">
                                            <i class="bi bi-upc-scan"></i>
                                            <?= htmlspecialchars(
                                                $row['code_number']
                                            ) ?>
                                        </span>
                                    </td>
                                    <!-- LAST VISIT -->
                                    <td data-label="Last Visit">
                                        <?php if ($has_record): ?>
                                            <div>
                                                <div class="visit-date">
                                                    <?= date(
                                                        'M d, Y',
                                                        strtotime(
                                                            $row['last_visit']
                                                        )
                                                    ) ?>
                                                </div>
                                                <div class="visit-time">
                                                    <?= date(
                                                        'h:i A',
                                                        strtotime(
                                                            $row['last_visit']
                                                        )
                                                    ) ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-record">
                                                <i class="bi bi-dash-circle"></i>
                                                No Record
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- STATUS -->
                                    <td data-label="Status">
                                        <?php if ($has_record): ?>
                                            <span class="status status-active">
                                                <span class="status-dot"></span>
                                                Active Record
                                            </span>

                                        <?php else: ?>
                                            <span class="status status-none">
                                                <span class="status-dot"></span>
                                                No Record Yet
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- ACTION -->
                                    <td data-label="Action"class="text-end">
                                        <a href="patient-history-list.php?user_id=<?= $row['user_id'] ?>"class="history-button">
                                            <i class="bi bi-clock-history"></i>
                                            View History
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>

                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="padding:0;">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="bi bi-person-x"></i>
                                        </div>
                                        <h5>No Patient Records</h5>
                                        <p>There are currently no registered patients.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- FOOTER -->
                <div class="records-footer">
                    <i class="bi bi-info-circle me-1"></i>
                    Showing
                    <strong>
                        <?= number_format($total_patients) ?>
                    </strong>
                    registered patient
                    <?= $total_patients != 1 ? 's' : '' ?>.
                </div>
            </div>
        </div>
    </main>
 </div>
</div> 
<!-- SEARCH -->
<script>
const searchInput =
    document.getElementById('recordSearch');

const tableRows =
    document.querySelectorAll(
        '#recordTable tbody tr'
    );

searchInput.addEventListener(
    'input',
    function(){

        const search =
            this.value
                .toLowerCase()
                .trim();

        tableRows.forEach(
            function(row){

                const text =
                    row.innerText.toLowerCase();
                if(text.includes(search)){
                    row.style.display = '';
                }else{
                    row.style.display = 'none';

                }

            }
        );

    }
);

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/theme.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const searchInput = document.getElementById("recordSearch");

    const tableRows = document.querySelectorAll(
        "#recordTable tbody tr"
    );
    /* =========================================
       TABLE ROW FADE-UP ANIMATION
    ========================================= */
    tableRows.forEach(function(row, index) {
        setTimeout(function() {
            row.classList.add("table-row-visible");
        }, index * 100);
    });
    /* =========================================
       SEARCH
    ========================================= */
    searchInput.addEventListener("input", function () {
        const search = this.value
            .toLowerCase()
            .trim();
        tableRows.forEach(function(row) {
            const text = row.innerText.toLowerCase();
            if (text.includes(search)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    });
});
</script>
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
