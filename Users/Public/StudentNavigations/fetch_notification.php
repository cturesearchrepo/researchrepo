<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode([]);
    exit;
}

$student_id = $_SESSION['student_id'];

$conn = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "SELECT id, message, type, status, is_read, created_at 
        FROM notifications 
        WHERE student_id = ? 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['type'])) $row['type'] = 'general';
    if (empty($row['status'])) $row['status'] = 'pendingrequest';
    $notifications[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($notifications);
?>
