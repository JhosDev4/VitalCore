<?php
/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../db_conn.php');

if (
    !isset($_SESSION['staff_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Staff'], true)
) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Patient not found.");
}

$patient_id     = (int)$_GET['id'];
$measurement_id = isset($_GET['measurement_id']) && is_numeric($_GET['measurement_id']) ? (int)$_GET['measurement_id'] : 0;

// 1. Fetch Patient Info
$query = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE id = $patient_id LIMIT 1"
);
$patient = mysqli_fetch_assoc($query);

if (!$patient) {
    die("Patient not found.");
}

// 2. Fetch Vital Measurements for This Specific Visit
$temp       = '--';
$weight     = '--';
$height     = '--';
$bmi        = '--';
$heart_rate = '--';
$spo2       = '--';
$systolic   = '--';
$diastolic  = '--';
$last_visited = 'No visits recorded';
$doc_date     = date('F d, Y');

if ($measurement_id > 0) {
    $measurementQuery = mysqli_query(
        $conn,
        "SELECT * FROM measurements WHERE id = $measurement_id LIMIT 1"
    );
} else {
    $measurementQuery = mysqli_query(
        $conn,
        "SELECT * FROM measurements WHERE user_id = $patient_id ORDER BY created_at DESC LIMIT 1"
    );
}

if ($measurementQuery && mysqli_num_rows($measurementQuery) > 0) {
    $measurement = mysqli_fetch_assoc($measurementQuery);

    $temp       = $measurement['temperature'] ?? '--';
    $weight     = $measurement['weight'] ?? '--';
    $height     = $measurement['height'] ?? '--';
    $bmi        = $measurement['bmi'] ?? '--';
    $heart_rate = $measurement['heart_rate'] ?? '--';
    $spo2       = $measurement['spo2'] ?? '--';
    $systolic   = $measurement['systolic'] ?? '--';
    $diastolic  = $measurement['diastolic'] ?? '--';

    if (!empty($measurement['created_at'])) {
        $last_visited = date('F d, Y - h:i A', strtotime($measurement['created_at']));
        $doc_date     = date('F d, Y', strtotime($measurement['created_at']));
    }
}

// 3. Data Processing & Formatting
$temp_val       = (is_numeric($temp)       && (float)$temp > 0)       ? (float)$temp       : null;
$weight_val     = (is_numeric($weight)     && (float)$weight > 0)     ? (float)$weight     : null;
$height_val     = (is_numeric($height)     && (float)$height > 0)     ? (float)$height     : null;
$bmi_val        = (is_numeric($bmi)        && (float)$bmi > 0)        ? (float)$bmi        : null;
$heart_rate_val = (is_numeric($heart_rate) && (int)$heart_rate > 0)   ? (int)$heart_rate   : null;
$spo2_val       = (is_numeric($spo2)       && (int)$spo2 > 0)         ? (int)$spo2         : null;
$systolic_val   = (is_numeric($systolic)   && (int)$systolic > 0)     ? (int)$systolic     : null;
$diastolic_val  = (is_numeric($diastolic)  && (int)$diastolic > 0)    ? (int)$diastolic    : null;

// Display strings
$temp_disp       = ($temp_val !== null)   ? number_format($temp_val, 1) . ' °C' : '--';
$weight_disp     = ($weight_val !== null) ? number_format($weight_val, 1) . ' kg' : '--';
$height_disp     = ($height_val !== null) ? number_format($height_val, 1) . ' cm' : '--';
$bmi_disp        = ($bmi_val !== null)    ? number_format($bmi_val, 2)          : '--';
$heart_rate_disp = ($heart_rate_val !== null) ? $heart_rate_val . ' BPM'        : '--';
$spo2_disp       = ($spo2_val !== null)   ? $spo2_val . '%'                      : '--';
$bp_disp         = ($systolic_val !== null && $diastolic_val !== null) ? $systolic_val . '/' . $diastolic_val . ' mmHg' : '--';

// Status Evaluations
$temp_status = 'Normal';
$temp_class  = 'badge-success';
if ($temp_val !== null) {
    if ($temp_val < 36.0) { $temp_status = 'Low Temp'; $temp_class = 'badge-info'; }
    elseif ($temp_val > 37.5 && $temp_val <= 38.5) { $temp_status = 'Slight Fever'; $temp_class = 'badge-warning'; }
    elseif ($temp_val > 38.5) { $temp_status = 'High Fever'; $temp_class = 'badge-danger'; }
}

$has_bmi = ($bmi_val !== null && $weight_val !== null);
$bmi_status = '--';
$bmi_class  = 'badge-secondary';
if ($has_bmi) {
    if ($bmi_val < 18.5) { $bmi_status = 'Underweight'; $bmi_class = 'badge-info'; }
    elseif ($bmi_val <= 24.9) { $bmi_status = 'Normal'; $bmi_class = 'badge-success'; }
    elseif ($bmi_val <= 29.9) { $bmi_status = 'Overweight'; $bmi_class = 'badge-warning'; }
    else { $bmi_status = 'Obese'; $bmi_class = 'badge-danger'; }
}

$heart_status = 'Normal';
$heart_class  = 'badge-success';
if ($heart_rate_val !== null) {
    if ($heart_rate_val < 60) { $heart_status = 'Low (Bradycardia)'; $heart_class = 'badge-warning'; }
    elseif ($heart_rate_val > 100) { $heart_status = 'High (Tachycardia)'; $heart_class = 'badge-danger'; }
}

$spo2_status = 'Normal';
$spo2_class  = 'badge-success';
if ($spo2_val !== null) {
    if ($spo2_val < 90) { $spo2_status = 'Critical Low'; $spo2_class = 'badge-danger'; }
    elseif ($spo2_val < 95) { $spo2_status = 'Mild Low'; $spo2_class = 'badge-warning'; }
}

// Blood Pressure Status
$bp_status = '--';
$bp_class  = 'badge-secondary';
if ($systolic_val !== null && $diastolic_val !== null) {
    if ($systolic_val > 180 || $diastolic_val > 120) {
        $bp_status = 'Hypertensive Crisis'; $bp_class = 'badge-danger';
    } elseif ($systolic_val >= 140 || $diastolic_val >= 90) {
        $bp_status = 'High Stage 2';        $bp_class = 'badge-danger';
    } elseif (($systolic_val >= 130 && $systolic_val <= 139) || ($diastolic_val >= 80 && $diastolic_val <= 89)) {
        $bp_status = 'High Stage 1';        $bp_class = 'badge-warning';
    } elseif ($systolic_val >= 120 && $systolic_val <= 129 && $diastolic_val < 80) {
        $bp_status = 'Elevated';            $bp_class = 'badge-warning';
    } elseif ($systolic_val < 90 || $diastolic_val < 60) {
        $bp_status = 'Low (Hypotension)';   $bp_class = 'badge-info';
    } else {
        $bp_status = 'Normal';              $bp_class = 'badge-success';
    }
}

// Weight & Height Status
$weight_status = ($weight_val !== null) ? 'Recorded' : '--';
$height_status = ($height_val !== null) ? 'Recorded' : '--';

$safe_filename = 'Patient_Report_PT' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT) . '_' . preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', strtolower($patient['fullname'])));

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <!-- PRE-LOAD STAFF INDEPENDENT DARK MODE -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('staff_theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark-mode');
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Clinical Record - #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/document.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Dark Mode Override Fix for Clinic Title */
        .clinic-name,
        h1.clinic-name,
        html.dark-mode .clinic-name,
        body.dark-mode .clinic-name,
        .dark-mode h1.clinic-name {
            color: #0a49c4 !important;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .document-wrapper { box-shadow: none !important; margin: 0 !important; width: 100% !important; }
            .page-break { page-break-before: always; }
        }
    </style>

</head>
<body>

<!-- TOP ACTION TOOLBAR -->
<div class="no-print bg-white border-bottom p-3 mb-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 820px;">
        <a href="patient-history-list.php?user_id=<?= $patient['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Patient History
        </a>

        <!-- ACTION BUTTONS -->
        <div class="d-flex gap-2">
            <button onclick="saveAsPDF()" class="btn btn-success btn-sm px-3 fw-bold">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Save As PDF...
            </button>
            <button onclick="saveAsWord()" class="btn btn-info text-white btn-sm px-3 fw-bold">
                <i class="bi bi-file-earmark-word-fill me-1"></i> Save As Word...
            </button>
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Print
            </button>
        </div>
    </div>
</div>

<div class="container" style="max-width: 820px;">
    <div class="document-wrapper" id="document-wrapper">

        <!-- CLINIC LETTERHEAD HEADER -->
        <div class="clinic-header">
            <div class="clinic-brand">
                <img src="../../img/logo.jpg" alt="Logo" class="clinic-logo" onerror="this.src='https://via.placeholder.com/48?text=VC'">
                <div>
                    <h1 class="clinic-name" style="color:#0a49c4 !important;">Vital Screening</h1>
                    <p class="clinic-sub">Clinical Patient Record & Health Assessment</p>
                </div>
            </div>
        </div>

        <!-- PATIENT DEMOGRAPHICS -->
        <div class="section-header">
            <i class="bi bi-person-lines-fill text-primary"></i>
            Patient Demographics
        </div>

        <div class="info-grid">

            <div class="info-item">
                <span class="info-label">Patient ID:</span>
                <span class="info-value">
                    #PT<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Full Name:</span>
                <span class="info-value">
                    <?= htmlspecialchars($patient['fullname']); ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Gender / Age:</span>
                <span class="info-value">
                    <?= htmlspecialchars($patient['gender']); ?>
                    /
                    <?= htmlspecialchars($patient['age']); ?> yrs
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Birth Date:</span>
                <span class="info-value">
                    <?php
                        if (!empty($patient['birth_date'])) {
                            echo date('F d, Y', strtotime($patient['birth_date']));
                        } else {
                            echo '--';
                        }
                    ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Blood Type:</span>
                <span class="info-value">
                    <?= !empty($patient['blood_type'])
                        ? htmlspecialchars($patient['blood_type'])
                        : '--'; ?>
                </span>
            </div>

            <div class="info-item">
                <span class="info-label">Contact No:</span>
                <span class="info-value">
                    <?= !empty($patient['contact_number'])
                        ? htmlspecialchars($patient['contact_number'])
                        : '--'; ?>
                </span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Address:</span>
                <span class="info-value">
                    <?= !empty($patient['address'])
                        ? htmlspecialchars($patient['address'])
                        : '--'; ?>
                </span>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Visit Date/Time:</span>
                <span class="info-value text-primary">
                    <?= htmlspecialchars($last_visited); ?>
                </span>
            </div>
        </div>

        <!-- VITAL SIGNS BREAKDOWN TABLE -->
        <div class="section-header">
            <i class="bi bi-heart-pulse-fill text-danger"></i> Vital Signs & Physical Measurements
        </div>

        <table class="vitals-table">
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Measured Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Blood Pressure</strong></td>
                    <td><?= $bp_disp ?></td>
                    <td><span class="status-badge <?= $bp_class ?>"><?= $bp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Heart Rate</strong></td>
                    <td><?= $heart_rate_disp ?></td>
                    <td><span class="status-badge <?= $heart_class ?>"><?= $heart_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>SpO2 (Blood Oxygen)</strong></td>
                    <td><?= $spo2_disp ?></td>
                    <td><span class="status-badge <?= $spo2_class ?>"><?= $spo2_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Temperature</strong></td>
                    <td><?= $temp_disp ?></td>
                    <td><span class="status-badge <?= $temp_class ?>"><?= $temp_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Body Weight</strong></td>
                    <td><?= $weight_disp ?></td>
                    <td><span class="status-badge badge-secondary"><?= $weight_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>Height</strong></td>
                    <td><?= $height_disp ?></td>
                    <td><span class="status-badge badge-secondary"><?= $height_status ?></span></td>
                </tr>
                <tr>
                    <td><strong>BMI Score</strong></td>
                    <td><?= $bmi_disp ?></td>
                    <td><span class="status-badge <?= $bmi_class ?>"><?= $bmi_status ?></span></td>
                </tr>
            </tbody>
        </table>

        <!-- CLINICAL DIAGNOSIS / PHYSICIAN NOTES -->
        <div class="section-header">
            <i class="bi bi-journal-medical text-primary"></i> Clinical Observations & Diagnosis
        </div>

        <div class="notes-box" style="min-height: 60px;">
            <em>Physician / Attending Clinician Notes:</em>
        </div>

        <!-- SIGNATURE BLOCK -->
        <div class="signature-section">
            <div class="signature-line">
                Attending Physician Signature
                <div class="text-muted fw-normal" style="font-size: 0.72rem;">License No: __________________</div>
            </div>
            <div class="signature-line">
                Clinic Medical Officer Stamp & Date
                <div class="text-muted fw-normal" style="font-size: 0.72rem;">VitalCore Health Information System</div>
            </div>
        </div>

        <!-- CONFIDENTIALITY FOOTER -->
        <div class="doc-footer">
            CONFIDENTIAL MEDICAL RECORD • FOR AUTHORIZED PERSONNEL ONLY • VITALCORE CLINICAL SYSTEM
        </div>

    </div>
</div>

<script>
const defaultFileName = '<?= $safe_filename; ?>';

// 1. Opens Windows "Save As" File Picker Dialog for PDF
async function saveAsPDF() {
    const element = document.getElementById('document-wrapper');
    
    const pdfBlob = await html2pdf().set({
        margin: [6, 6, 6, 6],
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    }).from(element).output('blob');

    if ('showSaveFilePicker' in window) {
        try {
            const handle = await window.showSaveFilePicker({
                suggestedName: defaultFileName + '.pdf',
                types: [{
                    description: 'PDF Document (*.pdf)',
                    accept: { 'application/pdf': ['.pdf'] }
                }]
            });
            const writable = await handle.createWritable();
            await writable.write(pdfBlob);
            await writable.close();
        } catch (err) {
            if (err.name !== 'AbortError') {
                fallbackDownload(pdfBlob, defaultFileName + '.pdf');
            }
        }
    } else {
        fallbackDownload(pdfBlob, defaultFileName + '.pdf');
    }
}

// 2. Opens Windows "Save As" File Picker Dialog for Word (.doc)
async function saveAsWord() {
    const element = document.getElementById('document-wrapper');
    const clone = element.cloneNode(true);

    const clinicHeader = clone.querySelector('.clinic-header');
    if (clinicHeader) {
        const logoImg = clinicHeader.querySelector('.clinic-logo');
        const logoSrc = logoImg ? logoImg.src : '';
        const textEnd = clinicHeader.querySelector('.text-end');
        const textEndHTML = textEnd ? textEnd.innerHTML : '';

        const headerTable = document.createElement('table');
        headerTable.style.cssText = 'width:100%; border-collapse:collapse; margin-bottom:12px; border-bottom:2px solid #0a49c4; padding-bottom:8px;';
        headerTable.innerHTML = `
            <tr>
                <td style="vertical-align:middle; text-align:left;">
                    <table style="border-collapse:collapse;">
                        <tr>
                            <td style="vertical-align:middle; padding-right:12px;">
                                <table style="border-collapse:collapse; background-color:#f1f5f9; border:1px solid #e2e8f0; border-radius:10px;">
                                    <tr>
                                        <td style="padding:6px; text-align:center; vertical-align:middle;">
                                            <img src="${logoSrc}" width="40" height="40" style="width:40px !important; height:40px !important; display:block; border-radius:6px; object-fit:cover;">
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td style="vertical-align:middle;">
                                <div style="font-size:20px; font-weight:800; color:#0a49c4; line-height:1.1; font-family:'Segoe UI', Tahoma, Arial, sans-serif;">Vital Screening</div>
                                <div style="font-size:11px; color:#64748b; margin-top:2px; font-family:'Segoe UI', Tahoma, Arial, sans-serif;">Clinical Patient Record & Health Assessment</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="vertical-align:middle; text-align:right;">${textEndHTML}</td>
            </tr>
        `;
        clinicHeader.parentNode.replaceChild(headerTable, clinicHeader);
    }

    const infoGrids = clone.querySelectorAll('.info-grid');
    infoGrids.forEach(grid => {
        const items = Array.from(grid.querySelectorAll('.info-item'));
        const table = document.createElement('table');
        table.style.cssText = 'width:100%; border-collapse:collapse; background-color:#f8fafc; border:1px solid #dbe3ef; border-radius:6px; margin-bottom:10px; font-size:11px;';
        
        let currentRow = document.createElement('tr');
        let colCount = 0;

        items.forEach(item => {
            const isFullWidth = item.style.gridColumn && item.style.gridColumn.includes('span 2');
            const td = document.createElement('td');
            td.style.cssText = 'padding:5px 8px; vertical-align:top; font-family:"Segoe UI", Tahoma, Arial, sans-serif;';
            td.innerHTML = item.innerHTML;

            if (isFullWidth) {
                if (colCount === 1) {
                    const emptyTd = document.createElement('td');
                    currentRow.appendChild(emptyTd);
                    table.appendChild(currentRow);
                    currentRow = document.createElement('tr');
                    colCount = 0;
                }
                td.setAttribute('colspan', '2');
                td.style.width = '100%';
                const fullRow = document.createElement('tr');
                fullRow.appendChild(td);
                table.appendChild(fullRow);
            } else {
                td.style.width = '50%';
                currentRow.appendChild(td);
                colCount++;
                if (colCount === 2) {
                    table.appendChild(currentRow);
                    currentRow = document.createElement('tr');
                    colCount = 0;
                }
            }
        });

        if (colCount === 1) {
            const emptyTd = document.createElement('td');
            emptyTd.style.width = '50%';
            currentRow.appendChild(emptyTd);
            table.appendChild(currentRow);
        }

        grid.parentNode.replaceChild(table, grid);
    });

    const badges = clone.querySelectorAll('.status-badge, .doc-title-badge');
    badges.forEach(b => {
        b.style.display = 'inline-block';
        b.style.padding = '3px 8px';
        b.style.borderRadius = '10px';
        b.style.fontSize = '10px';
        b.style.fontWeight = 'bold';
        if (b.classList.contains('badge-success')) {
            b.style.backgroundColor = '#d1e7dd'; b.style.color = '#0f5132';
        } else if (b.classList.contains('badge-danger')) {
            b.style.backgroundColor = '#f8d7da'; b.style.color = '#842029';
        } else if (b.classList.contains('badge-warning')) {
            b.style.backgroundColor = '#fff3cd'; b.style.color = '#664d03';
        } else if (b.classList.contains('badge-info')) {
            b.style.backgroundColor = '#cff4fc'; b.style.color = '#055160';
        } else if (b.classList.contains('badge-secondary')) {
            b.style.backgroundColor = '#e2e3e5'; b.style.color = '#41464b';
        } else if (b.classList.contains('doc-title-badge')) {
            b.style.backgroundColor = '#eff6ff'; b.style.color = '#0a49c4'; b.style.border = '1px solid #bfdbfe'; b.style.padding = '4px 10px'; b.style.borderRadius = '6px';
        }
    });

    const sigSection = clone.querySelector('.signature-section');
    if (sigSection) {
        const sigLines = sigSection.querySelectorAll('.signature-line');
        if (sigLines.length >= 2) {
            const sigTable = document.createElement('table');
            sigTable.style.cssText = 'width:100%; border-collapse:collapse; margin-top:20px;';
            sigTable.innerHTML = `
                <tr>
                    <td style="width:45%; text-align:center; vertical-align:top; border-top:1px solid #64748b; padding-top:4px; font-size:11px; font-weight:bold;">${sigLines[0].innerHTML}</td>
                    <td style="width:10%;"></td>
                    <td style="width:45%; text-align:center; vertical-align:top; border-top:1px solid #64748b; padding-top:4px; font-size:11px; font-weight:bold;">${sigLines[1].innerHTML}</td>
                </tr>
            `;
            sigSection.parentNode.replaceChild(sigTable, sigSection);
        }
    }

    const wordStyles = `
        @page WordSection1 {
            size: 595.3pt 841.9pt;
            margin: 0.35in 0.45in 0.35in 0.45in;
            mso-header-margin: 15pt;
            mso-footer-margin: 15pt;
            mso-paper-source: 0;
        }
        div.WordSection1 { page: WordSection1; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important; font-size: 11px !important; color: #1e293b !important; background: #ffffff !important; margin: 0 !important; padding: 0 !important; }
        .document-wrapper { width: 100% !important; background: #ffffff !important; padding: 0 !important; margin: 0 !important; }
        .section-header { color: #0a49c4 !important; font-weight: bold !important; font-size: 11px !important; margin-top: 8px !important; margin-bottom: 4px !important; }
        .info-label { font-weight: bold !important; color: #475569 !important; }
        .info-value { color: #0f172a !important; font-weight: 600 !important; }
        .vitals-table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 8px !important; font-size: 10.5px !important; }
        .vitals-table th, .vitals-table td { border: 1px solid #dbe3ef !important; padding: 4px 7px !important; }
        .vitals-table th { background-color: #f8fafc !important; color: #475569 !important; font-size: 9.5px !important; text-transform: uppercase !important; }
        .notes-box { border: 1px dashed #cbd5e1 !important; background-color: #f8fafc !important; padding: 6px 10px !important; min-height: 35px !important; margin-bottom: 10px !important; font-size: 10.5px !important; }
        .doc-footer { margin-top: 10px !important; text-align: center !important; font-size: 8.5px !important; color: #94a3b8 !important; border-top: 1px solid #e2e8f0 !important; padding-top: 4px !important; }
    `;

    const sourceHTML = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <title>Vital Screening Record</title>
            <!--[if gte mso 9]>
            <xml>
                <w:WordDocument>
                    <w:View>Print</w:View>
                    <w:Zoom>100</w:Zoom>
                    <w:DoNotOptimizeForBrowser/>
                </w:WordDocument>
            </xml>
            <![endif]-->
            <style>${wordStyles}</style>
        </head>
        <body>
            <div class="WordSection1">${clone.innerHTML}</div>
        </body>
        </html>
    `;

    const wordBlob = new Blob(['\ufeff' + sourceHTML], { type: 'application/msword' });

    if ('showSaveFilePicker' in window) {
        try {
            const handle = await window.showSaveFilePicker({
                suggestedName: defaultFileName + '.doc',
                types: [{ description: 'Word Document (*.doc)', accept: { 'application/msword': ['.doc', '.docx'] } }]
            });
            const writable = await handle.createWritable();
            await writable.write(wordBlob);
            await writable.close();
        } catch (err) {
            if (err.name !== 'AbortError') fallbackDownload(wordBlob, defaultFileName + '.doc');
        }
    } else {
        fallbackDownload(wordBlob, defaultFileName + '.doc');
    }
}

function fallbackDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const savedTheme = localStorage.getItem('staff_theme');
    if (savedTheme === 'dark') {
        if (document.body) {
            document.body.classList.add('dark-mode');
            document.body.setAttribute('data-bs-theme', 'dark');
        }
    }
});
</script>
</body>
</html>