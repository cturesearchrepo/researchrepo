<?php
session_start();
header('Content-Type: text/plain'); 

$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) {
    exit("Error: Database connection failed: " . $mysqli->connect_error);
}

if (!isset($_SESSION['faculty_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("Error: Unauthorized access or invalid request method.");
}

$facultyQuery = $mysqli->prepare("SELECT id FROM faculty WHERE faculty_id = ?");
$facultyQuery->bind_param("i", $_SESSION['faculty_id']);
$facultyQuery->execute();
$facultyResult = $facultyQuery->get_result();
$faculty = $facultyResult->fetch_assoc();
$reviewer_id = $faculty['id'];

if (!$reviewer_id) {
    exit("Error: Faculty ID not found in the database.");
}

$research_id = intval($_POST['id']);
$feedback_text = trim($_POST['feedback']);
$new_status = $_POST['status'] ?? $_POST['prev_status'] ?? 'Pending'; 
$file_path = null;

if (isset($_FILES['feedback_file']) && $_FILES['feedback_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['feedback_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = "feedback_" . $research_id . "_" . time() . "." . $ext;
    $upload_dir = __DIR__ . "/uploads/feedback/" . date("Y") . "/";
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
             exit("Error: Failed to create upload directory.");
        }
    }
    
    $target_file = $upload_dir . $safe_filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $file_path = "uploads/feedback/" . date("Y") . "/" . $safe_filename;
    } else {
        exit("Error: Failed to move uploaded file. Check permissions (e.g., 0755) on the 'uploads' directory.");
    }
}

$mysqli->begin_transaction();
$success = true;

$feedbackStmt = $mysqli->prepare("
    INSERT INTO research_feedback (research_id, reviewer_id, feedback, feedback_file, status)
    VALUES (?, ?, ?, ?, ?)
");

if (!$feedbackStmt->bind_param("iisss", $research_id, $reviewer_id, $feedback_text, $file_path, $new_status)) {
    $success = false;
    goto end_transaction;
}

if (!$feedbackStmt->execute()) {
    $success = false;
    goto end_transaction;
}
$feedbackStmt->close();


$target_column = 'prev_status';
if (strpos($new_status, 'Advicer') !== false) {
    $target_column = 'prev_status'; // Use prev_status for Advicer actions as requested
}

$docStmt = $mysqli->prepare("
    UPDATE research_documents SET {$target_column} = ? WHERE id = ?
");

if (!$docStmt->bind_param("si", $new_status, $research_id)) {
    $success = false;
    goto end_transaction;
}

if (!$docStmt->execute()) {
    $success = false;
    goto end_transaction;
}
$docStmt->close();

$decision_statuses = ['ApprovedbyPanelist', 'RejectedbyPanelist', 'RevisionRequired'];

if (in_array($new_status, $decision_statuses)) {
    $reviewUpdateStmt = $mysqli->prepare("
        UPDATE research_reviewers SET status = ? 
        WHERE research_id = ? AND reviewer_id = ?
    ");
    
    if (!$reviewUpdateStmt->bind_param("sii", $new_status, $research_id, $reviewer_id)) {
        $success = false;
        goto end_transaction;
    }

    if (!$reviewUpdateStmt->execute()) {
        $success = false;
        goto end_transaction;
    }
    $reviewUpdateStmt->close();
}


// --- 5. Commit or Rollback ---
end_transaction:
if ($success) {
    $mysqli->commit();
    echo "Success: Research status updated to " . $new_status . " and feedback recorded.";
} else {
    // If an error occurred, log it and rollback
    error_log("Database Transaction Failed: " . $mysqli->error);
    $mysqli->rollback();
    echo "Error: Transaction failed. Status update rolled back.";
}

$mysqli->close();
?>