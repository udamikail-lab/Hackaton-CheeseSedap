<?php
session_start();
include "dbconn.php";

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username == "" || $password == "") {
    $_SESSION['error'] = "Please enter username and password.";
    header("Location: ../index.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ss", $username, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    header("Location: ../dashboard.php");
    exit();
}

$_SESSION['error'] = "Wrong username or password.";
header("Location: ../index.php");
exit();
?>
