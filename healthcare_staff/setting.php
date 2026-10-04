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
        const savedTheme = localStorage.getItem("theme");
        if (savedTheme === "dark") {
            document.documentElement.classList.add("dark-mode");
        }
        const savedTextSize = localStorage.getItem("textSize");
        if (savedTextSize) {
            const fontSizes = { small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
            document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
        }

         // ==============================
        // BRIGHTNESS
        // ==============================

        const savedBrightness =
            localStorage.getItem("brightness") || "100";

        document.documentElement
            .style
            .setProperty(
                "--saved-brightness",
                savedBrightness
            );
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
            mix-blend-mode: multiply;
        }
        /* =========================================================
        SCREEN BRIGHTNESS OVERLAY
        ========================================================= */
        #brightnessOverlay {
            position: fixed;
            inset: 0;

            background: #000;

            opacity: 0;

            pointer-events: none;

            z-index: 99998;

            transition: opacity 0.15s ease;
        }

        /* Night Light should stay above brightness */
        #nightLightOverlay {
            position: fixed;

            top: 0;
            left: 0;

            width: 100vw;
            height: 100vh;

            background-color: rgba(255, 140, 0, 0.18);

            pointer-events: none;

            z-index: 99999;

            display: none;

            mix-blend-mode: multiply;
        }
    </style>
</head>

<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

<div id="brightnessOverlay"></div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
            <div>
                <!-- LOGO & BRAND -->
                <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/2966/2966327.png';">
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
                    <ul class="nav flex-column mb-3">
                        <!-- Administration -->
                        <li class="nav-item">
                            <a class="nav-link active sidebar-collapse-link d-flex justify-content-between align-items-center"
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
                                        <a href="#" class="sidebar-submenu-link active text-decoration-none fw-bold text-primary">
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
                <div class="card-body d-flex justify-content-between align-items-center">
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

            <!-- Brightness -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-brightness-high-fill me-2 text-warning"></i>
                            <span data-i18n="brightness_title">Brightness</span>
                        </h6>
                        <span class="badge bg-secondary" id="brightnessValBadge">100%</span>
                    </div>
                    <small class="text-muted d-block mb-2" data-i18n="brightness_desc">
                        Adjust screen brightness.
                    </small>

                    <input type="range" class="form-range" min="20" max="100" value="100" id="brightnessRange">
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
                            <span data-i18n="datetime_title">Date & Time Format</span>
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
                                Sensor Connection Status
                            </h6>
                            <small class="text-muted">
                                View the current status of connected medical sensors.
                            </small>
                        </div>

                        <button class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            Refresh
                        </button>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Temperature Sensor (MLX90614)</span>
                        <span class="badge bg-success">Connected</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Weight Sensor (HX711)</span>
                        <span class="badge bg-success">Connected</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Height Sensor (TF-Luna)</span>
                        <span class="badge bg-success">Connected</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Heart Rate / SpO₂ (MAX30102)</span>
                        <span class="badge bg-success">Connected</span>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span>Blood Pressure Monitor (Contec O8A)</span>
                        <span class="badge bg-success">Connected</span>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============================================================================
// VITALCORE SETTINGS ENGINE (Fully Functional Preferences Engine)
// ============================================================================

// 1. DOM Elements
const darkModeToggle   = document.getElementById("darkModeToggle");
const brightnessRange  = document.getElementById("brightnessRange");
const brightnessBadge  = document.getElementById("brightnessValBadge");
const nightLightToggle = document.getElementById("nightLightToggle");
const nightLightOverlay = document.getElementById("nightLightOverlay");
const textScaleSelect  = document.getElementById("textScale");
const languageSelect   = document.getElementById("languageSelect");
const dateTimeFormatSelect = document.getElementById("dateTimeFormat");
const clockLivePreview = document.getElementById("clockLivePreview");
const brightnessOverlay = document.getElementById("brightnessOverlay");

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
        brightness_title: "Brightness",
        brightness_desc: "Adjust screen brightness.",
        night_light_title: "Night Light",
        night_light_desc: "Reduce blue light for night viewing.",
        text_size_title: "Text Size",
        text_size_desc: "Change the size of text in the system.",
        size_small: "Small",
        size_normal: "Normal",
        size_large: "Large",
        size_xlarge: "Extra Large",
        language_title: "Language",
        language_desc: "Select the language used throughout the system.",
        datetime_title: "Date & Time Format",
        datetime_desc: "Customize how dates and times are displayed."
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
        brightness_title: "Liwanag ng Screen",
        brightness_desc: "I-adjust ang liwanag ng screen.",
        night_light_title: "Night Light",
        night_light_desc: "Bawasan ang bughaw na liwanag sa gabi.",
        text_size_title: "Laki ng Teksto",
        text_size_desc: "Baguhin ang laki ng teksto sa sistema.",
        size_small: "Maliit",
        size_normal: "Pangkaraniwan",
        size_large: "Malaki",
        size_xlarge: "Napakalaki",
        language_title: "Wika",
        language_desc: "Pumili ng wikang gagamitin sa buong sistema.",
        datetime_title: "Format ng Petsa at Oras",
        datetime_desc: "I-customize kung paano ipinapakita ang petsa at oras."
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
        brightness_title: "Kahayag sa Screen",
        brightness_desc: "I-adjust ang kahayag sa screen.",
        night_light_title: "Night Light",
        night_light_desc: "Kuhai ang asul nga suga sa gabii.",
        text_size_title: "Gidak-on sa Teksto",
        text_size_desc: "Usba ang gidak-on sa teksto sa sistema.",
        size_small: "Gamay",
        size_normal: "Normal",
        size_large: "Dako",
        size_xlarge: "Dako Kaayo",
        language_title: "Pinulongan",
        language_desc: "Pilia ang pinulongan nga gamiton sa tibuok sistema.",
        datetime_title: "Format sa Petsa ug Oras",
        datetime_desc: "I-customize kon unsaon pagpakita ang petsa ug oras."
    }
};

// 3. Apply Language Function
function applyLanguage(lang) {
    const dict = i18n[lang] || i18n.en;
    document.querySelectorAll("[data-i18n]").forEach(el => {
        const key = el.getAttribute("data-i18n");
        if (dict[key]) {
            el.innerText = dict[key];
        }
    });
    localStorage.setItem("language", lang);
}

// 4. Dark Mode Logic
darkModeToggle.addEventListener("change", function () {
    if (this.checked) {
        document.body.classList.add("dark-mode");
        document.documentElement.classList.add("dark-mode");
        localStorage.setItem("theme", "dark");
    } else {
        document.body.classList.remove("dark-mode");
        document.documentElement.classList.remove("dark-mode");
        localStorage.setItem("theme", "light");
    }
});

// 5. Brightness Logic
brightnessRange.addEventListener("input", function() {
    setBrightness(this.value);
});

function setBrightness(val) {

    val = parseInt(val);

    /*
        100% brightness = overlay opacity 0
        20% brightness  = overlay opacity about 0.64

        We intentionally don't make it fully black.
    */

    const brightnessLevel = val / 100;

    const maxDarkness = 0.80;

    const overlayOpacity =
        (1 - brightnessLevel) * maxDarkness;

    brightnessOverlay.style.opacity = overlayOpacity;

    brightnessBadge.innerText = `${val}%`;

    localStorage.setItem("brightness", val);
}

// 6. Night Light Logic
nightLightToggle.addEventListener("change", function() {
    if (this.checked) {
        nightLightOverlay.style.display = "block";
        localStorage.setItem("nightLight", "enabled");
    } else {
        nightLightOverlay.style.display = "none";
        localStorage.setItem("nightLight", "disabled");
    }
});

// 7. Text Size Logic
const fontSizes = {
    xsmall: "80%",
    small: "85%",
    normal: "100%",
    large: "115%",
    xlarge: "130%"
};
textScaleSelect.addEventListener("change", function() {
    const scale = this.value;
    document.documentElement.style.fontSize = fontSizes[scale] || "100%";
    localStorage.setItem("textSize", scale);
});

// 8. Language Logic
languageSelect.addEventListener("change", function() {
    applyLanguage(this.value);
});

// 9. Date & Time Format Logic & Live Preview Clock
function updateClockPreview() {
    const now = new Date();
    const fmt = dateTimeFormatSelect.value;
    let timeStr = "";
    if (fmt === "12h") {
        timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    } else {
        timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    }
    clockLivePreview.innerText = timeStr;
}
dateTimeFormatSelect.addEventListener("change", function() {
    localStorage.setItem("dateTimeFormat", this.value);
    updateClockPreview();
});
setInterval(updateClockPreview, 1000);

// 10. INITIALIZE ALL SAVED PREFERENCES ON LOAD
(function initSettings() {
    // Theme
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme === "dark") {
        document.body.classList.add("dark-mode");
        document.documentElement.classList.add("dark-mode");
        darkModeToggle.checked = true;
    }

    // Brightness
    const savedBrightness = localStorage.getItem("brightness") || "100";
    brightnessRange.value = savedBrightness;
    setBrightness(savedBrightness);

    // Night Light
    const savedNightLight = localStorage.getItem("nightLight");
    if (savedNightLight === "enabled") {
        nightLightToggle.checked = true;
        nightLightOverlay.style.display = "block";
    }

    // Text Size
    const savedTextSize = localStorage.getItem("textSize") || "normal";
    textScaleSelect.value = savedTextSize;
    document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";

    // Language
    const savedLanguage = localStorage.getItem("language") || "en";
    languageSelect.value = savedLanguage;
    applyLanguage(savedLanguage);

    // Date Time Format
    const savedFmt = localStorage.getItem("dateTimeFormat") || "24h";
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