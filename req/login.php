<?php

session_start();
require_once __DIR__ . "/../db_conn.php";

if(!isset($_POST['pass'])){
    header("Location: ../login.php?error=Empty Code");
    exit();
}

$code = $_POST['pass'];

$sql = "SELECT * FROM users WHERE code_number='$code'";
$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) == 1){

    $user = mysqli_fetch_assoc($result);

    $_SESSION['id'] = $user['id'];
    $_SESSION['name'] = $user['fullname'];
    $_SESSION['role'] = $user['role'];
    unset($_SESSION['measurement_saved']);

    if($user['role'] == 'admin'){
        header("Location: ../admin/dashboard.php");
        exit();
    }

    if($user['role'] == 'patient'){
        header("Location: ../kiosk/index.php");
        exit();
    }

}else{
    header("Location: ../login.php?error=Invalid Code Number");
    exit();
}