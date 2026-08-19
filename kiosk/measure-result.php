<?php
/** @var mysqli $conn */
session_start();
require_once('../db_conn.php');

$patient_name = $_SESSION['name'] ?? 'Patient';

/* HEIGHT */
$height = '--';

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

/* TEMPERATURE */
$temp = '--';

$result = mysqli_query(
    $conn,
    "SELECT temperature
     FROM sensor_readings
     ORDER BY id DESC
     LIMIT 1"
);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $temp = $row['temperature'];
}

/* WEIGHT */
$weight = '--';

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

/* HEART RATE & SPO2 */
$heart_rate = '--';
$spo2 = '--';

$resultMax = mysqli_query(
    $conn,
    "SELECT heart_rate, spo2
     FROM max30102_readings
     ORDER BY id DESC
     LIMIT 1"
);

if ($resultMax && mysqli_num_rows($resultMax) > 0) {
    $rowMax = mysqli_fetch_assoc($resultMax);
    $heart_rate = $rowMax['heart_rate'];
    $spo2 = $rowMax['spo2'];
}

/* BMI */
$bmi = '--';

if (
    is_numeric($height) &&
    is_numeric($weight) &&
    $height > 0 &&
    $weight > 0
) {
    $heightMeters = $height / 100;
    $bmi = round(
        $weight / ($heightMeters * $heightMeters),
        2
    );
}

$user_id = $_SESSION['id'] ?? 0;

if(
    $user_id > 0 &&
    is_numeric($temp) &&
    is_numeric($weight) &&
    is_numeric($height)
){

    $check = mysqli_query(
        $conn,
        "SELECT id
         FROM measurements
         WHERE user_id = '$user_id'
         ORDER BY id DESC
         LIMIT 1"
    );

    $save = true;

    if($check && mysqli_num_rows($check) > 0){
        $row = mysqli_fetch_assoc($check);

        // Prevent duplicate insert on page refresh
        if(isset($_SESSION['measurement_saved'])){
            $save = false;
        }
    }

$service_type = $_SESSION['service_type'] ?? '';    

if($save){

   mysqli_query(
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
        service_type
    )
    VALUES
    (
        '$user_id',
        '$temp',
        '$weight',
        '$height',
        '$bmi',
        '$heart_rate',
        '$spo2',
        '$service_type'
    )"
);

if(mysqli_error($conn)){
    die(mysqli_error($conn));
}

    if(!empty($service_type)){

        mysqli_query(
            $conn,
            "INSERT INTO patient_services
            (
                user_id,
                service_type,
                created_at
            )
            VALUES
            (
                '$user_id',
                '$service_type',
                NOW()
            )"
        );
    }

    $_SESSION['measurement_saved'] = true;
}
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
body{
    margin:0;
    background:#f4f7fc;
    font-family:'Segoe UI',sans-serif;
    background:url('../img/back.jpg') center center/cover no-repeat;
}

.page-wrapper{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:20px;
}

.result-card{
    width:100%;
    max-width:850px;
    background:#fff;
    border-radius:20px;
    padding:40px;
    box-shadow:0 15px 40px rgba(0,0,0,0.08);
}

.result-header{
    text-align:center;
    margin-bottom:30px;
}

.value{
    font-weight:bold;
    color:#0d6efd;
    font-size:1.1rem;
}

.empty-state{
    text-align:center;
    padding:20px;
    color:#6c757d;
}
</style>
</head>

<body>

<div class="page-wrapper">
<div class="result-card">

    <div class="result-header">
        <i class="bi bi-check-circle-fill text-success" style="font-size:70px;"></i>

        <h2 class="fw-bold mt-3">
            Vital Signs Measurement Complete
        </h2>

        <p class="text-muted">
            Patient: <?= htmlspecialchars($patient_name); ?>
        </p>
    </div>

    <?php if($temp == '--'): ?>

        <div class="empty-state">
            <i class="bi bi-database-x fs-1"></i>

            <p class="mt-3 mb-0">
                No temperature data available yet.
            </p>

            <small class="text-muted">
                Please perform a temperature measurement first.
            </small>
        </div>

    <?php else: ?>


        <table class="table table-bordered">
            <tbody>

                <tr>
                    <td>Height</td>
                    <td class="value">
                        <?= htmlspecialchars($height); ?> cm
                    </td>
                </tr>

                <tr>
                    <td width="40%">Temperature</td>
                    <td class="value">
                        <?= htmlspecialchars($temp); ?> °C
                    </td>
                </tr>

                <tr>
                    <td>Weight</td>
                    <td class="value">
                        <?= htmlspecialchars($weight); ?> kg
                    </td>
                </tr>

                <tr>
                    <td>BMI</td>
                    <td class="value">
                        <?= htmlspecialchars($bmi); ?>
                    </td>
                </tr>

                <tr>
                    <td>SpO₂</td>
                    <td class="value">
                        <?= htmlspecialchars($spo2); ?> %
                    </td>
                </tr>

                <tr>
                    <td>Heart Rate</td>
                    <td class="value">
                        <?= htmlspecialchars($heart_rate); ?> bpm
                    </td>
                </tr>

            </tbody>
        </table>

    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="../../login.php" class="btn btn-primary btn-lg">
            Finish
        </a>
    </div>

</div>
</div>

</body>
</html>