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
$patient_count = (int) $count_row['total'];

/* PATIENT LIST */
$patients = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE role='patient' ORDER BY id DESC"
);

if (!$patients) {
    die("Patient Query Error: " . mysqli_error($conn));
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" translate="no">
<head>

    <!-- PRE-LOAD SETTINGS (prevents flicker). Reads the same keys as setting.php -->
    <script>
    (function () {
        try {
            // "theme" (setting.php) wins; fall back to the old "staff_theme" key
            var theme = localStorage.getItem('theme') || localStorage.getItem('staff_theme');
            var root = document.documentElement;
            if (theme === 'dark') {
                root.classList.add('dark-mode');
                root.setAttribute('data-bs-theme', 'dark');
            } else {
                root.classList.remove('dark-mode');
                root.setAttribute('data-bs-theme', 'light');
            }

            var sizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
            var size = localStorage.getItem('textSize');
            if (size && sizes[size]) root.style.fontSize = sizes[size];
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
        /* Night Light Warm Amber Overlay (controlled by setting.php) */
        #nightLightOverlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(255, 140, 0, 0.18);
            pointer-events: none;
            z-index: 99999;
            display: none;
        }

        /* Dark mode overrides */
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

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

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
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_clinical">
                        Clinical Services
                    </small>

                    <ul class="nav flex-column mb-3">
                        <!-- Patient Management (Active on Patient List) -->
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
                                        <a href="patient-history.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-clock-history me-2"></i>
                                            <span data-i18n="nav_records_history">Patient Records</span>
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="reports.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                            <span data-i18n="nav_reports">Reports</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

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

            <div class="d-flex align-items-center justify-content-between flex-wrap mb-4 page-header gap-3">

                <!-- PATIENT GRID HEADER -->
                <div class="patient-grid-header d-flex align-items-center gap-2">

                    <h4 class="fw-bold mb-0 patient-title me-2" data-i18n="page_title">Patient Grid</h4>

                    <!-- CATEGORIES DROPDOWN -->
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
                                <a class="dropdown-item" href="Service/child-immunization.php">
                                    <i class="bi bi-shield-check text-success me-2"></i>
                                    <span data-i18n="cat_immunization">Child Immunization Grid</span>
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

                    <!-- DARK MODE BUTTON (syncs with setting.php) -->
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" id="staffThemeToggleBtn" type="button">
                        <i class="bi bi-moon-stars-fill" id="staffThemeIcon"></i>
                        <span id="staffThemeText">Dark Mode</span>
                    </button>

                </div>

                <div class="d-flex align-items-center gap-2">

                    <!-- Live clock (uses the Date & Time Format setting) -->
                    <span class="badge bg-info text-dark" id="clockLivePreview">--:--</span>

                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text"
                               id="patientSearchInput"
                               class="form-control rounded-pill ps-5 bg-light border-light-subtle"
                               placeholder="Search patient name..."
                               data-i18n-placeholder="search_placeholder"
                               style="font-size: 0.85rem; width: 230px;">
                    </div>

                    <span class="badge bg-primary">
                        <span data-i18n="total_patients">Total Patients</span> : <?= $patient_count ?>
                    </span>

                </div>

            </div>

            <div class="row g-4">

                <?php while ($row = mysqli_fetch_assoc($patients)): ?>

                <div class="col-12 col-sm-6 col-xl-4">

                    <div class="card patient-card h-100">

                        <div class="card-body">

                            <div class="d-flex justify-content-between">

                                <div>
                                    <h6 class="fw-bold mb-1">
                                        <?= e($row['fullname']) ?>
                                    </h6>

                                    <small class="text-muted">
                                        <?= e($row['age'] ?? '--') ?>,
                                        <?= e($row['gender'] ?? 'N/A') ?>
                                    </small>
                                </div>

                                <a href="patient-view.php?id=<?= (int) $row['id'] ?>">
                                    <i class="bi bi-person-lines-fill"></i>
                                </a>

                            </div>

                            <hr>

                            <p class="small text-muted mb-2">
                                <i class="bi bi-person-badge"></i>
                                <span data-i18n="patient_code">Patient Code:</span>
                                <?= e($row['code_number']) ?>
                            </p>

                            <p class="small text-muted mb-0">
                                <i class="bi bi-geo-alt"></i>
                                <?= e($row['address'] ?? 'No Address') ?>
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

<!-- PATIENT SEARCH -->
<script>
document.getElementById('patientSearchInput').addEventListener('input', function () {
    const searchValue = this.value.toLowerCase().trim();
    const patientCards = document.querySelectorAll('.row.g-4 > .col-12.col-sm-6.col-xl-4');

    patientCards.forEach(function (card) {
        const patientName = card.querySelector('h6').textContent.toLowerCase();
        card.style.display = patientName.includes(searchValue) ? '' : 'none';
    });
});
</script>

<!-- SETTINGS ENGINE (reads the preferences saved by setting.php) -->
<script>
(function () {

    // Safe localStorage helpers
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

    const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };

    // Same languages as setting.php, plus keys for this page
    const i18n = {
        en: {
            section_clinical: "Clinical Services",
            nav_patient_mgmt: "Patient Management",
            nav_all_patients: "All Patients Services",
            nav_add_patient: "Add Patient",
            nav_patient_records: "Patient Records",
            nav_records_history: "Patient Records",
            nav_reports: "Reports",
            nav_admin: "Administration",
            nav_settings: "Settings",
            nav_logout: "Log out",
            page_title: "Patient Grid",
            categories: "Categories",
            cat_vital: "Vital Screening Grid",
            cat_prenatal: "Prenatal Grid",
            cat_immunization: "Child Immunization Grid",
            cat_family: "Family Planning Grid",
            dark_mode: "Dark Mode",
            light_mode: "Light Mode",
            search_placeholder: "Search patient name...",
            total_patients: "Total Patients",
            patient_code: "Patient Code:"
        },
        fil: {
            section_clinical: "Serbisyong Klinikal",
            nav_patient_mgmt: "Pamamahala ng Pasyente",
            nav_all_patients: "Lahat ng Serbisyong Pasyente",
            nav_add_patient: "Magdagdag ng Pasyente",
            nav_patient_records: "Mga Rekord ng Pasyente",
            nav_records_history: "Kasaysayan ng Rekord",
            nav_reports: "Mga Ulat",
            nav_admin: "Administrasyon",
            nav_settings: "Mga Setting",
            nav_logout: "Mag-logout",
            page_title: "Grid ng Pasyente",
            categories: "Mga Kategorya",
            cat_vital: "Grid ng Vital Screening",
            cat_prenatal: "Grid ng Prenatal",
            cat_immunization: "Grid ng Bakuna ng Bata",
            cat_family: "Grid ng Family Planning",
            dark_mode: "Dark Mode",
            light_mode: "Light Mode",
            search_placeholder: "Maghanap ng pangalan ng pasyente...",
            total_patients: "Kabuuang Pasyente",
            patient_code: "Code ng Pasyente:"
        },
        ceb: {
            section_clinical: "Mga Serbisyong Klinikal",
            nav_patient_mgmt: "Pagdumala sa Pasyente",
            nav_all_patients: "Tanan nga Serbisyong Pasyente",
            nav_add_patient: "Idugang ang Pasyente",
            nav_patient_records: "Mga Rekord sa Pasyente",
            nav_records_history: "Kasaysayan sa Rekord",
            nav_reports: "Mga Report",
            nav_admin: "Administrasyon",
            nav_settings: "Mga Setting",
            nav_logout: "Mo-logout",
            page_title: "Grid sa Pasyente",
            categories: "Mga Kategorya",
            cat_vital: "Grid sa Vital Screening",
            cat_prenatal: "Grid sa Prenatal",
            cat_immunization: "Grid sa Bakuna sa Bata",
            cat_family: "Grid sa Family Planning",
            dark_mode: "Dark Mode",
            light_mode: "Light Mode",
            search_placeholder: "Pangita og ngalan sa pasyente...",
            total_patients: "Tibuok nga Pasyente",
            patient_code: "Code sa Pasyente:"
        }
    };

    function t(key) {
        const lang = store.get("language", "en");
        return (i18n[lang] && i18n[lang][key]) || i18n.en[key] || key;
    }

    // ---------- Theme ----------
    function isDark() {
        return document.documentElement.classList.contains("dark-mode");
    }

    function setDark(on) {
        document.documentElement.classList.toggle("dark-mode", on);
        document.body.classList.toggle("dark-mode", on);
        document.documentElement.setAttribute("data-bs-theme", on ? "dark" : "light");
        document.body.setAttribute("data-bs-theme", on ? "dark" : "light");
        updateThemeButton();
    }

    function updateThemeButton() {
        const icon = document.getElementById("staffThemeIcon");
        const text = document.getElementById("staffThemeText");
        if (isDark()) {
            if (icon) icon.className = "bi bi-sun-fill text-warning";
            if (text) text.textContent = t("light_mode");
        } else {
            if (icon) icon.className = "bi bi-moon-stars-fill";
            if (text) text.textContent = t("dark_mode");
        }
    }

    function applyTheme() {
        const saved = store.get("theme") || store.get("staff_theme");
        setDark(saved === "dark");
    }

    // The button on this page writes BOTH keys so setting.php and other staff pages stay in sync
    const themeBtn = document.getElementById("staffThemeToggleBtn");
    if (themeBtn) {
        themeBtn.addEventListener("click", function () {
            const next = !isDark();
            setDark(next);
            store.set("theme", next ? "dark" : "light");
            store.set("staff_theme", next ? "dark" : "light");
        });
    }

    // ---------- Night Light ----------
    function applyNightLight() {
        const overlay = document.getElementById("nightLightOverlay");
        if (overlay) overlay.style.display = store.get("nightLight") === "enabled" ? "block" : "none";
    }

    // ---------- Text Size ----------
    function applyTextSize() {
        const size = store.get("textSize", "normal");
        document.documentElement.style.fontSize = fontSizes[size] || "100%";
    }

    // ---------- Language ----------
    function applyLanguage() {
        const lang = store.get("language", "en");
        const dict = i18n[lang] || i18n.en;
        document.documentElement.lang = i18n[lang] ? lang : "en";

        document.querySelectorAll("[data-i18n]").forEach(function (el) {
            const key = el.getAttribute("data-i18n");
            if (dict[key]) el.textContent = dict[key];
        });

        document.querySelectorAll("[data-i18n-placeholder]").forEach(function (el) {
            const key = el.getAttribute("data-i18n-placeholder");
            if (dict[key]) el.placeholder = dict[key];
        });

        updateThemeButton();
    }

    // ---------- Date & Time Format ----------
    function updateClock() {
        const el = document.getElementById("clockLivePreview");
        if (!el) return;
        const opts = { hour: "2-digit", minute: "2-digit", second: "2-digit" };
        if (store.get("dateTimeFormat", "24h") === "12h") {
            opts.hour12 = true;
        } else {
            opts.hourCycle = "h23";
        }
        el.textContent = new Date().toLocaleTimeString("en-US", opts);
    }

    function applyAll() {
        applyTheme();
        applyNightLight();
        applyTextSize();
        applyLanguage();
        updateClock();
    }

    applyAll();
    setInterval(updateClock, 1000);

    // If Settings is changed in another tab, update this page live
    window.addEventListener("storage", applyAll);

})();
</script>

</body>
</html>
<?php
if ($conn) {
    mysqli_close($conn);
}
?>