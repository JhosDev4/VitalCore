<?php
/** @var mysqli $conn */
require_once('../../db_conn.php');

$temp = $_POST['temperature'] ?? '';

if($temp != ''){

    $temp = mysqli_real_escape_string($conn, $temp);

    mysqli_query(
        $conn,
        "INSERT INTO sensor_readings(temperature)
         VALUES('$temp')"
    );

    echo "SUCCESS";
}
else{
    echo "NO DATA";
}
?>