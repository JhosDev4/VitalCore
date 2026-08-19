<?php
/** @var mysqli $conn */
require_once('../../db_conn.php');

$id = (int)$_GET['id'];

$patient = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT * FROM users WHERE id='$id'"
    )
);

$records = mysqli_query(
    $conn,
    "SELECT *
     FROM measurements
     WHERE user_id='$id'
     ORDER BY id DESC"
);
?>

<!DOCTYPE html>
<html>
<head>
<title>Patient History</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container py-0">

<h2><?= $patient['fullname'] ?></h2>

<p>
Patient Code:
<?= $patient['code_number'] ?>
</p>

<?php while($row=mysqli_fetch_assoc($records)): ?>

<div class="card mb-3">

    <div class="card-body">

        <h6>
            Record #<?= $row['id'] ?>
        </h6>

        <p>Temperature: <?= $row['temperature'] ?> °C</p>
        <p>Height: <?= $row['height'] ?> cm</p>
        <p>Weight: <?= $row['weight'] ?> kg</p>
        <p>BMI: <?= $row['bmi'] ?></p>
        <p>Heart Rate: <?= $row['heart_rate'] ?></p>
        <p>SpO₂: <?= $row['spo2'] ?></p>

    </div>

</div>

<?php endwhile; ?>

</div>

</body>
</html>