<?php
session_start();
header('Content-Type: application/json');

$studentId  = $_SESSION['student_id'] ?? 0;
$researchId = intval($_POST['research_id'] ?? 0);
$rating     = intval($_POST['rating'] ?? 0);

if (!$studentId) {
    echo json_encode(["ok" => false, "message" => "You must be logged in to rate"]);
    exit;
}

if ($researchId <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(["ok" => false, "message" => "Invalid data"]);
    exit;
}

$conn = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($conn->connect_error) {
    echo json_encode(["ok" => false, "message" => "Database connection failed"]);
    exit;
}

$sql = "
    INSERT INTO research_ratings (research_id, student_id, rating) 
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE rating = VALUES(rating)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $researchId, $studentId, $rating);

if (!$stmt->execute()) {
    echo json_encode(["ok" => false, "message" => "Failed to save rating"]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$stmt = $conn->prepare("SELECT ROUND(AVG(rating),1) as avg_rating FROM research_ratings WHERE research_id = ?");
$stmt->bind_param("i", $researchId);
$stmt->execute();
$res = $stmt->get_result();
$avg = $res->fetch_assoc()['avg_rating'] ?? 0;
$stmt->close();

$conn->close();

echo json_encode(["ok" => true, "avg" => $avg]);
