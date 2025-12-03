<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

try {
    $conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
    if ($conn->connect_error) {
        throw new Exception("DB connection failed: " . $conn->connect_error);
    }

    // inputs
    $fullname = trim($_POST['fullname'] ?? '');
    $user     = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $confirm  = $_POST['confirmPassword'] ?? '';
    $role     = $_POST['role'] ?? '';
    $avatar   = $_POST['selectedAvatar'] ?? ''; // avatar option from dropdown/radio

    // validations
    if (!$fullname || !$user || !$email || !$pass || !$confirm || !$role) {
        throw new Exception("Please fill in all required fields.");
    }

    if ($pass !== $confirm) {
        throw new Exception("Passwords do not match.");
    }

    $allowedRoles = ['Super Admin', 'Editor', 'Viewer'];
    if (!in_array($role, $allowedRoles, true)) {
        throw new Exception("Invalid role selected.");
    }

    // check duplicates
    $stmt = $conn->prepare("SELECT id, username, email FROM admins WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $user, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['username'] === $user) {
            throw new Exception("Username already exists. Please choose another.");
        } elseif ($row['email'] === $email) {
            throw new Exception("Email already exists. Please use another email.");
        }
    }
    $stmt->close();

    $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

    // ✅ Handle profile image
    $profilePath = "uploads/admins/default-avatar.png"; // default avatar

    // 1) If user uploaded their own image
    if (isset($_FILES['adminProfilePic']) && $_FILES['adminProfilePic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/admins/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $tmpName  = $_FILES['adminProfilePic']['tmp_name'];
        $origName = basename($_FILES['adminProfilePic']['name']);
        $safeName = time() . '_' . preg_replace("/[^A-Za-z0-9\._-]/", "_", $origName);
        $targetPath = $uploadDir . $safeName;

        if (move_uploaded_file($tmpName, $targetPath)) {
            $profilePath = $targetPath;
        }
    }
    // 2) If user selected an avatar instead
    elseif ($avatar !== '') {
        $avatarPath = "uploads/admins/avatars/" . basename($avatar);
        if (file_exists($avatarPath)) {
            $profilePath = $avatarPath;
        }
    }
    // else: remains default avatar

    // insert
    $stmt = $conn->prepare("
        INSERT INTO admins (fullname, username, email, password_hash, role, profile_image)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ssssss", $fullname, $user, $email, $hashedPassword, $role, $profilePath);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "New admin added"]);
    } else {
        throw new Exception("Database insert failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
