<?php

session_start();

$generated_code = "";

if (isset($_SESSION['generated_code'])) {
    $generated_code = $_SESSION['generated_code'];
    unset($_SESSION['generated_code']);
}

/* =========================
   SECURITY CHECK
========================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

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
   UPDATE PATIENT
========================= */

if (isset($_POST['update_patient'])) {

    $patient_id = (int)($_POST['patient_id'] ?? 0);

    $last_name = mysqli_real_escape_string(
        $conn,
        $_POST['last_name'] ?? ''
    );

    $first_name = mysqli_real_escape_string(
        $conn,
        $_POST['first_name'] ?? ''
    );

    $middle_name = mysqli_real_escape_string(
        $conn,
        $_POST['middle_name'] ?? ''
    );

    $suffix = mysqli_real_escape_string(
        $conn,
        $_POST['suffix'] ?? ''
    );

    $age = mysqli_real_escape_string(
        $conn,
        $_POST['age'] ?? ''
    );

    $gender = mysqli_real_escape_string(
        $conn,
        $_POST['gender'] ?? ''
    );

    $contact_number = mysqli_real_escape_string(
        $conn,
        $_POST['contact_number'] ?? ''
    );

    $birth_date = mysqli_real_escape_string(
        $conn,
        $_POST['birth_date'] ?? ''
    );

    $barangay = mysqli_real_escape_string(
        $conn,
        $_POST['barangay'] ?? ''
    );

    $city_municipality = mysqli_real_escape_string(
        $conn,
        $_POST['city_municipality'] ?? ''
    );

    $province = mysqli_real_escape_string(
        $conn,
        $_POST['province'] ?? ''
    );

    $service_type = mysqli_real_escape_string(
        $conn,
        $_POST['service_type'] ?? ''
    );

    $blood_type = mysqli_real_escape_string(
        $conn,
        $_POST['blood_type'] ?? ''
    );


    /* =========================
       FULL NAME
    ========================= */

    $fullname = trim(
        $first_name . ' ' .
        $middle_name . ' ' .
        $last_name . ' ' .
        $suffix
    );


    /* =========================
       FULL ADDRESS
    ========================= */

    $address_parts = [];

    if (!empty($barangay)) {
        $address_parts[] = $barangay;
    }

    if (!empty($city_municipality)) {
        $address_parts[] = $city_municipality;
    }

    if (!empty($province)) {
        $address_parts[] = $province;
    }

    $address = implode(', ', $address_parts);


    /* =========================
       UPDATE DATABASE
    ========================= */

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
            province = '$province',

            service_type = '$service_type',
            blood_type = '$blood_type'

        WHERE id = '$patient_id'
        AND role = 'patient'
    ";


    if (mysqli_query($conn, $update_sql)) {

        /* Save current scroll position */
        $scroll = isset($_POST['scroll_position'])
            ? (int)$_POST['scroll_position']
            : 0;

        $_SESSION['edit_success'] = "Patient information updated successfully.";
        $_SESSION['edit_scroll'] = $scroll;

        header("Location: admin-dashboard.php?page=" . $page);
        exit();

    } else {

        die(
            "Error updating patient: " .
            mysqli_error($conn)
        );

    }
}

/* =========================
   PATIENT COUNT
========================= */

$patient_count = 0;

$sql_count = "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'patient'
";

$result_count = mysqli_query($conn, $sql_count);

if ($result_count) {
    $row_count = mysqli_fetch_assoc($result_count);
    $patient_count = $row_count['total'];
}

/* =========================
   TOTAL PATIENTS
========================= */

$total_query = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role='patient'"
);

$total_row = mysqli_fetch_assoc($total_query);
$total_patients = $total_row['total'];

/* =========================
   PAGINATION
========================= */

$limit = 8;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

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

    $last_name = mysqli_real_escape_string(
        $conn,
        $_POST['last_name'] ?? ''
    );

    $first_name = mysqli_real_escape_string(
        $conn,
        $_POST['first_name'] ?? ''
    );

    $middle_name = mysqli_real_escape_string(
        $conn,
        $_POST['middle_name'] ?? ''
    );

    $suffix = mysqli_real_escape_string(
        $conn,
        $_POST['suffix'] ?? ''
    );

    /* ADDRESS */

    $barangay = mysqli_real_escape_string(
        $conn,
        $_POST['barangay'] ?? ''
    );

    $city_municipality = mysqli_real_escape_string(
        $conn,
        $_POST['city_municipality'] ?? ''
    );

    $province = mysqli_real_escape_string(
        $conn,
        $_POST['province'] ?? ''
    );

    /* OTHER INFORMATION */

    $age = mysqli_real_escape_string(
        $conn,
        $_POST['age'] ?? ''
    );

    $gender = mysqli_real_escape_string(
        $conn,
        $_POST['gender'] ?? ''
    );

    $contact_number = mysqli_real_escape_string(
        $conn,
        $_POST['contact_number'] ?? ''
    );

    $service_type = mysqli_real_escape_string(
        $conn,
        $_POST['service_type'] ?? ''
    );

    $blood_type = mysqli_real_escape_string(
        $conn,
        $_POST['blood_type'] ?? ''
    );

    $birth_date = mysqli_real_escape_string(
        $conn,
        $_POST['birth_date'] ?? ''
    );

    /* =========================
       FULL NAME
    ========================= */

    $fullname = trim(
        $first_name . ' ' .
        $middle_name . ' ' .
        $last_name . ' ' .
        $suffix
    );

    /* =========================
       FULL ADDRESS
    ========================= */

    $address_parts = [];

    if (!empty($barangay)) {
        $address_parts[] = $barangay;
    }

    if (!empty($city_municipality)) {
        $address_parts[] = $city_municipality;
    }

    if (!empty($province)) {
        $address_parts[] = $province;
    }

    $address = implode(', ', $address_parts);

    /* =========================
       GENERATE PATIENT CODE
    ========================= */

    $code = rand(100000, 999999);

    /* =========================
       INSERT PATIENT
    ========================= */

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
            province,

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
            '$province',

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"content="width=device-width, initial-scale=1.0">
    <title>VitalCore - Add Patients</title>
    <!-- BOOTSTRAP -->
    <link rel="stylesheet"href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <!-- BOOTSTRAP ICONS -->
    <link rel="stylesheet"href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <!-- YOUR CSS -->
    <link rel="stylesheet"href="../css/admin-dashboard.css">
    <link rel="stylesheet"href="../css/theme.css">
</head>
<body>

<div class="container-fluid">

    <div class="row">
        <nav
            class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
            <div>
                <div class="logo-section">
                    <img
                        src="../img/logo.jpg"
                        alt="VitalCore Logo"
                        class="sidebar-logo">
                    <span class="sidebar-brand">
                        VitalCore
                    </span>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-grid-1x2-fill me-2"></i>
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active sidebar-collapse-link d-flex justify-content-between align-items-center active-parent"
                            data-bs-toggle="collapse" href="#patientsMenu" role="button"
                            aria-expanded="true" aria-controls="patientsMenu">
                            <span>
                                <i class="bi bi-people-fill me-2"></i>
                                Patients
                            </span>
                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse show" id="patientsMenu">
                            <ul class="sidebar-submenu">
                                <li>
                                    <a href="patient-list.php" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        All Patients
                                    </a>
                                </li>

                                <li>
                                    <a href="admin-dashboard.php" class="sidebar-submenu-link active">
                                        <span class="submenu-dot">●</span>
                                        Add Patient
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>

                <ul class="nav flex-column">

                    <li class="nav-item">

                        <a class="nav-link sidebar-collapse-link
                                d-flex justify-content-between align-items-center"
                        data-bs-toggle="collapse"
                        href="#clinicServicesMenu"
                        role="button"
                        aria-expanded="false"
                        aria-controls="clinicServicesMenu">

                            <span>
                                <i class="bi bi-hospital-fill me-2"></i>
                                Clinic Services
                            </span>

                            <i class="bi bi-chevron-down collapse-chevron"></i>

                        </a>


                        <div class="collapse"
                            id="clinicServicesMenu">

                             <ul class="sidebar-submenu">

                                <li>
                                    <a href="Service/vital-screening.php" class="sidebar-submenu-link">
                                        <i class="bi bi-clipboard2-pulse-fill text-warning me-2"></i>
                                        <span class="submenu-dot"></span>
                                        Vital Screening
                                    </a>
                                </li>

                                <li>
                                    <a href="Service/prenatal.php" class="sidebar-submenu-link">
                                        <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                                        <span class="submenu-dot"></span>
                                        Prenatal Check-up
                                    </a>
                                </li>

                                <li>
                                    <a href="Service/child-immunization.php" class="sidebar-submenu-link">
                                        <i class="bi bi-shield-check text-success me-2"></i>
                                        <span class="submenu-dot"></span>
                                        Child Immunization
                                    </a>
                                </li>

                                <li>
                                    <a href="Service/family-planning.php" class="sidebar-submenu-link">
                                         <i class="bi bi-people-fill text-primary me-2"></i>
                                        <span class="submenu-dot"></span>
                                        Family Planning
                                    </a>
                                </li>

                            </ul>

                        </div>

                    </li>

                </ul>


                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#recordsMenu" role="button">
                            <span>
                                <i class="bi bi-folder2-open me-2"></i>
                                Patient Records
                            </span>
                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="recordsMenu">
                            <ul class="sidebar-submenu">
                                <li>
                                    <a href="logs/checkup-records.php" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Checkup Records
                                    </a>
                                </li>

                                <li>
                                    <a href="logs/patient-history.php" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Patient History
                                    </a>
                                </li>

                                <li>
                                    <a href="logs/service-records.php" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Service Records
                                    </a>
                                </li>

                                <li>
                                    <a href="logs/reports.php" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Reports
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>


                <!-- KIOSK & DEVICES <div class="sidebar-heading">KIOSK &amp; DEVICES</div> -->
                <ul class="nav flex-column">

                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center"
                           data-bs-toggle="collapse"
                           href="#kioskMenu"
                           role="button"
                           aria-expanded="false"
                           aria-controls="kioskMenu">

                            <span>
                                <i class="bi bi-display me-2"></i>
                                KioskManagement
                            </span>

                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="kioskMenu">
                            <ul class="sidebar-submenu">

                                <li>
                                    <a href="#" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Kiosk Monitor
                                    </a>
                                </li>

                                <li>
                                    <a href="#" id="sidebarSensorStatus" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Sensor Status
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>

                </ul>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#adminMenu" role="button" aria-expanded="false">
                            <span>
                                <i class="bi bi-shield-lock-fill me-2"></i>
                                Administration
                            </span>
                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="adminMenu">
                            <ul class="sidebar-submenu">
                                <li>
                                    <a href="#" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        User Management
                                    </a>
                                </li>

                                <li>
                                    <a href="#" class="sidebar-submenu-link">
                                        <span class="submenu-dot">●</span>
                                        Settings
                                    </a>
                                </li>

                            </ul>
                        </div>

                    </li>
                </ul>
            </div>

            <div class="logout-section">
                <a href="../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left"></i>
                    Log out
                </a>
            </div>
        </nav>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4 pb-3 border-bottom fade-up">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-hospital-fill me-2 text-primary"></i>
                        Good Day, Admin
                    </h3>
                    <p class="text-muted small mb-0">
                        System status overview and clinical intake telemetry.
                    </p>
                </div>

                <div>
                    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-semibold border border-success-subtle">
                        <i class="bi bi-shield-check me-1"></i>
                        Active Admin Session
                    </span>
                </div>

            </div>

            <div class="row g-3 mb-4">
                <div class="col-xl-8">
                    <div class="card stat-card p-3 fade-up fade-delay-1">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small text-uppercase fw-bold mb-1">
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
                                Patient Registration
                            </h4>
                            <small>
                                Register a patient before using the
                                VitalCore kiosk.
                            </small>
                        </div>
                    </div>

                    <?php if (!empty($generated_code)) { ?>
                        <div id="successBox" class="success-box">
                            <h5 class="text-success mb-2">
                                ✓ Patient Successfully Added
                            </h5>
                            <div>
                                Patient Code Number
                            </div>
                            <div class="code-number">
                                <?= htmlspecialchars($generated_code); ?>
                            </div>
                            <small class="text-muted">
                                Give this code to the patient.
                            </small>
                        </div>

                        <script>
                            setTimeout(() => {

                                const box =
                                    document.getElementById('successBox');

                                if (box) {

                                    box.style.display = 'none';

                                }

                            }, 5000);
                        </script>

                    <?php } ?>

                    <form method="POST" class="patient-form">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Patient Name
                            </label>
                            <div class="registration-row">
                                <div class="registration-field">
                                    <input
                                        type="text"
                                        name="last_name"
                                        class="form-control"
                                        placeholder="Last Name"
                                        required>
                                </div>

                                <div class="registration-field">
                                    <input
                                        type="text"
                                        name="first_name"
                                        class="form-control"
                                        placeholder="First Name"
                                        required>
                                </div>

                                <div class="registration-field">
                                    <input
                                        type="text"
                                        name="middle_name"
                                        class="form-control"
                                        placeholder="Middle Name">
                                </div>

                                <div class="registration-field">
                                    <input
                                        type="text"
                                        name="suffix"
                                        class="form-control"
                                        placeholder="Suffix (Jr., Sr., III)">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="registration-row">
                                <div class="registration-field">
                                    <label class="form-label fw-semibold">
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
                                            min="0"
                                            max="150"
                                            required>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <label class="form-label fw-semibold">
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
                                            <option value="Male">
                                                Male
                                            </option>
                                            <option value="Female">
                                                Female
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <label class="form-label fw-semibold">
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
                                    <label class="form-label fw-semibold">
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
                            <label class="form-label fw-semibold">
                                Address
                            </label>

                            <div class="address-row">
                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-house-fill"></i>
                                        </span>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="barangay"
                                            placeholder="Barangay"
                                            required>
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
                                            placeholder="City / Municipality"
                                            required>
                                    </div>
                                </div>

                                <div class="registration-field">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-map-fill"></i>
                                        </span>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="province"
                                            placeholder="Province"
                                            required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="registration-row mb-0">
                            <div class="registration-field">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-droplet-fill text-danger me-1"></i>
                                    Blood Type
                                    <small class="text-muted fw-normal">
                                        (Optional)
                                    </small>
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text bg-danger text-white">
                                        <i class="bi bi-droplet-fill"></i>
                                    </span>
                                    <select
                                        class="form-select"
                                        name="blood_type">
                                        <option
                                            value=""
                                            selected>
                                            Select blood type...
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
                      
                        <button type="submit" name="save" class="btn btn-save w-100">
                            <i class="bi bi-person-check-fill me-2"></i>
                            Register Patient
                        </button>
                    </form>
                </div>
            </div>

            <div class="list-card fade-up fade-delay-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
                    <h3 class="fw-bold m-0">
                        Patient List
                    </h3>
                    <input type="text"
                        id="searchPatient"
                        class="form-control search-box"
                        style="max-width:280px"
                        placeholder="Search patient...">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Code</th>
                                <th class="text-center">Action</th>
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
                                        <?= htmlspecialchars($row['fullname']); ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['age']); ?></td>
                                <td><?= htmlspecialchars($row['gender']); ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['code_number']); ?></td>

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
                    <small class="text-muted">
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
                                Edit Patient Information
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

                                <label class="form-label fw-semibold">
                                    Patient Name
                                </label>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-3">
                                        <input
                                            type="text"
                                            name="last_name"
                                            class="form-control"
                                            placeholder="Last Name"
                                            value="<?= htmlspecialchars($row['last_name'] ?? ''); ?>"
                                            required>
                                    </div>

                                    <div class="col-md-3">
                                        <input
                                            type="text"
                                            name="first_name"
                                            class="form-control"
                                            placeholder="First Name"
                                            value="<?= htmlspecialchars($row['first_name'] ?? ''); ?>"
                                            required>
                                    </div>

                                    <div class="col-md-3">
                                        <input
                                            type="text"
                                            name="middle_name"
                                            class="form-control"
                                            placeholder="Middle Name"
                                            value="<?= htmlspecialchars($row['middle_name'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input
                                            type="text"
                                            name="suffix"
                                            class="form-control"
                                            placeholder="Suffix"
                                            value="<?= htmlspecialchars($row['suffix'] ?? ''); ?>">
                                    </div>
                                </div>
                                <!-- AGE / GENDER / CONTACT / BIRTH DATE -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
                                            Age
                                        </label>
                                        <input
                                            type="number"
                                            name="age"
                                            class="form-control"
                                            min="0"
                                            max="150"
                                            value="<?= htmlspecialchars($row['age'] ?? ''); ?>"
                                            required>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
                                            Gender
                                        </label>
                                        <select
                                            name="gender"
                                            class="form-select"
                                            required>
                                            <option
                                                value="Male"
                                                <?= ($row['gender'] ?? '') === 'Male'
                                                    ? 'selected'
                                                    : ''; ?>>
                                                Male
                                            </option>

                                            <option
                                                value="Female"
                                                <?= ($row['gender'] ?? '') === 'Female'
                                                    ? 'selected'
                                                    : ''; ?>>
                                                Female
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
                                            Contact Number
                                        </label>
                                        <input
                                            type="text"
                                            name="contact_number"
                                            class="form-control"
                                            placeholder="09XXXXXXXXX"
                                            value="<?= htmlspecialchars($row['contact_number'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
                                            Birth Date
                                        </label>
                                        <input
                                            type="date"
                                            name="birth_date"
                                            class="form-control"
                                            value="<?= htmlspecialchars($row['birth_date'] ?? ''); ?>">
                                    </div>
                                </div>
                                <!-- ADDRESS -->
                                <label class="form-label fw-semibold">
                                    Address
                                </label>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <input
                                            type="text"
                                            name="barangay"
                                            class="form-control"
                                            placeholder="Barangay"
                                            value="<?= htmlspecialchars($row['barangay'] ?? ''); ?>"
                                            required>
                                    </div>

                                    <div class="col-md-4">
                                        <input
                                            type="text"
                                            name="city_municipality"
                                            class="form-control"
                                            placeholder="City / Municipality"
                                            value="<?= htmlspecialchars($row['city_municipality'] ?? ''); ?>"
                                            required>
                                    </div>

                                    <div class="col-md-4">
                                        <input
                                            type="text"
                                            name="province"
                                            class="form-control"
                                            placeholder="Province"
                                            value="<?= htmlspecialchars($row['province'] ?? ''); ?>"
                                            required>
                                    </div>
                                </div>
                                <!-- SERVICE / BLOOD TYPE -->
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            Blood Type
                                            <small class="text-muted fw-normal">
                                                (Optional)
                                            </small>
                                        </label>
                                        <select
                                            name="blood_type"
                                            class="form-select">
                                            <option value=""<?= empty($row['blood_type'])? 'selected': ''; ?>>
                                                Select blood type...
                                            </option>

                                            <option value="A+"<?= ($row['blood_type'] ?? '') === 'A+'? 'selected': ''; ?>> 🩸 A+</option>
                                            <option value="A-"<?= ($row['blood_type'] ?? '') === 'A-'? 'selected': ''; ?>> 🩸 A-</option>
                                            <option value="B+"<?= ($row['blood_type'] ?? '') === 'B+'? 'selected': ''; ?>> 🩸 B+</option>
                                            <option value="B-"<?= ($row['blood_type'] ?? '') === 'B-'? 'selected': ''; ?>> 🩸 B-</option>
                                            <option value="AB+"<?= ($row['blood_type'] ?? '') === 'AB+'? 'selected': ''; ?>>🩸 AB+</option>
                                            <option value="AB-"<?= ($row['blood_type'] ?? '') === 'AB-'? 'selected': ''; ?>> 🩸 AB-</option>
                                            <option value="O+"<?= ($row['blood_type'] ?? '') === 'O+'? 'selected': ''; ?>> 🩸 O+</option>
                                            <option value="O-"<?= ($row['blood_type'] ?? '') === 'O-'? 'selected': ''; ?>> 🩸 O-</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                          
                            <div class="modal-footer">
                                <button
                                    type="button"
                                    class="btn btn-secondary px-4"
                                    data-bs-dismiss="modal">
                                    <i class="bi bi-x-lg me-1"></i>
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    class="btn btn-warning px-4 fw-semibold">
                                    <i class="bi bi-check-lg me-1"></i>
                                    Save Changes
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
                                Delete Patient
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

                            <h5 class="fw-bold mb-2">
                                Are you sure?
                            </h5>

                            <p class="text-muted mb-0">
                                You are about to delete
                                <strong>
                                    <?= htmlspecialchars($row['fullname']); ?>
                                </strong>
                                from the patient registry.
                            </p>

                            <small class="text-danger d-block mt-2">
                                This action cannot be undone.
                            </small>
                        </div>

                        <div class="modal-footer justify-content-center">
                            <button type="button"
                                    class="btn btn-secondary px-4"
                                    data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-1"></i>
                                Cancel
                            </button>

                            <a href="delete-patient.php?id=<?= $patient_id; ?>"
                            class="btn btn-danger px-4">
                                <i class="bi bi-trash-fill me-1"></i>
                                Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php } ?>
        </main>


    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const searchPatient =
    document.getElementById('searchPatient');

if (searchPatient) {

    searchPatient.addEventListener(
        'keyup',
        function () {

            let filter =
                this.value.toLowerCase();

            document
                .querySelectorAll(
                    '#patientTable tr'
                )
                .forEach(function (row) {

                    row.style.display =
                        row.textContent
                            .toLowerCase()
                            .includes(filter)
                            ? ''
                            : 'none';

                });

        }
    );

}
</script>
<script src="../assets/js/theme.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const savedScrollPosition = sessionStorage.getItem("patientScrollPosition");

    if (savedScrollPosition !== null) {

        window.scrollTo({
            top: parseInt(savedScrollPosition),
            behavior: "instant"
        });

        sessionStorage.removeItem("patientScrollPosition");
    }

});

document.querySelectorAll('a[href^="delete-patient.php"]').forEach(function (deleteButton) {

    deleteButton.addEventListener("click", function () {

        sessionStorage.setItem(
            "patientScrollPosition",
            window.scrollY
        );

    });

});
</script>

<script>
/* =====================================================
   REMEMBER SCROLL POSITION
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    const savedScrollPosition =
        sessionStorage.getItem("patientScrollPosition");

    if (savedScrollPosition !== null) {

        window.scrollTo(
            0,
            parseInt(savedScrollPosition)
        );

        sessionStorage.removeItem(
            "patientScrollPosition"
        );
    }


    /* ================================================
       SAVE POSITION BEFORE DELETE
    ================================================ */

    document
        .querySelectorAll('a[href^="delete-patient.php"]')
        .forEach(function (deleteButton) {

            deleteButton.addEventListener(
                "click",
                function () {

                    sessionStorage.setItem(
                        "patientScrollPosition",
                        window.scrollY
                    );

                }
            );

        });


    /* ================================================
       SAVE POSITION BEFORE EDIT
    ================================================ */
    document
        .querySelectorAll('.edit-scroll-position')
        .forEach(function (input) {

            input.value = window.scrollY;

        });
    document
        .querySelectorAll('[data-bs-target^="#editModal"]')
        .forEach(function (button) {

            button.addEventListener(
                "click",
                function () {

                    const modalId =
                        this.getAttribute("data-bs-target");

                    const modal =
                        document.querySelector(modalId);

                    if (modal) {

                        const scrollInput =
                            modal.querySelector(
                                '.edit-scroll-position'
                            );

                        if (scrollInput) {

                            scrollInput.value =
                                window.scrollY;

                        }

                    }

                }
            );

        });

});
</script>

</body>
</html>
<?php
mysqli_close($conn);
?>