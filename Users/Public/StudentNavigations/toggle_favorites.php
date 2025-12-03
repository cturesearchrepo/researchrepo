<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["success" => false, "message" => "Access denied. Please log in first."]);
    exit;
}

$student_id = intval($_SESSION['student_id']);
$research_id = intval($_POST['research_id'] ?? 0);

if ($research_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid research ID."]);
    exit;
}

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$check = $conn->prepare("SELECT id FROM favorites WHERE research_id = ? AND student_id = ?");
$check->bind_param("ii", $research_id, $student_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $remove = $conn->prepare("DELETE FROM favorites WHERE research_id = ? AND student_id = ?");
    $remove->bind_param("ii", $research_id, $student_id);
    $remove->execute();
    echo json_encode(["success" => true, "action" => "removed", "message" => "Removed from favorites."]);
} else {
    $insert = $conn->prepare("INSERT INTO favorites (research_id, student_id, added_at) VALUES (?, ?, NOW())");
    $insert->bind_param("ii", $research_id, $student_id);
    if ($insert->execute()) {
        echo json_encode(["success" => true, "action" => "added", "message" => "Added to favorites successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to add to favorites."]);
    }
}

$conn->close();
?>
