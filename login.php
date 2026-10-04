<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitalCore Login</title>

    <!-- Google Font & Bootstrap -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="icon" href="img/logo.jpg">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('img/vitalcore-bck.jpg') center center/cover no-repeat;
            padding: 20px;
        }

        .overlay {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .login-card {
            width: 1000px;
            max-width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            padding: 40px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .left-panel {
            flex: 1;
        }

        .right-panel {
            width: 380px;
        }

        .divider {
            width: 1px;
            height: 480px;
            background: rgba(255, 255, 255, 0.2);
        }

        .logo {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #fff;
        }

        .brand-name {
            color: #fff;
            font-size: 3.5rem;
            font-weight: 700;
            margin-top: 10px;
            line-height: 1.1;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.15rem;
        }

        .kiosk-label {
            color: #fff;
            font-weight: 600;
            font-size: 1.3rem;
            margin-bottom: 8px;
            display: block;
        }

        .keypad-input {
            height: 70px;
            font-size: 2.2rem;
            font-weight: 600;
            letter-spacing: 6px;
            text-align: center;
            border-radius: 15px;
            background: #fff !important;
            border: none;
        }

        .btn-kiosk {
            height: 65px;
            font-size: 1.5rem;
            font-weight: 600;
            border-radius: 15px;
        }

        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .keypad-grid button {
            height: 85px;
            border: none;
            border-radius: 20px;
            font-size: 2rem;
            font-weight: bold;
            background: #fff;
            color: #333;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.1s, background-color 0.2s;
        }

        .keypad-grid button:active {
            transform: scale(0.93);
        }

        .clear-btn {
            background: #ff4d5a !important;
            color: white !important;
        }

        .back-btn {
            background: #ffd84d !important;
            color: #333 !important;
        }

        .copyright {
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.85);
            font-size: 1rem;
        }

        @media(max-width: 900px) {
            .login-card {
                flex-direction: column;
                padding: 25px;
            }

            .divider {
                display: none;
            }

            .right-panel {
                width: 100%;
            }

            .brand-name {
                font-size: 2.5rem;
            }
        }
    </style>
</head>

<body>

<div class="overlay">

    <!-- MAIN KIOSK LOGIN CARD -->
    <form class="login-card" method="post" action="req/login.php">

        <!-- LEFT PANEL -->
        <div class="left-panel w-100">
            <div class="text-center mb-3">
                <img src="img/logo.jpg" alt="VitalCore Logo" class="logo">
                <h1 class="brand-name">VitalCore</h1>
                <p class="subtitle">Vital Signs Measuring System</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger py-2 text-center rounded-3">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="kiosk-label text-center">Registrar Number</label>
                <input type="password"
                       id="pass"
                       class="form-control keypad-input"
                       name="pass"
                       placeholder="••••••"
                       maxlength="6"
                       readonly
                       required>
            </div>

            <button type="submit" class="btn btn-primary btn-kiosk w-100 mb-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>

            <button type="button"
                    class="btn btn-light btn-kiosk w-100"
                    data-bs-toggle="modal"
                    data-bs-target="#staffLoginModal">
                <i class="bi bi-person-badge me-1"></i> Staff Login
            </button>
        </div>

        <div class="divider"></div>

        <!-- RIGHT PANEL (KEYPAD) -->
        <div class="right-panel">
            <div class="keypad-grid">
                <button type="button" onclick="addNum('1')">1</button>
                <button type="button" onclick="addNum('2')">2</button>
                <button type="button" onclick="addNum('3')">3</button>

                <button type="button" onclick="addNum('4')">4</button>
                <button type="button" onclick="addNum('5')">5</button>
                <button type="button" onclick="addNum('6')">6</button>

                <button type="button" onclick="addNum('7')">7</button>
                <button type="button" onclick="addNum('8')">8</button>
                <button type="button" onclick="addNum('9')">9</button>

                <button type="button" class="clear-btn" onclick="clearNum()">C</button>
                <button type="button" onclick="addNum('0')">0</button>
                <button type="button" class="back-btn" onclick="backspace()">⌫</button>
            </div>
        </div>

    </form>

    <div class="copyright">
        Copyright © 2026 VitalCore. All rights reserved.
    </div>

</div>

<!-- =========================
     STAFF LOGIN MODAL (MOVED OUTSIDE)
========================== -->
<div class="modal fade" id="staffLoginModal" tabindex="-1" aria-labelledby="staffLoginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="staffLoginModalLabel">
                    <i class="bi bi-shield-lock-fill text-primary me-2"></i> Staff Login
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="post" action="req/login.php">
                <input type="hidden" name="login_type" value="staff">

                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Username</label>
                        <input type="text"
                               name="username"
                               class="form-control form-control-lg"
                               placeholder="Enter your username"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Password</label>
                        <input type="password"
                               name="password"
                               class="form-control form-control-lg"
                               placeholder="Enter your password"
                               required>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Keypad Script -->
<script>
    function addNum(num) {
        const input = document.getElementById("pass");
        if (input.value.length < 6) {
            input.value += num;
        }
    }

    function clearNum() {
        document.getElementById("pass").value = "";
    }

    function backspace() {
        const input = document.getElementById("pass");
        input.value = input.value.slice(0, -1);
    }
</script>

</body>
</html>