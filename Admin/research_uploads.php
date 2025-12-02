<?php
// fetch_research.php
header("Content-Type: application/json");

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["ok" => false, "message" => "DB connection failed"]);
    exit();
}

$sql = "SELECT id, title, author, year_completed, adviser, course, department, section, research_type, approval_status, visibility, file_path, abstract 
        FROM research_documents
        ORDER BY uploaded_at DESC";

$result = $conn->query($sql);

$documents = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}

echo json_encode([
    "ok" => true,
    "count" => count($documents),
    "documents" => $documents
]);

$conn->close();
?>
