<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["ok"=>false,"message"=>"DB connection failed"]);
    exit;
}

$studentId = intval($_SESSION['student_id'] ?? 0);
if($studentId <= 0){
    echo json_encode(["ok"=>false,"message"=>"Not logged in"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$researchId = intval($data['research_id'] ?? 0);
if($researchId <= 0){
    echo json_encode(["ok"=>false,"message"=>"Invalid research ID"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO research_access_requests (research_id, student_id, status, requested_at, expire_at) VALUES (?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))");
$stmt->bind_param("ii", $researchId, $studentId);

if($stmt->execute()){
    echo json_encode(["ok"=>true,"message"=>"Request submitted"]);
} else {
    echo json_encode(["ok"=>false,"message"=>"DB insert failed"]);
}

$stmt->close();
$conn->close();
