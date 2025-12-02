<?php
session_start();

$id = intval($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$status = $_POST['status'] ?? '';

if (!$reason) {
    echo "Rejection reason is required!";
    exit;
}

$conn = new mysqli("localhost","root","","CentralizedResearchRepository_userdb");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

if ($status === 'extendRequested') {
    $newStatus = 'extendDeclined';
} else {
    $newStatus = 'rejected';
}

$stmt = $conn->prepare("UPDATE research_access_requests SET status=?, admin_note=? WHERE id=?");
$stmt->bind_param("ssi", $newStatus, $reason, $id);
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
$reason_safe = htmlspecialchars($reason);
$msg = "Your request for '$research_title' has been rejected. Reason: $reason_safe";

$stmt = $conn->prepare("
    INSERT INTO notifications (student_id, message, type, status, is_read, created_at)
    VALUES (?, ?, 'request', ?, 0, NOW())
");
$notification_status = ($status === 'extendRequested') ? 'extendDeclined' : 'rejectedbyadmin';
$stmt->bind_param("iss", $student_id, $msg, $notification_status);
$stmt->execute();
$stmt->close();

$conn->close();

echo "Request has been $newStatus and notification sent!";
?>
