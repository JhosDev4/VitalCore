<?php
/** @var mysqli $conn */
header('Content-Type: text/plain');

require_once('../../db_conn.php');

$systolic = $_POST['systolic'] ?? null;
$diastolic = $_POST['diastolic'] ?? null;
$pulse_rate = $_POST['pulse_rate'] ?? null;


/* =========================================================
   VALIDATE DATA
========================================================= */

if (
    !is_numeric($systolic) ||
    !is_numeric($diastolic) ||
    !is_numeric($pulse_rate)
) {
    http_response_code(400);

    echo "ERROR: Invalid BP data";
    exit;
}


/* =========================================================
   SAVE BP
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO bp_readings
    (
        systolic,
        diastolic,
        pulse_rate
    )
    VALUES
    (?, ?, ?)"
);

if (!$stmt) {

    http_response_code(500);

    echo "ERROR: Database prepare failed: "
        . mysqli_error($conn);

    exit;
}


mysqli_stmt_bind_param(
    $stmt,
    "ddd",
    $systolic,
    $diastolic,
    $pulse_rate
);


if (mysqli_stmt_execute($stmt)) {

    echo "BP SAVED: "
        . "SYS=" . $systolic
        . " DIA=" . $diastolic
        . " PR=" . $pulse_rate;

} else {

    http_response_code(500);

    echo "ERROR: Could not save BP: "
        . mysqli_stmt_error($stmt);
}


mysqli_stmt_close($stmt);
?>
