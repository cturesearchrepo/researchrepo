<?php
session_start();

// Only allow students
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['student_id'];
$table   = 'student_preferences';
$id_col  = 'student_id';
$redirect = '../Public/StudentNavigations/Student_Dashboard.php';

// Database connection
$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) die("DB connection failed: ".$conn->connect_error);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize department input
    $department = trim($_POST['department'] ?? '');

    // Sanitize and filter interests
    $interests = $_POST['interests'] ?? [];
    $interests = array_filter(array_map('trim', (array)$interests));

    // Validate number of interests
    if (count($interests) < 3 || count($interests) > 5) {
        echo "<script>alert('Please select between 3 and 5 research interests.'); window.history.back();</script>";
        exit;
    }
    $interests_str = implode(',', $interests);

    // Validate theme
    $theme = in_array($_POST['theme'] ?? '', ['Light','Dark']) ? $_POST['theme'] : 'Light';

    // Check if preferences already exist
    $stmt = $conn->prepare("SELECT id FROM $table WHERE $id_col = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if ($exists) {
        // Update existing preferences
        $upd = $conn->prepare("UPDATE $table SET department=?, interests=?, theme=? WHERE $id_col=?");
        $upd->bind_param('sssi', $department, $interests_str, $theme, $user_id);
        $ok = $upd->execute();
        $upd->close();
    } else {
        // Insert new preferences
        $ins = $conn->prepare("INSERT INTO $table ($id_col, department, interests, theme) VALUES (?, ?, ?, ?)");
        $ins->bind_param('isss', $user_id, $department, $interests_str, $theme);
        $ok = $ins->execute();
        $ins->close();
    }

    $conn->close();

    if ($ok) {
        header("Location: $redirect");
        exit;
    } else {
        echo "❌ Failed to save preferences. Please try again.";
    }
}
?>
