<?php

/** @var mysqli $conn */

session_start();

/* =========================================================
   SECURITY CHECK
   (this page shows patient data AND can delete records)
========================================================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../../login.php");
    exit();
}

require_once('../../db_conn.php');


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

    header("Location: patient-history-list.php?user_id=" . $user_id_for_redirect);
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

        // Convert all IDs to integers (keeps invalid values out of the query)
        $clean_ids = [];

        foreach ($selected_ids as $id) {

            $id = (int)$id;

            if ($id > 0) {
                $clean_ids[] = $id;
            }
        }

        if (!empty($clean_ids)) {

            // e.g. 1,2,3,4
            $id_list = implode(',', $clean_ids);

            // Delete only records belonging to the current patient
            mysqli_query(
                $conn,
                "DELETE FROM measurements
                 WHERE user_id = '$user_id_for_redirect'
                 AND id IN ($id_list)"
            );
        }
    }

    header("Location: patient-history-list.php?user_id=" . $user_id_for_redirect);
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

$fullname = trim($patient['fullname'] ?? 'Patient');

$nameParts = preg_split('/\s+/', $fullname);

if (count($nameParts) >= 2) {

    $initials =
        strtoupper(substr($nameParts[0], 0, 1)) .
        strtoupper(substr(end($nameParts), 0, 1));

} else {

    $initials = strtoupper(substr($fullname, 0, 2));
}

?>

<!DOCTYPE html>

<html lang="en" translate="no">

<head>

    <script>
    // Apply saved theme + text size before first paint (prevents flicker)
    (function () {
        try {
            if (localStorage.getItem("theme") === "dark") {
                document.documentElement.classList.add("dark-mode");
            }
            const savedTextSize = localStorage.getItem("textSize");
            if (savedTextSize) {
                const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
                document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
            }
        } catch (e) { /* localStorage unavailable */ }
    })();
    </script>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?= htmlspecialchars($fullname) ?>
        - Patient History
    </title>

    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- BOOTSTRAP ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- PATIENT HISTORY CSS -->
    <link rel="stylesheet" href="../../css/patient-history-list.css">

    <!-- THEME CSS -->
    <link rel="stylesheet" href="../../css/theme.css">

    <style>

        /* ---------- Fallback Dark Mode base ---------- */
        body.dark-mode, html.dark-mode body {
            background-color: #121824;
            color: #e2e8f0;
        }

        /* ---------- Night Light Warm Amber Filter Overlay ---------- */
        #nightLightOverlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(255, 140, 0, 0.18);
            pointer-events: none;
            z-index: 99999;
            display: none;
        }

        /* ---------- SELECT CHECKBOXES ---------- */
        .record-checkbox,
        #selectAllRecords {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #dc3545;
        }

        /* ---------- SELECTED ROW ---------- */
        .history-table tbody tr.record-selected {
            background-color: rgba(220, 53, 69, 0.07);
        }

        /* ---------- DELETE SELECTED AREA ---------- */
        .delete-selected-wrapper {
            position: relative;
            display: inline-block;
        }

        #deleteSelectedBtn {
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        #deleteSelectedBtn:not(:disabled):hover {
            transform: translateY(-1px);
        }

        #deleteSelectedBtn:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* ---------- BULK DELETE CONFIRM POPUP ---------- */
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
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.18);
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

        /* ---------- SINGLE DELETE ---------- */
        .delete-action-wrapper {
            position: relative;
            display: inline-block;
        }

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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
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

        /* ---------- DARK MODE (works with the class on <html> or <body>) ---------- */
        body.dark-mode .bulk-confirm-popup,
        html.dark-mode .bulk-confirm-popup,
        body.dark-mode .confirm-popup,
        html.dark-mode .confirm-popup {
            background: #1e293b;
            border-color: #475569;
        }

        body.dark-mode .bulk-confirm-popup span,
        html.dark-mode .bulk-confirm-popup span,
        body.dark-mode .confirm-popup span,
        html.dark-mode .confirm-popup span {
            color: #f8fafc;
        }

        body.dark-mode .history-table tbody tr.record-selected,
        html.dark-mode .history-table tbody tr.record-selected {
            background-color: rgba(220, 53, 69, 0.14);
        }

    </style>

</head>


<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

<div class="history-wrapper">


    <!-- =====================================================
         TOP BAR
    ====================================================== -->

    <div class="history-topbar">

        <div>

            <a href="patient-history.php" class="back-button fade-delay fade-delay-1">

                <i class="bi bi-chevron-left"></i>

                <span data-i18n="back_to_history">Back to Patient History</span>

            </a>

            <h1 class="page-title mt-3 fade-delay fade-delay-2" data-i18n="pr_title">
                Patient Records
            </h1>

            <p class="page-subtitle fade-delay fade-delay-3" data-i18n="pr_sub">
                Complete record of the patient's previous measurements.
            </p>

        </div>

    </div>


    <!-- =====================================================
         PATIENT HEADER
    ====================================================== -->

    <div class="patient-header-card fade-delay fade-delay-4">

        <div class="patient-header-content fade-delay fade-delay-5">

            <!-- PATIENT AVATAR -->
            <div class="patient-avatar">
                <?= htmlspecialchars($initials) ?>
            </div>

            <div>

                <div class="patient-id" data-i18n="lbl_patient">
                    PATIENT
                </div>

                <h2 class="patient-name">
                    <?= htmlspecialchars($fullname) ?>
                </h2>

                <div class="patient-details">

                    <span class="patient-detail">
                        <i class="bi bi-person-vcard"></i>
                        <span data-i18n="lbl_code">Code:</span>
                        <?= htmlspecialchars((string)($patient['code_number'] ?? '--')) ?>
                    </span>

                    <span class="patient-detail">
                        <i class="bi bi-geo-alt"></i>
                        <?= htmlspecialchars((string)($patient['address'] ?? '--')) ?>
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         HISTORY CARD
    ====================================================== -->

    <div class="history-card fade-delay fade-delay-6">

        <!-- HISTORY HEADER -->
        <div class="history-card-header fade-delay fade-delay-7">

            <div class="history-title-wrapper fade-delay fade-delay-8">

                <div class="history-title-icon">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>

                <div>

                    <h5 class="history-title" data-i18n="mh_title">
                        Measurement History
                    </h5>

                    <p class="history-subtitle" data-i18n="mh_sub">
                        Vital signs and health measurements
                    </p>

                </div>

            </div>


            <!-- RIGHT SIDE CONTROLS -->
            <div class="d-flex align-items-center gap-3">

                <!-- DELETE SELECTED -->
                <div class="delete-selected-wrapper">

                    <button type="button" id="deleteSelectedBtn" class="btn btn-danger btn-sm" disabled>

                        <i class="bi bi-trash3"></i>

                        <span data-i18n="btn_delete_selected">Delete Selected</span>

                        <span id="selectedCount" class="ms-1"></span>

                    </button>

                    <!-- BULK DELETE CONFIRMATION -->
                    <div id="bulkDeleteConfirmBox" class="bulk-delete-confirm-box">

                        <div class="bulk-confirm-popup">

                            <span id="bulkDeleteMessage" data-i18n="confirm_delete_selected">
                                Delete selected records?
                            </span>

                            <button type="button" id="confirmBulkDelete" class="btn btn-danger btn-sm" data-i18n="btn_yes">
                                Yes
                            </button>

                            <button type="button" id="cancelBulkDelete" class="btn btn-secondary btn-sm" data-i18n="btn_no">
                                No
                            </button>

                        </div>

                    </div>

                </div>


                <!-- RECORD COUNT -->
                <span class="record-count">

                    <i class="bi bi-activity me-1"></i>

                    <?= $measurementCount ?>

                    <span data-i18n="<?= $measurementCount == 1 ? 'record_one' : 'record_many' ?>"><?= $measurementCount == 1 ? 'Record' : 'Records' ?></span>

                </span>

            </div>

        </div>


        <!-- TABLE -->
        <?php if ($measurementCount > 0): ?>

            <div class="table-container">

                <form
                    id="bulkDeleteForm"
                    method="POST"
                    action="patient-history-list.php?user_id=<?= $user_id ?>"
                >

                    <input type="hidden" name="user_id" value="<?= $user_id ?>">

                    <table class="table history-table fade-delay fade-delay-9">

                        <!-- TABLE HEADER -->
                        <thead>

                            <tr>

                                <!-- SELECT ALL -->
                                <th class="text-center" style="width: 45px;">
                                    <input type="checkbox" id="selectAllRecords" title="Select All Records">
                                </th>

                                <th data-i18n="col_date">Date</th>
                                <th data-i18n="col_temp">Temperature</th>
                                <th data-i18n="col_height">Height</th>
                                <th data-i18n="col_weight">Weight</th>
                                <th data-i18n="col_bmi">BMI</th>
                                <th data-i18n="col_hr">Heart Rate</th>
                                <th>SpO₂</th>
                                <th data-i18n="col_bp">Blood Pressure</th>
                                <th data-i18n="col_doc">Document</th>
                                <th class="text-center" data-i18n="col_action">Action</th>

                            </tr>

                        </thead>


                        <!-- TABLE BODY -->
                        <tbody>

                        <?php while ($row = mysqli_fetch_assoc($historyQuery)): ?>

                            <?php

                            // DATE
                            $createdAt = strtotime($row['created_at']);

                            $dateDisplay = date('M d, Y', $createdAt);
                            $timeDisplay = date('h:i A', $createdAt);
                            $timeH24     = date('H:i', $createdAt);

                            // TEMPERATURE
                            $temperature = !empty($row['temperature'])
                                ? number_format((float)$row['temperature'], 2)
                                : '--';

                            // HEIGHT
                            $height = !empty($row['height'])
                                ? number_format((float)$row['height'], 2)
                                : '--';

                            // WEIGHT
                            $weight = !empty($row['weight'])
                                ? number_format((float)$row['weight'], 2)
                                : '--';

                            // BMI
                            $bmi = !empty($row['bmi'])
                                ? number_format((float)$row['bmi'], 2)
                                : '--';

                            // HEART RATE
                            $heartRate = !empty($row['heart_rate'])
                                ? (int)$row['heart_rate']
                                : '--';

                            // SPO2
                            $spo2 = !empty($row['spo2'])
                                ? (int)$row['spo2']
                                : '--';

                            ?>

                            <tr>

                                <!-- SELECT CHECKBOX -->
                                <td class="text-center">
                                    <input
                                        type="checkbox"
                                        class="record-checkbox"
                                        name="selected_ids[]"
                                        value="<?= (int)$row['id'] ?>"
                                    >
                                </td>

                                <!-- DATE -->
                                <td>
                                    <span class="date-main"><?= $dateDisplay ?></span>
                                    <!-- data-h24 lets the Date & Time Format setting re-render this -->
                                    <span class="date-time" data-h24="<?= $timeH24 ?>"><?= $timeDisplay ?></span>
                                </td>

                                <!-- TEMPERATURE -->
                                <td>
                                    <?php if ($temperature !== '--'): ?>
                                        <span class="vital-value"><?= $temperature ?></span>
                                        <span class="vital-unit">°C</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>

                                <!-- HEIGHT -->
                                <td>
                                    <?php if ($height !== '--'): ?>
                                        <span class="vital-value"><?= $height ?></span>
                                        <span class="vital-unit">cm</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>

                                <!-- WEIGHT -->
                                <td>
                                    <?php if ($weight !== '--'): ?>
                                        <span class="vital-value"><?= $weight ?></span>
                                        <span class="vital-unit">kg</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>

                                <!-- BMI -->
                                <td>
                                    <?php if ($bmi !== '--'): ?>
                                        <span class="bmi-value"><?= $bmi ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>

                                <!-- HEART RATE -->
                                <td>
                                    <?php if ($heartRate !== '--'): ?>
                                        <span class="vital-value"><?= $heartRate ?></span>
                                        <span class="vital-unit">BPM</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>

                                <!-- SPO2 -->
                                <td>
                                    <?php if ($spo2 !== '--'): ?>
                                        <span class="vital-value"><?= $spo2 ?></span>
                                        <span class="vital-unit">%</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>

                                <!-- BLOOD PRESSURE -->
                                <td>
                                    <?php if (!empty($row['systolic']) && !empty($row['diastolic'])): ?>
                                        <span class="bp-value">
                                            <?= (int)$row['systolic'] ?> / <?= (int)$row['diastolic'] ?>
                                        </span>
                                        <span class="vital-unit">mmHg</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>

                                <!-- DOCUMENT -->
                                <td>

                                    <?php if ($row['service_type'] == 'vital'): ?>

                                        <a
                                            href="../Doc/docu-vital-screening.php?id=<?= $user_id ?>&measurement_id=<?= (int)$row['id'] ?>"
                                            class="document-btn document-vital"
                                        >
                                            <i class="bi bi-file-earmark-medical"></i>
                                            <span data-i18n="doc_vital">Vital Screening</span>
                                        </a>

                                    <?php elseif ($row['service_type'] == 'prenatal'): ?>

                                        <a
                                            href="../Doc/docu-prenatal.php?id=<?= $user_id ?>&measurement_id=<?= (int)$row['id'] ?>"
                                            class="document-btn document-prenatal"
                                        >
                                            <i class="bi bi-heart-pulse"></i>
                                            <span data-i18n="doc_prenatal">Prenatal</span>
                                        </a>

                                    <?php elseif ($row['service_type'] == 'immunization'): ?>

                                        <a
                                            href="../Doc/docu-child-immunization.php?id=<?= $user_id ?>&measurement_id=<?= (int)$row['id'] ?>"
                                            class="document-btn document-immunization"
                                        >
                                            <i class="bi bi-shield-check"></i>
                                            <span data-i18n="doc_immun">Immunization</span>
                                        </a>

                                    <?php elseif ($row['service_type'] == 'family'): ?>

                                        <a
                                            href="../Doc/docu-family-planning.php?id=<?= $user_id ?>&measurement_id=<?= (int)$row['id'] ?>"
                                            class="document-btn document-family"
                                        >
                                            <i class="bi bi-people"></i>
                                            <span data-i18n="doc_family">Family Planning</span>
                                        </a>

                                    <?php else: ?>

                                        <span class="no-document" data-i18n="no_document">
                                            No Document
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- SINGLE DELETE -->
                                <td class="text-center">

                                    <div class="delete-action-wrapper">

                                        <button
                                            type="button"
                                            class="delete-btn btn btn-sm btn-danger"
                                            title="Delete Record"
                                            data-delete-url="patient-history-list.php?user_id=<?= $user_id ?>&delete_id=<?= (int)$row['id'] ?>"
                                        >
                                            <i class="bi bi-trash3"></i>
                                        </button>

                                        <!-- SINGLE DELETE POPUP -->
                                        <div class="delete-confirm-box">

                                            <div class="confirm-popup">

                                                <span data-i18n="delete_q">Delete?</span>

                                                <a href="#" class="btn btn-danger btn-sm confirm-delete" data-i18n="btn_yes">
                                                    Yes
                                                </a>

                                                <button type="button" class="btn btn-secondary btn-sm cancel-delete" data-i18n="btn_no">
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

            <!-- EMPTY STATE -->
            <div class="empty-history">

                <div class="empty-icon">
                    <i class="bi bi-clipboard-x"></i>
                </div>

                <h6 data-i18n="empty_title">
                    No Measurement History
                </h6>

                <p data-i18n="empty_text">
                    This patient does not have any recorded measurements yet.
                </p>

            </div>

        <?php endif; ?>

    </div>
    <!-- END history-card -->

</div>
<!-- END history-wrapper -->


<!-- BOOTSTRAP JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- THEME JS -->
<script src="../../assets/js/theme.js"></script>


<!-- =========================================================
     DELETE / MULTI-SELECT SCRIPT
========================================================= -->
<script>

document.addEventListener('DOMContentLoaded', function () {

    /* ELEMENTS */
    const checkboxes          = document.querySelectorAll('.record-checkbox');
    const selectAllCheckbox   = document.getElementById('selectAllRecords');
    const deleteSelectedBtn   = document.getElementById('deleteSelectedBtn');
    const selectedCount       = document.getElementById('selectedCount');
    const bulkConfirmBox      = document.getElementById('bulkDeleteConfirmBox');
    const bulkDeleteMessage   = document.getElementById('bulkDeleteMessage');
    const confirmBulkDelete   = document.getElementById('confirmBulkDelete');
    const cancelBulkDelete    = document.getElementById('cancelBulkDelete');
    const bulkDeleteForm      = document.getElementById('bulkDeleteForm');


    /* UPDATE SELECTED COUNT */
    function updateSelectedState() {

        let selected = 0;

        checkboxes.forEach(function (checkbox) {

            if (checkbox.checked) {
                selected++;
            }

            // Highlight selected row
            const row = checkbox.closest('tr');

            if (row) {
                row.classList.toggle('record-selected', checkbox.checked);
            }
        });

        // Update button
        if (selected > 0) {
            deleteSelectedBtn.disabled = false;
            selectedCount.textContent = '(' + selected + ')';
        } else {
            deleteSelectedBtn.disabled = true;
            selectedCount.textContent = '';
        }

        // Update "select all"
        if (selectAllCheckbox && checkboxes.length > 0) {

            selectAllCheckbox.checked = (selected === checkboxes.length);
            selectAllCheckbox.indeterminate = (selected > 0 && selected < checkboxes.length);
        }
    }


    /* INDIVIDUAL CHECKBOX */
    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectedState);
    });


    /* SELECT ALL */
    if (selectAllCheckbox) {

        selectAllCheckbox.addEventListener('change', function () {

            const checked = this.checked;

            checkboxes.forEach(function (checkbox) {
                checkbox.checked = checked;
            });

            updateSelectedState();
        });
    }


    /* DELETE SELECTED BUTTON */
    if (deleteSelectedBtn) {

        deleteSelectedBtn.addEventListener('click', function (event) {

            event.stopPropagation();

            const selected = document.querySelectorAll('.record-checkbox:checked');

            if (selected.length === 0) {
                return;
            }

            // Close single delete popups
            document.querySelectorAll('.delete-confirm-box').forEach(function (box) {
                box.style.display = 'none';
            });

            // Update message (translated by the settings engine below)
            const key = selected.length === 1 ? 'del_n_one' : 'del_n_many';
            const fallback = 'Delete ' + selected.length + (selected.length === 1 ? ' record?' : ' records?');
            const template = window.vcT ? window.vcT(key, null) : null;

            bulkDeleteMessage.textContent = template
                ? template.replace('{n}', selected.length)
                : fallback;

            // Show popup
            bulkConfirmBox.style.display = 'block';
        });
    }


    /* CONFIRM BULK DELETE */
    if (confirmBulkDelete) {

        confirmBulkDelete.addEventListener('click', function (event) {

            event.stopPropagation();

            const selected = document.querySelectorAll('.record-checkbox:checked');

            if (selected.length === 0) {
                return;
            }

            // The checked inputs carry selected_ids[]; add the submit flag PHP looks for
            const hiddenSubmit = document.createElement('input');

            hiddenSubmit.type  = 'hidden';
            hiddenSubmit.name  = 'delete_selected';
            hiddenSubmit.value = '1';

            bulkDeleteForm.appendChild(hiddenSubmit);
            bulkDeleteForm.submit();
        });
    }


    /* CANCEL BULK DELETE */
    if (cancelBulkDelete) {

        cancelBulkDelete.addEventListener('click', function (event) {

            event.stopPropagation();
            bulkConfirmBox.style.display = 'none';
        });
    }


    /* SINGLE DELETE BUTTON */
    document.querySelectorAll('.delete-btn').forEach(function (button) {

        button.addEventListener('click', function (event) {

            event.stopPropagation();

            // Close bulk popup
            if (bulkConfirmBox) {
                bulkConfirmBox.style.display = 'none';
            }

            const wrapper = this.closest('.delete-action-wrapper');
            const popup = wrapper.querySelector('.delete-confirm-box');
            const confirmDelete = popup.querySelector('.confirm-delete');

            // Close other single popups
            document.querySelectorAll('.delete-confirm-box').forEach(function (otherPopup) {

                if (otherPopup !== popup) {
                    otherPopup.style.display = 'none';
                }
            });

            // Delete URL
            confirmDelete.setAttribute('href', this.getAttribute('data-delete-url'));

            // Show popup
            popup.style.display = 'block';
        });
    });


    /* SINGLE DELETE - NO */
    document.querySelectorAll('.cancel-delete').forEach(function (button) {

        button.addEventListener('click', function (event) {

            event.stopPropagation();
            this.closest('.delete-confirm-box').style.display = 'none';
        });
    });


    /* SINGLE DELETE - YES (do not prevent default - browser follows the delete URL) */
    document.querySelectorAll('.confirm-delete').forEach(function (button) {

        button.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });


    /* CLICK OUTSIDE closes popups */
    document.addEventListener('click', function () {

        if (bulkConfirmBox) {
            bulkConfirmBox.style.display = 'none';
        }

        document.querySelectorAll('.delete-confirm-box').forEach(function (box) {
            box.style.display = 'none';
        });
    });


    /* KEEP POPUPS OPEN WHEN CLICKING INSIDE */
    if (bulkConfirmBox) {

        bulkConfirmBox.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    }

    document.querySelectorAll('.delete-confirm-box').forEach(function (box) {

        box.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });


    /* INITIAL STATE */
    updateSelectedState();
});

</script>


<!-- =========================================================
     SYSTEM SETTINGS ENGINE (applies the preferences saved in setting.php)
========================================================= -->
<script>
(function applySystemSettings() {

    // Safe localStorage helper
    const store = {
        get(key, fallback = null) {
            try {
                const v = localStorage.getItem(key);
                return v === null ? fallback : v;
            } catch (e) { return fallback; }
        }
    };

    // ---------------------------------------------------------------
    // 1. Theme / Dark Mode
    // ---------------------------------------------------------------
    const isDark = store.get("theme") === "dark";
    document.body.classList.toggle("dark-mode", isDark);
    document.documentElement.classList.toggle("dark-mode", isDark);

    // ---------------------------------------------------------------
    // 2. Night Light
    // ---------------------------------------------------------------
    const nightLightOverlay = document.getElementById("nightLightOverlay");
    if (nightLightOverlay) {
        nightLightOverlay.style.display =
            store.get("nightLight") === "enabled" ? "block" : "none";
    }

    // ---------------------------------------------------------------
    // 3. Text Size
    // ---------------------------------------------------------------
    const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
    document.documentElement.style.fontSize =
        fontSizes[store.get("textSize", "normal")] || "100%";

    // ---------------------------------------------------------------
    // 4. Language (English is the server-rendered default, so only
    //    Filipino / Cebuano need to be applied)
    // ---------------------------------------------------------------
    const i18n = {
        fil: {
            back_to_history: "Bumalik sa Kasaysayan ng Pasyente",
            pr_title: "Mga Rekord ng Pasyente",
            pr_sub: "Kumpletong talaan ng mga nakaraang sukat ng pasyente.",
            lbl_patient: "PASYENTE",
            lbl_code: "Code:",
            mh_title: "Kasaysayan ng mga Sukat",
            mh_sub: "Vital signs at mga sukat ng kalusugan",
            btn_delete_selected: "Burahin ang mga Napili",
            confirm_delete_selected: "Burahin ang mga napiling rekord?",
            btn_yes: "Oo",
            btn_no: "Hindi",
            record_one: "Rekord",
            record_many: "Mga Rekord",
            col_date: "Petsa",
            col_temp: "Temperatura",
            col_height: "Taas",
            col_weight: "Timbang",
            col_bmi: "BMI",
            col_hr: "Bilis ng Puso",
            col_bp: "Presyon ng Dugo",
            col_doc: "Dokumento",
            col_action: "Aksyon",
            doc_vital: "Vital Screening",
            doc_prenatal: "Prenatal",
            doc_immun: "Immunization",
            doc_family: "Family Planning",
            no_document: "Walang Dokumento",
            delete_q: "Burahin?",
            empty_title: "Walang Kasaysayan ng Sukat",
            empty_text: "Wala pang naitalang sukat ang pasyenteng ito.",
            del_n_one: "Burahin ang {n} rekord?",
            del_n_many: "Burahin ang {n} rekord?"
        },
        ceb: {
            back_to_history: "Balik sa Kasaysayan sa Pasyente",
            pr_title: "Mga Rekord sa Pasyente",
            pr_sub: "Kompletong rekord sa miaging mga sukat sa pasyente.",
            lbl_patient: "PASYENTE",
            lbl_code: "Code:",
            mh_title: "Kasaysayan sa mga Sukat",
            mh_sub: "Vital signs ug mga sukat sa panglawas",
            btn_delete_selected: "Papasa ang mga Napili",
            confirm_delete_selected: "Papason ang napiling mga rekord?",
            btn_yes: "Oo",
            btn_no: "Dili",
            record_one: "Rekord",
            record_many: "Mga Rekord",
            col_date: "Petsa",
            col_temp: "Temperatura",
            col_height: "Gitas-on",
            col_weight: "Timbang",
            col_bmi: "BMI",
            col_hr: "Kusog sa Kasingkasing",
            col_bp: "Presyon sa Dugo",
            col_doc: "Dokumento",
            col_action: "Aksyon",
            doc_vital: "Vital Screening",
            doc_prenatal: "Prenatal",
            doc_immun: "Immunization",
            doc_family: "Family Planning",
            no_document: "Walay Dokumento",
            delete_q: "Papason?",
            empty_title: "Walay Kasaysayan sa Sukat",
            empty_text: "Wala pay nahitala nga sukat ang pasyente.",
            del_n_one: "Papason ang {n} ka rekord?",
            del_n_many: "Papason ang {n} ka rekord?"
        }
    };

    const lang = store.get("language", "en");
    const dict = i18n[lang] || null;

    // Exposed so the delete script can translate its dynamic message
    window.vcT = function (key, fallback) {
        return (dict && dict[key]) ? dict[key] : fallback;
    };

    if (dict) {
        document.documentElement.lang = lang;

        document.querySelectorAll("[data-i18n]").forEach(el => {
            const text = dict[el.getAttribute("data-i18n")];
            if (text) el.textContent = text;
        });
    }

    // ---------------------------------------------------------------
    // 5. Date & Time Format (time shown under each record's date)
    // ---------------------------------------------------------------
    const use12h = store.get("dateTimeFormat", "24h") === "12h";

    document.querySelectorAll(".date-time[data-h24]").forEach(el => {
        const [h, m] = el.dataset.h24.split(":").map(Number);
        const mm = String(m).padStart(2, "0");

        if (use12h) {
            const suffix = h >= 12 ? "PM" : "AM";
            const h12 = h % 12 === 0 ? 12 : h % 12;
            el.textContent = String(h12).padStart(2, "0") + ":" + mm + " " + suffix;
        } else {
            el.textContent = String(h).padStart(2, "0") + ":" + mm;
        }
    });

})();
</script>

</body>

</html>