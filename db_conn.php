<?php

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "vitalcore_db";

$conn = mysqli_connect($host,$user,$pass,$dbname);

if(!$conn){
    die("Connection Failed: " . mysqli_connect_error());
}
?>