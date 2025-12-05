<?php

const DB_HOST = "sql207.infinityfree.com";
const DB_USER = "if0_40577910";
const DB_PASS = "CTURepo2025";
const DB_NAME = "if0_40577910_repo_db";

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed.']));
}


function sendResponse($status, $message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// =========================
// COLLECT POST DATA
// =========================
$fullname   = trim($_POST['fullname'] ?? '');
$username   = trim($_POST['username'] ?? '');
$student_id = trim($_POST['studentid'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['number'] ?? '');
$password   = $_POST['password'] ?? '';
$year_level = $_POST['year_level'] ?? 'first';

// =========================
// VALIDATION
// =========================
if (!$fullname || !$username || !$student_id || !$email || !$phone || !$password) {
    sendResponse('error', 'All fields are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format.');
}

if (!preg_match('/^[0-9]{8,10}$/', $student_id)) {
    sendResponse('error', 'Invalid Student ID format.');
}

// Year level mapping
$yearOptions = [
    "first"  => "First Year",
    "second" => "Second Year",
    "third"  => "Third Year",
    "fourth" => "Fourth Year"
];
$year_level = $yearOptions[strtolower($year_level)] ?? "First Year";

// =========================
// CHECK DUPLICATES
// =========================
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

// =========================
// PASSWORD HASHING
// =========================
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// =========================
// PROFILE IMAGE HANDLING
// =========================
$profileName = "logoCtu.png";

if (isset($_FILES['profile']) && $_FILES['profile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . "/uploads/students/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $tmpName = $_FILES['profile']['tmp_name'];
    $origName = basename($_FILES['profile']['name']);
    $fileType = mime_content_type($tmpName);
    $fileSize = filesize($tmpName);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024;

    if (!in_array($fileType, $allowedTypes)) {
        sendResponse('error', 'Only JPG, PNG, GIF files are allowed.');
    }

    if ($fileSize > $maxSize) {
        sendResponse('error', 'Profile image exceeds 2MB.');
    }

    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    $safeName = uniqid('profile_', true) . "." . $ext;

    if (move_uploaded_file($tmpName, $uploadDir . $safeName)) {
        $profileName = $safeName;
    }
}

// =========================
// INSERT INTO DATABASE
// =========================
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
