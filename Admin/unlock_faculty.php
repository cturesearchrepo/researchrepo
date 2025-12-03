<?php
$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'DB connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $update = $mysqli->prepare("
        UPDATE faculty
        SET status = 'Active', lock_until = NULL, failed_attempts = 0
        WHERE id = ? AND status = 'Deactivated'
    ");
    $update->bind_param("i", $id);

    if ($update->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Faculty unlocked successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to unlock faculty.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>
