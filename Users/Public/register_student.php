<?php
include __DIR__ . '/../../db_connect.php';

$fullname   = trim($_POST['fullname'] ?? '');
$username   = trim($_POST['username'] ?? '');
$student_id = trim($_POST['studentid'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['number'] ?? '');
$password   = $_POST['password'] ?? '';
$year_level = $_POST['year_level'] ?? 'first';

$yearOptions = [
    "first"  => "First Year",
    "second" => "Second Year",
    "third"  => "Third Year",
    "fourth" => "Fourth Year"
];
$year_level = $yearOptions[$year_level] ?? "First Year";

if (!$fullname || !$username || !$student_id || !$email || !$phone || !$password) {
    exit("Error: All fields are required.");
}

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

    exit("Oops :( " . implode(", ", $conflict) . " already exist" . (count($conflict) > 1 ? "" : "s") . ".");
}
$checkStmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$profileName = "logoCtu.png"; 

if (isset($_FILES['profile']) && $_FILES['profile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . "/uploads/students/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $tmpName = $_FILES['profile']['tmp_name'];
    $origName = basename($_FILES['profile']['name']);
    $safeName = preg_replace("/[^A-Za-z0-9\._-]/", "_", $origName); 

    if (move_uploaded_file($tmpName, $uploadDir . $safeName)) {
        $profileName = $safeName; 
    }
}

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
    echo "Registration successful! Your account is pending approval by the admin.";
} else {
    echo "Error: Unable to register. " . $insertStmt->error;
}

$insertStmt->close();
$conn->close();
?>
