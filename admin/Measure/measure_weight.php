<?php

header('Content-Type: application/json');

$output = shell_exec("python3 /home/vitalcorepi4/weight.py");

echo json_encode([
    "success" => true
]);