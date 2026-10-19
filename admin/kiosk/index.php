<?php
session_start();

// Security check: redirect if not logged in or not a patient
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../login.php"); // FIX: same path as the Back button / image paths
    exit();
}

$patient_name = $_SESSION['name'] ?? 'Patient';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitalCore Kiosk Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

    <style>
        :root { --vc-primary: #0a49c4; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f4f7fc url('../../img/back.jpg') center center/cover no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .kiosk-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            width: 90vw;
            max-width: 600px;
            margin: auto;
            padding: 20px;
        }
        #panel-vitals.kiosk-card { max-width: 950px; }
        .guide-box {
            background-color: #f8f9fa;
            border-left: 5px solid var(--vc-primary);
            border-radius: 8px;
        }
        .kiosk-wrapper { position: relative; }
        .back-login-btn {
            position: absolute; top: 18px; left: 18px; z-index: 10;
            color: #6c757d; font-size: 50px; text-decoration: none;
            line-height: 1; border: none; background: transparent;
        }
        .back-login-btn:hover { color: #0d6efd; }
        .cancel-measurement-btn {
            position: absolute; top: 18px; left: 18px; z-index: 10;
            color: #dc3545; font-size: 25px; line-height: 1;
            border: none; background: transparent; padding: 0;
        }
        .cancel-measurement-btn:hover { color: #bb2d3b; }
        .step-number {
            width: 28px; height: 28px; background-color: #0d6efd; color: #fff;
            font-weight: bold; font-size: .9rem; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sensor-photo-col {
            flex: 0 0 340px; display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 10px;
        }
        .sensor-photo-col img {
            width: 100%; max-height: 420px; object-fit: cover; border-radius: 16px;
            box-shadow: 0 6px 24px rgba(0,0,0,.18); border: 4px solid #0d6efd;
        }
        .sensor-photo-label {
            margin-top: 10px; font-size: .85rem; color: #64748b;
            font-weight: 600; text-align: center; letter-spacing: .03em;
        }
        .vitals-inner { display: flex; gap: 24px; align-items: center; }
        .vitals-main-col { flex: 1; min-width: 0; }
        @media (max-width: 700px) {
            .vitals-inner { flex-direction: column; }
            .sensor-photo-col { flex: unset; width: 100%; }
            #panel-vitals.kiosk-card { max-width: 600px; }
        }
    </style>
</head>
<body>

<div class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-12">

            <!-- GUIDE PANEL -->
            <div id="panel-guide" class="kiosk-card text-center kiosk-wrapper">
                <a href="../../login.php" class="back-login-btn" title="Back to Login" aria-label="Back to Login">
                    <i class="bi bi-arrow-left"></i>
                </a>

                <div class="d-flex align-items-center justify-content-center gap-2 mb-4 text-primary">
                    <i class="bi bi-heart-pulse-fill fs-2"></i>
                    <span class="fw-bold fs-4">VitalCore Kiosk</span>
                </div>

                <h1 class="fw-bold text-dark mb-1">Welcome, <?php echo htmlspecialchars($patient_name); ?>!</h1>
                <p class="text-secondary mb-4 fs-5">Proceed to Vital Signs Measurement</p>

                <div class="guide-box text-start p-4 mb-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle-fill text-primary me-2"></i>Preparation Guide</h5>
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="step-number">1</span>
                        <p class="text-muted m-0">Please stand flat and steady in front of the kiosk system console.</p>
                    </div>
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="step-number">2</span>
                        <p class="text-muted m-0">Wear the automatic blood pressure monitor cuff snugly around your upper left arm.</p>
                    </div>
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="step-number">3</span>
                        <p class="m-0 fw-semibold text-danger">Remain completely still and avoid speaking to ensure an exact medical measurement is taken.</p>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <span class="step-number">4</span>
                        <p class="text-muted m-0">Once setup is finished, click the action button below to check standard diagnostics.</p>
                    </div>
                </div>

                <button onclick="openCustomLayout()" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow rounded-3">
                    Proceed <i class="bi bi-arrow-right-circle-fill ms-2"></i>
                </button>
            </div>

            <!-- VITALS MEASUREMENT PANEL -->
            <div id="panel-vitals" class="kiosk-card d-none text-center position-relative">
                <button type="button" id="cancel-measurement" class="cancel-measurement-btn"
                        title="Cancel Measurement" aria-label="Cancel Measurement">
                    <i class="bi bi-x-circle"></i>
                </button>

                <div class="mb-3"><span class="badge bg-primary fs-6">VitalCore Measurement</span></div>
                <h2 id="sensorTitle" class="fw-bold mb-3 display-6"></h2>

                <div class="vitals-inner">
                    <!-- LEFT: measurement UI -->
                    <div class="vitals-main-col">
                        <div class="my-3 text-center">
                            <div id="sensorCircle"
                                 class="rounded-circle border border-4 border-primary d-inline-flex align-items-center justify-content-center p-2"
                                 style="width:230px;height:230px;">
                                <div id="countdownIcon" class="w-100 h-100 d-flex align-items-center justify-content-center"></div>
                            </div>
                        </div>
                        <div id="measurementStatus" class="fw-bold text-success fs-5 mt-2"></div>
                    </div>

                    <!-- RIGHT: sensor photo (shown only when the sensor has an image) -->
                    <div class="sensor-photo-col d-none" id="sensorPhotoCol">
                        <img id="sensorPhoto" src="" alt="Sensor guide photo">
                        <div class="sensor-photo-label" id="sensorPhotoLabel"></div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button id="startMeasurementBtn" onclick="beginSequentialLiveProcessing()"
                            class="btn btn-success btn-lg px-5">
                        Start Measurement
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- SERVICE TYPE MODAL -->
<div class="modal fade" id="serviceTypeModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Select Service Type</h5>
            </div>
            <div class="modal-body">
                <select id="serviceType" class="form-select form-select-lg">
                    <option value="">-- Choose Clinic Service --</option>
                    <option value="vital">Vital Screening</option>
                    <option value="prenatal">Prenatal Check-up</option>
                    <option value="family">Family Planning</option>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="saveServiceType()">Continue</button>
            </div>
        </div>
    </div>
</div>

<!-- CUSTOM SENSOR SETUP MODAL -->
<div class="modal fade" id="customSetupModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="customSetupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0 pt-4 px-4">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="customSetupModalLabel">Custom Configuration</h5>
                    <p class="text-muted small mb-0">Deactivate modules for patients with standing difficulties (PWD) or alternate requirements.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-2">
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-height"><i class="bi bi-person-bounding-box text-muted me-2"></i>Height Sensor</label>
                    <input class="form-check-input ms-0" type="checkbox" id="chk-height" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-weight"><i class="bi bi-speedometer2 text-muted me-2"></i>Weight Platform</label>
                    <input class="form-check-input ms-0" type="checkbox" id="chk-weight" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-temp"><i class="bi bi-thermometer-half text-muted me-2"></i>Infrared Temperature</label>
                    <input class="form-check-input ms-0" type="checkbox" id="chk-temp" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-heart"><i class="bi bi-heart-pulse text-muted me-2"></i>Heart Pulse Rate</label>
                    <input class="form-check-input ms-0" type="checkbox" id="chk-heart" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-bp"><i class="bi bi-activity text-muted me-2"></i>Blood Pressure Cuff</label>
                    <input class="form-check-input ms-0" type="checkbox" id="chk-bp" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-spo2"><i class="bi bi-droplet-half text-muted me-2"></i>Oxygen Saturation (SpO2)</label>
                    <input class="form-check-input ms-0" type="checkbox" id="chk-spo2" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom-0 d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-bmi"><i class="bi bi-calculator text-muted me-2"></i>Body Mass Index (BMI)</label>
                    <input class="form-check-input ms-0" type="checkbox" id="chk-bmi" checked>
                </div>
            </div>
            <div class="modal-footer border-top-0 pb-4 px-4 gap-2">
                <button type="button" class="btn btn-light border fw-semibold px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" onclick="saveAndContinue()" class="btn btn-primary fw-bold px-4" data-bs-dismiss="modal">
                    <i class="bi bi-check-circle-fill me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const $ = id => document.getElementById(id);
const sleep = ms => new Promise(r => setTimeout(r, ms));
const guidePanel = $('panel-guide');
const vitalsPanel = $('panel-vitals');
let selectedService = '';

/* ───────────── Setup flow ───────────── */

function openCustomLayout() {
    // FIX: getOrCreateInstance avoids stacking a new Modal instance on every click
    bootstrap.Modal.getOrCreateInstance($('serviceTypeModal')).show();
}

function saveServiceType() {
    selectedService = $('serviceType').value;
    if (selectedService === '') return;

    configureServiceSensors();
    bootstrap.Modal.getOrCreateInstance($('serviceTypeModal')).hide();
    bootstrap.Modal.getOrCreateInstance($('customSetupModal')).show();
}

function saveAndContinue() {
    guidePanel.classList.add('d-none');
    vitalsPanel.classList.remove('d-none');
}

function configureServiceSensors() {
    ['height', 'weight', 'temp', 'heart', 'bp', 'spo2', 'bmi']
        .forEach(k => $('chk-' + k).checked = true);

    // FIX: removed the dead 'immunization' branch (that option doesn't exist in the dropdown)
    if (selectedService === 'prenatal') {
        $('chk-height').checked = true;
    }
    if (selectedService === 'family') {
        $('chk-heart').checked = true;
        $('chk-spo2').checked = true;
    }
}

/* ───────────── UI helpers ───────────── */

const setStatus = html => { $('measurementStatus').innerHTML = html; };
const setIcon   = html => { $('countdownIcon').innerHTML = html; };

const okIcon   = `<i class="bi bi-check-circle-fill text-success" style="font-size:100px;"></i>`;
const failIcon = `<i class="bi bi-x-circle-fill text-danger" style="font-size:100px;"></i>`;
const spinnerWith = svg => `
    <div class="position-relative d-inline-flex align-items-center justify-content-center w-100 h-100">
        <div class="spinner-border text-primary position-absolute w-100 h-100" role="status" style="border-width: 5px;"></div>
        <div class="d-flex align-items-center justify-content-center" style="z-index:1;">${svg}</div>
    </div>`;

function showPhoto(sensor) {
    const col = $('sensorPhotoCol');
    col.classList.toggle('d-none', !sensor.image); // FIX: replaces the inline-style/!important juggling
    if (sensor.image) {
        $('sensorPhoto').src = sensor.image;
        $('sensorPhoto').alt = sensor.title + ' sensor photo';
        $('sensorPhotoLabel').textContent = sensor.title;
    }
}

/* ───────────── Network helper (with timeout) ───────────── */

// FIX: every request now has a timeout so a dead sensor can't freeze the kiosk forever
async function fetchJson(url, timeoutMs) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
        const res = await fetch(url, { method: 'GET', cache: 'no-store', signal: ctrl.signal });
        if (!res.ok) throw new Error('HTTP ' + res.status + ' ' + res.statusText);
        const text = await res.text();
        try { return JSON.parse(text); }
        catch (e) {
            // Tolerate PHP warnings/notices printed before or after the JSON
            const start = text.indexOf('{'), end = text.lastIndexOf('}');
            if (start !== -1 && end > start) {
                try { return JSON.parse(text.slice(start, end + 1)); } catch (_) {}
            }
            throw new Error('Invalid JSON response: ' + text);
        }
    } finally {
        clearTimeout(timer);
    }
}

/* ───────────── Result handlers (return HTML on success, null on failure) ───────────── */

function clearBp(valid) {
    ['sys', 'dia', 'pr'].forEach(k => sessionStorage.removeItem('current_bp_' + k));
    sessionStorage.setItem('current_bp_valid', valid ? '1' : '0');
}

function handleBp(data) {
    clearBp(false); // never leave an old BP value behind
    if (!(data.success === true && data.status === 0)) return null;

    const output = Array.isArray(data.output) ? data.output.join('\n') : '';
    const m = output.match(/SYS\s*=\s*(\d+)\s*mmHg[\s\S]*?DIA\s*=\s*(\d+)\s*mmHg[\s\S]*?PR\s*=\s*(\d+)\s*bpm/i);
    if (!m) return null;

    const [sys, dia, pr] = [m[1], m[2], m[3]].map(Number);
    sessionStorage.setItem('current_bp_sys', sys);
    sessionStorage.setItem('current_bp_dia', dia);
    sessionStorage.setItem('current_bp_pr', pr);
    sessionStorage.setItem('current_bp_valid', '1');
    return `✓ Blood Pressure Completed<br><small>SYS ${sys} / DIA ${dia} mmHg — PR ${pr} bpm</small>`;
}

function handleHeight(data) {
    const ok =
        data.success === true  ||
        data.success === 'true' ||
        data.success === 1;

    let raw =
        data.height     ??
        data.height_cm  ??
        data.distance   ??
        data.value;

    let h = Number(raw);

    if (!Number.isFinite(h) && Array.isArray(data.output)) {
        const heightLine = data.output.find(line =>
            /Height:\s*[\d.]+\s*cm/i.test(String(line))
        );
        if (heightLine) {
            const match = String(heightLine).match(/Height:\s*([\d.]+)\s*cm/i);
            if (match) h = Number(match[1]);
        }
    }

    // FIX: status can be integer 0, string "0", or missing entirely
    const statusOk =
        data.status === 0    ||
        data.status === '0'  ||
        data.status === null ||
        data.status === undefined;

    if (ok && statusOk && Number.isFinite(h) && h > 30 && h < 300) {
        console.log('Height measurement successful:', h);
        return `✓ Height Completed<br><small>${h.toFixed(1)} cm</small>`;
    }

    // Log exactly why it failed so you can debug further
    console.warn('Height rejected — ok:', ok, 'statusOk:', statusOk, 'h:', h, 'raw data:', data);
    return null;
}


function handleWeight(data) {

    /*
     * Always remove any previous weight first.
     */
    sessionStorage.removeItem("current_weight");

    const w = Number(data.weight);

    if (
        data.success === true &&
        data.status === "READY" &&
        Number.isFinite(w) &&
        w >= 0 &&
        w < 500
    ) {

        /*
         * Save the NEW weight for measure-result.php
         */
        sessionStorage.setItem(
            "current_weight",
            w.toFixed(1)
        );

        console.log(
            "NEW WEIGHT SAVED:",
            w.toFixed(1),
            "kg"
        );

        return `
            ✓ Weight Completed<br>
            <small>${w.toFixed(1)} kg</small>
        `;
    }

    console.log(
        "Weight not ready:",
        data
    );

    return null;
}

const handleDefault = data => data.success === true ? '✓ Completed' : null;

/* ───────────── Sensors ───────────── */

const sensors = [
    {
        title: "Blood Pressure Measurement",
        checkIds: ["chk-bp"],
        image: "../../img/sensor_bp.jpg",
        guide: "Wear the cuff on your LEFT arm. The cuff is located on the LEFT side of the kiosk.",
        svg: `<svg viewBox="0 0 240 240" width="180" height="180">
            <style>
                .kiosk-body { fill: #ffffff; stroke: #0a49c4; stroke-width: 2.5; }
                .screen-blue { fill: #0d6efd; }
                .person { fill: #0a49c4; }
                .skin-arm { fill: #0d6efd; }
                .bp-cuff-outer { fill: #1e293b; stroke: #0a49c4; stroke-width: 2.5; }
                .cuff-wrap { fill: #2563eb; stroke: #ffffff; stroke-width: 1.5; }
                .pulse-squeeze { animation: cuffSqueeze 1.8s ease-in-out infinite; transform-box: fill-box; transform-origin: center; }
                .arm-enter { animation: armSlide 2s cubic-bezier(0.4, 0, 0.2, 1) infinite; }
                @keyframes cuffSqueeze {
                    0%, 100% { transform: scale(1); opacity: 0.6; stroke-width: 2; }
                    50% { transform: scale(1.08); opacity: 1; stroke-width: 5; stroke: #dc3545; }
                }
                @keyframes armSlide {
                    0% { transform: translateX(22px); opacity: 0.4; }
                    60%, 100% { transform: translateX(0); opacity: 1; }
                }
            </style>
            <rect x="30" y="195" width="180" height="14" rx="4" fill="#cbd5e1" stroke="#64748b" stroke-width="2"/>
            <rect x="95" y="65" width="80" height="132" rx="10" class="kiosk-body"/>
            <polygon points="103,75 167,75 162,103 108,103" class="screen-blue"/>
            <rect x="70" y="107" width="45" height="42" rx="8" class="bp-cuff-outer"/>
            <ellipse cx="70" cy="128" rx="8" ry="21" fill="#0f172a" stroke="#0a49c4" stroke-width="2"/>
            <rect x="67" y="103" width="50" height="50" rx="12" fill="none" stroke="#2563eb" stroke-width="3" class="pulse-squeeze"/>
            <g class="arm-enter">
                <circle cx="25" cy="65" r="16" class="person"/>
                <rect x="12" y="85" width="26" height="54" rx="8" class="person"/>
                <rect x="13" y="137" width="11" height="60" rx="5" class="person"/>
                <rect x="26" y="137" width="11" height="60" rx="5" class="person"/>
                <rect x="33" y="117" width="60" height="16" rx="8" class="skin-arm"/>
                <rect x="51" y="113" width="30" height="24" rx="5" class="cuff-wrap"/>
                <rect x="85" y="119" width="22" height="12" rx="6" class="skin-arm"/>
            </g>
        </svg>`,
        prepSeconds: 0,        // cuff inflates on request, no countdown needed
        timeoutMs: 90000,      // a BP cycle is slow
        handle: handleBp,
        endpoint: "http://192.168.1.101/measure_bp.php"
    },
    {
        title: "Height Measurement",
        checkIds: ["chk-height"],
        guide: "Stand straight in front of the kiosk. Look forward and do not move.",
        svg: `<svg viewBox="0 0 240 240" width="180" height="180">
            <style>
                .kiosk-body { fill: #ffffff; stroke: #0a49c4; stroke-width: 2.5; }
                .screen-blue { fill: #0d6efd; }
                .person { fill: #0a49c4; }
                .height-bar { stroke: #0a49c4; stroke-width: 4; stroke-linecap: round; }
                .height-sensor { fill: #10b981; }
                .scan-beam { stroke: #10b981; stroke-width: 3; stroke-dasharray: 5 4; animation: beamPulse 1.8s ease-in-out infinite; }
                @keyframes beamPulse { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }
            </style>
            <rect x="40" y="200" width="160" height="15" rx="4" fill="#cbd5e1" stroke="#64748b" stroke-width="2"/>
            <rect x="115" y="80" width="75" height="122" rx="10" class="kiosk-body"/>
            <polygon points="123,90 180,90 175,118 128,118" class="screen-blue"/>
            <line x1="150" y1="80" x2="150" y2="18" class="height-bar"/>
            <line x1="150" y1="18" x2="80" y2="18" class="height-bar"/>
            <rect x="65" y="10" width="30" height="14" rx="4" class="height-sensor"/>
            <line x1="80" y1="24" x2="80" y2="52" class="scan-beam"/>
            <circle cx="80" cy="68" r="16" class="person"/>
            <rect x="67" y="88" width="26" height="54" rx="8" class="person"/>
            <rect x="68" y="140" width="11" height="62" rx="5" class="person"/>
            <rect x="81" y="140" width="11" height="62" rx="5" class="person"/>
        </svg>`,
        prepSeconds: 10,
        timeoutMs: 15000,
        pollSeconds: 10,  
        handle: handleHeight,
        endpoint: "http://192.168.1.101/measure_height.php"
    },
    {
        title: "Weight Measurement",
        checkIds: ["chk-weight"],
        guide: "Stand on the weighing platform and remain still.",
        svg: `<svg viewBox="0 0 240 240" width="180" height="180">
            <style>
                .kiosk-body { fill: #ffffff; stroke: #0a49c4; stroke-width: 2.5; }
                .screen-blue { fill: #0d6efd; }
                .person { fill: #0a49c4; }
                .scale-platform { fill: #38bdf8; stroke: #0284c7; stroke-width: 3; animation: scaleGlow 1.8s ease-in-out infinite alternate; }
                .feet-print { fill: #ffffff; opacity: 0.9; }
                .person-down { animation: feetStand 1.6s ease-in-out infinite alternate; transform-box: fill-box; transform-origin: center; }
                @keyframes scaleGlow { 0% { fill: #e0f2fe; } 100% { fill: #7dd3fc; } }
                @keyframes feetStand { 0% { transform: translateY(-6px); } 100% { transform: translateY(0); } }
            </style>
            <rect x="115" y="80" width="75" height="122" rx="10" class="kiosk-body"/>
            <polygon points="123,90 180,90 175,118 128,118" class="screen-blue"/>
            <rect x="40" y="195" width="125" height="20" rx="5" class="scale-platform"/>
            <ellipse cx="80" cy="205" rx="8" ry="4" class="feet-print"/>
            <ellipse cx="110" cy="205" rx="8" ry="4" class="feet-print"/>
            <g class="person-down">
                <circle cx="95" cy="65" r="16" class="person"/>
                <rect x="82" y="85" width="26" height="54" rx="8" class="person"/>
                <rect x="83" y="137" width="11" height="60" rx="5" class="person"/>
                <rect x="96" y="137" width="11" height="60" rx="5" class="person"/>
            </g>
        </svg>`,
        prepSeconds: 10,
        timeoutMs: 5000,
        // FIX: measure_weight.php returns success:false immediately while the scale is still
        // settling (status CONNECTED / NO_DATA). Keep asking for up to 20s instead of giving up
        // after a single request.
        pollSeconds: 20,
        handle: handleWeight,
        endpoint: "http://192.168.1.101/measure_weight.php"
    },
    {
        title: "Temperature Measurement",
        checkIds: ["chk-temp"],
        image: "../../img/sensor_temp.jpg",
        guide: "Place your RIGHT hand near the temperature sensor located on the RIGHT side of the monitor.",
        svg: `<svg viewBox="0 0 240 240" width="180" height="180">
            <style>
                .kiosk-body { fill: #ffffff; stroke: #0a49c4; stroke-width: 2.5; }
                .screen-blue { fill: #0d6efd; }
                .person { fill: #0a49c4; }
                .temp-sensor { fill: #ef4444; }
                .wave-radiate { stroke: #ef4444; stroke-width: 3; stroke-linecap: round; fill: none; animation: wavePulse 1.4s linear infinite; }
                .hand-move { animation: handApproach 1.8s ease-in-out infinite alternate; }
                @keyframes wavePulse { 0% { opacity: 0.2; transform: scale(0.85); } 50% { opacity: 1; } 100% { opacity: 0; transform: scale(1.2); } }
                @keyframes handApproach { 0% { transform: translateX(-15px); } 100% { transform: translateX(0); } }
            </style>
            <rect x="35" y="200" width="140" height="15" rx="4" fill="#cbd5e1" stroke="#64748b" stroke-width="2"/>
            <rect x="35" y="80" width="75" height="122" rx="10" class="kiosk-body"/>
            <polygon points="43,90 100,90 95,118 48,118" class="screen-blue"/>
            <circle cx="110" cy="115" r="7" class="temp-sensor"/>
            <path d="M118,103 C127,110 127,120 118,127" class="wave-radiate"/>
            <path d="M125,97 C137,108 137,122 125,133" class="wave-radiate" style="animation-delay: 0.3s"/>
            <g class="hand-move">
                <circle cx="160" cy="72" r="16" class="person"/>
                <rect x="147" y="92" width="26" height="52" rx="8" class="person"/>
                <rect x="148" y="142" width="11" height="60" rx="5" class="person"/>
                <rect x="161" y="142" width="11" height="60" rx="5" class="person"/>
                <rect x="110" y="110" width="45" height="11" rx="5" class="person"/>
            </g>
        </svg>`,
        prepSeconds: 10,
        timeoutMs: 20000,
        handle: handleDefault,
        endpoint: "http://192.168.1.101/measure_temperature.php"
    },
    {
        title: "Heart Rate & SpO₂ Measurement",
        // FIX: runs if EITHER heart rate or SpO2 is enabled (replaces the confusing nested if/else)
        checkIds: ["chk-spo2", "chk-heart"],
        image: "../../img/Heart_SpO2.jpg",
        guide: "Place your index finger on the sensor and keep still.",
        svg: `<svg viewBox="0 0 240 240" width="180" height="180">
            <style>
                .kiosk-body { fill: #ffffff; stroke: #0a49c4; stroke-width: 2.5; }
                .screen-blue { fill: #0d6efd; }
                .person { fill: #0a49c4; }
                .sensor-box { fill: #1e293b; stroke: #ef4444; stroke-width: 2; }
                .sensor-light { fill: #ef4444; animation: redPulse 1.2s ease-in-out infinite; }
                .finger-in { animation: fingerSlide 1.8s cubic-bezier(0.4, 0, 0.2, 1) infinite; }
                @keyframes redPulse { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }
                @keyframes fingerSlide { 0% { transform: translateX(18px); opacity: 0.5; } 60%, 100% { transform: translateX(0); opacity: 1; } }
            </style>
            <rect x="35" y="200" width="140" height="15" rx="4" fill="#cbd5e1" stroke="#64748b" stroke-width="2"/>
            <rect x="35" y="80" width="75" height="122" rx="10" class="kiosk-body"/>
            <polygon points="43,90 100,90 95,118 48,118" class="screen-blue"/>
            <rect x="108" y="105" width="16" height="28" rx="4" class="sensor-box"/>
            <circle cx="116" cy="119" r="5" class="sensor-light"/>
            <g class="finger-in">
                <circle cx="170" cy="72" r="16" class="person"/>
                <rect x="157" y="92" width="26" height="52" rx="8" class="person"/>
                <rect x="158" y="142" width="11" height="60" rx="5" class="person"/>
                <rect x="171" y="142" width="11" height="60" rx="5" class="person"/>
                <rect x="115" y="112" width="50" height="11" rx="5" class="person"/>
            </g>
        </svg>`,
        prepSeconds: 10,
        timeoutMs: 20000,
        handle: handleDefault,
        endpoint: "http://192.168.1.101/measure_max30102.php"
    }
];

/* ───────────── Run one sensor ───────────── */

async function runSensor(sensor) {
    $('sensorTitle').innerText = sensor.title;
    showPhoto(sensor);
    setIcon(spinnerWith(sensor.svg));

    // FIX: the guide text was never shown and the "countdown" had no visible numbers
    for (let r = sensor.prepSeconds; r > 0; r--) {
        setStatus(`${sensor.guide}<br><small class="text-muted">Starting in ${r}s</small>`);
        await sleep(1000);
    }

    setStatus(`Measuring...<br><small class="text-muted">${sensor.guide}</small>`);

    try {
        const deadline = Date.now() + (sensor.pollSeconds || 0) * 1000;
        let result = null;

        while (true) {
            const data = await fetchJson(sensor.endpoint, sensor.timeoutMs);
            console.log(sensor.title + ' response:', data);
            result = sensor.handle(data);
            if (result || Date.now() >= deadline) break;
            await sleep(1000);
        }

        if (result) {
            setStatus(result);
            setIcon(okIcon);
        } else {
            setStatus(sensor.title + ' Failed');
            setIcon(failIcon);
        }
    } catch (err) {
        console.error(sensor.title + ' request error:', err);
        if (sensor.handle === handleBp) clearBp(false);
        setStatus(sensor.title + ' Error');
        setIcon(failIcon);
    }

    await sleep(1500); // let the patient read the result
}

/* ───────────── Main sequence ───────────── */

async function beginSequentialLiveProcessing() {

    sessionStorage.removeItem("current_weight");

    const startBtn = $('startMeasurementBtn');

    const toRun = sensors.filter(s => s.checkIds.some(id => $(id).checked));
    if (toRun.length === 0) {
        setStatus('<span class="text-danger">No sensors are enabled. Cancel and select at least one.</span>');
        return;
    }

    startBtn.style.display = 'none';

    for (const sensor of toRun) {
        await runSensor(sensor);
    }

    const checked = k => $('chk-' + k).checked;
    const activeSensors = {
        height: checked('height') ? 1 : 0,
        weight: checked('weight') ? 1 : 0,
        temp:   checked('temp')   ? 1 : 0,
        heart:  checked('heart')  ? 1 : 0,
        bp:     checked('bp')     ? 1 : 0,
        spo2:   checked('spo2')   ? 1 : 0,
        // FIX: BMI is impossible without both height and weight
        bmi:    (checked('bmi') && checked('height') && checked('weight')) ? 1 : 0
    };

    /*
 * =========================================================
 * SAVE WEIGHT RESULT
 * =========================================================
 *
 * The continuous weight monitor gives us the weight through
 * sessionStorage. Save that NEW weight into weight_readings
 * before opening measure-result.php.
 */

if (checked('weight')) {

    const currentWeight =
        sessionStorage.getItem('current_weight');

    console.log(
        "CURRENT WEIGHT BEFORE SAVE:",
        currentWeight
    );

    if (
        currentWeight !== null &&
        currentWeight !== '' &&
        Number.isFinite(Number(currentWeight)) &&
        Number(currentWeight) >= 0 &&
        Number(currentWeight) < 500
    ) {

        try {

            const weightResponse = await fetch(
                'save_weight.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        weight: Number(currentWeight)
                    })
                }
            );

            if (!weightResponse.ok) {
                throw new Error(
                    'HTTP ' +
                    weightResponse.status
                );
            }

            const weightText =
                await weightResponse.text();

            console.log(
                "save_weight.php response:",
                weightText
            );

        } catch (err) {

            console.error(
                "Failed to save weight:",
                err
            );

            $('sensorTitle').innerText = '';
            setIcon(failIcon);

            setStatus(
                '<span class="text-danger">' +
                'Could not save the weight measurement. ' +
                'Please inform the staff.' +
                '</span>'
            );

            return;
        }

    } else {

        console.error(
            "No valid current weight found."
        );

        $('sensorTitle').innerText = '';
        setIcon(failIcon);

        setStatus(
            '<span class="text-danger">' +
            'Weight measurement was not received. ' +
            'Please repeat the measurement.' +
            '</span>'
        );

        return;
    }
}


/*
 * =========================================================
 * SAVE SERVICE CONFIGURATION
 * =========================================================
 */

try {

    const res = await fetch(
        'save_service.php',
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                service_type: selectedService,
                active_sensors: activeSensors
            })
        }
    );

    if (!res.ok) {
        throw new Error(
            'HTTP ' + res.status
        );
    }

} catch (err) {

    console.error(
        'save_service.php failed:',
        err
    );

    $('sensorTitle').innerText = '';
    setIcon(failIcon);

    setStatus(
        '<span class="text-danger">' +
        'Could not save your results. ' +
        'Please inform the staff.' +
        '</span>'
    );

    return;
}


/*
 * =========================================================
 * OPEN RESULT PAGE
 * =========================================================
 */

window.location.href = "measure-result.php";
}

$('cancel-measurement').addEventListener('click', () => window.location.reload());
</script>
</body>
</html>
