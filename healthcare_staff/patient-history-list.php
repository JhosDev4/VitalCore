<?php

/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   DATABASE CONNECTION
========================================================= */

if (file_exists('../../db_conn.php')) {
    require_once('../../db_conn.php');
} elseif (file_exists('../db_conn.php')) {
    require_once('../db_conn.php');
} else {
    require_once('db_conn.php');
}

/* =========================================================
   ACCESS CONTROL
========================================================= */

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Staff'], true)
) {
    $loginRedirect = file_exists('../../login.php') ? '../../login.php' : '../login.php';
    header("Location: $loginRedirect");
    exit();
}

/* =========================================================
   DELETE SINGLE MEASUREMENT
========================================================= */

if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $user_id_for_redirect = (int)($_GET['user_id'] ?? 0);

    if ($delete_id > 0 && $user_id_for_redirect > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM measurements WHERE id = ? AND user_id = ?"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $delete_id, $user_id_for_redirect);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    header("Location: patient-history-list.php?user_id={$user_id_for_redirect}&msg=deleted");
    exit();
}

/* =========================================================
   DELETE MULTIPLE MEASUREMENTS (BULK)
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
        $clean_ids = [];
        foreach ($selected_ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $clean_ids[] = $id;
            }
        }

        if (!empty($clean_ids)) {
            $id_list = implode(',', $clean_ids);
            mysqli_query(
                $conn,
                "DELETE FROM measurements
                 WHERE user_id = '$user_id_for_redirect'
                 AND id IN ($id_list)"
            );
        }
    }

    header("Location: patient-history-list.php?user_id={$user_id_for_redirect}&msg=bulk_deleted");
    exit();
}

/* =========================================================
   CHECK PATIENT
========================================================= */

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    die("Patient not found.");
}

$user_id = (int)$_GET['user_id'];

/* =========================================================
   PATIENT INFO
========================================================= */

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$patientResult = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($patientResult);
mysqli_stmt_close($stmt);

if (!$patient) {
    die("Patient not found.");
}

/* =========================================================
   MEASUREMENT HISTORY
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM measurements WHERE user_id = ? ORDER BY created_at DESC"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$historyQuery = mysqli_stmt_get_result($stmt);
$measurementCount = $historyQuery ? mysqli_num_rows($historyQuery) : 0;

/* =========================================================
   PATIENT INITIALS & NAME
========================================================= */

$fullname = trim($patient['fullname'] ?? '');
if ($fullname === '') {
    $fullname = 'Patient';
}

$nameParts = preg_split('/\s+/', $fullname);
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1)) . strtoupper(substr(end($nameParts), 0, 1));
} else {
    $initials = strtoupper(substr($fullname, 0, min(2, strlen($fullname))));
}

$backUrl = file_exists('patient-history.php') ? 'patient-history.php' : '../patient-history.php';
$msg = $_GET['msg'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script>
    // Global Settings Application Script (Prevents UI Flicker on Page Load)
    (function() {
        try {
            const savedTheme = localStorage.getItem("theme") || localStorage.getItem("staff_theme");
            if (savedTheme === "dark") {
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
    <title><?= htmlspecialchars($fullname) ?> - Patient History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/patient-history-list.css">
    <link rel="stylesheet" href="../../css/theme.css">

    <style>
        /* Night Light Filter Overlay */
        #nightLightOverlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(255, 140, 0, 0.18);
            pointer-events: none;
            z-index: 99999;
            display: none;
        }

        .record-checkbox,
        #selectAllRecords {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #dc3545;
        }

        .history-table tbody tr.record-selected {
            background-color: rgba(220, 53, 69, 0.08) !important;
        }

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

        .bulk-delete-confirm-box {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 1050;
            display: none;
        }

        .bulk-confirm-popup {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 10px 14px;
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
            margin-right: 4px;
        }

        .bulk-confirm-popup .btn,
        .confirm-popup .btn {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .delete-action-wrapper {
            position: relative;
            display: inline-block;
        }

        .delete-confirm-box {
            position: absolute;
            z-index: 1050;
            top: 50%;
            right: calc(100% + 8px);
            transform: translateY(-50%);
            display: none;
        }

        .confirm-popup {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 8px 12px;
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

        /* Dark Mode Theme Overrides */
        body.dark-mode, html.dark-mode body {
            background-color: #121824 !important;
            color: #e2e8f0 !important;
        }

        body.dark-mode .history-card,
        body.dark-mode .patient-header-card {
            background-color: #1e293b !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: #e2e8f0 !important;
        }

        body.dark-mode .history-title,
        body.dark-mode .patient-name,
        body.dark-mode .page-title {
            color: #f8fafc !important;
        }

        body.dark-mode .page-subtitle,
        body.dark-mode .history-subtitle,
        body.dark-mode .patient-detail {
            color: #94a3b8 !important;
        }

        body.dark-mode .history-table {
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }

        body.dark-mode .history-table thead th {
            background-color: #0f172a !important;
            color: #94a3b8 !important;
            border-color: #334155 !important;
        }

        body.dark-mode .history-table tbody td {
            border-color: #334155 !important;
        }

        body.dark-mode .date-main,
        body.dark-mode .vital-value,
        body.dark-mode .bp-value,
        body.dark-mode .bmi-value {
            color: #f8fafc !important;
        }

        body.dark-mode .bulk-confirm-popup,
        body.dark-mode .confirm-popup {
            background: #1e293b;
            border-color: #475569;
        }

        body.dark-mode .bulk-confirm-popup span,
        body.dark-mode .confirm-popup span {
            color: #f8fafc;
        }

        body.dark-mode .history-table tbody tr.record-selected {
            background-color: rgba(220, 53, 69, 0.18) !important;
        }
    </style>
</head>

<body>

<!-- Night Light Filter Overlay -->
<div id="nightLightOverlay"></div>

<div class="history-wrapper">

    <!-- TOP BAR -->
    <div class="history-topbar">
        <div>
            <a href="<?= htmlspecialchars($backUrl) ?>" class="back-button fade-delay fade-delay-1">
                <i class="bi bi-chevron-left"></i> <span data-i18n="back_to_history">Back to Patient History</span>
            </a>
            <h1 class="page-title mt-3 fade-delay fade-delay-2" data-i18n="patient_records_title">Patient Records</h1>
            <p class="page-subtitle fade-delay fade-delay-3" data-i18n="patient_records_subtitle">Complete record of the patient's previous measurements.</p>
        </div>
    </div>

    <!-- FEEDBACK ALERT -->
    <?php if ($msg === 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show my-2" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <span data-i18n="msg_deleted">Measurement record deleted successfully.</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($msg === 'bulk_deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show my-2" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <span data-i18n="msg_bulk_deleted">Selected measurement records deleted successfully.</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- PATIENT HEADER CARD -->
    <div class="patient-header-card fade-delay fade-delay-4">
        <div class="patient-header-content fade-delay fade-delay-5">
            <div class="patient-avatar">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div>
                <div class="patient-id" data-i18n="badge_patient">PATIENT</div>
                <h2 class="patient-name"><?= htmlspecialchars($fullname) ?></h2>
                <div class="patient-details">
                    <span class="patient-detail">
                        <i class="bi bi-person-vcard"></i> <span data-i18n="lbl_code">Code:</span> <?= htmlspecialchars($patient['code_number'] ?? '--') ?>
                    </span>
                    <span class="patient-detail">
                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($patient['address'] ?? '--') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- HISTORY CARD -->
    <div class="history-card fade-delay fade-delay-6">
        <div class="history-card-header fade-delay fade-delay-7">
            <div class="history-title-wrapper fade-delay fade-delay-8">
                <div class="history-title-icon">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
                <div>
                    <h5 class="history-title" data-i18n="measurement_history_title">Measurement History</h5>
                    <p class="history-subtitle" data-i18n="measurement_history_subtitle">Vital signs and health measurements</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- DELETE SELECTED -->
                <div class="delete-selected-wrapper">
                    <button type="button" id="deleteSelectedBtn" class="btn btn-danger btn-sm" disabled>
                        <i class="bi bi-trash3"></i> <span data-i18n="btn_delete_selected">Delete Selected</span> <span id="selectedCount" class="ms-1"></span>
                    </button>

                    <div id="bulkDeleteConfirmBox" class="bulk-delete-confirm-box">
                        <div class="bulk-confirm-popup">
                            <span id="bulkDeleteMessage">Delete selected records?</span>
                            <button type="button" id="confirmBulkDelete" class="btn btn-danger btn-sm" data-i18n="btn_yes">Yes</button>
                            <button type="button" id="cancelBulkDelete" class="btn btn-secondary btn-sm" data-i18n="btn_no">No</button>
                        </div>
                    </div>
                </div>

                <!-- RECORD COUNT -->
                <span class="record-count">
                    <i class="bi bi-activity me-1"></i>
                    <span><?= $measurementCount ?></span>
                    <span data-i18n="<?= $measurementCount == 1 ? 'lbl_record' : 'lbl_records' ?>">
                        <?= $measurementCount == 1 ? 'Record' : 'Records' ?>
                    </span>
                </span>
            </div>
        </div>

        <?php if ($measurementCount > 0): ?>
            <div class="table-container">
                <form id="bulkDeleteForm" method="POST" action="patient-history-list.php?user_id=<?= $user_id ?>">
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                    <input type="hidden" name="delete_selected" value="1">

                    <table class="table history-table fade-delay fade-delay-9">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 45px;">
                                    <input type="checkbox" id="selectAllRecords" title="Select All Records">
                                </th>
                                <th data-i18n="tbl_date">Date</th>
                                <th data-i18n="tbl_temp">Temperature</th>
                                <th data-i18n="tbl_height">Height</th>
                                <th data-i18n="tbl_weight">Weight</th>
                                <th data-i18n="tbl_bmi">BMI</th>
                                <th data-i18n="tbl_heart">Heart Rate</th>
                                <th data-i18n="tbl_spo2">SpO₂</th>
                                <th data-i18n="tbl_bp">Blood Pressure</th>
                                <th data-i18n="tbl_document">Document</th>
                                <th class="text-center" data-i18n="tbl_action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysqli_fetch_assoc($historyQuery)): ?>
                            <?php
                            $createdAt = !empty($row['created_at']) ? strtotime($row['created_at']) : time();
                            $dateDisplay = date('M d, Y', $createdAt);
                            $time12Display = date('h:i A', $createdAt);
                            $time24Display = date('H:i', $createdAt);

                            $temperature = (!empty($row['temperature']) && is_numeric($row['temperature']))
                                ? number_format((float)$row['temperature'], 2) : '--';

                            $height = (!empty($row['height']) && is_numeric($row['height']))
                                ? number_format((float)$row['height'], 2) : '--';

                            $weight = (!empty($row['weight']) && is_numeric($row['weight']))
                                ? number_format((float)$row['weight'], 2) : '--';

                            $bmi = (!empty($row['bmi']) && is_numeric($row['bmi']))
                                ? number_format((float)$row['bmi'], 2) : '--';

                            $heartRate = (!empty($row['heart_rate']) && is_numeric($row['heart_rate']))
                                ? (int)$row['heart_rate'] : '--';

                            $spo2 = (!empty($row['spo2']) && is_numeric($row['spo2']))
                                ? (int)$row['spo2'] : '--';

                            $hasBp = !empty($row['systolic']) && !empty($row['diastolic']);

                            $serviceType = strtolower(trim($row['service_type'] ?? ''));
                            $recordId = (int)$row['id'];
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="record-checkbox" name="selected_ids[]" value="<?= $recordId ?>">
                                </td>
                                <td>
                                    <span class="date-main"><?= $dateDisplay ?></span>
                                    <span class="date-time" data-time12="<?= $time12Display ?>" data-time24="<?= $time24Display ?>"><?= $time12Display ?></span>
                                </td>
                                <td>
                                    <?php if ($temperature !== '--'): ?>
                                        <span class="vital-value"><?= $temperature ?></span>
                                        <span class="vital-unit">°C</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($height !== '--'): ?>
                                        <span class="vital-value"><?= $height ?></span>
                                        <span class="vital-unit">cm</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($weight !== '--'): ?>
                                        <span class="vital-value"><?= $weight ?></span>
                                        <span class="vital-unit">kg</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($bmi !== '--'): ?>
                                        <span class="bmi-value"><?= $bmi ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($heartRate !== '--'): ?>
                                        <span class="vital-value"><?= $heartRate ?></span>
                                        <span class="vital-unit">BPM</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($spo2 !== '--'): ?>
                                        <span class="vital-value"><?= $spo2 ?></span>
                                        <span class="vital-unit">%</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasBp): ?>
                                        <span class="bp-value"><?= (int)$row['systolic'] ?>/<?= (int)$row['diastolic'] ?></span>
                                        <span class="vital-unit">mmHg</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array($serviceType, ['vital', 'vitals', 'vital_screening'], true)): ?>
                                        <a href="docu-vital-screening.php?id=<?= $user_id ?>&measurement_id=<?= $recordId ?>" class="document-btn document-vital">
                                            <i class="bi bi-file-earmark-medical"></i> <span data-i18n="doc_vital">Vital Screening</span>
                                        </a>
                                    <?php elseif (in_array($serviceType, ['prenatal', 'pre-natal'], true)): ?>
                                        <a href="docu-prenatal.php?id=<?= $user_id ?>&measurement_id=<?= $recordId ?>" class="document-btn document-prenatal">
                                            <i class="bi bi-heart-pulse"></i> <span data-i18n="doc_prenatal">Prenatal</span>
                                        </a>
                                    <?php elseif (in_array($serviceType, ['family', 'family_planning', 'family planning'], true)): ?>
                                        <a href="docu-family-planning.php?id=<?= $user_id ?>&measurement_id=<?= $recordId ?>" class="document-btn document-family">
                                            <i class="bi bi-people"></i> <span data-i18n="doc_family">Family Planning</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="no-document" data-i18n="doc_none">No Document</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="delete-action-wrapper">
                                        <button type="button" class="delete-btn btn btn-sm btn-danger" title="Delete Record">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                        <div class="delete-confirm-box">
                                            <div class="confirm-popup">
                                                <span data-i18n="popup_delete_confirm">Delete?</span>
                                                <a href="patient-history-list.php?user_id=<?= $user_id ?>&delete_id=<?= $recordId ?>" class="btn btn-danger btn-sm confirm-delete" data-i18n="btn_yes">Yes</a>
                                                <button type="button" class="btn btn-secondary btn-sm cancel-delete" data-i18n="btn_no">No</button>
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
            <div class="empty-history">
                <div class="empty-icon"><i class="bi bi-clipboard-x"></i></div>
                <h6 data-i18n="empty_title">No Measurement History</h6>
                <p data-i18n="empty_desc">This patient does not have any recorded measurements yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/theme.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.record-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllRecords');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const selectedCount = document.getElementById('selectedCount');
    const bulkConfirmBox = document.getElementById('bulkDeleteConfirmBox');
    const bulkDeleteMessage = document.getElementById('bulkDeleteMessage');
    const confirmBulkDelete = document.getElementById('confirmBulkDelete');
    const cancelBulkDelete = document.getElementById('cancelBulkDelete');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');

    function updateSelectedState() {
        let selected = 0;
        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                selected++;
            }
            const row = checkbox.closest('tr');
            if (row) {
                row.classList.toggle('record-selected', checkbox.checked);
            }
        });

        if (selected > 0) {
            deleteSelectedBtn.disabled = false;
            selectedCount.textContent = '(' + selected + ')';
        } else {
            deleteSelectedBtn.disabled = true;
            selectedCount.textContent = '';
        }

        if (selectAllCheckbox && checkboxes.length > 0) {
            selectAllCheckbox.checked = selected === checkboxes.length;
            selectAllCheckbox.indeterminate = selected > 0 && selected < checkboxes.length;
        }
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectedState);
    });

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const checked = this.checked;
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = checked;
            });
            updateSelectedState();
        });
    }

    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            const selected = document.querySelectorAll('.record-checkbox:checked');
            if (selected.length === 0) return;

            document.querySelectorAll('.delete-confirm-box').forEach(box => box.style.display = 'none');
            const savedLang = localStorage.getItem("language") || "en";
            if (savedLang === "fil") {
                bulkDeleteMessage.textContent = 'Burahin ang ' + selected.length + ' na rekord?';
            } else if (savedLang === "ceb") {
                bulkDeleteMessage.textContent = 'Papason ang ' + selected.length + ' ka rekord?';
            } else {
                bulkDeleteMessage.textContent = 'Delete ' + selected.length + (selected.length === 1 ? ' record?' : ' records?');
            }
            bulkConfirmBox.style.display = 'block';
        });
    }

    if (confirmBulkDelete) {
        confirmBulkDelete.addEventListener('click', function (event) {
            event.stopPropagation();
            const selected = document.querySelectorAll('.record-checkbox:checked');
            if (selected.length === 0) return;
            bulkDeleteForm.submit();
        });
    }

    if (cancelBulkDelete) {
        cancelBulkDelete.addEventListener('click', function (event) {
            event.stopPropagation();
            bulkConfirmBox.style.display = 'none';
        });
    }

    // Single delete button logic
    document.querySelectorAll('.delete-btn').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            if (bulkConfirmBox) bulkConfirmBox.style.display = 'none';

            const wrapper = this.closest('.delete-action-wrapper');
            const popup = wrapper.querySelector('.delete-confirm-box');

            document.querySelectorAll('.delete-confirm-box').forEach(function (otherPopup) {
                if (otherPopup !== popup) otherPopup.style.display = 'none';
            });

            popup.style.display = 'block';
        });
    });

    document.querySelectorAll('.cancel-delete').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            const popup = this.closest('.delete-confirm-box');
            if (popup) popup.style.display = 'none';
        });
    });

    // Close all popups on clicking outside
    document.addEventListener('click', function () {
        if (bulkConfirmBox) bulkConfirmBox.style.display = 'none';
        document.querySelectorAll('.delete-confirm-box').forEach(box => box.style.display = 'none');
    });

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

    updateSelectedState();
});
</script>

<!-- SYSTEM SETTINGS ENGINE (Connected directly to setting.php) -->
<script>
function applySystemSettings() {
    // 1. Dark Mode
    const savedTheme = localStorage.getItem("theme") || localStorage.getItem("staff_theme");
    const isDark = (savedTheme === "dark");
    document.body.classList.toggle("dark-mode", isDark);
    document.documentElement.classList.toggle("dark-mode", isDark);

    // 2. Night Light
    const savedNightLight = localStorage.getItem("nightLight");
    const nightLightOverlay = document.getElementById("nightLightOverlay");
    if (nightLightOverlay) {
        nightLightOverlay.style.display = (savedNightLight === "enabled") ? "block" : "none";
    }

    // 3. Text Size
    const savedTextSize = localStorage.getItem("textSize");
    if (savedTextSize) {
        const fontSizes = { xsmall: "80%", small: "85%", normal: "100%", large: "115%", xlarge: "130%" };
        document.documentElement.style.fontSize = fontSizes[savedTextSize] || "100%";
    }

    // 4. Date & Time Format (12h vs 24h)
    const savedFmt = localStorage.getItem("dateTimeFormat") || "24h";
    document.querySelectorAll(".date-time").forEach(function (el) {
        if (savedFmt === "24h") {
            if (el.dataset.time24) el.textContent = el.dataset.time24;
        } else {
            if (el.dataset.time12) el.textContent = el.dataset.time12;
        }
    });

    // 5. Language Dictionary & Translations
    const i18n = {
        en: {
            back_to_history: "Back to Patient History",
            patient_records_title: "Patient Records",
            patient_records_subtitle: "Complete record of the patient's previous measurements.",
            badge_patient: "PATIENT",
            lbl_code: "Code:",
            measurement_history_title: "Measurement History",
            measurement_history_subtitle: "Vital signs and health measurements",
            btn_delete_selected: "Delete Selected",
            btn_yes: "Yes",
            btn_no: "No",
            lbl_record: "Record",
            lbl_records: "Records",
            tbl_date: "Date",
            tbl_temp: "Temperature",
            tbl_height: "Height",
            tbl_weight: "Weight",
            tbl_bmi: "BMI",
            tbl_heart: "Heart Rate",
            tbl_spo2: "SpO₂",
            tbl_bp: "Blood Pressure",
            tbl_document: "Document",
            tbl_action: "Action",
            doc_vital: "Vital Screening",
            doc_prenatal: "Prenatal",
            doc_family: "Family Planning",
            doc_none: "No Document",
            popup_delete_confirm: "Delete?",
            empty_title: "No Measurement History",
            empty_desc: "This patient does not have any recorded measurements yet.",
            msg_deleted: "Measurement record deleted successfully.",
            msg_bulk_deleted: "Selected measurement records deleted successfully."
        },
        fil: {
            back_to_history: "Bumalik sa Kasaysayan ng Pasyente",
            patient_records_title: "Mga Rekord ng Pasyente",
            patient_records_subtitle: "Kumpletong rekord ng mga nakaraang sukat ng pasyente.",
            badge_patient: "PASYENTE",
            lbl_code: "Kowd:",
            measurement_history_title: "Kasaysayan ng Sukat",
            measurement_history_subtitle: "Vital signs at mga sukat ng kalusugan",
            btn_delete_selected: "Burahin ang Napili",
            btn_yes: "Oo",
            btn_no: "Hindi",
            lbl_record: "Rekord",
            lbl_records: "Mga Rekord",
            tbl_date: "Petsa",
            tbl_temp: "Temperatura",
            tbl_height: "Taas",
            tbl_weight: "Timbang",
            tbl_bmi: "BMI",
            tbl_heart: "Bilis ng Puso",
            tbl_spo2: "SpO₂",
            tbl_bp: "Presyon ng Dugo",
            tbl_document: "Dokumento",
            tbl_action: "Aksyon",
            doc_vital: "Pagsusuri sa Vitals",
            doc_prenatal: "Pre-natal",
            doc_family: "Pagpaplano ng Pamilya",
            doc_none: "Walang Dokumento",
            popup_delete_confirm: "Burahin?",
            empty_title: "Walang Kasaysayan ng Sukat",
            empty_desc: "Ang pasyenteng ito ay wala pang naitalang mga sukat.",
            msg_deleted: "Matagumpay na nabura ang rekord ng sukat.",
            msg_bulk_deleted: "Matagumpay na nabura ang mga napiling rekord."
        },
        ceb: {
            back_to_history: "Balik sa Kasaysayan sa Pasyente",
            patient_records_title: "Mga Rekord sa Pasyente",
            patient_records_subtitle: "Kompletong rekord sa mga nangaging sukat sa pasyente.",
            badge_patient: "PASYENTE",
            lbl_code: "Koda:",
            measurement_history_title: "Kasaysayan sa Sukat",
            measurement_history_subtitle: "Vital signs ug mga sukat sa panglawas",
            btn_delete_selected: "Papasa ang Napili",
            btn_yes: "Oo",
            btn_no: "Dili",
            lbl_record: "Rekord",
            lbl_records: "Mga Rekord",
            tbl_date: "Petsa",
            tbl_temp: "Temperatura",
            tbl_height: "Gitas-on",
            tbl_weight: "Timbang",
            tbl_bmi: "BMI",
            tbl_heart: "Kusog sa Kasingkasing",
            tbl_spo2: "SpO₂",
            tbl_bp: "Presyon sa Dugo",
            tbl_document: "Dokumento",
            tbl_action: "Aksyon",
            doc_vital: "Pagsusi sa Vitals",
            doc_prenatal: "Pre-natal",
            doc_family: "Pagplano sa Pamilya",
            doc_none: "Walay Dokumento",
            popup_delete_confirm: "Papason?",
            empty_title: "Walay Kasaysayan sa Sukat",
            empty_desc: "Kini nga pasyente wala pay natala nga mga sukat.",
            msg_deleted: "Malamposong napapas ang rekord sa sukat.",
            msg_bulk_deleted: "Malamposong napapas ang mga napiling rekord."
        }
    };

    const savedLang = localStorage.getItem("language") || "en";
    const dict = i18n[savedLang] || i18n.en;
    document.querySelectorAll("[data-i18n]").forEach(function (el) {
        const key = el.getAttribute("data-i18n");
        if (dict[key]) {
            el.textContent = dict[key];
        }
    });
}

// Run immediately on page initialization
applySystemSettings();

// Live listener: If settings change in setting.php in another tab/window, sync instantly!
window.addEventListener("storage", function (e) {
    if (["theme", "staff_theme", "nightLight", "textSize", "language", "dateTimeFormat"].includes(e.key)) {
        applySystemSettings();
    }
});
</script>

</body>
</html>
