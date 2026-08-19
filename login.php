<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VitalCore Login</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
<link rel="icon" href="logo.png">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;

    background:url('img/vitalcore-bck.jpg') center center/cover no-repeat;
}

.overlay{
    width:100%;
    display:flex;
    flex-direction:column;
    align-items:center;
}

.login-card{
    width:1000px;
    max-width:95%;

    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:40px;

    padding:40px;

    border-radius:25px;

    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(10px);

    border:1px solid rgba(255,255,255,0.2);

    box-shadow:0 10px 30px rgba(0,0,0,0.2);
}

.left-panel{
    flex:1;
}

.right-panel{
    width:380px;
}

.divider{
    width:1px;
    height:500px;
    background:rgba(255,255,255,0.2);
}

.logo{
    width:120px;
    height:120px;
    object-fit:cover;
    border-radius:50%;
    border:4px solid #fff;
}

.brand-name{
    color:#fff;
    font-size:4rem;
    font-weight:700;
    margin-top:15px;
}

.subtitle{
    color:#fff;
    font-size:1.3rem;
}

.form-label{
    color:#fff;
    font-weight:600;
    font-size:1.5rem;
}

.form-control{
    height:75px;
    font-size:2rem;
    text-align:center;
    border-radius:15px;
}

.btn-login{
    height:75px;
    font-size:1.8rem;
    border-radius:15px;
}

.keypad-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
}

.keypad-grid button{
    height:90px;

    border:none;
    border-radius:20px;

    font-size:2rem;
    font-weight:bold;

    background:#fff;

    transition:.2s;
}

.keypad-grid button:active{
    transform:scale(.95);
}

.clear-btn{
    background:#ff4d5a !important;
    color:white;
}

.back-btn{
    background:#ffd84d !important;
}

.copyright{
    margin-top:20px;
    color:white;
    font-size:1.2rem;
}

@media(max-width:900px){

    .login-card{
        flex-direction:column;
    }

    .divider{
        display:none;
    }

    .right-panel{
        width:100%;
    }
}
</style>

</head>

<body>

<div class="overlay">

<form class="login-card"
      method="post"
      action="req/login.php">

    <div class="left-panel">

        <div class="text-center mb-4">
            <img src="img/logo.jpg" alt="VitalCore Logo" class="logo">

            <h1 class="brand-name">VitalCore</h1>

            <p class="subtitle">
                Vital Signs Measuring System
            </p>
        </div>

        <?php if (isset($_GET['error'])) { ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php } ?>

        <div class="mb-4">

            <label class="form-label">
                Registrar Number
            </label>

            <input type="tel"
                   id="pass"
                   class="form-control"
                   name="pass"
                   placeholder="Enter code number"
                   maxlength="6"
                   readonly
                   required>


        </div>

        <button type="submit"
                class="btn btn-primary btn-login w-100">
            Login
        </button>

    </div>

    <div class="divider"></div>

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

            <button type="button"
                    class="clear-btn"
                    onclick="clearNum()">
                C
            </button>

            <button type="button"
                    onclick="addNum('0')">
                0
            </button>

            <button type="button"
                    class="back-btn"
                    onclick="backspace()">
                ⌫
            </button>

        </div>

    </div>

</form>

<div class="copyright">
    Copyright © 2026 VitalCore
</div>

</div>

<script>
function addNum(num){

    let input = document.getElementById("pass");

    if(input.value.length < 6){
        input.value += num;
    }
}

function clearNum(){
    document.getElementById("pass").value = "";
}

function backspace(){

    let input = document.getElementById("pass");

    input.value =
    input.value.slice(0,-1);
}
</script>

</body>
</html>