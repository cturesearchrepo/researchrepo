<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please log in as a student.']);
    exit;
}

$student_id = (int)$_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['research_id']) || empty($_FILES['transmittal_file']['name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing research ID or file.']);
        exit;
    }

    $research_id = (int)$_POST['research_id'];
    $file = $_FILES['transmittal_file'];

    // Validate PDF
    $allowedMime = ['application/pdf'];
    $fileMime = mime_content_type($file['tmp_name']);
    if (!in_array($fileMime, $allowedMime)) {
        echo json_encode(['status' => 'error', 'message' => 'Only PDF files are allowed.']);
        exit;
    }

    $uploadDir = __DIR__ . '/uploads/transmittal/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'transmittal_' . $research_id . '_' . time() . '.' . $fileExt;
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $conn = new mysqli('localhost','root','','CentralizedResearchRepository_userdb');
        if ($conn->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'DB connection failed.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE research_documents SET transmittal_file = ? WHERE id = ? AND uploaded_by_student = ?");
        $dbPath = 'uploads/transmittal/' . $fileName;
        $stmt->bind_param("sii", $dbPath, $research_id, $student_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Transmittal uploaded successfully.', 'file_path' => $dbPath]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
