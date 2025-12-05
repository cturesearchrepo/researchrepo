<?php
include __DIR__ . '/../../db_connect.php';

function sanitize($conn, $input) {
    return htmlspecialchars(mysqli_real_escape_string($conn, trim($input)));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname   = sanitize($conn, $_POST["fullname"] ?? "");
    $username   = sanitize($conn, $_POST["username"] ?? "");
    $facultyid  = sanitize($conn, $_POST["facultyid"] ?? "");
    $email      = sanitize($conn, $_POST["email"] ?? "");
    $phone      = sanitize($conn, $_POST["phone"] ?? "");
    $password   = $_POST["password"] ?? "";
    $department = sanitize($conn, $_POST["department"] ?? "");

    if (strlen($fullname) < 10) {
        echo "Fullname must be at least 10 characters.";
        exit;
    }
    if (strlen($username) < 10 || strlen($username) > 15) {
        echo "Username must be between 10 and 15 characters.";
        exit;
    }
    if (!ctype_digit($facultyid)) {
        echo "Faculty ID must be numeric.";
        exit;
    }
    if (!preg_match("/^[a-zA-Z0-9._%+-]+@gmail\.com$/", $email)) {
        echo "Email must be a valid Gmail address (e.g., example@gmail.com).";
        exit;
    }
    if (!preg_match("/^\d{11}$/", $phone)) {
        echo "Phone number must be exactly 11 digits.";
        exit;
    }
    if (strlen($password) < 18 ||
        !preg_match("/[A-Z]/", $password) ||
        !preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password) ||
        !preg_match("/[0-9]/", $password)) {
        echo "Password must be strong and meet all requirements.";
        exit;
    }

    $checkQuery = "SELECT * FROM faculty WHERE username=? OR faculty_id=? OR email=?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("sss", $username, $facultyid, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo "Username, Faculty ID, or Email already exists.";
        exit;
    }
    $stmt->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$uploadBase = __DIR__ . "/uploads/";
$userType = 'faculty';
$uploadDir = $uploadBase . $userType . "/";

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die("❌ Failed to create folder '$uploadDir'. Check folder permissions.");
    }
}

$profileFileName = 'logoCtu.png'; 

if (isset($_FILES['profile']) && $_FILES['profile']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['profile']['tmp_name'];
    $originalName = basename($_FILES['profile']['name']);
    $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];

    if (in_array($fileExt, $allowed)) {
        $cleanName = preg_replace("/[^a-zA-Z0-9\-_.]/", "", $originalName);
        $targetPath = $uploadDir . $cleanName;

        if (move_uploaded_file($fileTmp, $targetPath)) {
            $profileFileName = $cleanName; 
        } else {
            error_log("❌ Failed to move uploaded file.");
        }
    } else {
        error_log("❌ Invalid file type: $fileExt");
    }
}



    // ✅ Insert faculty with status = 'pending'
    $insertQuery = "
        INSERT INTO faculty (
            fullname, username, faculty_id, email, phone, password, department, profile_image, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("ssssssss", $fullname, $username, $facultyid, $email, $phone, $hashedPassword, $department, $profileFileName);

    if ($stmt->execute()) {
        echo "Registration successful! Your account is pending approval by the admin.";
    } else {
        echo "Error: Unable to register. Please try again later.";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Invalid request.";
}
?>
