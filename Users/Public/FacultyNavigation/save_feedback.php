<?php
session_start();
$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die("DB error: " . $mysqli->connect_error);
}

if (!isset($_SESSION['faculty_id'])) {
    exit("Unauthorized");
}

// Get faculty DB ID
$facultyQuery = $mysqli->prepare("SELECT id FROM faculty WHERE faculty_id = ?");
$facultyQuery->bind_param("i", $_SESSION['faculty_id']);
$facultyQuery->execute();
$faculty = $facultyQuery->get_result()->fetch_assoc();
if (!$faculty) {
    exit("Faculty not found.");
}
$facultyDbId = $faculty['id'];

// Inputs
$researchId   = intval($_POST['research_id'] ?? 0);
$status       = $_POST['status'] ?? 'RevisionRequired';
$feedback     = trim($_POST['feedback'] ?? '');
$feedbackFile = null;

// Handle file upload
if (!empty($_FILES['feedback_file']['name'])) {
    $uploadDir = __DIR__ . "/uploads/feedbacks/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename   = time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "_", $_FILES['feedback_file']['name']);
    $targetFile = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['feedback_file']['tmp_name'], $targetFile)) {
        $feedbackFile = "uploads/feedbacks/" . $filename;
    }
}

// Insert feedback
$stmt = $mysqli->prepare("
    INSERT INTO research_feedback (research_id, reviewer_id, feedback, feedback_file, status)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("iisss", $researchId, $facultyDbId, $feedback, $feedbackFile, $status);

if ($stmt->execute()) {
    echo "Feedback saved successfully!";
} else {
    echo "Error: " . $mysqli->error;
}
