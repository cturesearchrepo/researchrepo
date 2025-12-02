<?php
session_start();

$id = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

$conn = new mysqli("localhost","root","","CentralizedResearchRepository_userdb");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

if ($status === 'extendRequested') {
    $newStatus = 'extended';
    $expire_at = date("Y-m-d H:i:s", strtotime("+3 days")); // extend by 3 days
} else {
    $newStatus = 'approved';
    $expire_at = date("Y-m-d H:i:s", strtotime("+3 days")); // normal approval
}

$stmt = $conn->prepare("UPDATE research_access_requests SET status=?, expire_at=? WHERE id=?");
$stmt->bind_param("ssi", $newStatus, $expire_at, $id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("
    SELECT student_id, (SELECT title FROM research_documents WHERE id=rar.research_id) AS research_title
    FROM research_access_requests rar
    WHERE id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($student_id, $research_title);
$stmt->fetch();
$stmt->close();

$research_title = htmlspecialchars($research_title);
$msg = "Your request for '$research_title' has been approved! Access expires on $expire_at.";

$stmt = $conn->prepare("
    INSERT INTO notifications (student_id, message, type, status, is_read, created_at)
    VALUES (?, ?, 'request', ?, 0, NOW())
");
$notification_status = ($status === 'extendRequested') ? 'extendedbyadmin' : 'approvedbyadmin';
$stmt->bind_param("iss", $student_id, $msg, $notification_status);
$stmt->execute();
$stmt->close();

$conn->close();

echo "Request has been $newStatus and notification sent!";
?>
