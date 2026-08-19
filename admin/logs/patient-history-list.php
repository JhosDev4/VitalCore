<?php
/** @var mysqli $conn */

session_start();
require_once('../../db_conn.php');

/* DELETE MEASUREMENT */
if(isset($_GET['delete_id'])){

    $delete_id = (int)$_GET['delete_id'];

    mysqli_query(
        $conn,
        "DELETE FROM measurements
         WHERE id = '$delete_id'"
    );

    header(
        "Location: patient-history-list.php?user_id=" .
        (int)$_GET['user_id']
    );
    exit();
}

if (!isset($_GET['user_id'])) {
    die("Patient not found.");
}

$user_id = (int)$_GET['user_id'];

/* Patient Info */
$patientQuery = mysqli_query(
    $conn,
    "SELECT *
     FROM users
     WHERE id = '$user_id'"
);

$patient = mysqli_fetch_assoc($patientQuery);

/* Measurement History */
$historyQuery = mysqli_query(
    $conn,
    "SELECT *
     FROM measurements
     WHERE user_id = '$user_id'
     ORDER BY created_at DESC"
);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">

   <a href="patient-history.php" class="btn btn-secondary mb-3">
        ← Back
   </a>

    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <h3><?= htmlspecialchars($patient['fullname']) ?></h3>

            <p class="mb-1">
                <strong>Code:</strong>
                <?= htmlspecialchars($patient['code_number']) ?>
            </p>

            <p class="mb-0">
                <strong>Address:</strong>
                <?= htmlspecialchars($patient['address']) ?>
            </p>

        </div>
    </div>

    <div class="card shadow-sm">

        <div class="card-header">
            Measurement History
        </div>

        <div class="card-body">

            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Temp</th>
                        <th>Height</th>
                        <th>Weight</th>
                        <th>BMI</th>
                        <th>Heart Rate</th>
                        <th>SpO₂</th>
                        <th>Blood Pressure</th>
                        <th>Document</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while($row = mysqli_fetch_assoc($historyQuery)): ?>

                   <tr>

                        <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>

                        <td><?= $row['temperature'] ?> °C</td>
                        <td><?= $row['height'] ?> cm</td>
                        <td><?= $row['weight'] ?> kg</td>
                        <td><?= $row['bmi'] ?></td>
                        <td><?= $row['heart_rate'] ?> bpm</td>
                        <td><?= $row['spo2'] ?> %</td>

                        <td>
                            <?php
                            if (!empty($row['systolic']) && !empty($row['diastolic'])) {
                                echo $row['systolic'] . '/' . $row['diastolic'] . ' mmHg';
                            } else {
                                echo '--';
                            }
                            ?>
                        </td>

                        <td>

                            <?php if($row['service_type'] == 'vital'): ?>

                                <a href="../Doc/docu-vital-screening.php?id=<?= $user_id ?>"
                                class="btn btn-primary btn-sm">
                                    Vital Screening
                                </a>

                            <?php elseif($row['service_type'] == 'prenatal'): ?>

                                <a href="../Doc/docu-prenatal.php?id=<?= $user_id ?>"
                                class="btn btn-danger btn-sm">
                                    Prenatal
                                </a>

                            <?php elseif($row['service_type'] == 'immunization'): ?>

                                <a href="../Doc/docu-child-immunization.php?id=<?= $user_id ?>"
                                class="btn btn-success btn-sm">
                                    Child Immunization
                                </a>

                            <?php elseif($row['service_type'] == 'family'): ?>

                                <a href="../Doc/docu-family-planning.php?id=<?= $user_id ?>"
                                class="btn btn-info btn-sm">
                                    Family Planning
                                </a>

                            <?php else: ?>

                                <span class="text-muted">No Document</span>

                            <?php endif; ?>

                            </td>

                            <td>
                               <a href="patient-history-list.php?user_id=<?= $user_id ?>&delete_id=<?= $row['id'] ?>"
                                class="btn btn-danger btn-sm">
                                    Delete
                                </a>
                            </td>
                    </tr>

                    <?php endwhile; ?>

                </tbody>    
</div>

</body>
</html>