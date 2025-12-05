<?php
include __DIR__ . '/db_connect.php';

// Helper function to send JSON responses
function sendResponse($status, $message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Collect POST data safely
$fullname   = trim($_POST['fullname'] ?? '');
$username   = trim($_POST['username'] ?? '');
$student_id = trim($_POST['studentid'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['number'] ?? '');
$password   = $_POST['password'] ?? '';
$year_level = $_POST['year_level'] ?? 'first';

// Validate required fields
if (!$fullname || !$username || !$student_id || !$email || !$phone || !$password) {
    sendResponse('error', 'All fields are required.');
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format.');
}

// Validate student ID (digits only, 8-10 characters)
if (!preg_match('/^[0-9]{8,10}$/', $student_id)) {
    sendResponse('error', 'Invalid Student ID format.');
}

// Map year level
$yearOptions = [
    "first"  => "First Year",
    "second" => "Second Year",
    "third"  => "Third Year",
    "fourth" => "Fourth Year"
];
$year_level = $yearOptions[strtolower($year_level)] ?? "First Year";

// Check for duplicates in DB
$checkQuery = "SELECT student_id, email, username FROM students WHERE student_id = ? OR email = ? OR username = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("sss", $student_id, $email, $username);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    $row = $checkResult->fetch_assoc();
    $conflict = [];
    if ($row['student_id'] === $student_id) $conflict[] = "Student ID";
    if ($row['email'] === $email) $conflict[] = "Email";
    if ($row['username'] === $username) $conflict[] = "Username";

    sendResponse('error', "Oops :( " . implode(", ", $conflict) . " already exist" . (count($conflict) > 1 ? "" : "s") . ".");
}
$checkStmt->close();

// Hash password securely
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Default profile image
$profileName = "logoCtu.png";

// Handle profile upload
if (isset($_FILES['profile']) && $_FILES['profile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . "/uploads/students/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $tmpName = $_FILES['profile']['tmp_name'];
    $origName = basename($_FILES['profile']['name']);
    $fileType = mime_content_type($tmpName);
    $fileSize = filesize($tmpName);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2 MB

    if (!in_array($fileType, $allowedTypes)) {
        sendResponse('error', 'Only JPG, PNG, GIF files are allowed.');
    }

    if ($fileSize > $maxSize) {
        sendResponse('error', 'Profile image exceeds 2MB.');
    }

    // Use unique filename
    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    $safeName = uniqid('profile_', true) . "." . $ext;

    if (move_uploaded_file($tmpName, $uploadDir . $safeName)) {
        $profileName = $safeName;
    }
}

// Insert student into database
$insertStmt = $conn->prepare("
    INSERT INTO students (
        fullname, username, student_id, email, phone, password, profile_image, status, year_level
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
");

$insertStmt->bind_param(
    "ssssssss",
    $fullname,
    $username,
    $student_id,
    $email,
    $phone,
    $hashedPassword,
    $profileName,
    $year_level
);

if ($insertStmt->execute()) {
    sendResponse('success', 'Registration successful! Your account is pending approval by the admin.');
} else {
    sendResponse('error', 'Unable to register. ' . $insertStmt->error);
}

$insertStmt->close();
$conn->close();
?>
