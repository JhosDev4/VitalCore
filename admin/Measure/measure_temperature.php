<?php

header('Content-Type: application/json');

$output = shell_exec("python3 /home/vitalcorepi4/mlx_temp.py");

echo json_encode([
    "success" => true,
    "output" => $output
]);