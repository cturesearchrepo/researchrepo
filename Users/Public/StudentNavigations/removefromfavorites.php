<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["success" => false, "message" => "Access denied."]);
    exit;
}

$student_id = (int)$_SESSION['student_id'];

$conn = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite_id'])) {
    $fav_id = (int)$_POST['favorite_id'];

    $check = $conn->prepare("SELECT id FROM favorites WHERE id = ? AND student_id = ?");
    $check->bind_param("ii", $fav_id, $student_id);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Favorite not found or access denied."]);
        $check->close();
        $conn->close();
        exit;
    }
    $check->close();

    $delete = $conn->prepare("DELETE FROM favorites WHERE id = ? AND student_id = ?");
    $delete->bind_param("ii", $fav_id, $student_id);
    $delete->execute();

    if ($delete->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Removed from favorites successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to remove from favorites."]);
    }
    $delete->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}

$conn->close();
?>
