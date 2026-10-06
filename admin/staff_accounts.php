<?php

session_start();

date_default_timezone_set('Asia/Manila');

// ADMINISTRATOR ACCESS
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login.php");
    exit();
}

require_once "../db_conn.php";

/** @var mysqli $conn */

$error_message = "";
$success_message = "";


// SUCCESS MESSAGES
if (isset($_GET['created'])) {
    $success_message = "Staff account created successfully!";
} elseif (isset($_GET['updated'])) {
    $success_message = "Staff account updated successfully!";
} elseif (isset($_GET['deleted'])) {
    $success_message = "Staff account deleted successfully!";
}


// CREATE STAFF ACCOUNT
if (isset($_POST['create_staff'])) {

    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $role = in_array($_POST['role'] ?? '', ['Staff', 'Administrator'], true) ? $_POST['role'] : 'Staff';
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';

    // Basic validation
    if ($fullname === '') {

        $error_message = "Full name is required.";

    } elseif ($username === '') {

        $error_message = "Username is required.";

    } elseif ($password === '') {

        $error_message = "Password is required.";

    // Reserve built-in admin username
    } elseif (strcasecmp($username, 'admin') === 0) {

        $error_message = 'The username "admin" is reserved for the system administrator.';

    } else {

        // Check duplicate username
        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM staff_accounts WHERE username = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($check_stmt, "s", $username);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {

            $error_message = "Username already exists.";

        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO staff_accounts (fullname, username, password, role, status)
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

                $error_message = "Failed to create account: " . mysqli_error($conn);
            }

            mysqli_stmt_close($insert_stmt);
        }

        mysqli_stmt_close($check_stmt);
    }
}


// UPDATE STAFF ACCOUNT
if (isset($_POST['update_staff'])) {

    $id = intval($_POST['staff_id'] ?? 0);

    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $role = in_array($_POST['role'] ?? '', ['Staff', 'Administrator'], true) ? $_POST['role'] : 'Staff';
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';

    if ($id <= 0) {

        $error_message = "Invalid staff account.";

    } elseif ($fullname === '') {

        $error_message = "Full name is required.";

    } elseif ($username === '') {

        $error_message = "Username is required.";

    // Protect built-in admin username
    } elseif (strcasecmp($username, 'admin') === 0) {

        $error_message = 'The username "admin" is reserved for the system administrator.';

    } else {

        // Get current account
        $target_stmt = mysqli_prepare(
            $conn,
            "SELECT id, fullname, username, role, status
             FROM staff_accounts WHERE id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($target_stmt, "i", $id);
        mysqli_stmt_execute($target_stmt);
        $target_result = mysqli_stmt_get_result($target_stmt);

        if (mysqli_num_rows($target_result) !== 1) {

            $error_message = "Staff account not found.";
            mysqli_stmt_close($target_stmt);

        } else {

            $target = mysqli_fetch_assoc($target_result);
            mysqli_stmt_close($target_stmt);

            // Is this the currently logged-in account?
            $current_staff_id = intval($_SESSION['staff_id'] ?? 0);
            $is_current_account = ($current_staff_id === $id);

            // Count administrators
            $admin_count_result = mysqli_query(
                $conn,
                "SELECT COUNT(*) AS total FROM staff_accounts WHERE role = 'Administrator'"
            );
            $admin_count_row = mysqli_fetch_assoc($admin_count_result);
            $administrator_count = intval($admin_count_row['total']);

            // Protect last administrator
            if (
                $target['role'] === 'Administrator'
                && $administrator_count <= 1
                && ($role !== 'Administrator' || $status !== 'Active')
            ) {

                $error_message = "The last Administrator cannot be demoted or deactivated.";

            // Protect current account
            } elseif (
                $is_current_account
                && ($role !== 'Administrator' || $status !== 'Active')
            ) {

                $error_message = "You cannot deactivate or change the role of the account currently being used.";

            } else {

                // Check duplicate username
                $check_stmt = mysqli_prepare(
                    $conn,
                    "SELECT id FROM staff_accounts
                     WHERE username = ? AND id != ? LIMIT 1"
                );
                mysqli_stmt_bind_param($check_stmt, "si", $username, $id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);

                if (mysqli_num_rows($check_result) > 0) {

                    $error_message = "Username is already used by another account.";
                    mysqli_stmt_close($check_stmt);

                } else {

                    if ($password !== '') {

                        // Update with new password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        $update_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE staff_accounts
                             SET fullname = ?, username = ?, password = ?, role = ?, status = ?
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

                    } else {

                        // Update without changing password
                        $update_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE staff_accounts
                             SET fullname = ?, username = ?, role = ?, status = ?
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

                        header("Location: staff_accounts.php?updated=1");
                        exit();

                    } else {

                        $error_message = "Failed to update account: " . mysqli_error($conn);
                    }

                    mysqli_stmt_close($update_stmt);
                    mysqli_stmt_close($check_stmt);
                }
            }
        }
    }
}


// DELETE STAFF ACCOUNT
if (isset($_POST['delete_staff'])) {

    $id = intval($_POST['staff_id'] ?? 0);

    if ($id <= 0) {

        $error_message = "Invalid staff account.";

    } else {

        // Get account being deleted
        $target_stmt = mysqli_prepare(
            $conn,
            "SELECT id, username, role FROM staff_accounts WHERE id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($target_stmt, "i", $id);
        mysqli_stmt_execute($target_stmt);
        $target_result = mysqli_stmt_get_result($target_stmt);

        if (mysqli_num_rows($target_result) !== 1) {

            $error_message = "Staff account not found.";
            mysqli_stmt_close($target_stmt);

        } else {

            $target = mysqli_fetch_assoc($target_result);
            mysqli_stmt_close($target_stmt);

            $current_staff_id = intval($_SESSION['staff_id'] ?? 0);

            if ($current_staff_id === $id) {

                $error_message = "You cannot delete the account currently being used.";

            // Never delete built-in admin username
            } elseif (strcasecmp($target['username'], 'admin') === 0) {

                $error_message = 'The "admin" account is protected.';

            } else {

                // Protect last administrator
                if ($target['role'] === 'Administrator') {

                    $admin_count_result = mysqli_query(
                        $conn,
                        "SELECT COUNT(*) AS total FROM staff_accounts WHERE role = 'Administrator'"
                    );
                    $admin_count_row = mysqli_fetch_assoc($admin_count_result);
                    $administrator_count = intval($admin_count_row['total']);

                    if ($administrator_count <= 1) {
                        $error_message = "The last Administrator cannot be deleted.";
                    }
                }

                // Delete account
                if ($error_message === '') {

                    $delete_stmt = mysqli_prepare(
                        $conn,
                        "DELETE FROM staff_accounts WHERE id = ?"
                    );
                    mysqli_stmt_bind_param($delete_stmt, "i", $id);

                    if (mysqli_stmt_execute($delete_stmt)) {

                        mysqli_stmt_close($delete_stmt);

                        header("Location: staff_accounts.php?deleted=1");
                        exit();

                    } else {

                        $error_message = "Failed to delete account: " . mysqli_error($conn);
                    }

                    mysqli_stmt_close($delete_stmt);
                }
            }
        }
    }
}


// FETCH STAFF ACCOUNTS
$staff_query = mysqli_query(
    $conn,
    "SELECT id, fullname, username, role, status, created_at
     FROM staff_accounts
     ORDER BY id DESC"
);

if (!$staff_query) {
    die("Query Failed: " . mysqli_error($conn));
}

$staff_count = mysqli_num_rows($staff_query);
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
    <title>VitalCore - User Management</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/theme.css">

    <style>
        /* Fallback Dark Mode Styles (same as setting.php) */
        body.dark-mode, html.dark-mode body {
            background-color: #121824 !important;
            color: #e2e8f0 !important;
        }
        html.dark-mode .card,
        html.dark-mode .modal-content {
            background-color: #1e293b !important;
            color: #e2e8f0 !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        html.dark-mode .text-dark { color: #f8fafc !important; }
        html.dark-mode .text-muted { color: #94a3b8 !important; }
        html.dark-mode .form-select,
        html.dark-mode .form-control {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        html.dark-mode .sidebar {
            background-color: #0f172a !important;
            border-right-color: rgba(255,255,255,0.1) !important;
        }
        html.dark-mode .table {
            --bs-table-bg: transparent;
            --bs-table-color: #e2e8f0;
            --bs-table-hover-color: #f8fafc;
            --bs-table-hover-bg: rgba(255,255,255,0.05);
            --bs-table-border-color: rgba(255,255,255,0.1);
            color: #e2e8f0;
        }
        html.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

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

        <!-- =========================
             SIDEBAR
        ========================== -->
        <nav class="col-md-3 col-lg-2 d-md-flex sidebar p-3 flex-column justify-content-between">
            <div>
                <!-- LOGO & BRAND -->
                <div class="logo-section mb-4 d-flex align-items-center gap-2 px-2">
                    <img src="../../img/logo.jpg" alt="VitalCore Logo" class="sidebar-logo" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.onerror=null; this.src='https://cdn-icons-png.flaticon.com/512/2966/2966327.png';">
                    <span class="sidebar-brand">VitalCore</span>
                </div>

                <div class="sidebar-menu-wrapper">
                    <!-- MAIN -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_main">Main</small>
                    <ul class="nav flex-column mb-3">
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/dashboard.php">
                                <i class="bi bi-grid-1x2-fill me-2"></i>
                                <span data-i18n="nav_dashboard">Dashboard</span>
                            </a>
                        </li>
                    </ul>

                    <!-- CLINICAL SERVICES -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_clinical">Clinical Services</small>
                    <ul class="nav flex-column mb-3">
                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#patientsMenu" role="button" aria-expanded="false" aria-controls="patientsMenu">
                                <span>
                                    <i class="bi bi-people-fill me-2 text-primary"></i>
                                    <span data-i18n="nav_patient_mgmt">Patient Management</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse" id="patientsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="../admin/patient-list.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-list-ul me-2"></i>
                                            <span data-i18n="nav_all_patients">All Patients Services</span>
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="../admin/admin-dashboard.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-person-plus-fill me-2"></i>
                                            <span data-i18n="nav_add_patient">Add Patient</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link sidebar-collapse-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#recordsMenu" role="button" aria-expanded="false" aria-controls="recordsMenu">
                                <span>
                                    <i class="bi bi-folder2-open me-2 text-warning"></i>
                                    <span data-i18n="nav_patient_records">Patient Records</span>
                                </span>
                                <i class="bi bi-chevron-down collapse-chevron"></i>
                            </a>
                            <div class="collapse" id="recordsMenu">
                                <ul class="sidebar-submenu list-unstyled ps-4 py-1">
                                    <li class="py-1">
                                        <a href="../admin/logs/patient-history.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-clock-history me-2"></i>
                                            <span data-i18n="nav_records_history">Patient Records</span>
                                        </a>
                                    </li>
                                    <li class="py-1">
                                        <a href="../admin/logs/reports.php" class="sidebar-submenu-link text-decoration-none">
                                            <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                            <span data-i18n="nav_reports">Reports</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>

                    <!-- HARDWARE & SYSTEM -->
                    <small class="text-uppercase text-muted fw-bold px-3 d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;" data-i18n="section_system">Hardware &amp; System</small>
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
                                        <a href="staff_accounts.php" class="sidebar-submenu-link active text-decoration-none">
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

            <!-- LOGOUT -->
            <div class="pt-3 px-2 border-top border-secondary border-opacity-25">
                <a href="../login.php" class="text-decoration-none text-danger fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-left fs-5"></i>
                    <span data-i18n="nav_logout">Log out</span>
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
                        <i class="bi bi-person-gear me-2 text-primary"></i>
                        <span data-i18n="um_title">User Management</span>
                    </h3>
                    <p class="text-muted small mb-0" data-i18n="um_subtitle">
                        Manage VitalCore clinic staff accounts and access.
                    </p>
                </div>

                <div class="top-actions m-0">
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill fw-semibold shadow-sm" style="font-size: 0.85rem;">
                        <i class="bi bi-calendar-event me-1 text-primary"></i>
                        <?= date("l, M d, Y"); ?>
                        <span class="ms-1 text-primary" id="liveClock"></span>
                    </span>
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
                            <i class="bi bi-people-fill text-primary me-2"></i>
                            <span data-i18n="staff_accounts">Staff Accounts</span>
                        </h5>
                        <small class="text-muted" id="staffCountText" data-count="<?= (int)$staff_count; ?>">
                            <?= $staff_count; ?> staff account<?= $staff_count != 1 ? 's' : ''; ?> registered
                        </small>
                    </div>

                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="bi bi-person-plus-fill me-1"></i>
                        <span data-i18n="add_staff">Add Staff</span>
                    </button>
                </div>

                <!-- STAFF TABLE -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th data-i18n="col_id">ID</th>
                                <th data-i18n="col_fullname">Full Name</th>
                                <th data-i18n="col_username">Username</th>
                                <th data-i18n="col_role">Role</th>
                                <th data-i18n="col_status">Status</th>
                                <th data-i18n="col_created">Created</th>
                                <th class="text-end" data-i18n="col_action">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($staff_count > 0): ?>
                            <?php while ($staff = mysqli_fetch_assoc($staff_query)): ?>
                                <tr>
                                    <td class="fw-semibold"><?= (int)$staff['id']; ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($staff['fullname']); ?></td>
                                    <td><?= htmlspecialchars($staff['username']); ?></td>
                                    <td>
                                        <?php
                                        $is_admin_role = ($staff['role'] === 'Administrator');
                                        $role_class = $is_admin_role
                                            ? 'bg-danger-subtle text-danger'
                                            : 'bg-primary-subtle text-primary';
                                        ?>
                                        <span class="badge <?= $role_class ?> fw-semibold" data-i18n="<?= $is_admin_role ? 'role_admin' : 'role_staff'; ?>">
                                            <?= htmlspecialchars($staff['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($staff['status'] === 'Active'): ?>
                                            <span class="badge bg-success-subtle text-success fw-semibold">
                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                <span data-i18n="status_active">Active</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary fw-semibold">
                                                <i class="bi bi-x-circle-fill me-1"></i>
                                                <span data-i18n="status_inactive">Inactive</span>
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
                                                data-id="<?= (int)$staff['id']; ?>"
                                                data-fullname="<?= htmlspecialchars($staff['fullname']); ?>"
                                                data-username="<?= htmlspecialchars($staff['username']); ?>"
                                                data-role="<?= htmlspecialchars($staff['role']); ?>"
                                                data-status="<?= htmlspecialchars($staff['status']); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                            <span data-i18n="btn_edit">Edit</span>
                                        </button>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger rounded-pill px-3 delete-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteStaffModal"
                                                data-id="<?= (int)$staff['id']; ?>"
                                                data-name="<?= htmlspecialchars($staff['fullname']); ?>">
                                            <i class="bi bi-trash"></i>
                                            <span data-i18n="btn_delete">Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-2 d-block mb-2 opacity-50"></i>
                                    <span data-i18n="no_staff">No staff accounts found.</span>
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
                    <i class="bi bi-person-plus-fill text-primary me-2"></i>
                    <span data-i18n="modal_add_title">Add Staff Account</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="col_fullname">Full Name</label>
                        <input type="text" name="fullname" class="form-control" placeholder="Enter full name" data-i18n-placeholder="ph_fullname" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="col_username">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" data-i18n-placeholder="ph_username" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="label_password">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" data-i18n-placeholder="ph_password" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="col_role">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="Staff" data-i18n="role_staff">Staff</option>
                            <option value="Administrator" data-i18n="role_admin">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="col_status">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Active" data-i18n="status_active">Active</option>
                            <option value="Inactive" data-i18n="status_inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal" data-i18n="btn_cancel">Cancel</button>
                    <button type="submit" name="create_staff" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-person-plus-fill me-1"></i>
                        <span data-i18n="btn_create">Create Account</span>
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
                    <i class="bi bi-pencil-square text-primary me-2"></i>
                    <span data-i18n="modal_edit_title">Edit Staff Account</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="staff_id" id="edit_staff_id">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="col_fullname">Full Name</label>
                        <input type="text" name="fullname" id="edit_fullname" class="form-control" placeholder="Enter full name" data-i18n-placeholder="ph_fullname" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="col_username">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" placeholder="Enter username" data-i18n-placeholder="ph_username" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="label_password">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password" data-i18n-placeholder="ph_password_keep">
                        <small class="text-muted" data-i18n="password_hint">Only fill this in if you want to change the password.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="col_role">Role</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="Staff" data-i18n="role_staff">Staff</option>
                            <option value="Administrator" data-i18n="role_admin">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" data-i18n="col_status">Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="Active" data-i18n="status_active">Active</option>
                            <option value="Inactive" data-i18n="status_inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal" data-i18n="btn_cancel">Cancel</button>
                    <button type="submit" name="update_staff" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-save me-1"></i>
                        <span data-i18n="btn_save">Save Changes</span>
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
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span data-i18n="modal_delete_title">Delete Account</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="staff_id" id="delete_staff_id">

                <div class="modal-body">
                    <p>
                        <span data-i18n="delete_confirm">Are you sure you want to delete the account for</span>
                        <strong id="delete_staff_name"></strong>?
                    </p>
                    <p class="text-muted small mb-0" data-i18n="delete_warning">This action cannot be undone.</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal" data-i18n="btn_cancel">Cancel</button>
                    <button type="submit" name="delete_staff" class="btn btn-danger rounded-pill px-4">
                        <i class="bi bi-trash me-1"></i>
                        <span data-i18n="btn_delete">Delete</span>
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
    document.querySelectorAll(".edit-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            document.getElementById("edit_staff_id").value = this.dataset.id;
            document.getElementById("edit_fullname").value = this.dataset.fullname;
            document.getElementById("edit_username").value = this.dataset.username;
            document.getElementById("edit_role").value = this.dataset.role;
            document.getElementById("edit_status").value = this.dataset.status;
        });
    });

    // Populate Delete Modal
    document.querySelectorAll(".delete-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            document.getElementById("delete_staff_id").value = this.dataset.id;
            document.getElementById("delete_staff_name").textContent = this.dataset.name;
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
        },
        set(key, value) {
            try { localStorage.setItem(key, value); } catch (e) {}
        }
    };

    // ---------------------------------------------------------------
    // 1. Theme / Dark Mode
    // ---------------------------------------------------------------
    function applyTheme(isDark) {
        document.body.classList.toggle("dark-mode", isDark);
        document.documentElement.classList.toggle("dark-mode", isDark);
    }
    applyTheme(store.get("theme") === "dark");

    // Replace the button with a clone so only ONE click handler exists
    // (prevents theme.js and this script from toggling twice and cancelling out)
    let darkBtn = document.getElementById("darkModeToggle");
    if (darkBtn) {
        const fresh = darkBtn.cloneNode(true);
        darkBtn.parentNode.replaceChild(fresh, darkBtn);
        darkBtn = fresh;
        darkBtn.addEventListener("click", function () {
            const isDark = !document.documentElement.classList.contains("dark-mode");
            applyTheme(isDark);
            store.set("theme", isDark ? "dark" : "light");
        });
    }

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
    // 4. Language
    // ---------------------------------------------------------------
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
            dark_mode_title: "Dark Mode",
            um_title: "User Management",
            um_subtitle: "Manage VitalCore clinic staff accounts and access.",
            staff_accounts: "Staff Accounts",
            add_staff: "Add Staff",
            col_id: "ID",
            col_fullname: "Full Name",
            col_username: "Username",
            col_role: "Role",
            col_status: "Status",
            col_created: "Created",
            col_action: "Action",
            role_staff: "Staff",
            role_admin: "Administrator",
            status_active: "Active",
            status_inactive: "Inactive",
            btn_edit: "Edit",
            btn_delete: "Delete",
            btn_cancel: "Cancel",
            btn_create: "Create Account",
            btn_save: "Save Changes",
            no_staff: "No staff accounts found.",
            modal_add_title: "Add Staff Account",
            modal_edit_title: "Edit Staff Account",
            modal_delete_title: "Delete Account",
            label_password: "Password",
            ph_fullname: "Enter full name",
            ph_username: "Enter username",
            ph_password: "Enter password",
            ph_password_keep: "Leave blank to keep current password",
            password_hint: "Only fill this in if you want to change the password.",
            delete_confirm: "Are you sure you want to delete the account for",
            delete_warning: "This action cannot be undone.",
            count_one: "{n} staff account registered",
            count_many: "{n} staff accounts registered"
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
            dark_mode_title: "Dark Mode",
            um_title: "Pamamahala ng Gumagamit",
            um_subtitle: "Pamahalaan ang mga account at access ng staff ng klinika ng VitalCore.",
            staff_accounts: "Mga Account ng Staff",
            add_staff: "Magdagdag ng Staff",
            col_id: "ID",
            col_fullname: "Buong Pangalan",
            col_username: "Username",
            col_role: "Tungkulin",
            col_status: "Katayuan",
            col_created: "Nilikha",
            col_action: "Aksyon",
            role_staff: "Staff",
            role_admin: "Administrator",
            status_active: "Aktibo",
            status_inactive: "Hindi Aktibo",
            btn_edit: "I-edit",
            btn_delete: "Burahin",
            btn_cancel: "Kanselahin",
            btn_create: "Gumawa ng Account",
            btn_save: "I-save ang mga Pagbabago",
            no_staff: "Walang nakitang account ng staff.",
            modal_add_title: "Magdagdag ng Account ng Staff",
            modal_edit_title: "I-edit ang Account ng Staff",
            modal_delete_title: "Burahin ang Account",
            label_password: "Password",
            ph_fullname: "Ilagay ang buong pangalan",
            ph_username: "Ilagay ang username",
            ph_password: "Ilagay ang password",
            ph_password_keep: "Iwanang blangko upang mapanatili ang kasalukuyang password",
            password_hint: "Punan lamang ito kung nais mong palitan ang password.",
            delete_confirm: "Sigurado ka bang buburahin mo ang account ni",
            delete_warning: "Hindi na ito maaaring ibalik.",
            count_one: "{n} account ng staff ang nakarehistro",
            count_many: "{n} account ng staff ang nakarehistro"
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
            dark_mode_title: "Dark Mode",
            um_title: "Pagdumala sa Paggamit",
            um_subtitle: "Dumalaha ang mga account ug access sa staff sa klinika sa VitalCore.",
            staff_accounts: "Mga Account sa Staff",
            add_staff: "Idugang ang Staff",
            col_id: "ID",
            col_fullname: "Tibuok Ngalan",
            col_username: "Username",
            col_role: "Tahas",
            col_status: "Kahimtang",
            col_created: "Gihimo",
            col_action: "Aksyon",
            role_staff: "Staff",
            role_admin: "Administrator",
            status_active: "Aktibo",
            status_inactive: "Dili Aktibo",
            btn_edit: "I-edit",
            btn_delete: "Papasa",
            btn_cancel: "Kansela",
            btn_create: "Himoa ang Account",
            btn_save: "I-save ang mga Kausaban",
            no_staff: "Walay nakitang account sa staff.",
            modal_add_title: "Idugang ang Account sa Staff",
            modal_edit_title: "I-edit ang Account sa Staff",
            modal_delete_title: "Papasa ang Account",
            label_password: "Password",
            ph_fullname: "Ibutang ang tibuok ngalan",
            ph_username: "Ibutang ang username",
            ph_password: "Ibutang ang password",
            ph_password_keep: "Biyaan nga blangko aron magpabilin ang karon nga password",
            password_hint: "Pun-a lang kini kung gusto nimong usbon ang password.",
            delete_confirm: "Sigurado ka ba nga papason nimo ang account ni",
            delete_warning: "Dili na kini mabalik.",
            count_one: "{n} ka account sa staff ang narehistro",
            count_many: "{n} ka account sa staff ang narehistro"
        }
    };

    function applyLanguage(lang) {
        const dict = i18n[lang] || i18n.en;
        document.documentElement.lang = i18n[lang] ? lang : "en";

        document.querySelectorAll("[data-i18n]").forEach(el => {
            const key = el.getAttribute("data-i18n");
            if (dict[key]) el.textContent = dict[key];
        });

        document.querySelectorAll("[data-i18n-placeholder]").forEach(el => {
            const key = el.getAttribute("data-i18n-placeholder");
            if (dict[key]) el.placeholder = dict[key];
        });

        // "N staff accounts registered" (dynamic count)
        const countEl = document.getElementById("staffCountText");
        if (countEl) {
            const n = parseInt(countEl.dataset.count, 10) || 0;
            const tpl = n === 1 ? dict.count_one : dict.count_many;
            countEl.textContent = tpl.replace("{n}", n);
        }
    }
    const savedLang = store.get("language", "en");
    applyLanguage(i18n[savedLang] ? savedLang : "en");

    // ---------------------------------------------------------------
    // 5. Date & Time Format (live clock next to the date)
    // ---------------------------------------------------------------
    const clockEl = document.getElementById("liveClock");
    function updateClock() {
        if (!clockEl) return;
        const opts = { hour: "2-digit", minute: "2-digit", second: "2-digit" };
        if (store.get("dateTimeFormat", "24h") === "12h") {
            opts.hour12 = true;
        } else {
            opts.hourCycle = "h23";
        }
        clockEl.textContent = "• " + new Date().toLocaleTimeString("en-US", { ...opts, timeZone: "Asia/Manila" });
    }
    updateClock();
    setInterval(updateClock, 1000);

})();
</script>
</body>
</html>

<?php mysqli_close($conn); ?>