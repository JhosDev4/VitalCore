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

    $conn = mysqli_connect("localhost", "root", "", "vitalcore_db");

    if (!$conn) {
        die("Connection Failed: " . mysqli_connect_error());
    }
    /* TOTAL PATIENTS */
    $count_query = mysqli_query(
        $conn,
        "SELECT COUNT(DISTINCT u.id) AS total
        FROM users u
        INNER JOIN measurements m
            ON u.id = m.user_id
        WHERE u.role = 'patient'
        AND m.service_type = 'vital'"
    );

    $count_row = mysqli_fetch_assoc($count_query);
    $patient_count = $count_row['total'];

    /* PATIENT LIST */
    $patients = mysqli_query(
        $conn,
        "SELECT DISTINCT u.*
        FROM users u
        INNER JOIN measurements m
            ON u.id = m.user_id
        WHERE m.service_type = 'vital'
        AND u.role = 'patient'
        ORDER BY u.id DESC"
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
    <html lang="en">
    <head>
    <meta name="google" content="notranslate">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Patient List</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/patient-list.css">
    <link rel="stylesheet" href="../../css/theme.css">
    </head>

    <body>



    <div class="container-fluid">
        <div class="row">

 <!-- Sidebar Navigation -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
        <div>
            <!-- LOGO & BRAND -->
           <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;">
                <span class="sidebar-brand">VitalCore</span>
           </div>

            <div class="sidebar-menu-wrapper">

                <!-- SECTION: MAIN -->
                <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                    Main
                </small>

                <ul class="nav flex-column mb-3">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-grid-1x2-fill me-2"></i>
                            Dashboard
                        </a>
                    </li>
                </ul>

                <!-- SECTION: CLINICAL SERVICES & PATIENTS -->
                <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                    Clinical Services
                </small>

                <ul class="nav flex-column mb-3">
                    <!-- Patient Management -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-collapse-link active d-flex justify-content-between align-items-center"
                        data-bs-toggle="collapse"
                        href="#patientsMenu"
                        role="button"
                        aria-expanded="True"
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
                                    <a href="../patient-list.php" class="sidebar-submenu-link active text-decoration-none">
                                        <i class="bi bi-list-ul me-2"></i>
                                        All Patients Services
                                    </a>
                                </li>
                                <li class="py-1">
                                    <a href="../admin-dashboard.php" class="sidebar-submenu-link text-decoration-none">
                                        <i class="bi bi-person-plus-fill me-2"></i>
                                        Add Patient
                                    </a>
                                </li>
                                <li class="py-1">
                                    <a href="#" class="sidebar-submenu-link text-decoration-none">
                                        <i class="bi bi-hospital-fill me-2 text-info"></i>
                                        Add Service
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
                                    <a href="../logs/patient-history.php" class="sidebar-submenu-link text-decoration-none">
                                        <i class="bi bi-clock-history me-2"></i>
                                        Patient Records
                                    </a>
                                </li>
                                <li class="py-1">
                                    <a href="../logs/reports.php" class="sidebar-submenu-link text-decoration-none">
                                        <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                        Reports
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>

                <!-- SECTION: HARDWARE & SYSTEM -->
                <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                    Hardware & System
                </small>

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
                                Administration
                            </span>
                            <i class="bi bi-chevron-down collapse-chevron"></i>
                        </a>

                        <div class="collapse" id="adminMenu">
                            <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                <li class="py-1">
                                    <a href="#" class="sidebar-submenu-link text-decoration-none">
                                        <i class="bi bi-person-gear me-2"></i>
                                        User Management
                                    </a>
                                </li>
                                <li class="py-1">
                                    <a href="../setting.php" class="sidebar-submenu-link text-decoration-none">
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
            <!-- MAIN CONTENT -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

               <div class="d-flex align-items-center justify-content-between flex-wrap mb-4 page-header">

                <div class="patient-grid-header d-flex align-items-center">

                    <h4 class="fw-bold mb-0 patient-title">
                        Vital Screening Grid
                    </h4>

                    <div class="dropdown">
                        <button class="btn patient-menu-btn"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">

                            <i class="bi bi-list"></i>
                            <span>Services Categories</span>

                        </button>

                        <ul class="dropdown-menu patient-dropdown shadow border-0">

                            <li>
                                <a class="dropdown-item" href="vital-screening.php">
                                    <i class="bi bi-clipboard2-pulse-fill text-warning me-2"></i>
                                    Vital Screening Grid
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="prenatal.php">
                                    <i class="bi bi-heart-pulse-fill text-danger me-2"></i>
                                    Prenatal Grid
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="family-planning.php">
                                    <i class="bi bi-people-fill text-primary me-2"></i>
                                    Family Planning Grid
                                </a>
                            </li>

                           
                        </ul>
                    </div>

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

                                    <div class="dropdown">

                                        <button class="btn btn-light btn-sm rounded-circle"
                                            data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>

                                        <ul class="dropdown-menu dropdown-menu-end">

                                            <li>
                                                <a class="dropdown-item"
                                                    href="../patient-view.php?id=<?= $row['id'] ?>">
                                                    View
                                                </a>
                                            </li>

                                            <li>
                                                <a class="dropdown-item"
                                                    href="patient-edit.php?id=<?= $row['id'] ?>">
                                                    Edit
                                                </a>
                                            </li>

                                            <li>
                                                <a class="dropdown-item text-danger"
                                                    href="patient-delete.php?id=<?= $row['id'] ?>">
                                                    Delete
                                                </a>
                                            </li>

                                        </ul>

                                    </div>

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
    <script src="../../assets/js/theme.js"></script>
        
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
    </body>
    </html>