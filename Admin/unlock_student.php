<?php
// unlock_student.php
header('Content-Type: application/json; charset=utf-8');
$mysqli = new mysqli("localhost","root","","CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) {
    echo json_encode(['status'=>'error','message'=>'DB connection failed']); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $stmt = $mysqli->prepare("
        UPDATE students
        SET status='active', lock_until=NULL, failed_attempts=0
        WHERE id = ? AND status = 'deactivated'
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status'=>'success','message'=>'Student unlocked and set to active.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'No matching deactivated student found or update failed.']);
    }
} else {
    echo json_encode(['status'=>'error','message'=>'Invalid request.']);
}
$mysqli->close();
