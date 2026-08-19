<?php
/** @var mysqli $conn */
require_once('../../db_conn.php');

$query = mysqli_query(
    $conn,
    "SELECT
        fullname,
        service_type,
        code_number
     FROM users
     WHERE role='patient'
     ORDER BY service_type"
);
?>

<!DOCTYPE html>
<html lang="en" translate="no">
<head>
<meta name="google" content="notranslate"> 
<title>Service Records</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

        <div>   
             <button type="button"
                    onclick="goBack()"
                    class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 mb-2">

                <i class="bi bi-arrow-left"></i>
                <span>Back</span>

            </button>
        </div>

<div class="container py-0">

<h3>Service Records</h3>

<table class="table table-striped">

    <thead>
        <tr>
            <th>Patient</th>
            <th>Code</th>
            <th>Service</th>
        </tr>
    </thead>

    <tbody>

    <?php while($row=mysqli_fetch_assoc($query)): ?>

        <tr>
            <td><?= $row['fullname'] ?></td>
            <td><?= $row['code_number'] ?></td>
            <td><?= $row['service_type'] ?></td>
        </tr>

    <?php endwhile; ?>

    </tbody>

</table>

</div>

<script>
    function goBack() {
        window.history.back();
    }
</script>

</body>
</html>