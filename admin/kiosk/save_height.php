<?php
/** @var mysqli $conn */
require_once('../../db_conn.php');

$data = json_decode(file_get_contents("php://input"), true);

if(isset($data['height']))
{
    $height = floatval($data['height']);

    $sql = "INSERT INTO height_readings(height)
            VALUES('$height')";

    mysqli_query($conn, $sql);

    echo "OK";
}
else
{
    echo "No height received";
}
?>