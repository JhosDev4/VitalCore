<?php
session_start();

// Security Check: Redirect if not logged in or not a patient
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$patient_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Patient';
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
        :root {
            --vc-primary: #0a49c4;
            --vc-success: #198754;
            --vc-bg: #f4f7fc;
        }
        body {
            background-color: var(--vc-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background:url('../../img/back.jpg') center center/cover no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
       .kiosk-card{
            background:#fff;
            border-radius:20px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);

            width:90vw;
            max-width:900px;

            margin:auto;
            padding:20px;
        }
        .guide-box {
            background-color: #f8f9fa;
            border-left: 5px solid var(--vc-primary);
            border-radius: 8px;
        }
        .step-number {
            width: 28px;
            height: 28px;
            background-color: #0d6efd;
            color: #ffffff;
            font-weight: bold;
            font-size: 0.9rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        /* Vital Matrix Item Row Design Layouts */
        .vital-item {
            padding: 16px 20px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.5s ease-in-out;
        }
        .vital-item:last-child {
            border-bottom: none;
        }
        .status-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 50px;
            transition: all 0.4s ease;
        }
        
        /* Interactive Animation Variable State Modifiers */
        .vital-item.measuring-now {
            background-color: #f0f4fe !important;
            border-left: 4px solid var(--vc-primary);
        }
        .vital-item.measurement-done {
            background-color: #f0fdf4 !important;
            color: #14532d;
        }
        .vital-item.disabled-vital {
            opacity: 0.35;
            background-color: #f8f9fa;
        }

        /* Success Check Pop Out Keyframe Animation */
        .pop-icon {
            display: inline-block;
            font-size: 1.25rem;
            animation: iconPop 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        @keyframes iconPop {
            0% { transform: scale(0); opacity: 0; }
            70% { transform: scale(1.3); }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes pulse {
            0%{
                transform:scale(1);
            }

            50%{
                transform:scale(1.15);
            }

            100%{
                transform:scale(1);
            }
        }

        .sensor-icon{
            animation:pulse 1s infinite;
        }

        #countdown{
            transition:0.3s;
        }

        .animation-container{
            height:250px;
            display:flex;
            justify-content:center;
            align-items:center;
            margin-bottom:20px;
        }

       .guide-image{
            width:90%;
            max-width:700px;

            height:auto;
            max-height:350px;

            object-fit:contain;
            display:block;
            margin:auto;

            animation:float 2s ease-in-out infinite;
        }

        @keyframes float{
            0%{
                transform:translateY(0px);
            }
            50%{
                transform:translateY(-10px);
            }
            100%{
                transform:translateY(0px);
            }
        }

        .bp-pulse{
            animation:bpPulse 1.5s infinite;
        }

        @keyframes bpPulse{
            0%{
                transform:scale(1);
            }
            50%{
                transform:scale(1.08);
            }
            100%{
                transform:scale(1);
            }
        }
    </style>
</head>
<body>

<div class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-12">
    <div id="panel-guide" class="kiosk-card text-center">
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
                <p class="text-muted m-0 fw-semibold text-danger">Remain completely still and avoid speaking to ensure an exact medical measurement is taken.</p>
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

    <div id="panel-vitals" class="kiosk-card d-none text-center">

        <div class="mb-3">
            <span class="badge bg-primary fs-6">
                VitalCore Measurement
            </span>
        </div>

        <h2 id="sensorTitle" class="fw-bold mb-3 display-6">
            Height Measurement
        </h2>

       <div class="text-center mb-4">
            <div id="sensorAnimation"></div>
        </div>

        <div class="my-2">
           <div class="position-relative d-inline-block">

              <div class="text-center my-2">

                    <div
                        id="sensorCircle"
                        class="rounded-circle border border-4 border-primary
                            d-inline-flex align-items-center justify-content-center"
                        style="width:110px;height:110px;" style="font-size:45px;color:#0d6efd;">

                        <div id="countdownIcon"></div>

                    </div>

                </div>
            </div>

            <div class="text-muted">
                Please follow the instruction above
            </div>
        </div>

        <div id="measurementStatus"
            class="fw-bold text-success">
        </div>

       <div class="text-center mt-4">
            <button
                id="startMeasurementBtn"
                onclick="beginSequentialLiveProcessing()"
                class="btn btn-success btn-lg px-5">
                Start Measurement

            </button>
        </div>

    </div>

</div>

<!-- SERVICE TYPE MODAL -->
<div class="modal fade" id="serviceTypeModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    Select Service Type
                </h5>
            </div>

            <div class="modal-body">

                <label class="form-label fw-semibold">
                    Choose Clinic Service
                </label>

                <select id="serviceType" class="form-select form-select-lg">
                    <option value="">-- Select Service --</option>
                    <option value="vital">Vital Screening</option>
                    <option value="prenatal">Prenatal Check-up</option>
                    <option value="immunization">Child Immunization</option>
                    <option value="family">Family Planning</option>
                </select>

            </div>

            <div class="modal-footer">
                <button
                    class="btn btn-primary"
                    onclick="saveServiceType()">
                    Continue
                </button>
            </div>
        </div>
    </div>
</div>

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
                    <label class="form-check-label fw-semibold text-dark" for="chk-height">
                        <i class="bi bi-person-bounding-box text-muted me-2"></i>Height Sensor
                    </label>
                    <input class="form-check-input ms-0 target-checkbox" type="checkbox" id="chk-height" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-weight">
                        <i class="bi bi-speedometer2 text-muted me-2"></i>Weight Platform
                    </label>
                    <input class="form-check-input ms-0 target-checkbox" type="checkbox" id="chk-weight" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-temp">
                        <i class="bi bi-thermometer-half text-muted me-2"></i>Infrared Temperature
                    </label>
                    <input class="form-check-input ms-0 target-checkbox" type="checkbox" id="chk-temp" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-heart">
                        <i class="bi bi-heart-pulse text-muted me-2"></i>Heart Pulse Rate
                    </label>
                    <input class="form-check-input ms-0 target-checkbox" type="checkbox" id="chk-heart" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-bp">
                        <i class="bi bi-activity text-muted me-2"></i>Blood Pressure Cuff
                    </label>
                    <input class="form-check-input ms-0 target-checkbox" type="checkbox" id="chk-bp" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-spo2">
                        <i class="bi bi-droplet-half text-muted me-2"></i>Oxygen Saturation (SpO2)
                    </label>
                    <input class="form-check-input ms-0 target-checkbox" type="checkbox" id="chk-spo2" checked>
                </div>
                <div class="form-check form-switch p-3 border-bottom-0 d-flex justify-content-between align-items-center ps-0">
                    <label class="form-check-label fw-semibold text-dark" for="chk-bmi">
                        <i class="bi bi-calculator text-muted me-2"></i>Body Mass Index (BMI)
                    </label>
                    <input class="form-check-input ms-0 target-checkbox" type="checkbox" id="chk-bmi" checked>
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
    const guidePanel = document.getElementById('panel-guide');
    const vitalsPanel = document.getElementById('panel-vitals');

    let sequenceQueue = ['height', 'weight', 'temp', 'heart', 'bp', 'spo2', 'bmi'];

    // Capture modular user preference array updates

    function commitCustomLayoutChanges() {
        console.log("Custom sensor configuration saved.");
    }

    function openCustomLayout(){

        const modal = new bootstrap.Modal(
            document.getElementById('serviceTypeModal')
        );

        modal.show();
    }

    function saveServiceType(){

        selectedService =
            document.getElementById('serviceType').value;

        if(selectedService === ''){

            alert('Please select a service type.');
            return;
        }

        configureServiceSensors();

        bootstrap.Modal.getInstance(
            document.getElementById('serviceTypeModal')
        ).hide();

        const customModal = new bootstrap.Modal(
            document.getElementById('customSetupModal')
        );

        customModal.show();
    }

    function saveAndContinue(){

        commitCustomLayoutChanges();

        guidePanel.classList.add('d-none');
        vitalsPanel.classList.remove('d-none');
    }

    function configureServiceSensors(){

    // Enable everything first

    document.getElementById('chk-height').checked = true;
    document.getElementById('chk-weight').checked = true;
    document.getElementById('chk-temp').checked = true;
    document.getElementById('chk-heart').checked = true;
    document.getElementById('chk-bp').checked = true;
    document.getElementById('chk-spo2').checked = true;
    document.getElementById('chk-bmi').checked = true;

    // PRENATAL
    if(selectedService === 'prenatal'){

        document.getElementById('chk-height').checked = false;
    }

    // CHILD IMMUNIZATION
    if(selectedService === 'immunization'){

        document.getElementById('chk-heart').checked = false;
        document.getElementById('chk-spo2').checked = false;
        document.getElementById('chk-bp').checked = false;
    }

    // FAMILY PLANNING
    if(selectedService === 'family'){

        document.getElementById('chk-heart').checked = false;
        document.getElementById('chk-spo2').checked = false;
    }
}

    // Core Animation Engine Loop: Evaluates active elements sequentially every 5 seconds
    const sensors = [

        {
            title:"Height Measurement",
            guide:"Stand straight in front of the kiosk. Look forward and do not move.",
            image:"../../assets/guide/height.png",
            icon:"bi-person-standing",
            seconds:5,
            endpoint:"http://192.168.1.101/measure_all.php"
        },

        {
            title:"Weight Measurement",
            guide:"Stand on the weighing platform and remain still.",
            image:"../assets/guide/weight.png",
            icon:"bi-speedometer2",
            seconds:5,
            endpoint:"http://192.168.1.101/measure_all.php"
        },

        {
            title:"Temperature Measurement",
            guide:"Place your RIGHT hand near the temperature sensor located on the RIGHT side of the monitor.",
            image:"../assets/guide/temperature.png",
            icon:"bi-thermometer-half",
            seconds:5,
            endpoint:"http://192.168.1.101/measure_all.php"
        },

        {
            title:"Heart Rate & SpO₂ Measurement",
            guide:"Place your index finger on the sensor and keep still.",
            image:"../assets/guide/spo2.png",
            icon:"bi-heart-pulse",
            seconds:15,
            endpoint:"http://192.168.1.101/measure_all.php"
        },

        {
            title:"Blood Pressure Measurement",
            guide:"Wear the cuff on your LEFT arm. The cuff is located on the LEFT side of the kiosk.",
            image:"../assets/guide/bp.png",
            icon:"bi-activity",
            seconds:60,
            endpoint:"http://192.168.1.101/measure_all.php"
        },

        ];

        async function beginSequentialLiveProcessing(){

        document.getElementById(
            'startMeasurementBtn'
        ).style.display = 'none';

       for(let sensor of sensors){

            const key = sensor.title.toLowerCase();

            if(
                key.includes('height') && !document.getElementById('chk-height').checked
            ) continue;

            if(
                key.includes('weight') && !document.getElementById('chk-weight').checked
            ) continue;

            if(
                key.includes('temperature') && !document.getElementById('chk-temp').checked
            ) continue;

            if(
                key.includes('heart') && !document.getElementById('chk-heart').checked
            ) continue;

            if(
                key.includes('blood') && !document.getElementById('chk-bp').checked
            ) continue;

            if(
                key.includes('spo') && !document.getElementById('chk-spo2').checked
            ) continue;

            await runSensor(sensor);
        }

         // Save selected service type to PHP session

        await fetch('save_service.php', {
            method:'POST',
            headers:{
                'Content-Type':'application/x-www-form-urlencoded'
            },
            body:'service_type=' + encodeURIComponent(selectedService)
        });

        // Then go to result page

        window.location.href = "measure-result.php";
    }

    async function runSensor(sensor){

    document.getElementById("sensorTitle").innerText =
        sensor.title;

    document.getElementById("sensorAnimation").innerHTML =
`
        <img
        src="${sensor.image}"
        class="img-fluid rounded shadow-sm guide-image"
        alt="Guide">
        `;

    document.getElementById("measurementStatus").innerHTML="";

    let remaining = sensor.seconds;

    document.getElementById("countdownIcon").innerHTML =
    `
    <i class="bi ${sensor.icon}"
    style="font-size:70px;color:#0d6efd;">
    </i>
    `;

    return new Promise(resolve=>{

        const timer = setInterval(()=>{

            remaining--;

            if(remaining<=0){

                clearInterval(timer);

                document.getElementById(
                    "measurementStatus"
                ).innerHTML =
                "Measuring...";

                document.getElementById("countdownIcon").innerHTML =
                `
                <div class="spinner-border text-primary"
                    style="width:70px;height:70px;">
                </div>
                `;

               fetch(sensor.endpoint)
                .then(res => res.text())
                .then(data => {

                    console.log("Response:", data);

                    if(data.toLowerCase().includes("success")){

                        document.getElementById("measurementStatus").innerHTML =
                        "✓ Completed";

                        document.getElementById("countdownIcon").innerHTML =
                        `<i class="bi bi-check-circle-fill text-success"
                            style="font-size:80px;"></i>`;

                    }else{

                        document.getElementById("measurementStatus").innerHTML =
                        "Measurement Failed";

                        document.getElementById("countdownIcon").innerHTML =
                        `<i class="bi bi-x-circle-fill text-danger"
                            style="font-size:80px;"></i>`;
                    }

                    setTimeout(resolve,1500);

                })
                .catch(err=>{

                    console.error(err);

                    document.getElementById("measurementStatus").innerHTML =
                    "Measurement Error";

                    setTimeout(resolve,1500);

                });

            }

        },1000);

    });
}
</script>
</body>
</html>