<?php
session_start();

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) die("Database connection failed");

$studentId = intval($_SESSION['student_id'] ?? 0);
$requestId = intval($_GET['id'] ?? 0);

if (!$studentId || !$requestId) die("Unauthorized access");

$sql = "
    SELECT rar.status, rar.expire_at, rd.file_path, rd.title, rd.id AS research_id
    FROM research_access_requests rar
    JOIN research_documents rd ON rar.research_id = rd.id
    WHERE rar.id=? AND rar.student_id=?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $requestId, $studentId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("Unauthorized or request not found");
$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

if ($data['status'] !== 'approved') die("Request not approved");
if ($data['expire_at'] && strtotime($data['expire_at']) < time()) die("Access expired");

$userPath  = __DIR__ . '/' . $data['file_path'];
$adminPath = __DIR__ . '/../../../Admin/' . $data['file_path'];
if (file_exists($userPath)) {
    $filePath = $userPath;
} elseif (file_exists($adminPath)) {
    $filePath = $adminPath;
} else {
    die("File not found");
}

header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=\"" . basename($filePath) . "\"");
header("Content-Length: " . filesize($filePath));
readfile($filePath);
exit;
?>
