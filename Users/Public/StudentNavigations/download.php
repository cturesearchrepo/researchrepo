<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

if (!isset($_SESSION['faculty_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit("Invalid request.");
}

$research_id = intval($_GET['id']);

$stmt = $mysqli->prepare("SELECT title, file_path, year_completed FROM research_documents WHERE id = ?");
$stmt->bind_param("i", $research_id);
$stmt->execute();
$stmt->bind_result($title, $file_path, $year_completed);
$stmt->fetch();
$stmt->close();
$mysqli->close();

$file_full_path = __DIR__ . "/../StudentNavigations/uploads/" . $year_completed . "/" . basename($file_path);

if (!file_exists($file_full_path)) {
    http_response_code(404);
    exit("File not found.");
}

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($file_full_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_full_path));
readfile($file_full_path);
exit;
?>
