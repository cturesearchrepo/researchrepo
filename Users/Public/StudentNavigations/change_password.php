<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['student_id'])){
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

$student_id = $_SESSION['student_id'];

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'CentralizedResearchRepository_userdb';

$conn = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if($conn->connect_error){
    echo json_encode(['status'=>'error','message'=>'DB connection failed']);
    exit;
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if($new_password !== $confirm_password){
    echo json_encode(['status'=>'error','message'=>'New passwords do not match']);
    exit;
}

$stmt = $conn->prepare("SELECT password FROM students WHERE student_id=?");
$stmt->bind_param("i",$student_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if(!$user || !password_verify($current_password, $user['password'])){
    echo json_encode(['status'=>'error','message'=>'Current password is incorrect']);
    exit;
}

$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE students SET password=? WHERE student_id=?");
$stmt->bind_param("si", $new_hash, $student_id);

if($stmt->execute()){
    echo json_encode(['status'=>'success','message'=>'Password changed successfully']);
} else {
    echo json_encode(['status'=>'error','message'=>'Failed to change password']);
}

$stmt->close();
$conn->close();
?>
