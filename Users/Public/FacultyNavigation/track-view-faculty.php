<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");

$faculty_id = intval($_SESSION['faculty_id'] ?? 0);
if (!$faculty_id) exit('Unauthorized');

$research_id = intval($_POST['research_id'] ?? 0);
if (!$research_id) exit('Invalid request');

$viewed_at = date('Y-m-d H:i:s');

$stmt = $mysqli->prepare("INSERT INTO research_views (research_id, student_id, faculty_id, viewed_at) VALUES (?, 0, ?, ?)");
$stmt->bind_param("iis", $research_id, $faculty_id, $viewed_at);
$stmt->execute();
$stmt->close();

echo json_encode(['status'=>'success']);
?>
