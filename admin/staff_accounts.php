<?php

session_start();

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| ADMINISTRATOR ACCESS
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {

    header("Location: ../login.php");
    exit();
}

require_once "../db_conn.php";

/** @var mysqli $conn */

$error_message = "";
$success_message = "";


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET['created'])) {

    $success_message = "Staff account created successfully!";

} elseif (isset($_GET['updated'])) {

    $success_message = "Staff account updated successfully!";

} elseif (isset($_GET['deleted'])) {

    $success_message = "Staff account deleted successfully!";
}


/*
|--------------------------------------------------------------------------
| CREATE STAFF ACCOUNT
|--------------------------------------------------------------------------
*/

if (isset($_POST['create_staff'])) {

    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $role = in_array(
        $_POST['role'] ?? '',
        ['Staff', 'Administrator'],
        true
    ) ? $_POST['role'] : 'Staff';

    $status = in_array(
        $_POST['status'] ?? '',
        ['Active', 'Inactive'],
        true
    ) ? $_POST['status'] : 'Active';


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($fullname === '') {

        $error_message = "Full name is required.";

    } elseif ($username === '') {

        $error_message = "Username is required.";

    } elseif ($password === '') {

        $error_message = "Password is required.";

    /*
    |--------------------------------------------------------------------------
    | RESERVE BUILT-IN ADMIN USERNAME
    |--------------------------------------------------------------------------
    */

    } elseif (strcasecmp($username, 'admin') === 0) {

        $error_message =
            'The username "admin" is reserved for the system administrator.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE USERNAME
        |--------------------------------------------------------------------------
        */

        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM staff_accounts
             WHERE username = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check_stmt,
            "s",
            $username
        );

        mysqli_stmt_execute($check_stmt);

        $check_result = mysqli_stmt_get_result($check_stmt);


        if (mysqli_num_rows($check_result) > 0) {

            $error_message = "Username already exists.";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO staff_accounts
                (fullname, username, password, role, status)
                VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $insert_stmt,
                "sssss",
                $fullname,
                $username,
                $hashed_password,
                $role,
                $status
            );


            if (mysqli_stmt_execute($insert_stmt)) {

                mysqli_stmt_close($insert_stmt);
                mysqli_stmt_close($check_stmt);

                header("Location: staff_accounts.php?created=1");
                exit();

            } else {

                $error_message =
                    "Failed to create account: " .
                    mysqli_error($conn);
            }

            mysqli_stmt_close($insert_stmt);
        }

        mysqli_stmt_close($check_stmt);
    }
}


/*
|--------------------------------------------------------------------------
| UPDATE STAFF ACCOUNT
|--------------------------------------------------------------------------
*/

if (isset($_POST['update_staff'])) {

    $id = intval($_POST['staff_id'] ?? 0);

    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $role = in_array(
        $_POST['role'] ?? '',
        ['Staff', 'Administrator'],
        true
    ) ? $_POST['role'] : 'Staff';

    $status = in_array(
        $_POST['status'] ?? '',
        ['Active', 'Inactive'],
        true
    ) ? $_POST['status'] : 'Active';


    if ($id <= 0) {

        $error_message = "Invalid staff account.";

    } elseif ($fullname === '') {

        $error_message = "Full name is required.";

    } elseif ($username === '') {

        $error_message = "Username is required.";

    /*
    |--------------------------------------------------------------------------
    | PROTECT BUILT-IN ADMIN USERNAME
    |--------------------------------------------------------------------------
    */

    } elseif (strcasecmp($username, 'admin') === 0) {

        $error_message =
            'The username "admin" is reserved for the system administrator.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | GET CURRENT ACCOUNT
        |--------------------------------------------------------------------------
        */

        $target_stmt = mysqli_prepare(
            $conn,
            "SELECT id, fullname, username, role, status
             FROM staff_accounts
             WHERE id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $target_stmt,
            "i",
            $id
        );

        mysqli_stmt_execute($target_stmt);

        $target_result = mysqli_stmt_get_result($target_stmt);


        if (mysqli_num_rows($target_result) !== 1) {

            $error_message = "Staff account not found.";

            mysqli_stmt_close($target_stmt);

        } else {

            $target = mysqli_fetch_assoc($target_result);

            mysqli_stmt_close($target_stmt);


            /*
            |--------------------------------------------------------------------------
            | CHECK IF THIS IS THE CURRENTLY LOGGED-IN ACCOUNT
            |--------------------------------------------------------------------------
            */

            $current_staff_id =
                intval($_SESSION['staff_id'] ?? 0);

            $is_current_account =
                ($current_staff_id === $id);


            /*
            |--------------------------------------------------------------------------
            | COUNT ADMINISTRATORS
            |--------------------------------------------------------------------------
            */

            $admin_count_result = mysqli_query(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM staff_accounts
                 WHERE role = 'Administrator'"
            );

            $admin_count_row =
                mysqli_fetch_assoc($admin_count_result);

            $administrator_count =
                intval($admin_count_row['total']);


            /*
            |--------------------------------------------------------------------------
            | PROTECT LAST ADMINISTRATOR
            |--------------------------------------------------------------------------
            */

            if (
                $target['role'] === 'Administrator'
                && $administrator_count <= 1
                && (
                    $role !== 'Administrator'
                    || $status !== 'Active'
                )
            ) {

                $error_message =
                    "The last Administrator cannot be demoted or deactivated.";

            /*
            |--------------------------------------------------------------------------
            | PROTECT CURRENT ACCOUNT
            |--------------------------------------------------------------------------
            */

            } elseif (
                $is_current_account
                && (
                    $role !== 'Administrator'
                    || $status !== 'Active'
                )
            ) {

                $error_message =
                    "You cannot deactivate or change the role of the account currently being used.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | CHECK DUPLICATE USERNAME
                |--------------------------------------------------------------------------
                */

                $check_stmt = mysqli_prepare(
                    $conn,
                    "SELECT id
                     FROM staff_accounts
                     WHERE username = ?
                     AND id != ?
                     LIMIT 1"
                );

                mysqli_stmt_bind_param(
                    $check_stmt,
                    "si",
                    $username,
                    $id
                );

                mysqli_stmt_execute($check_stmt);

                $check_result =
                    mysqli_stmt_get_result($check_stmt);


                if (mysqli_num_rows($check_result) > 0) {

                    $error_message =
                        "Username is already used by another account.";

                    mysqli_stmt_close($check_stmt);

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE WITH NEW PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    if ($password !== '') {

                        $hashed_password =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                        $update_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE staff_accounts
                             SET fullname = ?,
                                 username = ?,
                                 password = ?,
                                 role = ?,
                                 status = ?
                             WHERE id = ?"
                        );

                        mysqli_stmt_bind_param(
                            $update_stmt,
                            "sssssi",
                            $fullname,
                            $username,
                            $hashed_password,
                            $role,
                            $status,
                            $id
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE WITHOUT CHANGING PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    } else {

                        $update_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE staff_accounts
                             SET fullname = ?,
                                 username = ?,
                                 role = ?,
                                 status = ?
                             WHERE id = ?"
                        );

                        mysqli_stmt_bind_param(
                            $update_stmt,
                            "ssssi",
                            $fullname,
                            $username,
                            $role,
                            $status,
                            $id
                        );
                    }


                    if (mysqli_stmt_execute($update_stmt)) {

                        mysqli_stmt_close($update_stmt);
                        mysqli_stmt_close($check_stmt);

                        header(
                            "Location: staff_accounts.php?updated=1"
                        );

                        exit();

                    } else {

                        $error_message =
                            "Failed to update account: " .
                            mysqli_error($conn);
                    }

                    mysqli_stmt_close($update_stmt);
                    mysqli_stmt_close($check_stmt);
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE STAFF ACCOUNT
|--------------------------------------------------------------------------
*/

if (isset($_POST['delete_staff'])) {

    $id = intval($_POST['staff_id'] ?? 0);


    if ($id <= 0) {

        $error_message = "Invalid staff account.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | GET ACCOUNT BEING DELETED
        |--------------------------------------------------------------------------
        */

        $target_stmt = mysqli_prepare(
            $conn,
            "SELECT id, username, role
             FROM staff_accounts
             WHERE id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $target_stmt,
            "i",
            $id
        );

        mysqli_stmt_execute($target_stmt);

        $target_result =
            mysqli_stmt_get_result($target_stmt);


        if (mysqli_num_rows($target_result) !== 1) {

            $error_message = "Staff account not found.";

            mysqli_stmt_close($target_stmt);

        } else {

            $target = mysqli_fetch_assoc($target_result);

            mysqli_stmt_close($target_stmt);


            /*
            |--------------------------------------------------------------------------
            | CURRENT LOGGED-IN ACCOUNT
            |--------------------------------------------------------------------------
            */

            $current_staff_id =
                intval($_SESSION['staff_id'] ?? 0);


            if ($current_staff_id === $id) {

                $error_message =
                    "You cannot delete the account currently being used.";

            /*
            |--------------------------------------------------------------------------
            | NEVER DELETE BUILT-IN ADMIN USERNAME
            |--------------------------------------------------------------------------
            */

            } elseif (
                strcasecmp($target['username'], 'admin') === 0
            ) {

                $error_message =
                    'The "admin" account is protected.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | PROTECT LAST ADMINISTRATOR
                |--------------------------------------------------------------------------
                */

                if ($target['role'] === 'Administrator') {

                    $admin_count_result = mysqli_query(
                        $conn,
                        "SELECT COUNT(*) AS total
                         FROM staff_accounts
                         WHERE role = 'Administrator'"
                    );

                    $admin_count_row =
                        mysqli_fetch_assoc(
                            $admin_count_result
                        );

                    $administrator_count =
                        intval($admin_count_row['total']);


                    if ($administrator_count <= 1) {

                        $error_message =
                            "The last Administrator cannot be deleted.";
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | DELETE ACCOUNT
                |--------------------------------------------------------------------------
                */

                if ($error_message === '') {

                    $delete_stmt = mysqli_prepare(
                        $conn,
                        "DELETE FROM staff_accounts
                         WHERE id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $delete_stmt,
                        "i",
                        $id
                    );


                    if (mysqli_stmt_execute($delete_stmt)) {

                        mysqli_stmt_close($delete_stmt);

                        header(
                            "Location: staff_accounts.php?deleted=1"
                        );

                        exit();

                    } else {

                        $error_message =
                            "Failed to delete account: " .
                            mysqli_error($conn);
                    }

                    mysqli_stmt_close($delete_stmt);
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| FETCH STAFF ACCOUNTS
|--------------------------------------------------------------------------
*/

$staff_query = mysqli_query(
    $conn,
    "SELECT id,
            fullname,
            username,
            role,
            status,
            created_at
     FROM staff_accounts
     ORDER BY id DESC"
);


if (!$staff_query) {

    die(
        "Query Failed: " .
        mysqli_error($conn)
    );
}


$staff_count = mysqli_num_rows($staff_query);
?>

<!DOCTYPE html>
<html lang="en" translate="no">

<head>
    <script>
        if (localStorage.getItem("theme") === "dark") {
            document.documentElement.classList.add("dark-mode");
        }
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitalCore - User Management</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/theme.css">
</head>

<body>

<div class="container-fluid">
    <div class="row">

        <!-- =========================
             SIDEBAR
        ========================== -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
            <div>
                <!-- LOGO & BRAND -->
                <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

                <div class="sidebar-menu-wrapper">
                    <!-- MAIN -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Main</small>
                    <ul class="nav flex-column mb-3">
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/dashboard.php">
                                <i class="bi bi-grid-1x2-fill me-2"></i> Dashboard
                            </a>
                        </li>
                    </ul>

                    <!-- CLINICAL SERVICES -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Clinical Services</small>
                    <ul class="nav flex-column mb-3">
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#patientsMenu" role="button" aria-expanded="false" aria-controls="patientsMenu">
                                <span><i class="bi bi-people-fill me-2 text-primary"></i> Patient Management</span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse" id="patientsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="../admin/patient-list.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-list-ul me-2"></i> All Patients Services
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="../admin/admin-dashboard.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-person-plus-fill me-2"></i> Add Patient
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#recordsMenu" role="button" aria-expanded="false" aria-controls="recordsMenu">
                                <span><i class="bi bi-folder2-open me-2 text-warning"></i> Patient Records</span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse" id="recordsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="../admin/logs/patient-history.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-clock-history me-2"></i> Patient Records
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="../admin/logs/reports.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-file-earmark-bar-graph me-2"></i> Reports
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>

                    <!-- HARDWARE & SYSTEM -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Hardware & System</small>
                    <ul class="nav flex-column mb-3">
                        <!-- Administration -->
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center active" data-bs-toggle="collapse" href="#adminMenu" role="button" aria-expanded="true" aria-controls="adminMenu">
                                <span><i class="bi bi-shield-lock-fill me-2 text-danger"></i> Administration</span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse show" id="adminMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="staff_accounts.php" class="sidebar-submenu-link text-decoration-none active">
                                            <i class="bi bi-person-gear me-2"></i> User Management
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="setting.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-sliders me-2"></i> Settings
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- LOGOUT -->
            <div class="pt-3 px-2 border-top border-secondary border-opacity-25">
                <a href="../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left fs-5"></i> Log out
                </a>
            </div>
        </nav>

        <!-- =========================
             MAIN CONTENT
        ========================== -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <!-- HEADER -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 fade-up">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-person-gear me-2 text-primary"></i> User Management
                    </h3>
                    <p class="text-muted small mb-0">
                        Manage VitalCore clinic staff accounts and access.
                    </p>
                </div>

                <div class="top-actions m-0">
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill fw-semibold shadow-sm" style="font-size: 0.85rem;">
                        <i class="bi bi-calendar-event me-1 text-primary"></i> <?= date("l, M d, Y"); ?>
                    </span>
                    <button id="darkModeToggle" class="darkmode-btn">
                        <i class="bi bi-moon-stars-fill"></i> <span>Dark Mode</span>
                    </button>
                </div>
            </div>

            <!-- NOTIFICATION ALERTS -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- STAFF MANAGEMENT CARD -->
            <div class="card analytics-card p-4 fade-up fade-delay-1">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="bi bi-people-fill text-primary me-2"></i> Staff Accounts
                        </h5>
                        <small class="text-muted">
                            <?= $staff_count; ?> staff account<?= $staff_count != 1 ? 's' : ''; ?> registered
                        </small>
                    </div>

                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="bi bi-person-plus-fill me-1"></i> Add Staff
                    </button>
                </div>

                <!-- STAFF TABLE -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($staff_count > 0): ?>
                            <?php while ($staff = mysqli_fetch_assoc($staff_query)): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $staff['id']; ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($staff['fullname']); ?></td>
                                    <td><?= htmlspecialchars($staff['username']); ?></td>
                                    <td>
                                        <?php
                                        $role_class = ($staff['role'] === 'Administrator') 
                                            ? 'bg-danger-subtle text-danger' 
                                            : 'bg-primary-subtle text-primary';
                                        ?>
                                        <span class="badge <?= $role_class ?> fw-semibold">
                                            <?= htmlspecialchars($staff['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($staff['status'] === 'Active'): ?>
                                            <span class="badge bg-success-subtle text-success fw-semibold">
                                                <i class="bi bi-check-circle-fill me-1"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary fw-semibold">
                                                <i class="bi bi-x-circle-fill me-1"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted">
                                        <?= date("M d, Y", strtotime($staff['created_at'])); ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary rounded-pill px-3 edit-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editStaffModal"
                                                data-id="<?= $staff['id']; ?>"
                                                data-fullname="<?= htmlspecialchars($staff['fullname']); ?>"
                                                data-username="<?= htmlspecialchars($staff['username']); ?>"
                                                data-role="<?= htmlspecialchars($staff['role']); ?>"
                                                data-status="<?= htmlspecialchars($staff['status']); ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>

                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger rounded-pill px-3 delete-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteStaffModal"
                                                data-id="<?= $staff['id']; ?>"
                                                data-name="<?= htmlspecialchars($staff['fullname']); ?>">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-2 d-block mb-2 opacity-50"></i>
                                    No staff accounts found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- =========================
     ADD STAFF MODAL
========================== -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-plus-fill text-primary me-2"></i> Add Staff Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="fullname" class="form-control" placeholder="Enter full name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="Staff">Staff</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_staff" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-person-plus-fill me-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================
     EDIT STAFF MODAL
========================== -->
<div class="modal fade" id="editStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil-square text-primary me-2"></i> Edit Staff Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="staff_id" id="edit_staff_id">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="fullname" id="edit_fullname" class="form-control" placeholder="Enter full name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" placeholder="Enter username" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                        <small class="text-muted">Only fill this in if you want to change the password.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="Staff">Staff</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_staff" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================
     DELETE CONFIRMATION MODAL
========================== -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="staff_id" id="delete_staff_id">

                <div class="modal-body">
                    <p>Are you sure you want to delete the account for <strong id="delete_staff_name"></strong>?</p>
                    <p class="text-muted small mb-0">This action cannot be undone.</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_staff" class="btn btn-danger rounded-pill px-4">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Theme JS -->
<script src="../assets/js/theme.js"></script>

<!-- Modal Data Population Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Populate Edit Modal
    const editButtons = document.querySelectorAll(".edit-btn");
    editButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            document.getElementById("edit_staff_id").value = this.dataset.id;
            document.getElementById("edit_fullname").value = this.dataset.fullname;
            document.getElementById("edit_username").value = this.dataset.username;
            document.getElementById("edit_role").value = this.dataset.role;
            document.getElementById("edit_status").value = this.dataset.status;
        });
    });

    // Populate Delete Modal
    const deleteButtons = document.querySelectorAll(".delete-btn");
    deleteButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            document.getElementById("delete_staff_id").value = this.dataset.id;
            document.getElementById("delete_staff_name").textContent = this.dataset.name;
        });
    });
});
</script>
<!-- SYSTEM SETTINGS ENGINE SCRIPT (Applies Theme, Brightness, NightLight, TextSize, Language) -->
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

<?php mysqli_close($conn); ?>