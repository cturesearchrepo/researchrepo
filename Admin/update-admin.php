<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
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
