<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed."]);
    exit;
}

$studentId = intval($_SESSION['student_id']);
$requestId = intval($_POST['id'] ?? 0);

if ($requestId <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit;
}

$sql = "UPDATE research_access_requests 
        SET status = 'extendRequested', requested_at = NOW()
        WHERE id = ? AND student_id = ? AND status IN ('expired', 'approved')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $requestId, $studentId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Extension request sent successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Unable to send extension request."]);
}

$stmt->close();
$conn->close();
?>
