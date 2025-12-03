<?php
$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'DB connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $update = $mysqli->prepare("UPDATE faculty SET status = 'Active' WHERE id = ? AND status IN ('suspended', 'declined','archived')");
    $update->bind_param("i", $id);

    if ($update->execute()) {
        if ($update->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Faculty restored to Active.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Faculty is already Active or does not exist.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to restore faculty.']);
    }

    $update->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$mysqli->close();
?>
