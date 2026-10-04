<?php
/** @var mysqli $conn */

session_start();

require_once('../../db_conn.php');

$patient_name = $_SESSION['name'] ?? 'Patient';
$user_id = $_SESSION['id'] ?? 0;
$active_sensors = $_SESSION['active_sensors'] ?? [
    'height' => 1,
    'weight' => 1,
    'temp' => 1,
    'heart' => 1,
    'bp' => 1,
    'spo2' => 1,
    'bmi' => 1
];

/* =========================================================
   HEIGHT
========================================================= */
$height = '--';
if (!empty($active_sensors['height'])) {
    $resultHeight = mysqli_query(
        $conn,
        "SELECT height
         FROM height_readings
         ORDER BY id DESC
         LIMIT 1"
    );

    if ($resultHeight && mysqli_num_rows($resultHeight) > 0) {
        $rowHeight = mysqli_fetch_assoc($resultHeight);
        $height = $rowHeight['height'];
    }
}

/* =========================================================
   TEMPERATURE
========================================================= */
$temp = '--';
if (!empty($active_sensors['temp'])) {
    $resultTemp = mysqli_query(
        $conn,
        "SELECT temperature
         FROM sensor_readings
         ORDER BY id DESC
         LIMIT 1"
    );

    if ($resultTemp && mysqli_num_rows($resultTemp) > 0) {
        $rowTemp = mysqli_fetch_assoc($resultTemp);
        $temp = $rowTemp['temperature'];
    }
}

/* =========================================================
   WEIGHT
========================================================= */
$weight = '--';
if (!empty($active_sensors['weight'])) {
    $resultWeight = mysqli_query(
        $conn,
        "SELECT weight
         FROM weight_readings
         ORDER BY id DESC
         LIMIT 1"
    );

    if ($resultWeight && mysqli_num_rows($resultWeight) > 0) {
        $rowWeight = mysqli_fetch_assoc($resultWeight);
        $weight = $rowWeight['weight'];
    }
}

/* =========================================================
   HEART RATE & SPO2
========================================================= */
$heart_rate = '--';
$spo2 = '--';
if (!empty($active_sensors['heart']) || !empty($active_sensors['spo2'])) {
    $resultMax = mysqli_query(
        $conn,
        "SELECT heart_rate, spo2
         FROM max30102_readings
         ORDER BY id DESC
         LIMIT 1"
    );

    if ($resultMax && mysqli_num_rows($resultMax) > 0) {
        $rowMax = mysqli_fetch_assoc($resultMax);
        if (!empty($active_sensors['heart'])) {
            $heart_rate = $rowMax['heart_rate'];
        }
        if (!empty($active_sensors['spo2'])) {
            $spo2 = $rowMax['spo2'];
        }
    }
}

/* =========================================================
   BLOOD PRESSURE
========================================================= */
$systolic = '--';
$diastolic = '--';
$bp_pulse_rate = '--';
if (!empty($active_sensors['bp'])) {
    $resultBP = mysqli_query(
        $conn,
        "SELECT systolic, diastolic, pulse_rate
         FROM bp_readings
         ORDER BY id DESC
         LIMIT 1"
    );

    if ($resultBP && mysqli_num_rows($resultBP) > 0) {
        $rowBP = mysqli_fetch_assoc($resultBP);
        $systolic = $rowBP['systolic'];
        $diastolic = $rowBP['diastolic'];
        $bp_pulse_rate = $rowBP['pulse_rate'];
    }
}

/* =========================================================
   BMI
========================================================= */
$bmi = '--';
if (
    !empty($active_sensors['bmi']) &&
    is_numeric($height) &&
    is_numeric($weight) &&
    $height > 0 &&
    $weight > 0
) {
    $heightMeters = $height / 100;
    $bmi = round($weight / ($heightMeters * $heightMeters), 2);
}

/* =========================================================
   SAVE MEASUREMENT TO DATABASE
========================================================= */
$service_type = $_SESSION['service_type'] ?? '';

// Check if at least one sensor was measured
$has_measurement = is_numeric($temp) || is_numeric($weight) || is_numeric($height) || is_numeric($systolic) || is_numeric($heart_rate);

if ($user_id > 0 && $has_measurement && !isset($_SESSION['measurement_saved'])) {

    // Convert non-numeric / deactivated values to NULL for SQL
    $db_temp       = is_numeric($temp) ? (float)$temp : null;
    $db_weight     = is_numeric($weight) ? (float)$weight : null;
    $db_height     = is_numeric($height) ? (float)$height : null;
    $db_bmi        = is_numeric($bmi) ? (float)$bmi : null;
    $db_heart_rate = is_numeric($heart_rate) ? (float)$heart_rate : null;
    $db_spo2       = is_numeric($spo2) ? (float)$spo2 : null;
    $db_systolic   = is_numeric($systolic) ? (float)$systolic : null;
    $db_diastolic  = is_numeric($diastolic) ? (float)$diastolic : null;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO measurements
        (
            user_id,
            temperature,
            weight,
            height,
            bmi,
            heart_rate,
            spo2,
            systolic,
            diastolic,
            service_type
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "idddddddds",
            $user_id,
            $db_temp,
            $db_weight,
            $db_height,
            $db_bmi,
            $db_heart_rate,
            $db_spo2,
            $db_systolic,
            $db_diastolic,
            $service_type
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /* SAVE SERVICE */
    if (!empty($service_type)) {
        $stmtService = mysqli_prepare(
            $conn,
            "INSERT INTO patient_services (user_id, service_type, created_at) VALUES (?, ?, NOW())"
        );
        if ($stmtService) {
            mysqli_stmt_bind_param($stmtService, "is", $user_id, $service_type);
            mysqli_stmt_execute($stmtService);
            mysqli_stmt_close($stmtService);
        }
    }

    $_SESSION['measurement_saved'] = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Measurement Result</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

<style>
body {
    margin: 0;
    background: #f4f7fc;
    font-family: 'Segoe UI', sans-serif;
    background: url('../../img/back.jpg') center center/cover no-repeat;
}
.page-wrapper {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.result-card {
    width: 100%;
    max-width: 850px;
    background: #fff;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.08);
}
.result-header {
    text-align: center;
    margin-bottom: 30px;
}
.value {
    font-weight: bold;
    color: #0d6efd;
    font-size: 1.1rem;
}
.text-disabled {
    color: #adb5bd !important;
    font-weight: normal !important;
}
</style>
</head>

<body>

<div class="page-wrapper">
<div class="result-card">

    <div class="result-header">
        <i class="bi bi-check-circle-fill text-success" style="font-size:70px;"></i>
        <h2 class="fw-bold mt-3">Vital Signs Measurement Complete</h2>
        <p class="text-muted">Patient: <?= htmlspecialchars($patient_name); ?></p>
    </div>

    <table class="table table-bordered">
        <tbody>
            <tr>
                <td width="40%">Height</td>
                <td class="value <?= empty($active_sensors['height']) ? 'text-disabled' : ''; ?>">
                    <?= empty($active_sensors['height']) ? 'Deactivated' : htmlspecialchars($height) . ' cm'; ?>
                </td>
            </tr>

            <tr>
                <td>Temperature</td>
                <td class="value <?= empty($active_sensors['temp']) ? 'text-disabled' : ''; ?>">
                    <?= empty($active_sensors['temp']) ? 'Deactivated' : htmlspecialchars($temp) . ' °C'; ?>
                </td>
            </tr>

            <tr>
                <td>Weight</td>
                <td class="value <?= empty($active_sensors['weight']) ? 'text-disabled' : ''; ?>">
                    <?= empty($active_sensors['weight']) ? 'Deactivated' : htmlspecialchars($weight) . ' kg'; ?>
                </td>
            </tr>

            <tr>
                <td>BMI</td>
                <td class="value <?= empty($active_sensors['bmi']) ? 'text-disabled' : ''; ?>">
                    <?= empty($active_sensors['bmi']) ? 'Deactivated' : htmlspecialchars($bmi); ?>
                </td>
            </tr>

            <tr>
                <td>SpO₂</td>
                <td class="value <?= empty($active_sensors['spo2']) ? 'text-disabled' : ''; ?>">
                    <?= empty($active_sensors['spo2']) ? 'Deactivated' : htmlspecialchars($spo2) . ' %'; ?>
                </td>
            </tr>

            <tr>
                <td>Blood Pressure</td>
                <td class="value <?= empty($active_sensors['bp']) ? 'text-disabled' : ''; ?>">
                    <?= empty($active_sensors['bp']) ? 'Deactivated' : (htmlspecialchars($systolic) . ' / ' . htmlspecialchars($diastolic) . ' mmHg'); ?>
                </td>
            </tr>

            <tr>
                <td>Heart Rate</td>
                <td class="value <?= empty($active_sensors['heart']) ? 'text-disabled' : ''; ?>">
                    <?= empty($active_sensors['heart']) ? 'Deactivated' : htmlspecialchars($heart_rate) . ' bpm'; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="text-center mt-4">
        <a href="../../login.php" class="btn btn-primary btn-lg px-5">
            Finish
        </a>
    </div>

</div>
</div>

</body>
</html>