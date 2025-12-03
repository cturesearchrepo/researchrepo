<?php
session_start();
header('Content-Type: application/json');

$studentId  = intval($_SESSION['student_id'] ?? 0);
$researchId = intval($_POST['research_id'] ?? 0);
$rating     = intval($_POST['rating'] ?? 0);

if (!$studentId || !$researchId || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$stmt = $mysqli->prepare("SELECT id FROM ratings WHERE research_id=? AND student_id=?");
$stmt->bind_param("ii", $researchId, $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $update = $mysqli->prepare("UPDATE ratings SET rating=?, created_at=NOW() WHERE id=?");
    $update->bind_param("ii", $rating, $row['id']);
    $success = $update->execute();
    $update->close();
    $message = $success ? "Your rating has been updated." : "Failed to update rating.";
} else {
    $insert = $mysqli->prepare("INSERT INTO ratings (research_id, student_id, rating, created_at) VALUES (?,?,?,NOW())");
    $insert->bind_param("iii", $researchId, $studentId, $rating);
    $success = $insert->execute();
    $insert->close();
    $message = $success ? "Thank you for rating!" : "Failed to submit rating.";
}

$stmt->close();

$avg = 0;
$avgStmt = $mysqli->prepare("SELECT AVG(rating) AS avg_rating FROM ratings WHERE research_id=?");
$avgStmt->bind_param("i", $researchId);
$avgStmt->execute();
$avgResult = $avgStmt->get_result();
if ($avgRow = $avgResult->fetch_assoc()) {
    $avg = round($avgRow['avg_rating'], 2);
}
$avgStmt->close();

$mysqli->close();

echo json_encode([
    'success' => $success,
    'message' => $message,
    'avg'     => $avg
]);
