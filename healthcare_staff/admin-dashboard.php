<?php
session_start();

$generated_code = "";

if (isset($_SESSION['generated_code'])) {
    $generated_code = $_SESSION['generated_code'];
    unset($_SESSION['generated_code']);
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


if (
    !isset($_SESSION['staff_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Staff'
) {
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
        $_POST['city_municipality'] ?? 'Villaba'
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
        $_POST['city_municipality'] ?? 'Villaba'
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
<html lang="en">
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitalCore - Add Patients</title>
    <!-- BOOTSTRAP -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <!-- BOOTSTRAP ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <!-- YOUR CSS -->
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <link rel="stylesheet" href="../css/theme.css">
</head>
<body>

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
                                Patient Management
                            </span>
                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse show" id="patientsMenu">
                            <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                <li class="py-1">
                                    <a href="patient-list.php" class="sidebar-submenu-link text-decoration-none">
                                        <i class="bi bi-list-ul me-2"></i>
                                        All Patients Services
                                    </a>
                                </li>
                                <li class="py-1">
                                    <a href="admin-dashboard.php" class="sidebar-submenu-link active text-decoration-none">
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
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person-fill"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="last_name"
                                            class="form-control"
                                            placeholder="Last Name"
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
                                            placeholder="Middle Name">
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
                                            placeholder="Suffix (Jr., Sr., III)">
                                    </div>
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
                                Address & Details
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
                                            <option value="" selected>Select Barangay...</option>
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
                                                selected>
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
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                            <input
                                                type="text"
                                                name="last_name"
                                                class="form-control"
                                                placeholder="Last Name"
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
                                                value="<?= htmlspecialchars($row['suffix'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <!-- AGE / GENDER / CONTACT / BIRTH DATE -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
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
                                                value="<?= htmlspecialchars($row['age'] ?? ''); ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
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
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
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
                                        <label class="form-label fw-semibold">
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
                                <label class="form-label fw-semibold">
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
                                                <option value="" <?= empty($row['barangay']) ? 'selected' : ''; ?>>
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
                                                value="<?= htmlspecialchars(!empty($row['city_municipality']) ? $row['city_municipality'] : 'Villaba'); ?>"
                                                required>
                                        </div>
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
                                        <div class="input-group">
                                            <span class="input-group-text bg-danger text-white"><i class="bi bi-droplet-fill"></i></span>
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
<?php
mysqli_close($conn);
?>