
<?php
/** @var mysqli $conn */

session_start();

require_once('../../db_conn.php');

/* =========================================================
   CONFIG
========================================================= */
const READING_TS_COLUMN   = 'created_at';
const READING_MAX_AGE_MIN = 15;

/* =========================================================
   SECURITY + FINISH
========================================================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php");
    exit();
}

// Finish button: clear measurement session state and return to login.
if (isset($_GET['finish'])) {
    unset(
        $_SESSION['active_sensors'],
        $_SESSION['service_type'],
        $_SESSION['last_saved_hash']
    );

    header("Location: ../../login.php");
    exit();
}

$patient_name = $_SESSION['name'] ?? 'Patient';
$user_id      = (int)($_SESSION['id'] ?? 0);
$service_type = $_SESSION['service_type'] ?? '';

$active_sensors = $_SESSION['active_sensors'] ?? [
    'height' => 1,
    'weight' => 1,
    'temp'   => 1,
    'heart'  => 1,
    'bp'     => 1,
    'spo2'   => 1,
    'bmi'    => 1
];

/* =========================================================
   HELPERS
========================================================= */

/**
 * Get the latest reading within the allowed age.
 * Table and column names are defined by the application.
 */
function latestReading(
    mysqli $conn,
    string $table,
    array $columns
): ?array {
    $cols = implode(', ', $columns);
    $ts   = READING_TS_COLUMN;
    $age  = (int)READING_MAX_AGE_MIN;

    try {
        $res = mysqli_query(
            $conn,
            "SELECT $cols
             FROM $table
             WHERE $ts >= (NOW() - INTERVAL $age MINUTE)
             ORDER BY id DESC
             LIMIT 1"
        );
    } catch (mysqli_sql_exception $e) {
        error_log(
            "latestReading($table): " . $e->getMessage()
        );
        return null;
    }

    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }

    return null;
}

/**
 * Display a numeric value with its unit.
 */
function show($value, string $unit = ''): string
{
    if (!is_numeric($value)) {
        return '--';
    }

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    ) . $unit;
}

/**
 * Display height in centimeters and feet/inches.
 * Example: 178 cm / 5'10"
 *
 * The original centimeter value is not modified.
 */
function showHeight($value): string
{
    if (!is_numeric($value) || (float)$value <= 0) {
        return '--';
    }

    $cm = (float)$value;

    // Convert centimeters to the nearest whole inch.
    $totalInches = (int)round($cm / 2.54);

    $feet   = intdiv($totalInches, 12);
    $inches = $totalInches % 12;

    // Display up to one decimal place in centimeters.
    $cmDisplay = rtrim(
        rtrim(number_format($cm, 1, '.', ''), '0'),
        '.'
    );

    return htmlspecialchars(
        $cmDisplay,
        ENT_QUOTES,
        'UTF-8'
    ) . ' cm / ' . $feet . "'" . $inches . '"';
}

/**
 * Convert a numeric measurement to a database value.
 */
$toDb = fn($v) => is_numeric($v) ? (float)$v : null;

/* =========================================================
   READ SENSOR DATA
========================================================= */

$height = $temp = $weight = $heart_rate = $spo2 = '--';
$systolic = $diastolic = $bp_pulse_rate = $bmi = '--';

// Height
if (!empty($active_sensors['height'])) {
    $row = latestReading(
        $conn,
        'height_readings',
        ['height']
    );

    if ($row) {
        $height = $row['height'];
    }
}

// Temperature
if (!empty($active_sensors['temp'])) {
    $row = latestReading(
        $conn,
        'sensor_readings',
        ['temperature']
    );

    if ($row) {
        $temp = $row['temperature'];
    }
}

// Weight
if (!empty($active_sensors['weight'])) {
    $row = latestReading(
        $conn,
        'weight_readings',
        ['weight']
    );

    if ($row) {
        $weight = $row['weight'];
    }
}

// Heart rate and SpO2
if (
    !empty($active_sensors['heart']) ||
    !empty($active_sensors['spo2'])
) {
    $row = latestReading(
        $conn,
        'max30102_readings',
        ['heart_rate', 'spo2']
    );

    if ($row) {
        if (!empty($active_sensors['heart'])) {
            $heart_rate = $row['heart_rate'];
        }

        if (!empty($active_sensors['spo2'])) {
            $spo2 = $row['spo2'];
        }
    }
}

// Blood pressure
if (!empty($active_sensors['bp'])) {
    $row = latestReading(
        $conn,
        'bp_readings',
        ['systolic', 'diastolic', 'pulse_rate']
    );

    if ($row) {
        $systolic      = $row['systolic'];
        $diastolic     = $row['diastolic'];
        $bp_pulse_rate = $row['pulse_rate'];
    }
}

/* =========================================================
   CALCULATE BMI
   Height remains in centimeters in the database.
========================================================= */

if (
    !empty($active_sensors['bmi']) &&
    is_numeric($height) &&
    is_numeric($weight) &&
    (float)$height > 0 &&
    (float)$weight > 0
) {
    $heightMeters = (float)$height / 100;

    $bmi = round(
        (float)$weight / ($heightMeters * $heightMeters),
        2
    );
}

/* =========================================================
   SAVE MEASUREMENT
========================================================= */

$has_measurement =
    is_numeric($temp) ||
    is_numeric($weight) ||
    is_numeric($height) ||
    is_numeric($systolic) ||
    is_numeric($heart_rate) ||
    is_numeric($spo2);

$db = [
    'temp'   => $toDb($temp),
    'weight' => $toDb($weight),
    'height' => $toDb($height),
    'bmi'    => $toDb($bmi),
    'hr'     => $toDb($heart_rate),
    'spo2'   => $toDb($spo2),
    'sys'    => $toDb($systolic),
    'dia'    => $toDb($diastolic),
];

// Prevent duplicate inserts when refreshing the same result.
$result_hash = md5(
    $user_id . '|' .
    json_encode($db) . '|' .
    $service_type
);

if (
    $user_id > 0 &&
    $has_measurement &&
    ($_SESSION['last_saved_hash'] ?? '') !== $result_hash
) {
    $transactionStarted = false;

    try {
        mysqli_begin_transaction($conn);
        $transactionStarted = true;

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
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            throw new RuntimeException(mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "idddddddds",
            $user_id,
            $db['temp'],
            $db['weight'],
            $db['height'],
            $db['bmi'],
            $db['hr'],
            $db['spo2'],
            $db['sys'],
            $db['dia'],
            $service_type
        );

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);

            throw new RuntimeException($error);
        }

        mysqli_stmt_close($stmt);

        // Record the service, if selected.
        if ($service_type !== '') {
            $stmtService = mysqli_prepare(
                $conn,
                "INSERT INTO patient_services
                    (user_id, service_type, created_at)
                 VALUES (?, ?, NOW())"
            );

            if (!$stmtService) {
                throw new RuntimeException(mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $stmtService,
                "is",
                $user_id,
                $service_type
            );

            if (!mysqli_stmt_execute($stmtService)) {
                $error = mysqli_stmt_error($stmtService);
                mysqli_stmt_close($stmtService);

                throw new RuntimeException($error);
            }

            mysqli_stmt_close($stmtService);
        }

        mysqli_commit($conn);
        $transactionStarted = false;

        $_SESSION['last_saved_hash'] = $result_hash;

    } catch (Throwable $e) {
        if ($transactionStarted) {
            mysqli_rollback($conn);
        }

        error_log(
            'Saving measurement failed: ' . $e->getMessage()
        );
    }
}

/* =========================================================
   TABLE ROWS
========================================================= */

$bp_display = (
    is_numeric($systolic) &&
    is_numeric($diastolic)
)
    ? show($systolic) . ' / ' .
      show($diastolic) . ' mmHg'
    : '--';

$rows = [
    ['Height',         'height', showHeight($height)],
    ['Temperature',    'temp',   show($temp, ' °C')],
    ['Weight',         'weight', show($weight, ' kg')],
    ['BMI',            'bmi',    show($bmi)],
    ['SpO₂',           'spo2',   show($spo2, ' %')],
    ['Blood Pressure', 'bp',     $bp_display],
    ['Heart Rate',     'heart',  show($heart_rate, ' bpm')],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Measurement Result - VitalCore</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css"
    >

    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f4f7fc url('../../img/back.jpg')
                center center / cover no-repeat;
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
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
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

        .label-col {
            width: 40%;
        }

        @media (max-width: 576px) {
            .result-card {
                padding: 20px;
            }

            .result-header h2 {
                font-size: 1.4rem;
            }

            .label-col {
                width: 42%;
            }

            .value {
                font-size: 0.95rem;
                overflow-wrap: anywhere;
            }

            .table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>

<body>
<div class="page-wrapper">
    <div class="result-card">

        <div class="result-header">
            <i
                class="bi bi-check-circle-fill text-success"
                style="font-size: 70px;"
                aria-hidden="true"
            ></i>

            <h2 class="fw-bold mt-3">
                Vital Signs Measurement Complete
            </h2>

            <p class="text-muted">
                Patient:
                <?= htmlspecialchars(
                    $patient_name,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered">
                <tbody>
                <?php foreach ($rows as [$label, $key, $display]): ?>
                    <?php $off = empty($active_sensors[$key]); ?>

                    <tr>
                        <td class="label-col">
                            <?= htmlspecialchars(
                                $label,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </td>

                        <td class="value <?= $off ? 'text-disabled' : ''; ?>">
                            <?= $off ? 'Deactivated' : $display; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mt-4">
            <a href="?finish=1" class="btn btn-primary btn-lg px-5">
                Finish
            </a>
        </div>

    </div>
</div>
</body>
</html>
