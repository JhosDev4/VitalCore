<?php

header('Content-Type: application/json');

$output = shell_exec("python3 /home/vitalcorepi4/tf_luna_test.py");

echo json_encode([
    "success" => true,
    "message" => "Height measured"
]);