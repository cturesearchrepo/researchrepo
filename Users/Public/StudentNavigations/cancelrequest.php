<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$studentId = intval($_SESSION['student_id']);
$requestId = intval($_POST['id'] ?? 0);

if ($requestId <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid request ID."]);
    exit;
}

$sql = "UPDATE research_access_requests
        SET status = 'canceledbyUser'
        WHERE id = ? AND student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $requestId, $studentId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Request has been canceled successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Unable to cancel request. It may already be processed or not yours."]);
}

$stmt->close();
$conn->close();
?>
