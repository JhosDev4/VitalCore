
<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

if (
    !isset($_SESSION['staff_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Staff'
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Staff access required.'
    ]);
    exit;
}

// Use the SAME secret as admin/sensor_status_proxy.php.
// Do not expose this secret in JavaScript.
const STATUS_TOKEN = 'e2d5c94d34661fdb864976abae87dcec72e88e4209267fe91f6494634662e141';

$piUrl = 'http://192.168.1.101/sensor_status.php';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'ignore_errors' => true,
        'header' =>
            "X-VitalCore-Token: " . STATUS_TOKEN . "\r\n" .
            "Accept: application/json\r\n"
    ]
]);

$response = @file_get_contents($piUrl, false, $context);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Cannot reach the Raspberry Pi. Check its IP and Apache service.'
    ]);
    exit;
}

$data = json_decode($response, true);

if (
    !is_array($data) ||
    ($data['success'] ?? false) !== true ||
    !isset($data['sensors']) ||
    !is_array($data['sensors'])
) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'The Raspberry Pi returned an invalid sensor status response.'
    ]);
    exit;
}

echo json_encode($data, JSON_UNESCAPED_SLASHES);
