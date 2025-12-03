<?php
$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $id = $_POST['id'];
  $usertype = $_POST['usertype'];
  $action = $_POST['action'];

  $table = $usertype === 'student' ? 'students' : 'faculty';

  if ($action === 'approve') {
    $sql = "UPDATE $table SET status='active' WHERE id=?";
  } elseif ($action === 'suspend') {
    $sql = "UPDATE $table SET status='suspended' WHERE id=?";
  } elseif ($action === 'remove') {
    $sql = "DELETE FROM $table WHERE id=?";
  }

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
}

$conn->close();
?>
