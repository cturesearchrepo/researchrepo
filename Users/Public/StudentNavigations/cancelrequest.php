<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
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
