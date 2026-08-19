<?php
/** @var mysqli $conn */
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once('../../db_conn.php');

$query = mysqli_query(
    $conn,
    "SELECT
        u.id AS user_id,
        u.fullname,
        u.code_number,
        MAX(m.created_at) AS last_visit
    FROM users u
    LEFT JOIN measurements m
        ON m.user_id = u.id
    WHERE u.role = 'patient'
    GROUP BY u.id
    ORDER BY u.fullname ASC"
);

$total_patients = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="en" translate="no">

<head>
<meta name="google" content="notranslate">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Records | VitalCore</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"rel="stylesheet">
<link rel="stylesheet"href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../../css/patient-history.css">
<link rel="stylesheet" href="../../css/theme.css">
</head>

<body class="dark-mode" data-theme="dark">

<div class="page-container">
    <!-- TOP NAVIGATION -->
    <div class="top-navigation">
        <div class="breadcrumb-area">
            <i class="bi bi-house-door-fill"></i>
            <span>Admin</span>
            <i class="bi bi-chevron-right"></i>
            <span>Patient Records</span>
        </div>
        <a href="../patient-list.php"class="back-button">
            <i class="bi bi-arrow-left"></i>
            Back to Patient List
        </a>
    </div>
    <!-- HERO -->
    <div class="hero-section">
        <div class="hero-content">
            <div class="hero-icon">
                <i class="bi bi-folder2-open"></i>
            </div>
            <h1>Patient Records</h1>
            <p>View patient medical history and previous checkup records.</p>
            <div class="hero-date">
                <i class="bi bi-calendar3"></i>
                <?= date('l, F d, Y') ?>
            </div>
        </div>
    </div>
    <!-- STATISTICS -->
    <div class="row g-3">
        <!-- TOTAL PATIENTS -->
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">TOTAL PATIENTS</span>
                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="stat-number"><?= number_format($total_patients) ?></div>
                <div class="stat-description">Registered patients</div>
            </div>
        </div>
        <!-- RECORDS -->
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">RECORDS MODULE</span>
                    <div class="stat-icon">
                        <i class="bi bi-clipboard2-pulse"></i>
                    </div>
                </div>
                <div class="stat-number"style="font-size:21px;">
                    Active
                </div>
                <div class="stat-description">
                    Patient history system
                </div>
            </div>
        </div>
        <!-- CURRENT DATE -->
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">CURRENT DATE</span>
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                </div>
                <div class="stat-number"style="font-size:20px;">
                    <?= date('M d, Y') ?>
                </div>
                <div class="stat-description">
                    Philippines local time
                </div>
            </div>
        </div>
    </div>
    <!-- RECORDS -->
    <div class="records-card">
        <!-- HEADER -->
        <div class="records-header">
            <div class="records-heading">
                <div class="records-icon">
                    <i class="bi bi-person-vcard"></i>
                </div>
                <div>
                    <h5>
                        Patient History
                    </h5>
                    <p>
                        Browse registered patient records
                    </p>
                </div>
            </div>
            <!-- SEARCH -->
            <div class="search-wrapper">
                <i class="bi bi-search"></i>
                <input
                    type="text"
                    id="recordSearch"
                    class="search-input"
                    placeholder="Search patient..."
                    autocomplete="off"
                >
            </div>
        </div>
        <!-- TABLE -->
        <div class="table-wrapper">
            <table class="patient-table"id="recordTable">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Code Number</th>
                        <th>Last Visit</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php mysqli_data_seek($query, 0);?>
                <?php if ($total_patients > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($query)): ?>
                        <?php
                        $fullname = trim($row['fullname']);
                        $parts = preg_split(
                            '/\s+/',
                            $fullname
                        );

                        $initials = '';

                        if (!empty($parts[0])) {

                            $initials .= strtoupper(
                                substr(
                                    $parts[0],
                                    0,
                                    1
                                )
                            );

                        }

                        if (count($parts) > 1) {

                            $initials .= strtoupper(
                                substr(
                                    $parts[count($parts)-1],
                                    0,
                                    1
                                )
                            );

                        }

                        $has_record =
                            !empty($row['last_visit']);

                        ?>
                        <tr>
                            <!-- PATIENT -->
                            <td>
                                <div class="patient-profile">
                                    <div class="patient-avatar">
                                        <?= $initials ?: 'P' ?>
                                    </div>
                                    <div>
                                        <div class="patient-name">
                                            <?= htmlspecialchars(
                                                $row['fullname']
                                            ) ?>
                                        </div>
                                        <div class="patient-id">
                                            Patient ID:
                                            #<?= $row['user_id'] ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <!-- CODE -->
                            <td data-label="Code Number">
                                <span class="code-badge">
                                    <i class="bi bi-upc-scan"></i>
                                    <?= htmlspecialchars(
                                        $row['code_number']
                                    ) ?>
                                </span>
                            </td>
                            <!-- LAST VISIT -->
                            <td data-label="Last Visit">
                                <?php if ($has_record): ?>
                                    <div>
                                        <div class="visit-date">
                                            <?= date(
                                                'M d, Y',
                                                strtotime(
                                                    $row['last_visit']
                                                )
                                            ) ?>
                                        </div>
                                        <div class="visit-time">
                                            <?= date(
                                                'h:i A',
                                                strtotime(
                                                    $row['last_visit']
                                                )
                                            ) ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="no-record">
                                        <i class="bi bi-dash-circle"></i>
                                        No Record
                                    </span>
                                <?php endif; ?>
                            </td>
                            <!-- STATUS -->
                            <td data-label="Status">
                                <?php if ($has_record): ?>
                                    <span class="status status-active">
                                        <span class="status-dot"></span>
                                        Active Record
                                    </span>

                                <?php else: ?>
                                    <span class="status status-none">
                                        <span class="status-dot"></span>
                                        No Visit Yet
                                    </span>
                                <?php endif; ?>
                            </td>
                            <!-- ACTION -->
                            <td data-label="Action"class="text-end">
                                <a href="patient-history-list.php?user_id=<?= $row['user_id'] ?>"class="history-button">
                                    <i class="bi bi-clock-history"></i>
                                    View History
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>

                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:0;">
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-person-x"></i>
                                </div>
                                <h5>No Patient Records</h5>
                                <p>There are currently no registered patients.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- FOOTER -->
        <div class="records-footer">
            <i class="bi bi-info-circle me-1"></i>
            Showing
            <strong>
                <?= number_format($total_patients) ?>
            </strong>
            registered patient
            <?= $total_patients != 1 ? 's' : '' ?>.
        </div>
    </div>
</div>
<!-- SEARCH -->
<script>
const searchInput =
    document.getElementById('recordSearch');

const tableRows =
    document.querySelectorAll(
        '#recordTable tbody tr'
    );

searchInput.addEventListener(
    'input',
    function(){

        const search =
            this.value
                .toLowerCase()
                .trim();

        tableRows.forEach(
            function(row){

                const text =
                    row.innerText.toLowerCase();
                if(text.includes(search)){
                    row.style.display = '';
                }else{
                    row.style.display = 'none';

                }

            }
        );

    }
);

</script>
<script src="../../assets/js/theme.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const searchInput =
        document.getElementById("recordSearch");

    const tableRows =
        document.querySelectorAll(
            "#recordTable tbody tr"
        );


    /* =========================================
       TABLE DIAGONAL FADE-IN
    ========================================= */

    tableRows.forEach(function(row, index) {

        setTimeout(function() {

            row.classList.add("table-row-visible");

        }, index * 100);

    });


    /* =========================================
       SEARCH
    ========================================= */

    searchInput.addEventListener(
        "input",
        function () {

            const search =
                this.value
                    .toLowerCase()
                    .trim();

            tableRows.forEach(function(row) {

                const text =
                    row.innerText.toLowerCase();

                if (text.includes(search)) {

                    row.style.display = "";

                } else {

                    row.style.display = "none";

                }

            });

        }
    );

});
</script>
</body>

</html>


