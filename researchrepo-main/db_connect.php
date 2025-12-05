<?php
$host = "sql207.infinityfree.com";
$username = "if0_40577910";
$password = "CTURepo2025";
$database = "if0_40577910_repo_db";

// Use the variables
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
