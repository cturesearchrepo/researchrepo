<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit;
}

$studentId = intval($_SESSION['student_id']);
$id = intval($_POST['id'] ?? 0);

$conn = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$stmt = $conn->prepare("SELECT status FROM research_access_requests WHERE id=? AND student_id=?");
$stmt->bind_param("ii", $id, $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Request not found."]);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$status = $row['status'];

if ($status !== 'expired') {
    echo json_encode(["success" => false, "message" => "You can only delete expired requests."]);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt = $conn->prepare("DELETE FROM research_access_requests WHERE id=? AND student_id=?");
$stmt->bind_param("ii", $id, $studentId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Expired request deleted successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to delete request."]);
}

$stmt->close();
$conn->close();
?>
