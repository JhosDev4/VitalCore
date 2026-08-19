<?php
/** @var mysqli $conn */
session_start();
require_once('../../db_conn.php');

$query = mysqli_query($conn,"
    SELECT
        m.*,
        u.fullname,
        u.code_number
    FROM measurements m
    INNER JOIN users u
        ON u.id = m.user_id
    WHERE u.role = 'patient'
    ORDER BY m.id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Checkup Records</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container py-0">

    <h3 class="mb-4">
        Checkup Records
    </h3>

    <div class="card shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead class="table-primary">
                    <tr>
                        <th>Patient</th>
                        <th>Code</th>
                        <th>Temp</th>
                        <th>Height</th>
                        <th>Weight</th>
                        <th>BMI</th>
                        <th>HR</th>
                        <th>SpO₂</th>
                    </tr>
                </thead>

                <tbody>

                <?php while($row=mysqli_fetch_assoc($query)): ?>

                    <tr>
                        <td><?= $row['fullname'] ?></td>
                        <td><?= $row['code_number'] ?></td>
                        <td><?= $row['temperature'] ?> °C</td>
                        <td><?= $row['height'] ?> cm</td>
                        <td><?= $row['weight'] ?> kg</td>
                        <td><?= $row['bmi'] ?></td>
                        <td><?= $row['heart_rate'] ?></td>
                        <td><?= $row['spo2'] ?></td>
                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>