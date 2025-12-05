<?php
$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Get data from AJAX
$id = $_POST['id'] ?? null;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "✅ Admin deleted successfully!";
    } else {
        echo "❌ Error deleting admin: " . $conn->error;
    }

    $stmt->close();
} else {
    echo "❌ Invalid request!";
}

$conn->close();
?>
