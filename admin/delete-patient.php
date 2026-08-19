<?php
session_start();

/* =========================
   ADMIN SECURITY CHECK
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
    die("Database connection failed: " . mysqli_connect_error());
}


/* =========================
   CHECK PATIENT ID
========================= */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin-dashboard.php");
    exit();
}

$patient_id = (int) $_GET['id'];


/* =========================
   DELETE PATIENT
========================= */

$sql = "DELETE FROM users
        WHERE id = ?
        AND role = 'patient'";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Delete preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $patient_id);

if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    header("Location: admin-dashboard.php?deleted=1");
    exit();

} else {

    $error = mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    die("Unable to delete patient: " . $error);
}
?>