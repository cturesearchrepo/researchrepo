<?php
$host = "sql207.infinityfree.com";
$username = "if0_40577910";
$password = "CTURepo2025";
$database = "if0_40577910_repo_db";

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}?>
