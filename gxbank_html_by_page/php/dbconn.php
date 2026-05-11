<?php
$host = "localhost";
$uname = "root";
$pwd = "";
$db = "gxbank_app";
$port = 3306;

$conn = mysqli_connect($host, $uname, $pwd, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>