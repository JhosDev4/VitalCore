<?php
session_start();

if(isset($_POST['service_type'])){
    $_SESSION['service_type'] = $_POST['service_type'];
}

echo "success";