<?php
session_start();
include "dbconn.php";

$username = trim($_POST['username'] ?? '');
$full_name = strtoupper(trim($_POST['full_name'] ?? ''));
$age = intval($_POST['age'] ?? 0);
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($username == "" || $full_name == "" || $email == "" || $password == "") {
    $_SESSION['error'] = "Please fill in all required fields.";
    header("Location: /gxbank_html_by_page/register.php");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email address.";
    header("Location: /gxbank_html_by_page/register.php");
    exit();
}

$check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? OR email = ?");
mysqli_stmt_bind_param($check, "ss", $username, $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    $_SESSION['error'] = "Username or email already exists.";
    header("Location: /gxbank_html_by_page/register.php");
    exit();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "INSERT INTO users (username, full_name, age, email, password) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssiss", $username, $full_name, $age, $email, $hashed_password);

if (!mysqli_stmt_execute($stmt)) {
    $_SESSION['error'] = "Registration failed: " . mysqli_error($conn);
    header("Location: /gxbank_html_by_page/register.php");
    exit();
}

$user_id = mysqli_insert_id($conn);

$account_number = "48" . rand(100000, 999999);
$balance = 0.00;

$acc = mysqli_prepare($conn, "INSERT INTO accounts (user_id, account_type, account_number, balance) VALUES (?, 'Savings Account', ?, ?)");
mysqli_stmt_bind_param($acc, "isd", $user_id, $account_number, $balance);

if (!mysqli_stmt_execute($acc)) {
    $_SESSION['error'] = "Failed to create account: " . mysqli_error($conn);
    header("Location: /gxbank_html_by_page/register.php");
    exit();
}

$_SESSION['success'] = "Registration successful. Please login.";
header("Location: /gxbank_html_by_page/index.php");
exit();
?>