<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$conn = new mysqli('localhost', 'root', '', 'CentralizedResearchRepository_userdb');
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$research_id  = (int)($_POST['research_id'] ?? 0);
$title        = trim($_POST['title'] ?? '');
$type         = trim($_POST['research_type'] ?? '');
$abstract     = trim($_POST['abstract'] ?? '');
$faculty_id   = (int)($_POST['faculty_id'] ?? 0);
$panelists    = $_POST['panelists'] ?? [];

if (!$research_id || !$title || !$type || !$faculty_id) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields']);
    exit;
}

$filePath = '';
if (!empty($_FILES['file_path']['name'])) {
    $uploadDir = __DIR__ . '/uploads/research/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileName = time() . '_' . basename($_FILES['file_path']['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['file_path']['tmp_name'], $targetPath)) {
        $filePath = 'uploads/research/' . $fileName;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
        exit;
    }
}

if ($filePath) {
    $query = "UPDATE research_documents 
              SET title=?, research_type=?, abstract=?, faculty_id=?, file_path=? 
              WHERE id=? AND uploaded_by_student=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssisii', $title, $type, $abstract, $faculty_id, $filePath, $research_id, $student_id);
} else {
    $query = "UPDATE research_documents 
              SET title=?, research_type=?, abstract=?, faculty_id=? 
              WHERE id=? AND uploaded_by_student=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssiii', $title, $type, $abstract, $faculty_id, $research_id, $student_id);
}

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update research.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$conn->query("DELETE FROM research_reviewers WHERE research_id = $research_id");

if (!empty($panelists) && is_array($panelists)) {
    $stmt2 = $conn->prepare("INSERT INTO research_reviewers (research_id, reviewer_id, status) VALUES (?, ?, 'Pending')");
    foreach ($panelists as $pid) {
        $pid = (int)$pid;
        $stmt2->bind_param("ii", $research_id, $pid);
        $stmt2->execute();
    }
    $stmt2->close();
}

$conn->close();
echo json_encode(['status' => 'success', 'message' => 'Research updated successfully.']);
?>
