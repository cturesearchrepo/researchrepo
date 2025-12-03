<?php
$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$id       = $_POST['id'] ?? null;
$fullname = $_POST['fullname'] ?? null;
$role     = $_POST['role'] ?? null;

if ($id && $fullname && $role) {
    $stmt = $conn->prepare("UPDATE admins SET fullname = ?, role = ? WHERE id = ?");
    $stmt->bind_param("ssi", $fullname, $role, $id);

    if ($stmt->execute()) {
        echo "✅ Admin updated successfully!";
    } else {
        echo "❌ Error updating admin: " . $conn->error;
    }

    $stmt->close();
} else {
    echo "❌ Invalid input data!";
}

$conn->close();
?>
