<?php
if (!isset($_SESSION['faculty_id'])) {
    header("Location: login.php");
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_errno) die("Failed to connect: " . $mysqli->connect_error);

$stmt = $mysqli->prepare("SELECT fullname, department, email, phone, profile_image FROM faculty WHERE faculty_id = ?");
$stmt->bind_param("s", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc() ?? [];
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = htmlspecialchars($_POST['fullname']);
    $department = htmlspecialchars($_POST['department']);
    $email = htmlspecialchars($_POST['email']);
    $contact = htmlspecialchars($_POST['contact']);

    if (!empty($_FILES['profile_image']['name'])) {
        $file = $_FILES['profile_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) {
            $error = "Only JPG, JPEG, PNG files allowed.";
        } else {
           $targetDir = __DIR__ . "/../uploads/faculty/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $newFileName = time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
            move_uploaded_file($file['tmp_name'], $targetDir.$newFileName);
            $profile_image = $newFileName;
        }
    } else {
        $profile_image = $faculty['profile_image'];
    }

    if (!isset($error)) {
        $stmt = $mysqli->prepare("UPDATE faculty SET fullname=?, department=?, email=?, phone=?, profile_image=? WHERE faculty_id=?");
        $stmt->bind_param("ssssss", $fullname, $department, $email, $contact, $profile_image, $faculty_id);
        if ($stmt->execute()) $success = "Profile updated successfully.";
        else $error = "Failed to update profile.";
        $stmt->close();
        $faculty['fullname'] = $fullname;
        $faculty['department'] = $department;
        $faculty['email'] = $email;
        $faculty['phone'] = $contact;
        $faculty['profile_image'] = $profile_image;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $mysqli->prepare("SELECT password FROM faculty WHERE faculty_id=?");
    $stmt->bind_param("s", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($current, $row['password'])) $pw_error = "Current password is incorrect.";
    elseif ($new !== $confirm) $pw_error = "New password and confirm password do not match.";
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE faculty SET password=? WHERE faculty_id=?");
        $stmt->bind_param("ss", $hash, $faculty_id);
        if ($stmt->execute()) $pw_success = "Password changed successfully.";
        else $pw_error = "Failed to change password.";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Faculty Profile & Settings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
.card-header { font-size: 1.5rem; font-weight: 600; color: #8B0000; }
img.profile-pic { width:100px; height:100px; object-fit:cover; border-radius:50%; }
</style>
</head>
<body>
<div class="container">

<div class="card p-4 mb-4">
    <div class="card-header mb-3">Profile Information</div>
    <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3 text-center">
            <img src="../uploads/faculty/<?= htmlspecialchars($faculty['profile_image'] ?? 'default.png'); ?>" class="profile-pic mb-2" alt="Profile">
            <input class="form-control" type="file" name="profile_image" accept="image/*">
        </div>
        <div class="mb-3">
            <label>Full Name</label>
            <input class="form-control" type="text" name="fullname" value="<?= htmlspecialchars($faculty['fullname'] ?? ''); ?>" required>
        </div>
        <div class="mb-3">
            <label>Department</label>
            <input class="form-control" type="text" name="department" value="<?= htmlspecialchars($faculty['department'] ?? ''); ?>" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($faculty['email'] ?? ''); ?>" required>
        </div>
        <div class="mb-3">
            <label>Contact Number</label>
            <input class="form-control" type="text" name="contact" value="<?= htmlspecialchars($faculty['phone'] ?? ''); ?>">
        </div>
        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<div class="card p-4">
    <div class="card-header mb-3">Change Password</div>
    <?php if(isset($pw_success)) echo "<div class='alert alert-success'>$pw_success</div>"; ?>
    <?php if(isset($pw_error)) echo "<div class='alert alert-danger'>$pw_error</div>"; ?>
    <form method="POST">
        <div class="mb-3">
            <label>Current Password</label>
            <input class="form-control" type="password" name="current_password" required>
        </div>
        <div class="mb-3">
            <label>New Password</label>
            <input class="form-control" type="password" name="new_password" required>
        </div>
        <div class="mb-3">
            <label>Confirm New Password</label>
            <input class="form-control" type="password" name="confirm_password" required>
        </div>
        <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
    </form>
</div>

</div>
</body>
</html>
