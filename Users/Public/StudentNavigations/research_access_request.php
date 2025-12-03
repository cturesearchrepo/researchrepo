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

$check = $conn->prepare("SELECT id, status FROM research_access_requests WHERE research_id = ? AND student_id = ?");
$check->bind_param("ii", $research_id, $student_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->bind_result($req_id, $status);
    $check->fetch();

    if (strtolower($status) === 'canceledbyuser') {
        $update = $conn->prepare("UPDATE research_access_requests SET status = 'Pending', requested_at = NOW() WHERE id = ?");
        $update->bind_param("i", $req_id);
        $update->execute();
        echo json_encode(["success" => true, "message" => "Access request re-submitted successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "You have already requested access."]);
    }
} else {
    $stmt = $conn->prepare("
        INSERT INTO research_access_requests (research_id, student_id, status, requested_at)
        VALUES (?, ?, 'Pending', NOW())
    ");
    $stmt->bind_param("ii", $research_id, $student_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Access request submitted successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to submit access request."]);
    }
}

$conn->close();
?>
