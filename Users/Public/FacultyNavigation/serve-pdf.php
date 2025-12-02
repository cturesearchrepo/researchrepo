<?php
session_start();

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Database connection failed");

$researchId = intval($_GET['id'] ?? 0);
if (!$researchId) die("Invalid request");

$sql = "SELECT rd.file_path, rd.title 
        FROM research_documents rd
        WHERE rd.id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $researchId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("File not found in DB");
$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

$userPath    = __DIR__ . '/' . $data['file_path'];                  
$adminPath   = __DIR__ . '/../../../Admin/' . $data['file_path'];
$facultyPath = __DIR__ . '/../../StudentNavigations/uploads/' . $data['file_path']; 

if (file_exists($userPath)) {
    $filePath = $userPath;
} elseif (file_exists($adminPath)) {
    $filePath = $adminPath;
} elseif (file_exists($facultyPath)) {
    $filePath = $facultyPath;
} else {
    die("File not found on server");
}

header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=\"" . basename($filePath) . "\"");
header("Content-Length: " . filesize($filePath));
readfile($filePath);
exit;
?>
