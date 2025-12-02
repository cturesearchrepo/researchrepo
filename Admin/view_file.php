<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

$researchId = intval($_GET['id'] ?? 0);
if (!$researchId) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$conn = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$sql = "SELECT file_path, title FROM research_documents WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $researchId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("File not found in database.");
}

$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

$userPath    = __DIR__ . '/' . $data['file_path'];
$adminPath   = __DIR__ . '/../../../Admin/' . $data['file_path'];
$facultyPath = __DIR__ . '/../../StudentNavigations/uploads/' . $data['file_path'];

$filepath = null;

if (file_exists($userPath)) {
    $filepath = $userPath;
} elseif (file_exists($adminPath)) {
    $filepath = $adminPath;
} elseif (file_exists($facultyPath)) {
    $filepath = $facultyPath;
} else {
    die("File not found on server.");
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filepath) . '"');
header('Content-Length: ' . filesize($filepath));

readfile($filepath);
exit;
