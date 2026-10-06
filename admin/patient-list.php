<?php
session_start();

/* =========================
   SECURITY CHECK
========================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "vitalcore_db");

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

/* Helper: JSON-encode template variables for data-vars attributes */
function vars_attr(array $vars): string {
    return htmlspecialchars(json_encode($vars, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

/* TOTAL PATIENTS */
$count_query = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role='patient'"
);

$count_row = mysqli_fetch_assoc($count_query);
$patient_count = (int)$count_row['total'];

/* PATIENT LIST */
$patients = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE role='patient' ORDER BY id DESC"
);

if (!$patients) {
    die("Patient Query Error: " . mysqli_error($conn));
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
    <meta name="google" content="notranslate">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Patient List</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/patient-list.css">
    <link rel="stylesheet" href="../css/theme.css">

    <style>
        /* Fallback Dark Mode Styles (same approach as setting.php) */
        body.dark-mode, html.dark-mode body {
            background-color: #121824 !important;
            color: #e2e8f0 !important;
        }
        html.dark-mode .card,
        html.dark-mode .patient-card,
        html.dark-mode .dropdown-menu {
            background-color: #1e293b !important;
            color: #e2e8f0 !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        html.dark-mode .dropdown-item { color: #e2e8f0 !important; }
        html.dark-mode .dropdown-item:hover,
        html.dark-mode .dropdown-item:focus {
            background-color: rgba(255,255,255,0.08) !important;
        }
        html.dark-mode .text-dark { color: #f8fafc !important; }
        html.dark-mode .text-muted { color: #94a3b8 !important; }
        html.dark-mode .form-control,
        html.dark-mode .form-control.bg-light {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        html.dark-mode hr { border-color: rgba(255,255,255,0.15); opacity: 1; }
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

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-grid-1x2-fill me-2"></i>
                                <span data-i18n="nav_dashboard">Dashboard</span>
                            </a>
                        </li>
                    </ul>

                    <!-- SECTION: CLINICAL SERVICES & PATIENTS -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_clinical">Clinical Services</small>

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

        <!-- MAIN CONTENT -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex align-items-center justify-content-between flex-wrap mb-4 page-header">

                <div class="patient-grid-header d-flex align-items-center">

                    <h4 class="fw-bold mb-0 patient-title" data-i18n="pg_title">
                        Patient Grid
                    </h4>

                    <div class="dropdown">
                        <button class="btn patient-menu-btn"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">

                            <i class="bi bi-list"></i>
                            <span data-i18n="categories">Categories</span>

                        </button>

                        <ul class="dropdown-menu patient-dropdown shadow border-0">

                            <li>
                                <a class="dropdown-item" href="Service/vital-screening.php">
                                    <i class="bi bi-clipboard2-pulse-fill text-warning me-2"></i>
                                    <span data-i18n="cat_vital">Vital Screening Grid</span>
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="Service/prenatal.php">
                                    <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                                    <span data-i18n="cat_prenatal">Prenatal Grid</span>
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="Service/family-planning.php">
                                    <i class="bi bi-people-fill text-primary me-2"></i>
                                    <span data-i18n="cat_family">Family Planning Grid</span>
                                </a>
                            </li>

                        </ul>
                    </div>

                </div>

                <div class="d-flex align-items-center gap-2">

                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="patientSearchInput" class="form-control rounded-pill ps-5 bg-light border-light-subtle" placeholder="Search patient name..." data-i18n-placeholder="ph_search" style="font-size: 0.85rem; width: 230px;">
                    </div>

                    <span class="badge bg-primary" data-i18n="total_patients" data-vars="<?= vars_attr(['n' => (string)$patient_count]); ?>">
                        Total Patients : <?= $patient_count ?>
                    </span>

                    <span class="small text-muted" id="liveClock"></span>

                </div>

            </div>

            <div class="row g-4">

                <?php while($row = mysqli_fetch_assoc($patients)): ?>

                <div class="col-12 col-sm-6 col-xl-4 patient-col">

                    <div class="card patient-card h-100">

                        <div class="card-body">

                            <div class="d-flex justify-content-between">

                                <div>
                                    <h6 class="fw-bold mb-1">
                                        <?= htmlspecialchars((string)$row['fullname']) ?>
                                    </h6>

                                    <small class="text-muted">
                                        <?= htmlspecialchars((string)($row['age'] ?? '--')) ?>,
                                        <?= htmlspecialchars((string)($row['gender'] ?? 'N/A')) ?>
                                    </small>
                                </div>

                                 <a href="patient-view.php?id=<?= (int)$row['id'] ?>">
                                    <i class="bi bi-person-lines-fill"></i>
                                </a>

                            </div>

                            <hr>

                            <p class="small text-muted mb-2">
                                <i class="bi bi-person-badge"></i>
                                <span data-i18n="patient_code">Patient Code:</span>
                                <?= htmlspecialchars((string)$row['code_number']) ?>
                            </p>

                            <p class="small text-muted mb-0">
                                <i class="bi bi-geo-alt"></i>
                                <?php if (!empty($row['address'])): ?>
                                    <?= htmlspecialchars($row['address']) ?>
                                <?php else: ?>
                                    <span data-i18n="no_address">No Address</span>
                                <?php endif; ?>
                            </p>

                        </div>

                    </div>

                </div>

                <?php endwhile; ?>

            </div>

            <!-- Shown when the search matches nobody -->
            <div id="noResults" class="text-center text-muted py-5 d-none">
                <i class="bi bi-person-x fs-2 d-block mb-2 opacity-50"></i>
                <span data-i18n="no_results">No patients found.</span>
            </div>
        </main>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/theme.js"></script>

<!-- SEARCH -->
<script>
document.getElementById('patientSearchInput').addEventListener('input', function () {

    const searchValue = this.value.toLowerCase().trim();
    const patientCards = document.querySelectorAll('.patient-col');
    let visible = 0;

    patientCards.forEach(function (card) {

        const patientName = card.querySelector('h6').textContent.toLowerCase();
        const match = patientName.includes(searchValue);

        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    const noResults = document.getElementById('noResults');
    if (noResults) {
        noResults.classList.toggle('d-none', visible > 0 || patientCards.length === 0);
    }
});
</script>

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
            pg_title: "Grid ng Pasyente",
            categories: "Mga Kategorya",
            cat_vital: "Grid ng Vital Screening",
            cat_prenatal: "Grid ng Prenatal",
            cat_family: "Grid ng Family Planning",
            ph_search: "Maghanap ng pangalan ng pasyente...",
            total_patients: "Kabuuang Pasyente : {n}",
            patient_code: "Code ng Pasyente:",
            no_address: "Walang Tirahan",
            no_results: "Walang nakitang pasyente."
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
            pg_title: "Grid sa Pasyente",
            categories: "Mga Kategorya",
            cat_vital: "Grid sa Vital Screening",
            cat_prenatal: "Grid sa Prenatal",
            cat_family: "Grid sa Family Planning",
            ph_search: "Pangita og ngalan sa pasyente...",
            total_patients: "Total nga Pasyente : {n}",
            patient_code: "Code sa Pasyente:",
            no_address: "Walay Adres",
            no_results: "Walay nakitang pasyente."
        }
    };

    const lang = store.get("language", "en");
    if (i18n[lang]) {
        const dict = i18n[lang];
        document.documentElement.lang = lang;

        document.querySelectorAll("[data-i18n]").forEach(el => {
            let text = dict[el.getAttribute("data-i18n")];
            if (!text) return;

            // Fill {placeholders} from data-vars (e.g. total patients)
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
    // 5. Date & Time Format (live clock next to the patient total)
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
</body>
</html>
<?php
mysqli_close($conn);
?>