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


/* Helper: JSON-encode template variables for data-vars attributes */
function vars_attr(array $vars): string {
    return htmlspecialchars(json_encode($vars, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

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

$total_checkups = $checkup_row['total'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en" translate="no">

<head>
<script>
// Apply saved theme + text size before first paint (prevents flicker)
(function () {
    try {
        const dark = localStorage.getItem("theme") === "dark";
        document.documentElement.classList.toggle("dark-mode", dark);
        document.documentElement.setAttribute("data-theme", dark ? "dark" : "light");

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Records | VitalCore</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../../css/patient-history.css">
<link rel="stylesheet" href="../../css/theme.css">

<style>
    /* Fallback Dark Mode base (same approach as setting.php) */
    body.dark-mode, html.dark-mode body {
        background-color: #121824;
        color: #e2e8f0;
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

<!-- Theme classes are applied from the saved setting (no longer hard-coded to dark) -->
<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

<div class="container-fluid">
    <div class="row">

        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
        <div>
            <!-- LOGO & BRAND -->
             <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.onerror=null; this.src='https://cdn-icons-png.flaticon.com/512/2966/2966327.png';">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

            <div class="sidebar-menu-wrapper">

                <!-- SECTION: CLINICAL SERVICES & PATIENTS -->
                <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_clinical">Clinical Services</small>

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
                                <span data-i18n="nav_patient_records">Patient Records</span>
                            </span>
                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse show" id="recordsMenu">
                            <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                <li class="py-1">
                                    <a href="patient-history.php" class="sidebar-submenu-link active text-decoration-none">
                                        <i class="bi bi-clock-history me-2"></i>
                                        <span data-i18n="nav_patient_records">Patient Records</span>
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
            <a href="../../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-left fs-5"></i>
                <span data-i18n="nav_logout">Log out</span>
            </a>
        </div>
    </nav>

    <main class="col-md-9 col-lg-10 p-2 main-content">
        <div class="page-container">
        <!-- TOP NAVIGATION -->
        <div class="top-navigation fade-up fade-delay-1">
            <div class="breadcrumb-area">
                <i class="bi bi-house-door-fill"></i>
                <span data-i18n="crumb_admin">Admin</span>
                <i class="bi bi-chevron-right"></i>
                <span data-i18n="nav_patient_records">Patient Records</span>
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
                        <h1 data-i18n="nav_patient_records">Patient Records</h1>
                    </div>
                    <p data-i18n="pr_subtitle">View patient medical history and previous checkup records.</p>
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

                            <span class="stat-title" data-i18n="stat_total_patients">
                                TOTAL PATIENTS
                            </span>

                            <div class="stat-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>

                        </div>

                        <div class="stat-number">
                            <?= number_format($total_patients) ?>
                        </div>

                        <div class="stat-description" data-i18n="stat_registered">
                            Registered patients
                        </div>

                    </div>
                </div>


                 <!-- TOTAL CHECKUPS -->
                <div class="col-12 col-md-4">
                    <div class="stat-card fade-up fade-delay-3">
                        <div class="stat-header">
                            <span class="stat-title" data-i18n="stat_total_checkups">
                                TOTAL CHECKUPS
                            </span>
                            <div class="stat-icon">
                                <i class="bi bi-clipboard2-pulse"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= number_format($total_checkups) ?></div>
                        <div class="stat-description" data-i18n="stat_completed">
                            Completed checkups
                        </div>
                    </div>
                </div>


                <!-- CURRENT DATE -->
                <div class="col-12 col-md-4">
                    <div class="stat-card fade-up fade-delay-4">

                        <div class="stat-header">

                            <span class="stat-title" data-i18n="stat_current_date">
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
                            <span data-i18n="stat_local_time">Philippines local time</span>
                            <span id="liveClock"></span>
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
                            <h5 data-i18n="ph_title">
                                Patient History
                            </h5>
                            <p data-i18n="ph_sub">
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
                            data-i18n-placeholder="search_ph"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <!-- TABLE -->
                <div class="table-wrapper">
                    <table class="patient-table" id="recordTable">
                        <thead>
                            <tr>
                                <th data-i18n="col_patient">Patient</th>
                                <th data-i18n="col_code">Code Number</th>
                                <th data-i18n="col_last_visit">Last Visit</th>
                                <th data-i18n="col_status">Status</th>
                                <th class="text-end" data-i18n="col_action">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php mysqli_data_seek($query, 0);?>
                        <?php if ($total_patients > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($query)): ?>
                                <?php
                                $fullname = trim((string)$row['fullname']);
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
                                                        (string)$row['fullname']
                                                    ) ?>
                                                </div>
                                                <div class="patient-id">
                                                    <span data-i18n="patient_id_lbl">Patient ID:</span>
                                                    #<?= (int)$row['user_id'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- CODE -->
                                    <td data-label="Code Number" data-i18n-label="col_code">
                                        <span class="code-badge">
                                            <i class="bi bi-upc-scan"></i>
                                            <?= htmlspecialchars(
                                                (string)$row['code_number']
                                            ) ?>
                                        </span>
                                    </td>
                                    <!-- LAST VISIT -->
                                    <td data-label="Last Visit" data-i18n-label="col_last_visit">
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
                                                <!-- data-h24 lets the Date & Time Format setting re-render this -->
                                                <div class="visit-time" data-h24="<?= date('H:i', strtotime($row['last_visit'])) ?>">
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
                                                <span data-i18n="no_record">No Record</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- STATUS -->
                                    <td data-label="Status" data-i18n-label="col_status">
                                        <?php if ($has_record): ?>
                                            <span class="status status-active">
                                                <span class="status-dot"></span>
                                                <span data-i18n="status_active">Active Record</span>
                                            </span>

                                        <?php else: ?>
                                            <span class="status status-none">
                                                <span class="status-dot"></span>
                                                <span data-i18n="status_none">No Record Yet</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- ACTION -->
                                    <td data-label="Action" data-i18n-label="col_action" class="text-end">
                                        <a href="patient-history-list.php?user_id=<?= (int)$row['user_id'] ?>" class="history-button">
                                            <i class="bi bi-clock-history"></i>
                                            <span data-i18n="view_history">View History</span>
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
                                        <h5 data-i18n="empty_title">No Patient Records</h5>
                                        <p data-i18n="empty_text">There are currently no registered patients.</p>
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
                    <span data-i18n="<?= $total_patients == 1 ? 'footer_one' : 'footer_many' ?>" data-vars="<?= vars_attr(['n' => number_format($total_patients)]); ?>">
                        Showing
                        <strong><?= number_format($total_patients) ?></strong>
                        registered patient<?= $total_patients != 1 ? 's' : '' ?>.
                    </span>
                </div>
            </div>
        </div>
    </main>
 </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/theme.js"></script>

<!-- PAGE SCRIPT: row animation + search (previously duplicated in two scripts) -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    const searchInput = document.getElementById("recordSearch");
    const tableRows = document.querySelectorAll("#recordTable tbody tr");

    /* TABLE ROW FADE-UP ANIMATION */
    tableRows.forEach(function (row, index) {
        setTimeout(function () {
            row.classList.add("table-row-visible");
        }, index * 100);
    });

    /* SEARCH */
    searchInput.addEventListener("input", function () {
        const search = this.value.toLowerCase().trim();
        tableRows.forEach(function (row) {
            row.style.display = row.innerText.toLowerCase().includes(search) ? "" : "none";
        });
    });
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
    // 1. Theme / Dark Mode (class + data-theme, in case the page CSS uses either)
    // ---------------------------------------------------------------
    const isDark = store.get("theme") === "dark";
    document.body.classList.toggle("dark-mode", isDark);
    document.documentElement.classList.toggle("dark-mode", isDark);
    document.body.setAttribute("data-theme", isDark ? "dark" : "light");
    document.documentElement.setAttribute("data-theme", isDark ? "dark" : "light");

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
            crumb_admin: "Admin",
            pr_subtitle: "Tingnan ang kasaysayang medikal ng pasyente at mga nakaraang rekord ng check-up.",
            stat_total_patients: "KABUUANG PASYENTE",
            stat_registered: "Mga nakarehistrong pasyente",
            stat_total_checkups: "KABUUANG CHECK-UP",
            stat_completed: "Mga natapos na check-up",
            stat_current_date: "KASALUKUYANG PETSA",
            stat_local_time: "Lokal na oras sa Pilipinas",
            ph_title: "Kasaysayan ng Pasyente",
            ph_sub: "I-browse ang mga rekord ng mga nakarehistrong pasyente",
            search_ph: "Maghanap ng pasyente...",
            col_patient: "Pasyente",
            col_code: "Numero ng Code",
            col_last_visit: "Huling Bisita",
            col_status: "Katayuan",
            col_action: "Aksyon",
            patient_id_lbl: "ID ng Pasyente:",
            no_record: "Walang Rekord",
            status_active: "May Aktibong Rekord",
            status_none: "Wala Pang Rekord",
            view_history: "Tingnan ang Kasaysayan",
            empty_title: "Walang Rekord ng Pasyente",
            empty_text: "Kasalukuyang walang nakarehistrong pasyente.",
            footer_one: "Ipinapakita ang {n} nakarehistrong pasyente.",
            footer_many: "Ipinapakita ang {n} nakarehistrong pasyente."
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
            crumb_admin: "Admin",
            pr_subtitle: "Tan-awa ang medikal nga kasaysayan sa pasyente ug miaging mga rekord sa check-up.",
            stat_total_patients: "TOTAL NGA PASYENTE",
            stat_registered: "Mga narehistrong pasyente",
            stat_total_checkups: "TOTAL NGA CHECK-UP",
            stat_completed: "Mga nahuman nga check-up",
            stat_current_date: "KARON NGA PETSA",
            stat_local_time: "Lokal nga oras sa Pilipinas",
            ph_title: "Kasaysayan sa Pasyente",
            ph_sub: "I-browse ang mga rekord sa mga narehistrong pasyente",
            search_ph: "Pangita og pasyente...",
            col_patient: "Pasyente",
            col_code: "Numero sa Code",
            col_last_visit: "Katapusang Bisita",
            col_status: "Kahimtang",
            col_action: "Aksyon",
            patient_id_lbl: "ID sa Pasyente:",
            no_record: "Walay Rekord",
            status_active: "Aktibo nga Rekord",
            status_none: "Wala pay Rekord",
            view_history: "Tan-awa ang Kasaysayan",
            empty_title: "Walay Rekord sa Pasyente",
            empty_text: "Karon walay narehistrong pasyente.",
            footer_one: "Gipakita ang {n} ka narehistrong pasyente.",
            footer_many: "Gipakita ang {n} ka narehistrong pasyente."
        }
    };

    const lang = store.get("language", "en");
    if (i18n[lang]) {
        const dict = i18n[lang];
        document.documentElement.lang = lang;

        document.querySelectorAll("[data-i18n]").forEach(el => {
            let text = dict[el.getAttribute("data-i18n")];
            if (!text) return;

            // Fill {placeholders} from data-vars (e.g. patient count)
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

        // Mobile card layout uses data-label for column captions
        document.querySelectorAll("[data-i18n-label]").forEach(el => {
            const t = dict[el.getAttribute("data-i18n-label")];
            if (t) el.setAttribute("data-label", t);
        });
    }

    // ---------------------------------------------------------------
    // 5. Date & Time Format (last-visit times + live clock)
    // ---------------------------------------------------------------
    const use12h = store.get("dateTimeFormat", "24h") === "12h";

    // Re-render each "last visit" time (server sends 24h value in data-h24)
    document.querySelectorAll(".visit-time[data-h24]").forEach(el => {
        const [h, m] = el.dataset.h24.split(":").map(Number);
        if (use12h) {
            const suffix = h >= 12 ? "PM" : "AM";
            const h12 = h % 12 === 0 ? 12 : h % 12;
            el.textContent = String(h12).padStart(2, "0") + ":" + String(m).padStart(2, "0") + " " + suffix;
        } else {
            el.textContent = String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
        }
    });

    const clockEl = document.getElementById("liveClock");
    function updateClock() {
        if (!clockEl) return;
        const opts = { hour: "2-digit", minute: "2-digit", second: "2-digit", timeZone: "Asia/Manila" };
        if (use12h) { opts.hour12 = true; } else { opts.hourCycle = "h23"; }
        clockEl.textContent = "• " + new Date().toLocaleTimeString("en-US", opts);
    }
    updateClock();
    setInterval(updateClock, 1000);

})();
</script>
</body>

</html>
