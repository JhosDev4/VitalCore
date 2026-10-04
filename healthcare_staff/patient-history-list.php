<?php

/** @var mysqli $conn */

session_start();

require_once('../db_conn.php');


/* =========================================================
   DELETE SINGLE MEASUREMENT
========================================================= */

if (isset($_GET['delete_id'])) {

    $delete_id = (int)$_GET['delete_id'];

    $user_id_for_redirect = (int)($_GET['user_id'] ?? 0);

    if ($delete_id > 0 && $user_id_for_redirect > 0) {

        mysqli_query(
            $conn,
            "DELETE FROM measurements
             WHERE id = '$delete_id'
             AND user_id = '$user_id_for_redirect'"
        );
    }

    header(
        "Location: patient-history-list.php?user_id=" .
        $user_id_for_redirect
    );

    exit();
}


/* =========================================================
   DELETE MULTIPLE MEASUREMENTS
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_selected'])
) {

    $user_id_for_redirect = (int)($_POST['user_id'] ?? 0);

    $selected_ids = $_POST['selected_ids'] ?? [];

    if (
        $user_id_for_redirect > 0 &&
        is_array($selected_ids) &&
        !empty($selected_ids)
    ) {

        /*
         * Convert all IDs to integers.
         * This prevents invalid values from entering the query.
         */

        $clean_ids = [];

        foreach ($selected_ids as $id) {

            $id = (int)$id;

            if ($id > 0) {
                $clean_ids[] = $id;
            }
        }


        if (!empty($clean_ids)) {

            /*
             * Create:
             * 1,2,3,4
             */

            $id_list = implode(',', $clean_ids);


            /*
             * Delete only records belonging
             * to the current patient.
             */

            mysqli_query(
                $conn,
                "DELETE FROM measurements
                 WHERE user_id = '$user_id_for_redirect'
                 AND id IN ($id_list)"
            );
        }
    }


    header(
        "Location: patient-history-list.php?user_id=" .
        $user_id_for_redirect
    );

    exit();
}


/* =========================================================
   CHECK PATIENT
========================================================= */

if (!isset($_GET['user_id'])) {

    die("Patient not found.");
}


$user_id = (int)$_GET['user_id'];


/* =========================================================
   PATIENT INFO
========================================================= */

$patientQuery = mysqli_query(
    $conn,
    "SELECT *
     FROM users
     WHERE id = '$user_id'
     LIMIT 1"
);


$patient = mysqli_fetch_assoc($patientQuery);


if (!$patient) {

    die("Patient not found.");
}


/* =========================================================
   MEASUREMENT HISTORY
========================================================= */

$historyQuery = mysqli_query(
    $conn,
    "SELECT *
     FROM measurements
     WHERE user_id = '$user_id'
     ORDER BY created_at DESC"
);


$measurementCount = $historyQuery
    ? mysqli_num_rows($historyQuery)
    : 0;


/* =========================================================
   PATIENT INITIALS
========================================================= */

$fullname = trim(
    $patient['fullname'] ?? 'Patient'
);


$nameParts = preg_split(
    '/\s+/',
    $fullname
);


if (count($nameParts) >= 2) {

    $initials =
        strtoupper(
            substr($nameParts[0], 0, 1)
        ) .
        strtoupper(
            substr(end($nameParts), 0, 1)
        );

} else {

    $initials =
        strtoupper(
            substr($fullname, 0, 2)
        );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= htmlspecialchars($fullname) ?>
        - Patient History
    </title>


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICONS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- =====================================================
         PATIENT HISTORY CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="../../css/patient-history-list.css"
    >


    <!-- =====================================================
         THEME CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="../../css/theme.css"
    >


    <style>

        /* =====================================================
           SELECT CHECKBOX
        ====================================================== */

        .record-checkbox {

            width: 18px;

            height: 18px;

            cursor: pointer;

            accent-color: #dc3545;

        }


        /* =====================================================
           SELECT ALL CHECKBOX
        ====================================================== */

        #selectAllRecords {

            width: 18px;

            height: 18px;

            cursor: pointer;

            accent-color: #dc3545;

        }


        /* =====================================================
           SELECTED ROW
        ====================================================== */

        .history-table tbody tr.record-selected {

            background-color: rgba(
                220,
                53,
                69,
                0.07
            );

        }


        /* =====================================================
           DELETE SELECTED AREA
        ====================================================== */

        .delete-selected-wrapper {

            position: relative;

            display: inline-block;

        }


        /* =====================================================
           DELETE SELECTED BUTTON
        ====================================================== */

        #deleteSelectedBtn {

            transition:
                opacity 0.2s ease,
                transform 0.2s ease;

        }


        #deleteSelectedBtn:not(:disabled):hover {

            transform: translateY(-1px);

        }


        #deleteSelectedBtn:disabled {

            cursor: not-allowed;

            opacity: 0.5;

        }


        /* =====================================================
           BULK DELETE CONFIRM POPUP
        ====================================================== */

        .bulk-delete-confirm-box {

            position: absolute;

            top: calc(100% + 8px);

            right: 0;

            z-index: 9999;

            display: none;

        }


        .bulk-confirm-popup {

            background: #ffffff;

            border: 1px solid #dee2e6;

            border-radius: 10px;

            padding: 10px 12px;

            box-shadow:
                0 6px 20px rgba(
                    0,
                    0,
                    0,
                    0.18
                );

            display: flex;

            align-items: center;

            gap: 8px;

            white-space: nowrap;

        }


        .bulk-confirm-popup span {

            font-size: 13px;

            font-weight: 600;

            color: #333;

            margin-right: 3px;

        }


        .bulk-confirm-popup .btn {

            font-size: 12px;

            padding: 4px 10px;

            border-radius: 6px;

        }


        /* =====================================================
           SINGLE DELETE BUTTON
        ====================================================== */

        .delete-action-wrapper {

            position: relative;

            display: inline-block;

        }


        /* =====================================================
           SINGLE DELETE POPUP
        ====================================================== */

        .delete-confirm-box {

            position: absolute;

            z-index: 9999;

            top: 50%;

            right: calc(100% + 8px);

            transform: translateY(-50%);

            display: none;

        }


        .confirm-popup {

            background: #ffffff;

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 8px 10px;

            box-shadow:
                0 5px 15px rgba(
                    0,
                    0,
                    0,
                    0.15
                );

            display: flex;

            align-items: center;

            gap: 8px;

            white-space: nowrap;

        }


        .confirm-popup span {

            font-size: 13px;

            font-weight: 600;

            color: #333;

        }


        .confirm-popup .btn {

            font-size: 12px;

            padding: 4px 9px;

            border-radius: 6px;

        }


        /* =====================================================
           DARK MODE
        ====================================================== */

        body.dark-mode .bulk-confirm-popup {

            background: #1e293b;

            border-color: #475569;

        }


        body.dark-mode .bulk-confirm-popup span {

            color: #f8fafc;

        }


        body.dark-mode .confirm-popup {

            background: #1e293b;

            border-color: #475569;

        }


        body.dark-mode .confirm-popup span {

            color: #f8fafc;

        }


        body.dark-mode
        .history-table tbody tr.record-selected {

            background-color: rgba(
                220,
                53,
                69,
                0.14
            );

        }

    </style>

</head>


<body>


<div class="history-wrapper">


    <!-- =====================================================
         TOP BAR
    ====================================================== -->

    <div class="history-topbar">

        <div>

            <a
                href="patient-history.php"
                class="back-button fade-delay fade-delay-1"
            >

                <i class="bi bi-chevron-left"></i>

                Back to Patient History

            </a>


            <h1
                class="page-title mt-3 fade-delay fade-delay-2"
            >

                Patient Records

            </h1>


            <p
                class="page-subtitle fade-delay fade-delay-3"
            >

                Complete record of the patient's previous measurements.

            </p>

        </div>

    </div>



    <!-- =====================================================
         PATIENT HEADER
    ====================================================== -->

    <div
        class="patient-header-card fade-delay fade-delay-4"
    >

        <div
            class="patient-header-content fade-delay fade-delay-5"
        >


            <!-- PATIENT AVATAR -->

            <div class="patient-avatar">

                <?= htmlspecialchars($initials) ?>

            </div>


            <div>

                <div class="patient-id">

                    PATIENT

                </div>


                <h2 class="patient-name">

                    <?= htmlspecialchars($fullname) ?>

                </h2>


                <div class="patient-details">


                    <span class="patient-detail">

                        <i class="bi bi-person-vcard"></i>

                        Code:

                        <?= htmlspecialchars(
                            $patient['code_number'] ?? '--'
                        ) ?>

                    </span>


                    <span class="patient-detail">

                        <i class="bi bi-geo-alt"></i>

                        <?= htmlspecialchars(
                            $patient['address'] ?? '--'
                        ) ?>

                    </span>

                </div>

            </div>

        </div>

    </div>



    <!-- =====================================================
         HISTORY CARD
    ====================================================== -->

    <div
        class="history-card fade-delay fade-delay-6"
    >


        <!-- =================================================
             HISTORY HEADER
        ================================================== -->

        <div
            class="history-card-header fade-delay fade-delay-7"
        >


            <div
                class="history-title-wrapper fade-delay fade-delay-8"
            >


                <div class="history-title-icon">

                    <i class="bi bi-clipboard2-pulse"></i>

                </div>


                <div>

                    <h5 class="history-title">

                        Measurement History

                    </h5>


                    <p class="history-subtitle">

                        Vital signs and health measurements

                    </p>

                </div>

            </div>



            <!-- =================================================
                 RIGHT SIDE CONTROLS
            ================================================== -->

            <div
                class="d-flex align-items-center gap-3"
            >


                <!-- =================================================
                     DELETE SELECTED
                ================================================== -->

                <div
                    class="delete-selected-wrapper"
                >


                    <button
                        type="button"
                        id="deleteSelectedBtn"
                        class="btn btn-danger btn-sm"
                        disabled
                    >

                        <i class="bi bi-trash3"></i>

                        Delete Selected

                        <span
                            id="selectedCount"
                            class="ms-1"
                        ></span>

                    </button>


                    <!-- =================================================
                         BULK DELETE CONFIRMATION
                    ================================================== -->

                    <div
                        id="bulkDeleteConfirmBox"
                        class="bulk-delete-confirm-box"
                    >

                        <div
                            class="bulk-confirm-popup"
                        >

                            <span
                                id="bulkDeleteMessage"
                            >
                                Delete selected records?
                            </span>


                            <button
                                type="button"
                                id="confirmBulkDelete"
                                class="btn btn-danger btn-sm"
                            >

                                Yes

                            </button>


                            <button
                                type="button"
                                id="cancelBulkDelete"
                                class="btn btn-secondary btn-sm"
                            >

                                No

                            </button>

                        </div>

                    </div>

                </div>



                <!-- =================================================
                     RECORD COUNT
                ================================================== -->

                <span class="record-count">

                    <i class="bi bi-activity me-1"></i>

                    <?= $measurementCount ?>

                    <?= $measurementCount == 1
                        ? 'Record'
                        : 'Records'
                    ?>

                </span>

            </div>

        </div>



        <!-- =================================================
             TABLE
        ================================================== -->

        <?php if ($measurementCount > 0): ?>


            <div class="table-container">


                <form
                    id="bulkDeleteForm"
                    method="POST"
                    action="patient-history-list.php?user_id=<?= $user_id ?>"
                >

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= $user_id ?>"
                    >


                    <table
                        class="table history-table fade-delay fade-delay-9"
                    >


                        <!-- =================================================
                             TABLE HEADER
                        ================================================== -->

                        <thead>

                            <tr>


                                <!-- SELECT ALL -->

                                <th
                                    class="text-center"
                                    style="width: 45px;"
                                >

                                    <input
                                        type="checkbox"
                                        id="selectAllRecords"
                                        title="Select All Records"
                                    >

                                </th>


                                <th>

                                    Date

                                </th>


                                <th>

                                    Temperature

                                </th>


                                <th>

                                    Height

                                </th>


                                <th>

                                    Weight

                                </th>


                                <th>

                                    BMI

                                </th>


                                <th>

                                    Heart Rate

                                </th>


                                <th>

                                    SpO₂

                                </th>


                                <th>

                                    Blood Pressure

                                </th>


                                <th>

                                    Document

                                </th>


                                <th
                                    class="text-center"
                                >

                                    Action

                                </th>


                            </tr>

                        </thead>



                        <!-- =================================================
                             TABLE BODY
                        ================================================== -->

                        <tbody>


                        <?php while (
                            $row = mysqli_fetch_assoc(
                                $historyQuery
                            )
                        ): ?>


                            <?php


                            /* =================================================
                               DATE
                            ================================================= */

                            $createdAt =
                                strtotime(
                                    $row['created_at']
                                );


                            $dateDisplay =
                                date(
                                    'M d, Y',
                                    $createdAt
                                );


                            $timeDisplay =
                                date(
                                    'h:i A',
                                    $createdAt
                                );


                            /* =================================================
                               TEMPERATURE
                            ================================================= */

                            $temperature =

                                !empty(
                                    $row['temperature']
                                )

                                ? number_format(
                                    (float)$row['temperature'],
                                    2
                                )

                                : '--';


                            /* =================================================
                               HEIGHT
                            ================================================= */

                            $height =

                                !empty(
                                    $row['height']
                                )

                                ? number_format(
                                    (float)$row['height'],
                                    2
                                )

                                : '--';


                            /* =================================================
                               WEIGHT
                            ================================================= */

                            $weight =

                                !empty(
                                    $row['weight']
                                )

                                ? number_format(
                                    (float)$row['weight'],
                                    2
                                )

                                : '--';


                            /* =================================================
                               BMI
                            ================================================= */

                            $bmi =

                                !empty(
                                    $row['bmi']
                                )

                                ? number_format(
                                    (float)$row['bmi'],
                                    2
                                )

                                : '--';


                            /* =================================================
                               HEART RATE
                            ================================================= */

                            $heartRate =

                                !empty(
                                    $row['heart_rate']
                                )

                                ? (int)$row['heart_rate']

                                : '--';


                            /* =================================================
                               SPO2
                            ================================================= */

                            $spo2 =

                                !empty(
                                    $row['spo2']
                                )

                                ? (int)$row['spo2']

                                : '--';


                            ?>


                            <tr>


                                <!-- =================================================
                                     SELECT CHECKBOX
                                ================================================== -->

                                <td class="text-center">

                                    <input
                                        type="checkbox"
                                        class="record-checkbox"
                                        name="selected_ids[]"
                                        value="<?= (int)$row['id'] ?>"
                                    >

                                </td>



                                <!-- =================================================
                                     DATE
                                ================================================== -->

                                <td>

                                    <span class="date-main">

                                        <?= $dateDisplay ?>

                                    </span>


                                    <span class="date-time">

                                        <?= $timeDisplay ?>

                                    </span>

                                </td>



                                <!-- =================================================
                                     TEMPERATURE
                                ================================================== -->

                                <td>

                                    <?php if (
                                        $temperature !== '--'
                                    ): ?>

                                        <span
                                            class="vital-value"
                                        >

                                            <?= $temperature ?>

                                        </span>

                                        <span
                                            class="vital-unit"
                                        >

                                            °C

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="text-muted"
                                        >

                                            --

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- =================================================
                                     HEIGHT
                                ================================================== -->

                                <td>

                                    <?php if (
                                        $height !== '--'
                                    ): ?>

                                        <span
                                            class="vital-value"
                                        >

                                            <?= $height ?>

                                        </span>

                                        <span
                                            class="vital-unit"
                                        >

                                            cm

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="text-muted"
                                        >

                                            --

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- =================================================
                                     WEIGHT
                                ================================================== -->

                                <td>

                                    <?php if (
                                        $weight !== '--'
                                    ): ?>

                                        <span
                                            class="vital-value"
                                        >

                                            <?= $weight ?>

                                        </span>

                                        <span
                                            class="vital-unit"
                                        >

                                            kg

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="text-muted"
                                        >

                                            --

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- =================================================
                                     BMI
                                ================================================== -->

                                <td>

                                    <?php if (
                                        $bmi !== '--'
                                    ): ?>

                                        <span
                                            class="bmi-value"
                                        >

                                            <?= $bmi ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="text-muted"
                                        >

                                            --

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- =================================================
                                     HEART RATE
                                ================================================== -->

                                <td>

                                    <?php if (
                                        $heartRate !== '--'
                                    ): ?>

                                        <span
                                            class="vital-value"
                                        >

                                            <?= $heartRate ?>

                                        </span>

                                        <span
                                            class="vital-unit"
                                        >

                                            BPM

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="text-muted"
                                        >

                                            --

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- =================================================
                                     SPO2
                                ================================================== -->

                                <td>

                                    <?php if (
                                        $spo2 !== '--'
                                    ): ?>

                                        <span
                                            class="vital-value"
                                        >

                                            <?= $spo2 ?>

                                        </span>

                                        <span
                                            class="vital-unit"
                                        >

                                            %

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="text-muted"
                                        >

                                            --

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- =================================================
                                     BLOOD PRESSURE
                                ================================================== -->

                                <td>

                                    <?php if (

                                        !empty(
                                            $row['systolic']
                                        )

                                        &&

                                        !empty(
                                            $row['diastolic']
                                        )

                                    ): ?>

                                        <span
                                            class="bp-value"
                                        >

                                            <?= (int)$row['systolic'] ?>

                                            /

                                            <?= (int)$row['diastolic'] ?>

                                        </span>

                                        <span
                                            class="vital-unit"
                                        >

                                            mmHg

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="text-muted"
                                        >

                                            --

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- =================================================
                                     DOCUMENT
                                ================================================== -->

                                <td>


                                    <?php if (

                                        $row['service_type']
                                        == 'vital'

                                    ): ?>


                                        <a
                                            href="docu-vital-screening.php?id=<?= $user_id ?>&measurement_id=<?= (int)$row['id'] ?>"
                                            class="document-btn document-vital"
                                        >

                                            <i
                                                class="bi bi-file-earmark-medical"
                                            ></i>

                                            Vital Screening

                                        </a>


                                    <?php elseif (

                                        $row['service_type']
                                        == 'prenatal'

                                    ): ?>


                                        <a
                                            href="../Doc/docu-prenatal.php?id=<?= $user_id ?>&measurement_id=<?= (int)$row['id'] ?>"
                                            class="document-btn document-prenatal"
                                        >

                                            <i
                                                class="bi bi-heart-pulse"
                                            ></i>

                                            Prenatal

                                        </a>


                                    <?php elseif (

                                        $row['service_type']
                                        == 'immunization'

                                    ): ?>


                                        <a
                                            href="../Doc/docu-child-immunization.php?id=<?= $user_id ?>&measurement_id=<?= (int)$row['id'] ?>"
                                            class="document-btn document-immunization"
                                        >

                                            <i
                                                class="bi bi-shield-check"
                                            ></i>

                                            Immunization

                                        </a>


                                    <?php elseif (

                                        $row['service_type']
                                        == 'family'

                                    ): ?>


                                        <a
                                            href="../Doc/docu-family-planning.php?id=<?= $user_id ?>&measurement_id=<?= (int)$row['id'] ?>"
                                            class="document-btn document-family"
                                        >

                                            <i
                                                class="bi bi-people"
                                            ></i>

                                            Family Planning

                                        </a>


                                    <?php else: ?>


                                        <span
                                            class="no-document"
                                        >

                                            No Document

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- =================================================
                                     SINGLE DELETE
                                ================================================== -->

                                <td class="text-center">


                                    <div
                                        class="delete-action-wrapper"
                                    >


                                        <button
                                            type="button"
                                            class="delete-btn btn btn-sm btn-danger"
                                            title="Delete Record"
                                            data-delete-url="patient-history-list.php?user_id=<?= $user_id ?>&delete_id=<?= (int)$row['id'] ?>"
                                        >

                                            <i
                                                class="bi bi-trash3"
                                            ></i>

                                        </button>


                                        <!-- SINGLE DELETE POPUP -->

                                        <div
                                            class="delete-confirm-box"
                                        >

                                            <div
                                                class="confirm-popup"
                                            >

                                                <span>
                                                    Delete?
                                                </span>


                                                <a
                                                    href="#"
                                                    class="btn btn-danger btn-sm confirm-delete"
                                                >

                                                    Yes

                                                </a>


                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-sm cancel-delete"
                                                >

                                                    No

                                                </button>

                                            </div>

                                        </div>


                                    </div>


                                </td>


                            </tr>


                        <?php endwhile; ?>


                        </tbody>

                    </table>


                </form>

            </div>


        <?php else: ?>


            <!-- =================================================
                 EMPTY STATE
            ================================================== -->

            <div class="empty-history">

                <div class="empty-icon">

                    <i
                        class="bi bi-clipboard-x"
                    ></i>

                </div>


                <h6>

                    No Measurement History

                </h6>


                <p>

                    This patient does not have any
                    recorded measurements yet.

                </p>

            </div>


        <?php endif; ?>


    </div>

    <!-- END history-card -->


</div>

<!-- END history-wrapper -->



<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>



<!-- =========================================================
     THEME JS
========================================================= -->

<script
    src="../../assets/js/theme.js"
></script>



<!-- =========================================================
     DELETE / MULTI-SELECT SCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /* =====================================================
           ELEMENTS
        ====================================================== */

        const checkboxes =
            document.querySelectorAll(
                '.record-checkbox'
            );


        const selectAllCheckbox =
            document.getElementById(
                'selectAllRecords'
            );


        const deleteSelectedBtn =
            document.getElementById(
                'deleteSelectedBtn'
            );


        const selectedCount =
            document.getElementById(
                'selectedCount'
            );


        const bulkConfirmBox =
            document.getElementById(
                'bulkDeleteConfirmBox'
            );


        const bulkDeleteMessage =
            document.getElementById(
                'bulkDeleteMessage'
            );


        const confirmBulkDelete =
            document.getElementById(
                'confirmBulkDelete'
            );


        const cancelBulkDelete =
            document.getElementById(
                'cancelBulkDelete'
            );


        const bulkDeleteForm =
            document.getElementById(
                'bulkDeleteForm'
            );



        /* =====================================================
           UPDATE SELECTED COUNT
        ====================================================== */

        function updateSelectedState() {


            let selected = 0;


            checkboxes.forEach(
                function (checkbox) {


                    if (checkbox.checked) {

                        selected++;

                    }


                    /* =========================================
                       HIGHLIGHT SELECTED ROW
                    ========================================== */

                    const row =
                        checkbox.closest('tr');


                    if (row) {

                        row.classList.toggle(
                            'record-selected',
                            checkbox.checked
                        );

                    }

                }
            );


            /* =================================================
               UPDATE BUTTON
            ================================================== */

            if (selected > 0) {

                deleteSelectedBtn.disabled =
                    false;


                selectedCount.textContent =
                    '(' + selected + ')';

            } else {

                deleteSelectedBtn.disabled =
                    true;


                selectedCount.textContent =
                    '';

            }


            /* =================================================
               UPDATE SELECT ALL
            ================================================== */

            if (
                selectAllCheckbox &&
                checkboxes.length > 0
            ) {


                const allSelected =
                    selected ===
                    checkboxes.length;


                const someSelected =
                    selected > 0 &&
                    selected <
                    checkboxes.length;


                selectAllCheckbox.checked =
                    allSelected;


                selectAllCheckbox.indeterminate =
                    someSelected;

            }

        }



        /* =====================================================
           INDIVIDUAL CHECKBOX
        ====================================================== */

        checkboxes.forEach(
            function (checkbox) {


                checkbox.addEventListener(
                    'change',
                    function () {

                        updateSelectedState();

                    }
                );

            }
        );



        /* =====================================================
           SELECT ALL
        ====================================================== */

        if (selectAllCheckbox) {


            selectAllCheckbox.addEventListener(
                'change',
                function () {


                    const checked =
                        this.checked;


                    checkboxes.forEach(
                        function (checkbox) {

                            checkbox.checked =
                                checked;

                        }
                    );


                    updateSelectedState();

                }
            );

        }



        /* =====================================================
           DELETE SELECTED BUTTON
        ====================================================== */

        if (deleteSelectedBtn) {


            deleteSelectedBtn.addEventListener(
                'click',
                function (event) {


                    event.stopPropagation();


                    const selected =
                        document.querySelectorAll(
                            '.record-checkbox:checked'
                        );


                    if (selected.length === 0) {

                        return;

                    }


                    /* =========================================
                       CLOSE SINGLE DELETE POPUPS
                    ========================================== */

                    document
                        .querySelectorAll(
                            '.delete-confirm-box'
                        )
                        .forEach(
                            function (box) {

                                box.style.display =
                                    'none';

                            }
                        );


                    /* =========================================
                       UPDATE MESSAGE
                    ========================================== */

                    bulkDeleteMessage.textContent =
                        'Delete ' +
                        selected.length +
                        (
                            selected.length === 1
                                ? ' record?'
                                : ' records?'
                        );


                    /* =========================================
                       SHOW POPUP
                    ========================================== */

                    bulkConfirmBox.style.display =
                        'block';

                }
            );

        }



        /* =====================================================
           CONFIRM BULK DELETE
        ====================================================== */

        if (confirmBulkDelete) {


            confirmBulkDelete.addEventListener(
                'click',
                function (event) {


                    event.stopPropagation();


                    const selected =
                        document.querySelectorAll(
                            '.record-checkbox:checked'
                        );


                    if (selected.length === 0) {

                        return;

                    }


                    /*
                     * Submit the form.
                     *
                     * The checked inputs contain:
                     *
                     * selected_ids[]
                     *
                     * so PHP receives all selected
                     * measurement IDs.
                     */

                    const hiddenSubmit =
                        document.createElement(
                            'input'
                        );


                    hiddenSubmit.type =
                        'hidden';


                    hiddenSubmit.name =
                        'delete_selected';


                    hiddenSubmit.value =
                        '1';


                    bulkDeleteForm.appendChild(
                        hiddenSubmit
                    );


                    bulkDeleteForm.submit();

                }
            );

        }



        /* =====================================================
           CANCEL BULK DELETE
        ====================================================== */

        if (cancelBulkDelete) {


            cancelBulkDelete.addEventListener(
                'click',
                function (event) {


                    event.stopPropagation();


                    bulkConfirmBox.style.display =
                        'none';

                }
            );

        }



        /* =====================================================
           SINGLE DELETE BUTTON
        ====================================================== */

        document
            .querySelectorAll('.delete-btn')
            .forEach(
                function (button) {


                    button.addEventListener(
                        'click',
                        function (event) {


                            event.stopPropagation();


                            /* =================================
                               CLOSE BULK POPUP
                            ================================== */

                            if (bulkConfirmBox) {

                                bulkConfirmBox.style.display =
                                    'none';

                            }


                            /* =================================
                               CURRENT POPUP
                            ================================== */

                            const wrapper =
                                this.closest(
                                    '.delete-action-wrapper'
                                );


                            const popup =
                                wrapper.querySelector(
                                    '.delete-confirm-box'
                                );


                            const confirmDelete =
                                popup.querySelector(
                                    '.confirm-delete'
                                );


                            /* =================================
                               CLOSE OTHER SINGLE POPUPS
                            ================================== */

                            document
                                .querySelectorAll(
                                    '.delete-confirm-box'
                                )
                                .forEach(
                                    function (otherPopup) {


                                        if (
                                            otherPopup !==
                                            popup
                                        ) {

                                            otherPopup.style.display =
                                                'none';

                                        }

                                    }
                                );


                            /* =================================
                               DELETE URL
                            ================================== */

                            const deleteUrl =
                                this.getAttribute(
                                    'data-delete-url'
                                );


                            confirmDelete.setAttribute(
                                'href',
                                deleteUrl
                            );


                            /* =================================
                               SHOW POPUP
                            ================================== */

                            popup.style.display =
                                'block';

                        }
                    );

                }
            );



        /* =====================================================
           SINGLE DELETE - NO
        ====================================================== */

        document
            .querySelectorAll('.cancel-delete')
            .forEach(
                function (button) {


                    button.addEventListener(
                        'click',
                        function (event) {


                            event.stopPropagation();


                            const popup =
                                this.closest(
                                    '.delete-confirm-box'
                                );


                            popup.style.display =
                                'none';

                        }
                    );

                }
            );



        /* =====================================================
           SINGLE DELETE - YES
        ====================================================== */

        document
            .querySelectorAll('.confirm-delete')
            .forEach(
                function (button) {


                    button.addEventListener(
                        'click',
                        function (event) {

                            /*
                             * Do not prevent default.
                             *
                             * Browser will follow the
                             * delete URL.
                             */

                            event.stopPropagation();

                        }
                    );

                }
            );



        /* =====================================================
           CLICK OUTSIDE
        ====================================================== */

        document.addEventListener(
            'click',
            function () {


                /* Close bulk popup */

                if (bulkConfirmBox) {

                    bulkConfirmBox.style.display =
                        'none';

                }


                /* Close single popups */

                document
                    .querySelectorAll(
                        '.delete-confirm-box'
                    )
                    .forEach(
                        function (box) {

                            box.style.display =
                                'none';

                        }
                    );

            }
        );



        /* =====================================================
           KEEP POPUPS OPEN WHEN CLICKING INSIDE
        ====================================================== */

        if (bulkConfirmBox) {


            bulkConfirmBox.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                }
            );

        }


        document
            .querySelectorAll(
                '.delete-confirm-box'
            )
            .forEach(
                function (box) {


                    box.addEventListener(
                        'click',
                        function (event) {

                            event.stopPropagation();

                        }
                    );

                }
            );



        /* =====================================================
           INITIAL STATE
        ====================================================== */

        updateSelectedState();

    }
);

</script>



<!-- =========================================================
     SYSTEM SETTINGS ENGINE
     Theme / Brightness / Night Light / Text Size / Language
========================================================= -->

<script>

(function applySystemSettings() {


    /* =====================================================
       1. THEME / DARK MODE
    ====================================================== */

    const darkModeToggleBtn =
        document.getElementById(
            "darkModeToggle"
        );


    const savedTheme =
        localStorage.getItem(
            "theme"
        );


    if (savedTheme === "dark") {

        document.body.classList.add(
            "dark-mode"
        );

        document.documentElement.classList.add(
            "dark-mode"
        );

    }


    if (darkModeToggleBtn) {

        darkModeToggleBtn.addEventListener(
            "click",
            function () {


                const isDark =
                    document.body.classList.toggle(
                        "dark-mode"
                    );


                document.documentElement.classList.toggle(
                    "dark-mode",
                    isDark
                );


                localStorage.setItem(
                    "theme",
                    isDark
                        ? "dark"
                        : "light"
                );

            }
        );

    }



    /* =====================================================
       2. BRIGHTNESS
    ====================================================== */

    const savedBrightness =
        localStorage.getItem(
            "brightness"
        );


    if (savedBrightness) {

        document.body.style.filter =
            `brightness(${savedBrightness}%)`;

    }



    /* =====================================================
       3. NIGHT LIGHT
    ====================================================== */

    const savedNightLight =
        localStorage.getItem(
            "nightLight"
        );


    const nightLightOverlay =
        document.getElementById(
            "nightLightOverlay"
        );


    if (
        savedNightLight === "enabled" &&
        nightLightOverlay
    ) {

        nightLightOverlay.style.display =
            "block";

    }



    /* =====================================================
       4. TEXT SIZE
    ====================================================== */

    const savedTextSize =
        localStorage.getItem(
            "textSize"
        );


    if (savedTextSize) {


        const fontSizes = {

            xsmall: "80%",

            small: "85%",

            normal: "100%",

            large: "115%",

            xlarge: "130%"

        };


        document.documentElement.style.fontSize =
            fontSizes[savedTextSize] ||
            "100%";

    }



    /* =====================================================
       5. LANGUAGE DICTIONARY
    ====================================================== */

    const i18n = {


        /* =================================================
           ENGLISH
        ================================================== */

        en: {

            section_main:
                "Main",

            nav_dashboard:
                "Dashboard",

            section_clinical:
                "Clinical Services",

            nav_patient_mgmt:
                "Patient Management",

            nav_all_patients:
                "All Patients Services",

            nav_add_patient:
                "Add Patient",

            nav_patient_records:
                "Patient Records",

            nav_records_history:
                "Patient Records",

            nav_reports:
                "Reports",

            section_system:
                "Hardware & System",

            nav_admin:
                "Administration",

            nav_user_mgmt:
                "User Management",

            nav_settings:
                "Settings",

            nav_logout:
                "Log out",

            header_greeting:
                "Good Day, Admin",

            header_desc:
                "System status overview and clinical intake telemetry.",

            avg_health_title:
                "Average Health",

            avg_health_desc:
                "Average recorded vital measurements",

            tbl_measurement:
                "Measurement",

            tbl_average:
                "Average",

            tbl_unit:
                "Unit",

            lbl_height:
                "Height",

            lbl_weight:
                "Weight",

            lbl_temp:
                "Temperature",

            lbl_heart:
                "Heart Rate",

            lbl_bp:
                "Blood Pressure",

            title_new_patients:
                "New Patients",

            title_patients_overview:
                "Patients Overview",

            title_services:
                "Service Categories",

            title_recent_patients:
                "Recent Patient List",

            title_followup:
                "Follow-up Schedule",

            dark_mode_title:
                "Dark Mode"

        },


        /* =================================================
           FILIPINO
        ================================================== */

        fil: {

            section_main:
                "Pangunahin",

            nav_dashboard:
                "Dashboard",

            section_clinical:
                "Serbisyong Klinikal",

            nav_patient_mgmt:
                "Pamamahala ng Pasyente",

            nav_all_patients:
                "Lahat ng Serbisyong Pasyente",

            nav_add_patient:
                "Magdagdag ng Pasyente",

            nav_patient_records:
                "Mga Rekord ng Pasyente",

            nav_records_history:
                "Kasaysayan ng Rekord",

            nav_reports:
                "Mga Ulat",

            section_system:
                "Hardware at Sistema",

            nav_admin:
                "Administrasyon",

            nav_user_mgmt:
                "Pamamahala ng Gumagamit",

            nav_settings:
                "Mga Setting",

            nav_logout:
                "Mag-logout",

            header_greeting:
                "Magandang Araw, Admin",

            header_desc:
                "Pangkalahatang-ideya ng estado ng sistema.",

            avg_health_title:
                "Gitnang Kalusugan",

            avg_health_desc:
                "Karaniwang naitalang sukat ng vital signs",

            tbl_measurement:
                "Sukat",

            tbl_average:
                "Average",

            tbl_unit:
                "Yunit",

            lbl_height:
                "Taas",

            lbl_weight:
                "Timbang",

            lbl_temp:
                "Temperatura",

            lbl_heart:
                "Bilis ng Puso",

            lbl_bp:
                "Presyon ng Dugo",

            title_new_patients:
                "Bagong Pasyente",

            title_patients_overview:
                "Pangkalahatang-ideya ng Pasyente",

            title_services:
                "Kategorya ng Serbisyo",

            title_recent_patients:
                "Kasalukuyang Listahan ng Pasyente",

            title_followup:
                "Iskedyul ng Follow-up",

            dark_mode_title:
                "Dark Mode"

        },


        /* =================================================
           CEBUANO
        ================================================== */

        ceb: {

            section_main:
                "Pangunahing",

            nav_dashboard:
                "Dashboard",

            section_clinical:
                "Mga Serbisyong Klinikal",

            nav_patient_mgmt:
                "Pagdumala sa Pasyente",

            nav_all_patients:
                "Tanan nga Serbisyong Pasyente",

            nav_add_patient:
                "Idugang ang Pasyente",

            nav_patient_records:
                "Mga Rekord sa Pasyente",

            nav_records_history:
                "Kasaysayan sa Rekord",

            nav_reports:
                "Mga Report",

            section_system:
                "Hardware ug Sistema",

            nav_admin:
                "Administrasyon",

            nav_user_mgmt:
                "Pagdumala sa Paggamit",

            nav_settings:
                "Mga Setting",

            nav_logout:
                "Mo-logout",

            header_greeting:
                "Maayong Adlaw, Admin",

            header_desc:
                "Kinatibuk-ang pagtan-aw sa estado sa sistema.",

            avg_health_title:
                "Kasagarang Panglawas",

            avg_health_desc:
                "Kasagarang nahitala nga vital signs",

            tbl_measurement:
                "Sukat",

            tbl_average:
                "Average",

            tbl_unit:
                "Yunit",

            lbl_height:
                "Gitas-on",

            lbl_weight:
                "Timbang",

            lbl_temp:
                "Temperatura",

            lbl_heart:
                "Kusog sa Kasingkasing",

            lbl_bp:
                "Presyon sa Dugo",

            title_new_patients:
                "Bag-ong Pasyente",

            title_patients_overview:
                "Kinatibuk-ang Pasyente",

            title_services:
                "Mga Kategorya sa Serbisyo",

            title_recent_patients:
                "Bag-ong Listahan sa Pasyente",

            title_followup:
                "Iskedyul sa Follow-up",

            dark_mode_title:
                "Dark Mode"

        }

    };



    /* =====================================================
       APPLY SAVED LANGUAGE
    ====================================================== */

    const savedLang =
        localStorage.getItem(
            "language"
        );


    if (
        savedLang &&
        i18n[savedLang]
    ) {


        const dict =
            i18n[savedLang];


        document
            .querySelectorAll(
                "[data-i18n]"
            )
            .forEach(
                function (el) {


                    const key =
                        el.getAttribute(
                            "data-i18n"
                        );


                    if (dict[key]) {

                        el.innerText =
                            dict[key];

                    }

                }
            );

    }


})();

</script>


</body>

</html>