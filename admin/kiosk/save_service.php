<?php
session_start();

// Reset the measurement saved flag for the new test run
unset($_SESSION['measurement_saved']);

// Get JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($data) {
    $_SESSION['service_type'] = $data['service_type'] ?? '';
    $_SESSION['active_sensors'] = $data['active_sensors'] ?? [];
    echo json_encode(['status' => 'success']);
} else if (isset($_POST['service_type'])) {
    // Fallback for form-data POST
    $_SESSION['service_type'] = $_POST['service_type'];
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
}