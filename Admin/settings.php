<?php
if (!isset($_SESSION['admin_id'])) {
    header("Location: Adminlogin.php");
    exit();
}

$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Fetch admin profile
$adminData = null;
$stmt = $mysqli->prepare("SELECT fullname, email, profile_image FROM admins WHERE id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$adminData = $result->fetch_assoc();
$stmt->close();

if (!$adminData) {
    $adminData = [
        "fullname" => "Administrator",
        "email" => "admin@example.com",
        "profile_image" => "Photos/logoCtu.png"
    ];
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $pageLength = $_POST['page_length'] ?? 5;
    $defaultArchive = $_POST['default_archive'] ?? 'research';
    $themeColor = $_POST['theme_color'] ?? '#8b0000';

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'Photos/admins/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = basename($_FILES['profile_image']['name']);
        $targetFile = $uploadDir . time() . '_' . $filename;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile);
        $adminData['profile_image'] = $targetFile;
    }

    $updateQuery = "UPDATE admins SET fullname=?, email=?, profile_image=?";
    $types = "sss";
    $params = [$fullname, $email, $adminData['profile_image']];

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $updateQuery .= ", password=?";
        $types .= "s";
        $params[] = $hashed;
    }
    $updateQuery .= " WHERE id=?";
    $types .= "i";
    $params[] = $_SESSION['admin_id'];

    $stmt = $mysqli->prepare($updateQuery);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $message = "Profile updated successfully!";
    } else {
        $message = "Error updating profile.";
    }
    $stmt->close();

    $stmt = $mysqli->prepare("REPLACE INTO system_settings (`setting_key`, `setting_value`) VALUES
        ('page_length', ?),
        ('default_archive', ?),
        ('theme_color', ?)
    ");
    $stmt->bind_param("iss", $pageLength, $defaultArchive, $themeColor);
    $stmt->execute();
    $stmt->close();
}

$settings = [];
$result = $mysqli->query("SELECT * FROM system_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$pageLength = $settings['page_length'] ?? 5;
$defaultArchive = $settings['default_archive'] ?? 'research';
$themeColor = $settings['theme_color'] ?? '#8b0000';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Settings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card { padding: 20px; margin-bottom: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
img.profile-img { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; margin-bottom: 10px; }
label { font-weight: 600; }
</style>
</head>
<body>
<div class="container">

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <h2>Admin Profile Management</h2>
    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <div class="text-center">
                <img src="<?= htmlspecialchars($adminData['profile_image']) ?>" alt="Profile" class="profile-img">
            </div>
            <div class="mb-3">
                <label>Full Name</label>
                <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($adminData['fullname']) ?>" required>
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($adminData['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label>New Password <small>(leave blank to keep current)</small></label>
                <input type="password" name="password" class="form-control">
            </div>
            <div class="mb-3">
                <label>Profile Image</label>
                <input type="file" name="profile_image" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>

    <h2>System Preferences</h2>
    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label>Default Page Length (for tables)</label>
                <input type="number" name="page_length" class="form-control" value="<?= htmlspecialchars($pageLength) ?>" min="1">
            </div>
            <div class="mb-3">
                <label>Default Archive Type</label>
                <select name="default_archive" class="form-select">
                    <option value="research" <?= $defaultArchive=='research'?'selected':'' ?>>Research</option>
                    <option value="students" <?= $defaultArchive=='students'?'selected':'' ?>>Students</option>
                    <option value="faculty" <?= $defaultArchive=='faculty'?'selected':'' ?>>Faculty</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Theme Color</label>
                <input type="color" name="theme_color" class="form-control form-control-color" value="<?= htmlspecialchars($themeColor) ?>">
            </div>
            <button type="submit" class="btn btn-success">Save Preferences</button>
        </form>
    </div>

    <h2>Security Settings</h2>
    <div class="card">
        <p>Creating new password must meet this criteria</p>
        <ul>
            <li>Password should be at least 8 characters.</li>
            <li>Password must include numbers and letters.</li>
            <li>Two-factor authentication (2FA)</li>
        </ul>
    </div>

</div>
</body>
</html>
