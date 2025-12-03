<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Request ID is required.']);
    exit;
}

$request_id = (int)$_POST['id'];

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$stmt = $conn->prepare("SELECT status FROM research_access_requests WHERE id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->bind_result($current_status);
$stmt->fetch();
$stmt->close();

$update = $conn->prepare("UPDATE research_access_requests SET status = 'pending', expire_at = NULL WHERE id = ?");
$update->bind_param("i", $request_id);
if ($update->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'The request you approved has now been canceled.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel the approved request.']);
}

$update->close();
$conn->close();
?>
