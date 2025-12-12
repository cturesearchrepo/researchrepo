<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die(json_encode(['status' => 'error', 'message' => "Connection failed: " . $mysqli->connect_error]));}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userid   = trim($_POST['userid'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$userid || !$password) {
        echo json_encode(['status' => 'error', 'message' => "Please enter both Student ID and password."]);
        exit;}
function checkLogin($conn, $table, $idColumn, $userid, $password) {
    $stmt = $conn->prepare("SELECT * FROM $table WHERE $idColumn = ?");
    $stmt->bind_param("s", $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (!empty($user['lock_until']) && strtotime($user['lock_until']) > time()) {
            echo json_encode([
                'status' => 'locked',
                'unlock_time' => $user['lock_until'],
                'message' => "Your account is locked until ".$user['lock_until']]);
            return true;}
        if (!password_verify($password, $user['password'])) {
            $failedAttempts = $user['failed_attempts'] + 1;
            if ($failedAttempts >= 5) {
                $lockUntil = date("Y-m-d H:i:s", strtotime("+1 day"));
                $update = $conn->prepare("UPDATE $table SET failed_attempts = 0, lock_until = ?, status = 'deactivated' WHERE $idColumn = ?");
                $update->bind_param("ss", $lockUntil, $userid);
                $update->execute();
                echo json_encode([
                    'status' => 'locked',
                    'unlock_time' => $lockUntil,
                    'message' => "Too many failed attempts. Your account is deactivated and locked for 24 hours."]);
                return true;
            } else {
                $update = $conn->prepare("UPDATE $table SET failed_attempts = ? WHERE $idColumn = ?");
                $update->bind_param("is", $failedAttempts, $userid);
                $update->execute();
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Incorrect password. Attempt '.$failedAttempts.' of 5.']);
                return true;}}
        $update = $conn->prepare("UPDATE $table SET failed_attempts = 0, lock_until = NULL WHERE $idColumn = ?");
        $update->bind_param("s", $userid);
        $update->execute();
        if ($table === 'students') {
            $_SESSION['student_id'] = $user['student_id'];
            $dashboardPage = "../Public/StudentNavigations/Student_Dashboard.php";
            $prefCheck = $conn->prepare("SELECT id FROM student_preferences WHERE student_id = ? LIMIT 1");
            $prefCheck->bind_param("i", $user['student_id']);
            $prefCheck->execute();
            $prefResult = $prefCheck->get_result();
            if ($prefResult->num_rows === 0) {
                echo json_encode(['status' => 'redirect', 'page' => "preferences.php"]);} else {
                echo json_encode(['status' => 'success', 'page' => $dashboardPage]);}
        } else {
            $_SESSION['faculty_id'] = $user['faculty_id'];
            $dashboardPage = "../Public/FacultyNavigation/FacultyDashboard.php";
            echo json_encode(['status' => 'success', 'page' => $dashboardPage]);}
        return true;}
    $stmt->close();
    return false;}
if (checkLogin($mysqli, 'students', 'student_id', $userid, $password)) {
    $mysqli->close();
    exit;}
if (checkLogin($mysqli, 'faculty', 'faculty_id', $userid, $password)) {
    $mysqli->close();
    exit;}
    echo json_encode(['status' => 'error', 'message' => "User not found."]);
    $mysqli->close();
    exit;}?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Portal Login</title>
<link rel="stylesheet" href="UsersStyle.css" />
<link rel="icon" type="image/png" href="Photos/logoCTU.png">

</head>
<body>
<div class="container">
    <div class="left-side">
      <img src="Photos/logoCtu.png" alt="CTU Logo" /></div>
    <div class="right-side">
      <div class="heading">
        <div class="bar"></div>
        <h1><span>User</span> Access</h1></div>
      <p>Online Research Repository Library System – Cebu Technological University</p>
      <form class="login-form" id="loginForm" method="POST">
        <input type="text" name="userid" placeholder="Student ID / Faculty ID" required />
        <div class="password-wrapper">
          <input type="password" id="login-password" name="password" placeholder="Your Password" required autocomplete="off" />
          <button type="button" class="toggle-password" onclick="togglePassword('login-password', this)">
            <img src="Photos/icons8-closed-eye-24.png" alt="Show Password" />
          </button>
        </div><br>
        <div class="form-extra">
          <label>
            <input type="checkbox" name="remember" /> Remember me
          </label>
          <a href="../../Admin/Adminlogin.php">Admin Access</a>
        </div>
        <button type="submit" class="login-btn">Login</button>
      </form><br>
      <div class="login-link">
        Don’t have an account? <a href="./UsersSignup.php">Register here</a>
      </div>
    </div>
</div><script>
document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.querySelector(".login-form");
    loginForm.addEventListener("submit", function (e) {
        e.preventDefault();
        const formData = new FormData(loginForm);
        fetch("", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'redirect' || data.status === 'success') {
                window.location.href = data.page;}
            else if (data.status === 'locked') {
                showLockCountdown(data.unlock_time);
                if (data.message) alert(data.message);}
            else {
                alert(data.message);
            } })
        .catch(err => {
            console.error("Login error:", err);
            alert("Login failed. Please try again.");
        });});});
function togglePassword(fieldId, button) {
    const field = document.getElementById(fieldId);
    const img = button.querySelector("img");
    if (field.type === "password") {
        field.type = "text";
        img.src = "Photos/icons8-eye-24.png";
        img.alt = "Hide Password";
    } else {
        field.type = "password";
        img.src = "Photos/icons8-closed-eye-24.png";
        img.alt = "Show Password";  }}
function showLockCountdown(untilTime) {
    let unlockTime = new Date(untilTime).getTime();
    let countdownDiv = document.getElementById('lockCountdown');
    if (!countdownDiv) {
        countdownDiv = document.createElement('div');
        countdownDiv.style.color = 'red';
        countdownDiv.style.marginTop = '10px';
        countdownDiv.id = 'lockCountdown';
        document.querySelector('.login-form').appendChild(countdownDiv);}
    function updateCountdown() {
        const now = new Date().getTime();
        let diff = unlockTime - now;
        if (diff <= 0) {
            countdownDiv.textContent = "Your account is now unlocked. Please try logging in again.";
            clearInterval(timer);
            return;}
        const hours = Math.floor(diff / (1000*60*60));
        const minutes = Math.floor((diff % (1000*60*60)) / (1000*60));
        const seconds = Math.floor((diff % (1000*60)) / 1000);
        countdownDiv.textContent = `Account locked. Try again in ${hours}h ${minutes}m ${seconds}s`;}
    updateCountdown();
    const timer = setInterval(updateCountdown, 1000);}
</script></body></html>
