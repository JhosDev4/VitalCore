<?php
/** @var mysqli $conn */
ob_start();                       // swallow stray warnings so the reply stays valid JSON
ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF); // we handle DB errors ourselves

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function out($arr, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($arr);
    exit;
}
function fail($msg, $code = 400) { out(['success' => false, 'message' => $msg], $code); }

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e['message']]);
    }
});

// healthcare_staff folder: one level up
require_once('../db_conn.php');

if (
    !isset($_SESSION['staff_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Staff'], true)
) {
    fail('Unauthorized. Please log in again.', 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Invalid request.', 405);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail('Invalid data.');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $in['csrf'] ?? '')) {
    fail('Invalid session token. Reload the page.', 403);
}

$mid = (int)($in['measurement_id'] ?? 0);
$pid = (int)($in['patient_id'] ?? 0);
if ($mid <= 0 || $pid <= 0) fail('Missing record ID.');

function num($v, $min, $max, $label, $int = false) {
    if ($v === '' || $v === null) return null;
    if (!is_numeric($v)) fail("$label must be a number.");
    $v = $int ? (int)round($v) : (float)$v;
    if ($v < $min || $v > $max) fail("$label must be between $min and $max.");
    return $v;
}

$temp   = num($in['temperature'] ?? null, 30, 45,  'Temperature');
$weight = num($in['weight']      ?? null, 1,  300, 'Weight');
$height = num($in['height']      ?? null, 30, 250, 'Height');
$hr     = num($in['heart_rate']  ?? null, 20, 250, 'Heart rate', true);
$spo2   = num($in['spo2']        ?? null, 50, 100, 'SpO2', true);
$sys    = num($in['systolic']    ?? null, 50, 260, 'Systolic', true);
$dia    = num($in['diastolic']   ?? null, 30, 160, 'Diastolic', true);

if ($sys !== null && $dia !== null && $sys <= $dia) {
    fail('Systolic must be higher than diastolic.');
}

$bmi = null;
if ($weight !== null && $height !== null && $height > 0) {
    $m   = $height / 100;
    $bmi = round($weight / ($m * $m), 2);
}

$stmt = $conn->prepare(
    "UPDATE measurements
     SET temperature=?, weight=?, height=?, bmi=?, heart_rate=?, spo2=?, systolic=?, diastolic=?
     WHERE id=? AND user_id=?"
);
if (!$stmt) fail('Database error (prepare): ' . $conn->error, 500);

$stmt->bind_param('ddddiiiiii', $temp, $weight, $height, $bmi, $hr, $spo2, $sys, $dia, $mid, $pid);

if (!$stmt->execute()) fail('Database error (save): ' . $stmt->error, 500);

out(['success' => true, 'bmi' => $bmi]);