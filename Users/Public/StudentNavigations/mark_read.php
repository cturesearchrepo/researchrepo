<?php
session_start();

if (!isset($_POST['notification_id']) || !isset($_SESSION['student_id'])) exit;

$notification_id = intval($_POST['notification_id']);
$student_id = $_SESSION['student_id'];

$conn= new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $notification_id, $student_id);
$stmt->execute();
$stmt->close();
$conn->close();
?>
