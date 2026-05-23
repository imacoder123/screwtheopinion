<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db.php';

$name = $_POST['name'];
$email = $_POST['email'];
$username = $_POST['username'];
$password = $_POST['password'];
$confirm = $_POST['confirm_password'];

if ($password !== $confirm) {
    die("Passwords do not match.");
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

$check = $conn->prepare("SELECT id FROM users WHERE email=? OR username=?");
$check->bind_param("ss", $email, $username);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    die("Email or Username already exists.");
}

$stmt = $conn->prepare("INSERT INTO users (name, email, username, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $username, $hashed);

if ($stmt->execute()) {

    // Set session so dashboard knows who logged in
    $_SESSION['user_id'] = $stmt->insert_id;
    $_SESSION['username'] = $username;

    header("Location: dashboard.php");
    exit();

} else {
    echo "Error: " . $stmt->error;
}
?>