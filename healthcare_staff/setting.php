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

?>
<!DOCTYPE html>
<html lang="en" translate="no">
<head>
    <script>
    // Global Settings Application Script (Prevents UI Flicker on Page Load)
    (function() {
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/theme.css">

    <style>
        /* Fallback Dark Mode Styles */
        body.dark-mode, html.dark-mode body {
            background-color: #121824 !important;
            color: #e2e8f0 !important;
        }
        html.dark-mode .card {
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
        html.dark-mode .form-select, html.dark-mode .form-control {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
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

        }

        .spin { animation: spin 0.8s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
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
                    </ul>

                    <!-- SECTION: HARDWARE & SYSTEM -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_system">
                        Hardware &amp; System
                    </small>

                    <ul class="nav flex-column mb-3">
                        <!-- Administration -->
                        <li class="nav-item">
                            <a class="nav-link active sidebar-collapse-link active-parent d-flex justify-content-between align-items-center"
                            data-bs-toggle="collapse"
                            href="#adminMenu"
                            role="button"
                            aria-expanded="true"
                            aria-controls="adminMenu">
                                <span>
                                    <i class="bi bi-shield-lock-fill me-2 text-danger"></i>
                                    <span data-i18n="nav_admin">Administration</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>

                            <div class="collapse show" id="adminMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="setting.php" class="sidebar-submenu-link active text-decoration-none">
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

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <!-- Header -->
            <div class="mb-4">
                <h4 class="fw-bold text-dark">
                    <i class="bi bi-gear-fill me-2 text-primary"></i>
                    <span data-i18n="header_title">System Settings</span>
                </h4>
                <p class="text-muted small mb-0" data-i18n="header_subtitle">
                    Configure VitalCore appearance and display preferences.
                </p>
            </div>

            <!-- Dark Mode -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body active-parent d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-1">
                            <i class="bi bi-moon-stars-fill me-2 text-primary"></i>
                            <span data-i18n="dark_mode_title">Dark Mode</span>
                        </h6>
                        <small class="text-muted" data-i18n="dark_mode_desc">
                            Switch between light and dark theme.
                        </small>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="darkModeToggle" role="switch">
                    </div>
                </div>
            </div>

            <!-- Night Light -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-1">
                            <i class="bi bi-lightbulb-fill me-2 text-warning"></i>
                            <span data-i18n="night_light_title">Night Light</span>
                        </h6>
                        <small class="text-muted" data-i18n="night_light_desc">
                            Reduce blue light for night viewing.
                        </small>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="nightLightToggle" role="switch">
                    </div>
                </div>
            </div>

            <!-- Text Size -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-1">
                        <i class="bi bi-fonts me-2 text-info"></i>
                        <span data-i18n="text_size_title">Text Size</span>
                    </h6>
                    <small class="text-muted d-block mb-2" data-i18n="text_size_desc">
                        Change the size of text in the system.
                    </small>

                    <select class="form-select" id="textScale">
                        <option value="xsmall" data-i18n="size_xsmall">Extra Small</option>
                        <option value="small" data-i18n="size_small">Small</option>
                        <option value="normal" selected data-i18n="size_normal">Normal</option>
                        <option value="large" data-i18n="size_large">Large</option>
                        <option value="xlarge" data-i18n="size_xlarge">Extra Large</option>
                    </select>
                </div>
            </div>

            <!-- Language -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-1">
                        <i class="bi bi-translate me-2 text-success"></i>
                        <span data-i18n="language_title">Language</span>
                    </h6>
                    <small class="text-muted d-block mb-2" data-i18n="language_desc">
                        Select the language used throughout the system.
                    </small>

                    <select class="form-select" id="languageSelect">
                        <option value="en" selected>English</option>
                        <option value="fil">Filipino (Tagalog)</option>
                        <option value="ceb">Cebuano (Bisaya)</option>
                    </select>
                </div>
            </div>

            <!-- Date & Time Format -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-clock-history me-2 text-info"></i>
                            <span data-i18n="datetime_title">Date &amp; Time Format</span>
                        </h6>
                        <span class="badge bg-info text-dark" id="clockLivePreview">--:--</span>
                    </div>
                    <small class="text-muted d-block mb-2" data-i18n="datetime_desc">
                        Customize how dates and times are displayed.
                    </small>

                    <select class="form-select" id="dateTimeFormat">
                        <option value="12h">12-Hour (08:30 PM)</option>
                        <option value="24h" selected>24-Hour (20:30)</option>
                    </select>
                </div>
            </div>

            <!-- Sensor Connection Status -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-1">
                                <i class="bi bi-cpu-fill me-2 text-primary"></i>
                                <span data-i18n="sensor_title">Sensor Connection Status</span>
                            </h6>
                            <small class="text-muted" data-i18n="sensor_desc">
                                View the current status of connected medical sensors.
                            </small>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm" id="refreshSensorsBtn">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            <span data-i18n="sensor_refresh">Refresh</span>
                        </button>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Temperature Sensor (MLX90614)</span>
                        <span class="badge bg-success sensor-badge" data-i18n="sensor_connected">Connected</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Weight Sensor (HX711)</span>
                        <span class="badge bg-success sensor-badge" data-i18n="sensor_connected">Connected</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Height Sensor (TF-Luna)</span>
                        <span class="badge bg-success sensor-badge" data-i18n="sensor_connected">Connected</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Heart Rate / SpO₂ (MAX30102)</span>
                        <span class="badge bg-success sensor-badge" data-i18n="sensor_connected">Connected</span>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span>Blood Pressure Monitor (Contec O8A)</span>
                        <span class="badge bg-success sensor-badge" data-i18n="sensor_connected">Connected</span>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============================================================================
// VITALCORE SETTINGS ENGINE
// ============================================================================

// Safe localStorage helpers (won't crash if storage is blocked)
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

// 1. DOM Elements
const darkModeToggle       = document.getElementById("darkModeToggle");
const nightLightToggle     = document.getElementById("nightLightToggle");
const nightLightOverlay    = document.getElementById("nightLightOverlay");
const textScaleSelect      = document.getElementById("textScale");
const languageSelect       = document.getElementById("languageSelect");
const dateTimeFormatSelect = document.getElementById("dateTimeFormat");
const clockLivePreview     = document.getElementById("clockLivePreview");
const refreshSensorsBtn    = document.getElementById("refreshSensorsBtn");

// 2. Language Dictionary (English, Filipino, Cebuano)
const i18n = {
    en: {
        section_main: "Main",
        nav_dashboard: "Dashboard",
        section_clinical: "Clinical Services",
        nav_patient_mgmt: "Patient Management",
        nav_all_patients: "All Patients Services",
        nav_add_patient: "Add Patient",
        nav_patient_records: "Patient Records",
        nav_records_history: "Patient Records",
        nav_reports: "Reports",
        section_system: "Hardware & System",
        nav_admin: "Administration",
        nav_user_mgmt: "User Management",
        nav_settings: "Settings",
        nav_logout: "Log out",
        header_title: "System Settings",
        header_subtitle: "Configure VitalCore appearance and display preferences.",
        dark_mode_title: "Dark Mode",
        dark_mode_desc: "Switch between light and dark theme.",
        night_light_title: "Night Light",
        night_light_desc: "Reduce blue light for night viewing.",
        text_size_title: "Text Size",
        text_size_desc: "Change the size of text in the system.",
        size_xsmall: "Extra Small",
        size_small: "Small",
        size_normal: "Normal",
        size_large: "Large",
        size_xlarge: "Extra Large",
        language_title: "Language",
        language_desc: "Select the language used throughout the system.",
        datetime_title: "Date & Time Format",
        datetime_desc: "Customize how dates and times are displayed.",
        sensor_title: "Sensor Connection Status",
        sensor_desc: "View the current status of connected medical sensors.",
        sensor_refresh: "Refresh",
        sensor_connected: "Connected"
    },
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
        header_title: "Mga Setting ng Sistema",
        header_subtitle: "I-configure ang hitsura at kagustuhan sa display ng VitalCore.",
        dark_mode_title: "Dark Mode",
        dark_mode_desc: "Lumipat sa pagitan ng maliwanag at madilim na tema.",
        night_light_title: "Night Light",
        night_light_desc: "Bawasan ang bughaw na liwanag sa gabi.",
        text_size_title: "Laki ng Teksto",
        text_size_desc: "Baguhin ang laki ng teksto sa sistema.",
        size_xsmall: "Napakaliit",
        size_small: "Maliit",
        size_normal: "Pangkaraniwan",
        size_large: "Malaki",
        size_xlarge: "Napakalaki",
        language_title: "Wika",
        language_desc: "Pumili ng wikang gagamitin sa buong sistema.",
        datetime_title: "Format ng Petsa at Oras",
        datetime_desc: "I-customize kung paano ipinapakita ang petsa at oras.",
        sensor_title: "Katayuan ng Koneksyon ng Sensor",
        sensor_desc: "Tingnan ang kasalukuyang katayuan ng mga nakakonektang medikal na sensor.",
        sensor_refresh: "I-refresh",
        sensor_connected: "Nakakonekta"
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
        header_title: "Mga Setting sa Sistema",
        header_subtitle: "I-configure ang hitsura ug mga gusto sa display sa VitalCore.",
        dark_mode_title: "Dark Mode",
        dark_mode_desc: "Pagbalhin tali sa hayag ug ngitngit nga tema.",
        night_light_title: "Night Light",
        night_light_desc: "Kuhai ang asul nga suga sa gabii.",
        text_size_title: "Gidak-on sa Teksto",
        text_size_desc: "Usba ang gidak-on sa teksto sa sistema.",
        size_xsmall: "Gamay Kaayo",
        size_small: "Gamay",
        size_normal: "Normal",
        size_large: "Dako",
        size_xlarge: "Dako Kaayo",
        language_title: "Pinulongan",
        language_desc: "Pilia ang pinulongan nga gamiton sa tibuok sistema.",
        datetime_title: "Format sa Petsa ug Oras",
        datetime_desc: "I-customize kon unsaon pagpakita ang petsa ug oras.",
        sensor_title: "Kahimtang sa Koneksyon sa Sensor",
        sensor_desc: "Tan-awa ang karon nga kahimtang sa konektado nga medikal nga mga sensor.",
        sensor_refresh: "I-refresh",
        sensor_connected: "Konektado"
    }
};

// 3. Apply Language
function applyLanguage(lang) {
    const dict = i18n[lang] || i18n.en;
    document.documentElement.lang = i18n[lang] ? lang : "en";
    document.querySelectorAll("[data-i18n]").forEach(el => {
        const key = el.getAttribute("data-i18n");
        if (dict[key]) {
            el.textContent = dict[key];
        }
    });
    store.set("language", lang);
}

// 4. Dark Mode
darkModeToggle.addEventListener("change", function () {
    document.body.classList.toggle("dark-mode", this.checked);
    document.documentElement.classList.toggle("dark-mode", this.checked);
    store.set("theme", this.checked ? "dark" : "light");
});

// 5. Night Light
nightLightToggle.addEventListener("change", function () {
    nightLightOverlay.style.display = this.checked ? "block" : "none";
    store.set("nightLight", this.checked ? "enabled" : "disabled");
});

// 6. Text Size
const fontSizes = {
    xsmall: "80%",
    small: "85%",
    normal: "100%",
    large: "115%",
    xlarge: "130%"
};
textScaleSelect.addEventListener("change", function () {
    document.documentElement.style.fontSize = fontSizes[this.value] || "100%";
    store.set("textSize", this.value);
});

// 7. Language
languageSelect.addEventListener("change", function () {
    applyLanguage(this.value);
});

// 8. Date & Time Format + Live Preview Clock
function updateClockPreview() {
    const now = new Date();
    const opts = { hour: "2-digit", minute: "2-digit", second: "2-digit" };
    if (dateTimeFormatSelect.value === "12h") {
        opts.hour12 = true;
    } else {
        opts.hourCycle = "h23"; // avoids "24:00:00" at midnight
    }
    clockLivePreview.textContent = now.toLocaleTimeString("en-US", opts);
}
dateTimeFormatSelect.addEventListener("change", function () {
    store.set("dateTimeFormat", this.value);
    updateClockPreview();
});
setInterval(updateClockPreview, 1000);

// 9. Sensor Refresh (UI only - statuses are static until a backend endpoint is wired in)
refreshSensorsBtn.addEventListener("click", function () {
    const icon = this.querySelector("i");
    icon.classList.add("spin");
    this.disabled = true;
    setTimeout(() => {
        icon.classList.remove("spin");
        this.disabled = false;
    }, 800);
});

// 10. INITIALIZE ALL SAVED PREFERENCES ON LOAD
(function initSettings() {
    // Theme
    const isDark = store.get("theme") === "dark";
    darkModeToggle.checked = isDark;
    document.body.classList.toggle("dark-mode", isDark);
    document.documentElement.classList.toggle("dark-mode", isDark);

    // Night Light
    const nightOn = store.get("nightLight") === "enabled";
    nightLightToggle.checked = nightOn;
    nightLightOverlay.style.display = nightOn ? "block" : "none";

    // Text Size
    const savedTextSize = store.get("textSize", "normal");
    textScaleSelect.value = fontSizes[savedTextSize] ? savedTextSize : "normal";
    document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";

    // Language
    const savedLanguage = store.get("language", "en");
    languageSelect.value = i18n[savedLanguage] ? savedLanguage : "en";
    applyLanguage(languageSelect.value);

    // Date & Time Format
    const savedFmt = store.get("dateTimeFormat", "24h");
    dateTimeFormatSelect.value = savedFmt;
    updateClockPreview();
})();
</script>

</body>
</html>
<?php
if ($conn) {
    mysqli_close($conn);
}
?>