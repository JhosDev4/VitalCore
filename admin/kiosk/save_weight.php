<?php
/** @var mysqli $conn */
require_once('../../db_conn.php');

$weight = $_POST['weight'] ?? '';

if($weight != ''){

    $weight = mysqli_real_escape_string($conn, $weight);

    mysqli_query(
        $conn,
        "INSERT INTO weight_readings(weight)
         VALUES('$weight')"
    );

    echo "SUCCESS";
}
else{
    echo "NO DATA";
}
?>