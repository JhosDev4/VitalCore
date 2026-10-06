<?php
session_start();

/* =========================
   SECURITY CHECK
========================= */

if (
    !isset($_SESSION['staff_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Staff'], true)
) {
    header("Location: ../login.php");
    exit();
}

/* Helper: JSON-encode template variables for data-vars attributes */
function vars_attr(array $vars): string {
    return htmlspecialchars(json_encode($vars, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

/* One-time flash messages */
$generated_code = "";
if (isset($_SESSION['generated_code'])) {
    $generated_code = $_SESSION['generated_code'];
    unset($_SESSION['generated_code']);
}

$edit_success = "";
if (isset($_SESSION['edit_success'])) {
    $edit_success = $_SESSION['edit_success'];
    unset($_SESSION['edit_success']);
}

$edit_scroll = null;
if (isset($_SESSION['edit_scroll'])) {
    $edit_scroll = (int)$_SESSION['edit_scroll'];
    unset($_SESSION['edit_scroll']);
}

/* =========================
   BARANGAY LIST
========================= */
$barangays = [
    "Abijao", "Balite", "Bugabuga", "Cabungahan", "Cabunga-an",
    "Cagnocot", "Cahigan", "Calbugos", "Camporog", "Capinyahan",
    "Casili-on", "Catagbacan", "Fatima", "Hibulangan", "Hinabuyan",
    "Iligay", "Jalas", "Jordan", "Libagong", "New Balanac",
    "Payao", "Poblacion Norte", "Poblacion Sur", "Sambulawan",
    "San Francisco", "Silad", "Sulpa", "Tabunok", "Tagbubunga",
    "Tinghub", "Bangcal", "Canquiason", "San Vicente", "Santa Cruz", "Suba"
];

/* =========================
   DATABASE CONNECTION
========================= */

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "vitalcore_db";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

/* =========================
   PAGINATION
   (defined BEFORE the update handler - it needs $page for the redirect)
========================= */

$limit = 8;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

/* =========================
   UPDATE PATIENT
========================= */

if (isset($_POST['update_patient'])) {

    $patient_id = (int)($_POST['patient_id'] ?? 0);

    $last_name = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name'] ?? '');
    $suffix = mysqli_real_escape_string($conn, $_POST['suffix'] ?? '');
    $age = mysqli_real_escape_string($conn, $_POST['age'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number'] ?? '');
    $birth_date = mysqli_real_escape_string($conn, $_POST['birth_date'] ?? '');
    $barangay = mysqli_real_escape_string($conn, $_POST['barangay'] ?? '');
    $city_municipality = mysqli_real_escape_string($conn, $_POST['city_municipality'] ?? 'Villaba');
    $service_type = mysqli_real_escape_string($conn, $_POST['service_type'] ?? '');
    $blood_type = mysqli_real_escape_string($conn, $_POST['blood_type'] ?? '');

    // FULL NAME
    $fullname = trim(
        $first_name . ' ' .
        $middle_name . ' ' .
        $last_name . ' ' .
        $suffix
    );

    // FULL ADDRESS
    $address_parts = [];

    if (!empty($barangay)) {
        $address_parts[] = $barangay;
    }

    if (!empty($city_municipality)) {
        $address_parts[] = $city_municipality;
    }

    $address = implode(', ', $address_parts);

    // UPDATE DATABASE
    $update_sql = "
        UPDATE users
        SET
            fullname = '$fullname',
            last_name = '$last_name',
            first_name = '$first_name',
            middle_name = '$middle_name',
            suffix = '$suffix',

            age = '$age',
            gender = '$gender',
            contact_number = '$contact_number',
            birth_date = '$birth_date',

            address = '$address',
            barangay = '$barangay',
            city_municipality = '$city_municipality',

            service_type = '$service_type',
            blood_type = '$blood_type'

        WHERE id = '$patient_id'
        AND role = 'patient'
    ";

    if (mysqli_query($conn, $update_sql)) {

        // Save current scroll position
        $scroll = isset($_POST['scroll_position'])
            ? (int)$_POST['scroll_position']
            : 0;

        $_SESSION['edit_success'] = "Patient information updated successfully.";
        $_SESSION['edit_scroll'] = $scroll;

        header("Location: admin-dashboard.php?page=" . $page);
        exit();

    } else {

        die("Error updating patient: " . mysqli_error($conn));
    }
}

/* =========================
   PATIENT COUNT
========================= */

$patient_count = 0;

$result_count = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'patient'"
);

if ($result_count) {
    $row_count = mysqli_fetch_assoc($result_count);
    $patient_count = (int)$row_count['total'];
}

$total_patients = $patient_count;

/* =========================
   NEXT PATIENT NUMBER
========================= */

$getLast = mysqli_query(
    $conn,
    "SELECT MAX(patient_number) AS last_no
     FROM users
     WHERE role='patient'"
);

$row = mysqli_fetch_assoc($getLast);

$patient_no = ($row['last_no'] ?? 0) + 1;

$start = ($page - 1) * $limit;

/* =========================
   FETCH PATIENTS
========================= */

$sql_patients = "
    SELECT *
    FROM users
    WHERE role='patient'
    ORDER BY id DESC
    LIMIT $start,$limit
";

$patients_result = mysqli_query($conn, $sql_patients);

/* =========================
   SAVE PATIENT
========================= */

if (isset($_POST['save'])) {

    $last_name = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name'] ?? '');
    $suffix = mysqli_real_escape_string($conn, $_POST['suffix'] ?? '');

    // ADDRESS
    $barangay = mysqli_real_escape_string($conn, $_POST['barangay'] ?? '');
    $city_municipality = mysqli_real_escape_string($conn, $_POST['city_municipality'] ?? 'Villaba');

    // OTHER INFORMATION
    $age = mysqli_real_escape_string($conn, $_POST['age'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number'] ?? '');
    $service_type = mysqli_real_escape_string($conn, $_POST['service_type'] ?? '');
    $blood_type = mysqli_real_escape_string($conn, $_POST['blood_type'] ?? '');
    $birth_date = mysqli_real_escape_string($conn, $_POST['birth_date'] ?? '');

    // FULL NAME
    $fullname = trim(
        $first_name . ' ' .
        $middle_name . ' ' .
        $last_name . ' ' .
        $suffix
    );

    // FULL ADDRESS
    $address_parts = [];

    if (!empty($barangay)) {
        $address_parts[] = $barangay;
    }

    if (!empty($city_municipality)) {
        $address_parts[] = $city_municipality;
    }

    $address = implode(', ', $address_parts);

    // GENERATE PATIENT CODE (retry if the code is already taken)
    $code = random_int(100000, 999999);

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $dup = mysqli_query($conn, "SELECT id FROM users WHERE code_number = '$code' LIMIT 1");

        if (!$dup || mysqli_num_rows($dup) === 0) {
            break;
        }

        $code = random_int(100000, 999999);
    }

    // INSERT PATIENT
    $sql = "
        INSERT INTO users
        (
            fullname,
            last_name,
            first_name,
            middle_name,
            suffix,

            age,
            gender,

            address,
            barangay,
            city_municipality,

            contact_number,
            service_type,
            code_number,
            patient_number,
            blood_type,
            birth_date,
            role
        )
        VALUES
        (
            '$fullname',
            '$last_name',
            '$first_name',
            '$middle_name',
            '$suffix',

            '$age',
            '$gender',

            '$address',
            '$barangay',
            '$city_municipality',

            '$contact_number',
            '$service_type',
            '$code',
            '$patient_no',
            '$blood_type',
            '$birth_date',
            'patient'
        )
    ";

    if (mysqli_query($conn, $sql)) {

        $_SESSION['generated_code'] = $code;

        header("Location: admin-dashboard.php?success=1");
        exit();

    } else {

        die("Error adding patient: " . mysqli_error($conn));
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
    <title>VitalCore - Add Patients</title>
    <!-- BOOTSTRAP 5.3 (the *-subtle classes used on this page need 5.3) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- BOOTSTRAP ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <!-- YOUR CSS -->
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <link rel="stylesheet" href="../css/theme.css">

<style>
        /* Fallback Dark Mode Styles */
        body.dark-mode, html.dark-mode body {
            background-color: #121824 !important;
            color: #e2e8f0 !important;
        }
        html.dark-mode .card,
        html.dark-mode .patient-card,
        html.dark-mode .analytics-card,
        html.dark-mode .add-card,
        html.dark-mode .list-card,
        html.dark-mode .modal-content {
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
        html.dark-mode .form-select,
        html.dark-mode .form-control,
        html.dark-mode .input-group-text:not(.bg-danger) {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        html.dark-mode .table {
            --bs-table-bg: transparent;
            --bs-table-color: #e2e8f0;
            --bs-table-hover-color: #f8fafc;
            --bs-table-hover-bg: rgba(255,255,255,0.05);
            --bs-table-border-color: rgba(255,255,255,0.1);
            color: #e2e8f0 !important;
        }
        html.dark-mode .table-light {
            --bs-table-bg: #172033;
            --bs-table-color: #e2e8f0;
            --bs-table-border-color: rgba(255,255,255,0.1);
        }
        html.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        html.dark-mode .sidebar {
            background-color: #0f172a !important;
            border-right-color: rgba(255,255,255,0.1) !important;
        }

        /* Night Light Filter Overlay */
        #nightLightOverlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
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
                    <!-- Patients / Patient Management (Active on Admin Dashboard) -->
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
                                    <a href="patient-list.php" class="sidebar-submenu-link text-decoration-none">
                                        <i class="bi bi-list-ul me-2"></i>
                                        <span data-i18n="nav_all_patients">All Patients Services</span>
                                    </a>
                                </li>
                                <li class="py-1">
                                    <a href="admin-dashboard.php" class="sidebar-submenu-link active text-decoration-none">
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


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4 pb-3 border-bottom fade-up">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-hospital-fill me-2 text-primary"></i>
                        <span data-i18n="greeting">Good Day, Admin</span>
                    </h3>
                    <p class="text-muted small mb-0" data-i18n="header_desc">
                        System status overview and clinical intake telemetry.
                    </p>
                </div>

                <div>
                    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-semibold border border-success-subtle">
                        <i class="bi bi-shield-check me-1"></i>
                        <span data-i18n="active_session">Active Admin Session</span>
                        <span class="ms-1" id="liveClock"></span>
                    </span>
                </div>

            </div>

            <?php if (!empty($edit_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <span data-i18n="edit_success_msg"><?= htmlspecialchars($edit_success); ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-xl-8">
                    <div class="card stat-card p-3 fade-up fade-delay-1">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small text-uppercase fw-bold mb-1" data-i18n="stat_total_registry">
                                    Total Registry
                                </p>
                                <h3
                                    class="fw-bold mb-0 stat-number"><?= number_format($patient_count); ?>
                                </h3>
                            </div>

                            <div class="icon-box bg-primary-subtle text-primary">
                                <i class="bi bi-people-fill fs-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="add-card fade-up fade-delay-2">
                    <div class="card-header-custom mb-4">
                        <div>
                            <h4 class="mb-1 fw-bold">
                                <i
                                    class="bi bi-person-plus-fill me-2">
                                </i>
                                <span data-i18n="reg_title">Patient Registration</span>
                            </h4>
                            <small data-i18n="reg_sub">
                                Register a patient before using the
                                VitalCore kiosk.
                            </small>
                        </div>
                    </div>

                    <?php if (!empty($generated_code)) { ?>
                        <div id="successBox" class="success-box">
                            <h5 class="text-success mb-2" data-i18n="reg_success_title">
                                ✓ Patient Successfully Added
                            </h5>
                            <div data-i18n="reg_code_label">
                                Patient Code Number
                            </div>
                            <div class="code-number">
                                <?= htmlspecialchars($generated_code); ?>
                            </div>
                            <small class="text-muted" data-i18n="reg_code_hint">
                                Give this code to the patient.
                            </small>
                        </div>

                        <script>
                            setTimeout(() => {
                                const box = document.getElementById('successBox');
                                if (box) {
                                    box.style.display = 'none';
                                }
                            }, 5000);
                        </script>

                    <?php } ?>

                    <form method="POST" class="patient-form">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" data-i18n="lbl_patient_name">
                                Patient Name
                            </label>
                            <div class="registration-row">
                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person-fill"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="last_name"
                                            class="form-control"
                                            placeholder="Last Name"
                                            data-i18n-placeholder="ph_last"
                                            required>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="first_name"
                                            class="form-control"
                                            placeholder="First Name"
                                            data-i18n-placeholder="ph_first"
                                            required>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person-lines-fill"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="middle_name"
                                            class="form-control"
                                            placeholder="Middle Name"
                                            data-i18n-placeholder="ph_middle">
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-award"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="suffix"
                                            class="form-control"
                                            placeholder="Suffix (Jr., Sr., III)"
                                            data-i18n-placeholder="ph_suffix">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="registration-row">
                                <div class="registration-field">
                                    <label class="form-label fw-semibold" data-i18n="lbl_age">
                                        Age
                                    </label>

                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-calendar3"></i>
                                        </span>
                                        <input
                                            type="number"
                                            class="form-control"
                                            name="age"
                                            placeholder="Age"
                                            data-i18n-placeholder="ph_age"
                                            min="0"
                                            max="150"
                                            required>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <label class="form-label fw-semibold" data-i18n="lbl_gender">
                                        Gender
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-gender-ambiguous"></i>
                                        </span>
                                        <select
                                            class="form-select"
                                            name="gender"
                                            required>
                                            <option value="Male" data-i18n="opt_male">
                                                Male
                                            </option>
                                            <option value="Female" data-i18n="opt_female">
                                                Female
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <label class="form-label fw-semibold" data-i18n="lbl_contact">
                                        Contact Number
                                    </label>

                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-telephone"></i>
                                        </span>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="contact_number"
                                            placeholder="09XXXXXXXXX">
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <label class="form-label fw-semibold" data-i18n="lbl_birthdate">
                                        Birth Date
                                    </label>

                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-calendar-date"></i>
                                        </span>
                                        <input
                                            type="date"
                                            class="form-control"
                                            name="birth_date">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" data-i18n="lbl_address_details">
                                Address &amp; Details
                            </label>

                            <div class="address-row">
                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-house-door-fill"></i>
                                        </span>
                                        <select
                                            class="form-select"
                                            name="barangay"
                                            required>
                                            <option value="" selected data-i18n="opt_select_brgy">Select Barangay...</option>
                                            <?php foreach ($barangays as $brgy): ?>
                                                <option value="<?= htmlspecialchars($brgy); ?>">
                                                    <?= htmlspecialchars($brgy); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-geo-alt-fill"></i>
                                        </span>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="city_municipality"
                                            value="Villaba"
                                            placeholder="City / Municipality"
                                            data-i18n-placeholder="ph_city"
                                            required>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text bg-danger text-white">
                                            <i class="bi bi-droplet-fill"></i>
                                        </span>
                                        <select
                                            class="form-select"
                                            name="blood_type">
                                            <option
                                                value=""
                                                selected
                                                data-i18n="opt_blood_opt">
                                                Blood Type (Optional)...
                                            </option>

                                            <option value="A+">🩸 A+</option>
                                            <option value="A-">🩸 A-</option>
                                            <option value="B+">🩸 B+</option>
                                            <option value="B-">🩸 B-</option>
                                            <option value="AB+">🩸 AB+</option>
                                            <option value="AB-">🩸 AB-</option>
                                            <option value="O+">🩸 O+</option>
                                            <option value="O-">🩸 O-</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="save" class="btn btn-save w-100">
                            <i class="bi bi-person-check-fill me-2"></i>
                            <span data-i18n="btn_register">Register Patient</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="list-card fade-up fade-delay-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
                    <h3 class="fw-bold m-0" data-i18n="list_title">
                        Patient List
                    </h3>
                    <input type="text"
                        id="searchPatient"
                        class="form-control search-box"
                        style="max-width:280px"
                        placeholder="Search patient..."
                        data-i18n-placeholder="ph_search">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th data-i18n="col_id">ID</th>
                                <th data-i18n="col_fullname">Full Name</th>
                                <th data-i18n="col_age">Age</th>
                                <th data-i18n="col_gender">Gender</th>
                                <th data-i18n="col_code">Code</th>
                                <th class="text-center" data-i18n="col_action">Action</th>
                            </tr>
                        </thead>

                        <tbody id="patientTable">

                            <?php
                            mysqli_data_seek($patients_result, 0);

                            $counter = $start + 1;

                            while($row = mysqli_fetch_assoc($patients_result)){

                                $patient_id = (int)$row['id'];
                            ?>

                            <tr>
                                <td><?= $counter++; ?></td>
                                <td>
                                    <span class="text-nowrap">
                                        <?= htmlspecialchars((string)$row['fullname']); ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string)$row['age']); ?></td>
                                <td><?= htmlspecialchars((string)$row['gender']); ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars((string)$row['code_number']); ?></td>

                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-warning me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= $patient_id; ?>"
                                        title="Edit Patient">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>

                                    <button type="button"
                                            class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal<?= $patient_id; ?>"
                                            title="Delete Patient">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </td>
                            </tr>

                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $total_pages = max(1, ceil($total_patients / $limit));

                if($total_patients > 0){

                    $start_record = ($page - 1) * $limit + 1;
                    $end_record = min($page * $limit, $total_patients);

                } else {

                    $start_record = 0;
                    $end_record = 0;

                }
                ?>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted" data-i18n="footer_showing" data-vars="<?= vars_attr(['a' => $start_record, 'b' => $end_record, 'c' => $total_patients]); ?>">
                        Showing
                        <?= $start_record ?>
                        to
                        <?= $end_record ?>
                        of
                        <?= $total_patients ?>
                        patients
                    </small>

                    <div>
                        <?php if($page > 1){ ?>
                            <a href="?page=<?= $page - 1 ?>"
                            class="btn btn-sm btn-outline-secondary">
                                &lt;
                            </a>
                        <?php } ?>

                        <span class="btn btn-sm btn-primary">
                            <?= $page ?>
                        </span>

                        <?php if($page < $total_pages){ ?>
                            <a href="?page=<?= $page + 1 ?>"
                            class="btn btn-sm btn-outline-secondary">
                                &gt;
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- =====================================================
                EDIT MODALS
            ===================================================== -->

            <?php
            mysqli_data_seek($patients_result, 0);
            while ($row = mysqli_fetch_assoc($patients_result)) {
                $patient_id = (int)$row['id'];
            ?>

            <div class="modal fade"
                id="editModal<?= $patient_id; ?>"
                tabindex="-1"
                aria-labelledby="editModalLabel<?= $patient_id; ?>"
                aria-hidden="true">

                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5
                                class="modal-title fw-bold"
                                id="editModalLabel<?= $patient_id; ?>">
                                <i class="bi bi-pencil-square text-warning me-2"></i>
                                <span data-i18n="edit_title">Edit Patient Information</span>
                            </h5>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close">
                            </button>
                        </div>

                        <form method="POST">
                            <div class="modal-body">
                                <input
                                    type="hidden"
                                    name="update_patient"
                                    value="1">
                                <input
                                    type="hidden"
                                    name="patient_id"
                                    value="<?= $patient_id; ?>">
                                <input
                                    type="hidden"
                                    name="scroll_position"
                                    class="edit-scroll-position">

                                <label class="form-label fw-semibold" data-i18n="lbl_patient_name">
                                    Patient Name
                                </label>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                            <input
                                                type="text"
                                                name="last_name"
                                                class="form-control"
                                                placeholder="Last Name"
                                                data-i18n-placeholder="ph_last"
                                                value="<?= htmlspecialchars($row['last_name'] ?? ''); ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                                            <input
                                                type="text"
                                                name="first_name"
                                                class="form-control"
                                                placeholder="First Name"
                                                data-i18n-placeholder="ph_first"
                                                value="<?= htmlspecialchars($row['first_name'] ?? ''); ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person-lines-fill"></i></span>
                                            <input
                                                type="text"
                                                name="middle_name"
                                                class="form-control"
                                                placeholder="Middle Name"
                                                data-i18n-placeholder="ph_middle"
                                                value="<?= htmlspecialchars($row['middle_name'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-award"></i></span>
                                            <input
                                                type="text"
                                                name="suffix"
                                                class="form-control"
                                                placeholder="Suffix"
                                                data-i18n-placeholder="ph_suffix_short"
                                                value="<?= htmlspecialchars($row['suffix'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <!-- AGE / GENDER / CONTACT / BIRTH DATE -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold" data-i18n="lbl_age">
                                            Age
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                            <input
                                                type="number"
                                                name="age"
                                                class="form-control"
                                                min="0"
                                                max="150"
                                                value="<?= htmlspecialchars((string)($row['age'] ?? '')); ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold" data-i18n="lbl_gender">
                                            Gender
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                                            <select
                                                name="gender"
                                                class="form-select"
                                                required>
                                                <option
                                                    value="Male"
                                                    data-i18n="opt_male"
                                                    <?= ($row['gender'] ?? '') === 'Male'
                                                        ? 'selected'
                                                        : ''; ?>>
                                                    Male
                                                </option>

                                                <option
                                                    value="Female"
                                                    data-i18n="opt_female"
                                                    <?= ($row['gender'] ?? '') === 'Female'
                                                        ? 'selected'
                                                        : ''; ?>>
                                                    Female
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold" data-i18n="lbl_contact">
                                            Contact Number
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                            <input
                                                type="text"
                                                name="contact_number"
                                                class="form-control"
                                                placeholder="09XXXXXXXXX"
                                                value="<?= htmlspecialchars($row['contact_number'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold" data-i18n="lbl_birthdate">
                                            Birth Date
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-calendar-date"></i></span>
                                            <input
                                                type="date"
                                                name="birth_date"
                                                class="form-control"
                                                value="<?= htmlspecialchars($row['birth_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <!-- ADDRESS -->
                                <label class="form-label fw-semibold" data-i18n="lbl_address">
                                    Address
                                </label>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-house-door-fill"></i></span>
                                            <select
                                                name="barangay"
                                                class="form-select"
                                                required>
                                                <option value="" data-i18n="opt_select_brgy" <?= empty($row['barangay']) ? 'selected' : ''; ?>>
                                                    Select Barangay...
                                                </option>
                                                <?php foreach ($barangays as $brgy): ?>
                                                    <option value="<?= htmlspecialchars($brgy); ?>" <?= (($row['barangay'] ?? '') === $brgy) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($brgy); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                                            <input
                                                type="text"
                                                name="city_municipality"
                                                class="form-control"
                                                placeholder="City / Municipality"
                                                data-i18n-placeholder="ph_city"
                                                value="<?= htmlspecialchars(!empty($row['city_municipality']) ? $row['city_municipality'] : 'Villaba'); ?>"
                                                required>
                                        </div>
                                    </div>
                                </div>
                                <!-- SERVICE / BLOOD TYPE -->
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <span data-i18n="lbl_blood">Blood Type</span>
                                            <small class="text-muted fw-normal" data-i18n="lbl_optional">
                                                (Optional)
                                            </small>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-danger text-white"><i class="bi bi-droplet-fill"></i></span>
                                            <select
                                                name="blood_type"
                                                class="form-select">
                                                <option value="" data-i18n="opt_select_blood" <?= empty($row['blood_type']) ? 'selected' : ''; ?>>
                                                    Select blood type...
                                                </option>

                                                <option value="A+" <?= ($row['blood_type'] ?? '') === 'A+' ? 'selected' : ''; ?>>🩸 A+</option>
                                                <option value="A-" <?= ($row['blood_type'] ?? '') === 'A-' ? 'selected' : ''; ?>>🩸 A-</option>
                                                <option value="B+" <?= ($row['blood_type'] ?? '') === 'B+' ? 'selected' : ''; ?>>🩸 B+</option>
                                                <option value="B-" <?= ($row['blood_type'] ?? '') === 'B-' ? 'selected' : ''; ?>>🩸 B-</option>
                                                <option value="AB+" <?= ($row['blood_type'] ?? '') === 'AB+' ? 'selected' : ''; ?>>🩸 AB+</option>
                                                <option value="AB-" <?= ($row['blood_type'] ?? '') === 'AB-' ? 'selected' : ''; ?>>🩸 AB-</option>
                                                <option value="O+" <?= ($row['blood_type'] ?? '') === 'O+' ? 'selected' : ''; ?>>🩸 O+</option>
                                                <option value="O-" <?= ($row['blood_type'] ?? '') === 'O-' ? 'selected' : ''; ?>>🩸 O-</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button
                                    type="button"
                                    class="btn btn-secondary px-4"
                                    data-bs-dismiss="modal">
                                    <i class="bi bi-x-lg me-1"></i>
                                    <span data-i18n="btn_cancel">Cancel</span>
                                </button>

                                <button
                                    type="submit"
                                    class="btn btn-warning px-4 fw-semibold">
                                    <i class="bi bi-check-lg me-1"></i>
                                    <span data-i18n="btn_save">Save Changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php } ?>

              <!-- =====================================================
                    DELETE MODALS
                ===================================================== -->

            <?php
            mysqli_data_seek($patients_result, 0);
            while($row = mysqli_fetch_assoc($patients_result)){
                $patient_id = (int)$row['id'];
            ?>

            <div class="modal fade"
                id="deleteModal<?= $patient_id; ?>"
                tabindex="-1"
                aria-labelledby="deleteModalLabel<?= $patient_id; ?>"
                aria-hidden="true">

                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"
                                id="deleteModalLabel<?= $patient_id; ?>">
                                <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                                <span data-i18n="del_title">Delete Patient</span>
                            </h5>
                            <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close">
                            </button>
                        </div>

                        <div class="modal-body text-center py-4">
                            <div class="mb-3">
                                <i class="bi bi-person-x-fill text-danger"
                                style="font-size:50px;">
                                </i>
                            </div>

                            <h5 class="fw-bold mb-2" data-i18n="del_sure">
                                Are you sure?
                            </h5>

                            <p class="text-muted mb-0">
                                <span data-i18n="del_about">You are about to delete</span>
                                <strong>
                                    <?= htmlspecialchars((string)$row['fullname']); ?>
                                </strong>
                                <span data-i18n="del_from">from the patient registry.</span>
                            </p>

                            <small class="text-danger d-block mt-2" data-i18n="del_warning">
                                This action cannot be undone.
                            </small>
                        </div>

                        <div class="modal-footer justify-content-center">
                            <button type="button"
                                    class="btn btn-secondary px-4"
                                    data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-1"></i>
                                <span data-i18n="btn_cancel">Cancel</span>
                            </button>

                            <a href="delete-patient.php?id=<?= $patient_id; ?>"
                            class="btn btn-danger px-4">
                                <i class="bi bi-trash-fill me-1"></i>
                                <span data-i18n="btn_delete">Delete</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php } ?>
        </main>


    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/theme.js"></script>

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
            greeting: "Magandang Araw, Admin",
            header_desc: "Pangkalahatang-ideya ng estado ng sistema at klinikal na intake.",
            active_session: "Aktibong Sesyon ng Admin",
            edit_success_msg: "Matagumpay na na-update ang impormasyon ng pasyente.",
            stat_total_registry: "Kabuuang Rehistro",
            reg_title: "Pagpaparehistro ng Pasyente",
            reg_sub: "Magrehistro ng pasyente bago gamitin ang VitalCore kiosk.",
            reg_success_title: "✓ Matagumpay na Naidagdag ang Pasyente",
            reg_code_label: "Numero ng Code ng Pasyente",
            reg_code_hint: "Ibigay ang code na ito sa pasyente.",
            lbl_patient_name: "Pangalan ng Pasyente",
            ph_last: "Apelyido",
            ph_first: "Pangalan",
            ph_middle: "Gitnang Pangalan",
            ph_suffix: "Suffix (Jr., Sr., III)",
            ph_suffix_short: "Suffix",
            lbl_age: "Edad",
            ph_age: "Edad",
            lbl_gender: "Kasarian",
            opt_male: "Lalaki",
            opt_female: "Babae",
            lbl_contact: "Numero ng Contact",
            lbl_birthdate: "Petsa ng Kapanganakan",
            lbl_address_details: "Tirahan at Detalye",
            lbl_address: "Tirahan",
            opt_select_brgy: "Pumili ng Barangay...",
            ph_city: "Lungsod / Munisipalidad",
            opt_blood_opt: "Uri ng Dugo (Opsyonal)...",
            opt_select_blood: "Pumili ng uri ng dugo...",
            lbl_blood: "Uri ng Dugo",
            lbl_optional: "(Opsyonal)",
            btn_register: "I-rehistro ang Pasyente",
            list_title: "Listahan ng Pasyente",
            ph_search: "Maghanap ng pasyente...",
            col_id: "ID",
            col_fullname: "Buong Pangalan",
            col_age: "Edad",
            col_gender: "Kasarian",
            col_code: "Code",
            col_action: "Aksyon",
            footer_showing: "Ipinapakita ang {a} hanggang {b} sa {c} pasyente",
            edit_title: "I-edit ang Impormasyon ng Pasyente",
            btn_cancel: "Kanselahin",
            btn_save: "I-save ang mga Pagbabago",
            del_title: "Burahin ang Pasyente",
            del_sure: "Sigurado ka ba?",
            del_about: "Buburahin mo si",
            del_from: "mula sa rehistro ng pasyente.",
            del_warning: "Hindi na ito maaaring ibalik.",
            btn_delete: "Burahin"
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
            greeting: "Maayong Adlaw, Admin",
            header_desc: "Kinatibuk-ang pagtan-aw sa estado sa sistema ug klinikal nga intake.",
            active_session: "Aktibong Sesyon sa Admin",
            edit_success_msg: "Malampusong na-update ang impormasyon sa pasyente.",
            stat_total_registry: "Total nga Rehistro",
            reg_title: "Pagparehistro sa Pasyente",
            reg_sub: "Pagparehistro og pasyente sa dili pa gamiton ang VitalCore kiosk.",
            reg_success_title: "✓ Malampusong Nadugang ang Pasyente",
            reg_code_label: "Numero sa Code sa Pasyente",
            reg_code_hint: "Ihatag kini nga code sa pasyente.",
            lbl_patient_name: "Ngalan sa Pasyente",
            ph_last: "Apelyido",
            ph_first: "Unang Ngalan",
            ph_middle: "Tunga-tunga nga Ngalan",
            ph_suffix: "Suffix (Jr., Sr., III)",
            ph_suffix_short: "Suffix",
            lbl_age: "Edad",
            ph_age: "Edad",
            lbl_gender: "Gender",
            opt_male: "Lalaki",
            opt_female: "Babaye",
            lbl_contact: "Numero sa Contact",
            lbl_birthdate: "Petsa sa Pagkatawo",
            lbl_address_details: "Adres ug mga Detalye",
            lbl_address: "Adres",
            opt_select_brgy: "Pilia ang Barangay...",
            ph_city: "Siyudad / Munisipyo",
            opt_blood_opt: "Tipo sa Dugo (Opsyonal)...",
            opt_select_blood: "Pilia ang tipo sa dugo...",
            lbl_blood: "Tipo sa Dugo",
            lbl_optional: "(Opsyonal)",
            btn_register: "I-rehistro ang Pasyente",
            list_title: "Listahan sa Pasyente",
            ph_search: "Pangita og pasyente...",
            col_id: "ID",
            col_fullname: "Tibuok Ngalan",
            col_age: "Edad",
            col_gender: "Gender",
            col_code: "Code",
            col_action: "Aksyon",
            footer_showing: "Gipakita ang {a} hangtod {b} sa {c} ka pasyente",
            edit_title: "I-edit ang Impormasyon sa Pasyente",
            btn_cancel: "Kansela",
            btn_save: "I-save ang mga Kausaban",
            del_title: "Papasa ang Pasyente",
            del_sure: "Sigurado ka ba?",
            del_about: "Papason nimo si",
            del_from: "gikan sa rehistro sa pasyente.",
            del_warning: "Dili na kini mabalik.",
            btn_delete: "Papasa"
        }
    };

    const lang = store.get("language", "en");
    if (i18n[lang]) {
        const dict = i18n[lang];
        document.documentElement.lang = lang;

        document.querySelectorAll("[data-i18n]").forEach(el => {
            let text = dict[el.getAttribute("data-i18n")];
            if (!text) return;

            // Fill {placeholders} from data-vars (e.g. "Showing 1 to 8 of 20")
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
    // 5. Date & Time Format (live clock in the header badge)
    // ---------------------------------------------------------------
    const use12h = store.get("dateTimeFormat", "24h") === "12h";
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

<!-- SEARCH (current page of the patient list) -->
<script>
const searchPatient = document.getElementById('searchPatient');

if (searchPatient) {
    searchPatient.addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();

        document.querySelectorAll('#patientTable tr').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
}
</script>

<?php if ($edit_scroll !== null): ?>
<!-- After a successful edit, return to where the admin was on the page -->
<script>
try { sessionStorage.setItem("patientScrollPosition", "<?= (int)$edit_scroll; ?>"); } catch (e) {}
</script>
<?php endif; ?>

<!-- REMEMBER SCROLL POSITION (edit / delete) - merged from two duplicate scripts -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    /* Restore position after an edit / delete reload */
    let savedScrollPosition = null;
    try { savedScrollPosition = sessionStorage.getItem("patientScrollPosition"); } catch (e) {}

    if (savedScrollPosition !== null) {
        window.scrollTo({ top: parseInt(savedScrollPosition, 10) || 0, behavior: "instant" });
        try { sessionStorage.removeItem("patientScrollPosition"); } catch (e) {}
    }

    /* Save position before DELETE */
    document.querySelectorAll('a[href^="delete-patient.php"]').forEach(function (deleteButton) {
        deleteButton.addEventListener("click", function () {
            try { sessionStorage.setItem("patientScrollPosition", window.scrollY); } catch (e) {}
        });
    });

    /* Save position before EDIT (sent along with the form) */
    document.querySelectorAll('.edit-scroll-position').forEach(function (input) {
        input.value = window.scrollY;
    });

    document.querySelectorAll('[data-bs-target^="#editModal"]').forEach(function (button) {
        button.addEventListener("click", function () {
            const modal = document.querySelector(this.getAttribute("data-bs-target"));
            const scrollInput = modal ? modal.querySelector('.edit-scroll-position') : null;

            if (scrollInput) {
                scrollInput.value = window.scrollY;
            }
        });
    });
});
</script>

</body>
</html>
<?php
mysqli_close($conn);
?>