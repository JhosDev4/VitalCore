<?php
/** @var mysqli $conn */
require_once('../../db_conn.php');

$heart_rate = $_POST['heart_rate'] ?? 0;
$spo2 = $_POST['spo2'] ?? 0;

mysqli_query(
    $conn,
    "INSERT INTO max30102_readings
    (heart_rate, spo2)
    VALUES
    ('$heart_rate', '$spo2')"
);

echo "SUCCESS";