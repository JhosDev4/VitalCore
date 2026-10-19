<?php

/** @var mysqli $conn */

require_once('../../db_conn.php');

header('Content-Type: application/json');

$data = json_decode(
    file_get_contents("php://input"),
    true
);

if (
    !isset($data['weight']) ||
    !is_numeric($data['weight'])
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid weight"
    ]);

    exit();
}

$weight = floatval($data['weight']);

if ($weight < 0 || $weight >= 500) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid weight range"
    ]);

    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO weight_readings (weight)
     VALUES (?)"
);

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => mysqli_error($conn)
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "d",
    $weight
);

if (mysqli_stmt_execute($stmt)) {

    echo json_encode([
        "success" => true,
        "weight" => round($weight, 1)
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => mysqli_stmt_error($stmt)
    ]);
}

mysqli_stmt_close($stmt);