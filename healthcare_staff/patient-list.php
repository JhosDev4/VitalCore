<?php

session_start();

if (
    !isset($_SESSION['staff_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Staff'
) {
    header("Location: ../login.php");
    exit();
}

    $conn = mysqli_connect("localhost", "root", "", "vitalcore_db");

    if (!$conn) {
        die("Connection Failed: " . mysqli_connect_error());
    }
    /* TOTAL PATIENTS */
    $count_query = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM users WHERE role='patient'"
    );

    $count_row = mysqli_fetch_assoc($count_query);
    $patient_count = $count_row['total'];

    /* PATIENT LIST */
    $patients = mysqli_query(
        $conn,
        "SELECT * FROM users WHERE role='patient' ORDER BY id DESC"
    );

    if (!$patients) {
        die("Patient Query Error: " . mysqli_error($conn));
    }

    // NEW: Get the first patient's ID for the sidebar link
    $first_patient_id = null;
    if (mysqli_num_rows($patients) > 0) {
        // Read the first row (the most recent patient)
        $first_row = mysqli_fetch_assoc($patients);
        $first_patient_id = $first_row['id'];
        
        // Reset database cursor back to the beginning so our loop can print all patients
        mysqli_data_seek($patients, 0);
    }
    ?>

    <!DOCTYPE html>
   <html lang="en" translate="no">
    <head>
    <!-- PRE-LOAD STAFF INDEPENDENT DARK MODE (PREVENTS SCREEN FLASH) -->
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
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Patient List</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/patient-list.css">
    <link rel="stylesheet" href="../css/theme.css">

    <!-- STAFF DARK MODE OVERRIDE STYLES -->
    <style>
        html.dark-mode, html[data-bs-theme="dark"],
        body.dark-mode, body[data-bs-theme="dark"] {
            background-color: #121824 !important;
            color: #e2e8f0 !important;
        }

        html.dark-mode .sidebar, body.dark-mode .sidebar {
            background-color: #1a202c !important;
            border-color: #2d3748 !important;
        }

        html.dark-mode .card, body.dark-mode .card,
        html.dark-mode .patient-card, body.dark-mode .patient-card,
        html.dark-mode .modal-content, body.dark-mode .modal-content {
            background-color: #1e293b !important;
            color: #f1f5f9 !important;
            border-color: #334155 !important;
        }

        html.dark-mode .table, body.dark-mode .table {
            color: #cbd5e1 !important;
        }

        html.dark-mode .form-control, body.dark-mode .form-control,
        html.dark-mode .form-select, body.dark-mode .form-select {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }

        html.dark-mode h1, html.dark-mode h2, html.dark-mode h3, html.dark-mode h4, html.dark-mode h5, html.dark-mode h6,
        body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
        html.dark-mode .text-dark, body.dark-mode .text-dark,
        html.dark-mode .patient-title, body.dark-mode .patient-title {
            color: #f8fafc !important;
        }

        html.dark-mode .dropdown-menu, body.dark-mode .dropdown-menu {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }

        html.dark-mode .dropdown-item, body.dark-mode .dropdown-item {
            color: #f1f5f9 !important;
        }

        html.dark-mode .dropdown-item:hover, body.dark-mode .dropdown-item:hover {
            background-color: #334155 !important;
        }
    </style>
    </head>

    <body>

    <div class="container-fluid">
        <div class="row">

        <!-- SIDEBAR -->
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
                        <!-- Patients / Patient Management (Active on Patient List) -->
                        <li class="nav-item">
                            <a class="nav-link active sidebar-collapse-link active-parent d-flex justify-content-between align-items-center"
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
                                        <a href="patient-history.php" class="sidebar-submenu-link text-decoration-none">
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
                <a href="../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left fs-5"></i>
                    Log out
                </a>
            </div>
        </nav>

            <!-- MAIN CONTENT -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

               <div class="d-flex align-items-center justify-content-between flex-wrap mb-4 page-header gap-3">

                <!-- PATIENT GRID HEADER WITH CATEGORIES & DARK MODE ALIGNED SIDE-BY-SIDE -->
                <div class="patient-grid-header d-flex align-items-center gap-2">

                    <h4 class="fw-bold mb-0 patient-title me-2">
                        Patient Grid
                    </h4>

                    <!-- CATEGORIES DROPDOWN -->
                    <div class="dropdown">
                        <button class="btn patient-menu-btn"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            <i class="bi bi-list"></i>
                            <span>Categories</span>
                        </button>

                        <ul class="dropdown-menu patient-dropdown shadow border-0">
                            <li>
                                <a class="dropdown-item" href="Service/vital-screening.php">
                                    <i class="bi bi-clipboard2-pulse-fill text-warning me-2"></i>
                                    Vital Screening Grid
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="Service/prenatal.php">
                                    <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                                    Prenatal Grid
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="Service/child-immunization.php">
                                    <i class="bi bi-shield-check text-success me-2"></i>
                                    Child Immunization Grid
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="Service/family-planning.php">
                                    <i class="bi bi-people-fill text-primary me-2"></i>
                                    Family Planning Grid
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- DARK MODE BUTTON ALIGNED SIDE-BY-SIDE WITH CATEGORIES -->
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" id="staffThemeToggleBtn" type="button">
                        <i class="bi bi-moon-stars-fill" id="staffThemeIcon"></i>
                        <span id="staffThemeText">Dark Mode</span>
                    </button>

                </div>

                <div class="d-flex align-items-center gap-2">

                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="patientSearchInput" class="form-control rounded-pill ps-5 bg-light border-light-subtle" placeholder="Search patient name..." style="font-size: 0.85rem; width: 230px;">
                    </div>

                    <span class="badge bg-primary">
                        Total Patients : <?= $patient_count ?>
                    </span>

                </div>

            </div>

                <div class="row g-4">

                    <?php while($row = mysqli_fetch_assoc($patients)): ?>

                    <div class="col-12 col-sm-6 col-xl-4">

                        <div class="card patient-card h-100">

                            <div class="card-body">

                                <div class="d-flex justify-content-between">

                                    <div>
                                        <h6 class="fw-bold mb-1">
                                            <?= htmlspecialchars($row['fullname']) ?>
                                        </h6>

                                        <small class="text-muted">
                                            <?= $row['age'] ?? '--' ?>,
                                            <?= $row['gender'] ?? 'N/A' ?>
                                        </small>
                                    </div>

                                    <a href="patient-view.php?id=<?= $row['id'] ?>">
                                        <i class="bi bi-person-lines-fill"></i>
                                    </a>

                                </div>

                                <hr>

                                <p class="small text-muted mb-2">
                                    <i class="bi bi-person-badge"></i>
                                    Patient Code:
                                    <?= htmlspecialchars($row['code_number']) ?>
                                </p>

                                <p class="small text-muted mb-0">
                                    <i class="bi bi-geo-alt"></i>
                                    <?= $row['address'] ?? 'No Address' ?>
                                </p>

                            </div>

                        </div>

                    </div>

                    <?php endwhile; ?>

                </div>
            </main>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- INDEPENDENT STAFF DARK MODE LOGIC -->
<script>
function applyStaffThemeUI() {
    const isDark = document.documentElement.classList.contains('dark-mode') || 
                   document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const icon = document.getElementById('staffThemeIcon');
    const text = document.getElementById('staffThemeText');
    
    if (isDark) {
        if (icon) icon.className = 'bi bi-sun-fill text-warning';
        if (text) text.textContent = 'Light Mode';
    } else {
        if (icon) icon.className = 'bi bi-moon-stars-fill';
        if (text) text.textContent = 'Dark Mode';
    }
}

function toggleStaffTheme() {
    const isDark = document.documentElement.classList.contains('dark-mode') || 
                   document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const newIsDark = !isDark;
    
    if (newIsDark) {
        document.documentElement.classList.add('dark-mode');
        if (document.body) document.body.classList.add('dark-mode');
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        if (document.body) document.body.setAttribute('data-bs-theme', 'dark');
        localStorage.setItem('staff_theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark-mode');
        if (document.body) document.body.classList.remove('dark-mode');
        document.documentElement.setAttribute('data-bs-theme', 'light');
        if (document.body) document.body.setAttribute('data-bs-theme', 'light');
        localStorage.setItem('staff_theme', 'light');
    }
    applyStaffThemeUI();
}

document.addEventListener("DOMContentLoaded", function () {
    // Sync body tag on page ready
    const savedTheme = localStorage.getItem('staff_theme');
    if (savedTheme === 'dark') {
        if (document.body) {
            document.body.classList.add('dark-mode');
            document.body.setAttribute('data-bs-theme', 'dark');
        }
    }
    applyStaffThemeUI();
    
    const btn = document.getElementById('staffThemeToggleBtn');
    if (btn) {
        btn.onclick = toggleStaffTheme;
    }
});
</script>

<script>
document.getElementById('patientSearchInput').addEventListener('input', function () {

    const searchValue = this.value.toLowerCase().trim();

    const patientCards = document.querySelectorAll(
        '.row.g-4 > .col-12.col-sm-6.col-xl-4'
    );

    patientCards.forEach(function (card) {

        const patientName = card
            .querySelector('h6')
            .textContent
            .toLowerCase();

        if (patientName.includes(searchValue)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }

    });

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