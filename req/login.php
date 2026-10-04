<?php

session_start();
require_once __DIR__ . "/../db_conn.php";


/*
|--------------------------------------------------------------------------
| PATIENT LOGIN
|--------------------------------------------------------------------------
| Patient code is submitted using:
| <input name="pass">
|
| Patient accounts are stored in:
| users
|--------------------------------------------------------------------------
*/

if (isset($_POST['pass'])) {

    $code = trim($_POST['pass']);

    if ($code === '') {
        header("Location: ../login.php?error=Empty Code");
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | FIND PATIENT
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, fullname, code_number, role
         FROM users
         WHERE code_number = ?
         AND role = 'patient'
         LIMIT 1"
    );

    if (!$stmt) {
        header("Location: ../login.php?error=Database Error");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $code);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 1) {

        $user = mysqli_fetch_assoc($result);

        /*
        |--------------------------------------------------------------------------
        | CREATE PATIENT SESSION
        |--------------------------------------------------------------------------
        */

        session_regenerate_id(true);

        /*
        | Clear previous staff/admin session
        */
        unset(
            $_SESSION['staff_id'],
            $_SESSION['fullname'],
            $_SESSION['username'],
            $_SESSION['status'],
            $_SESSION['is_main_admin']
        );

        /*
        | Patient session
        */
        $_SESSION['id'] = $user['id'];
        $_SESSION['name'] = $user['fullname'];
        $_SESSION['code_number'] = $user['code_number'];
        $_SESSION['role'] = 'patient';

        unset($_SESSION['measurement_saved']);

        mysqli_stmt_close($stmt);

        /*
        |--------------------------------------------------------------------------
        | REDIRECT PATIENT TO KIOSK
        |--------------------------------------------------------------------------
        */

        header("Location: ../admin/kiosk/index.php");
        exit();

    } else {

        mysqli_stmt_close($stmt);

        header("Location: ../login.php?error=Invalid Code Number");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| STAFF / ADMINISTRATOR LOGIN
|--------------------------------------------------------------------------
*/

if (isset($_POST['login_type']) && $_POST['login_type'] === 'staff') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        header("Location: ../login.php?error=Username and Password Required");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | BUILT-IN SYSTEM ADMINISTRATOR
    |--------------------------------------------------------------------------
    |
    | This account is NOT stored in staff_accounts.
    | It cannot be deleted from Staff Management.
    |
    */

    $main_admin_username = 'admin';

    $main_admin_password_hash =
    '$2y$10$XVJgWOMZPCOLjrwSybiXTeP1pHUEt/QvOgjWnyoyBk4zlPnaj1sUa';

    /*
    |--------------------------------------------------------------------------
    | CHECK BUILT-IN ADMIN
    |--------------------------------------------------------------------------
    */

    if (strcasecmp($username, $main_admin_username) === 0) {

        if (password_verify($password, $main_admin_password_hash)) {

            session_regenerate_id(true);

            /*
            | Clear patient session
            */
            unset(
                $_SESSION['id'],
                $_SESSION['name'],
                $_SESSION['code_number']
            );

            /*
            | Create administrator session
            */
            $_SESSION['staff_id'] = 0;
            $_SESSION['fullname'] = 'System Administrator';
            $_SESSION['username'] = 'admin';
            $_SESSION['role'] = 'Administrator';
            $_SESSION['status'] = 'Active';
            $_SESSION['is_main_admin'] = true;

            unset($_SESSION['measurement_saved']);

            header("Location: ../admin/dashboard.php");
            exit();

        } else {

            header("Location: ../login.php?error=Invalid Username or Password");
            exit();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | NORMAL STAFF / ADMINISTRATOR ACCOUNT
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, fullname, username, password, role, status
         FROM staff_accounts
         WHERE username = ?
         LIMIT 1"
    );

    if (!$stmt) {
        header("Location: ../login.php?error=Database Error");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);


    /*
    |--------------------------------------------------------------------------
    | ACCOUNT FOUND
    |--------------------------------------------------------------------------
    */

    if (mysqli_num_rows($result) === 1) {

        $staff = mysqli_fetch_assoc($result);


        /*
        |--------------------------------------------------------------------------
        | CHECK ACCOUNT STATUS
        |--------------------------------------------------------------------------
        */

        if ($staff['status'] !== 'Active') {

            mysqli_stmt_close($stmt);

            header("Location: ../login.php?error=Account Inactive");
            exit();
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY PASSWORD
        |--------------------------------------------------------------------------
        */

        if (password_verify($password, $staff['password'])) {

            session_regenerate_id(true);

            /*
            | Clear patient session
            */
            unset(
                $_SESSION['id'],
                $_SESSION['name'],
                $_SESSION['code_number']
            );


            /*
            | Create staff session
            */
            $_SESSION['staff_id'] = $staff['id'];
            $_SESSION['fullname'] = $staff['fullname'];
            $_SESSION['username'] = $staff['username'];
            $_SESSION['role'] = $staff['role'];
            $_SESSION['status'] = $staff['status'];
            $_SESSION['is_main_admin'] = false;

            unset($_SESSION['measurement_saved']);

            mysqli_stmt_close($stmt);


            /*
            |--------------------------------------------------------------------------
            | REDIRECT BASED ON ROLE
            |--------------------------------------------------------------------------
            */

            if ($staff['role'] === 'Administrator') {

                header("Location: ../admin/dashboard.php");
                exit();
            }


            if ($staff['role'] === 'Staff') {

                header("Location: ../healthcare_staff/admin-dashboard.php");
                exit();
            }


            header("Location: ../login.php?error=Invalid Role");
            exit();

        } else {

            mysqli_stmt_close($stmt);

            header("Location: ../login.php?error=Invalid Username or Password");
            exit();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STAFF ACCOUNT NOT FOUND
    |--------------------------------------------------------------------------
    */

    mysqli_stmt_close($stmt);

    header("Location: ../login.php?error=Invalid Username or Password");
    exit();
}


/*
|--------------------------------------------------------------------------
| INVALID LOGIN REQUEST
|--------------------------------------------------------------------------
*/

header("Location: ../login.php?error=Invalid Login Request");
exit();

?>