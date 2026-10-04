<?php
/** @var mysqli $conn */

session_start();
require_once('../db_conn.php');

$user_id = (int)$_POST['user_id'];
$service = mysqli_real_escape_string(
    $conn,
    $_POST['service_type']
);

/*
Create a service history record
*/
mysqli_query(
    $conn,
    "INSERT INTO patient_services
    (
        user_id,
        service_type,
        created_at
    )
    VALUES
    (
        '$user_id',
        '$service',
        NOW()
    )"
);

header(
    "Location: ../patient-view.php?id=$user_id"
);
exit;