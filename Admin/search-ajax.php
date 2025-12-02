<?php
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) { die("Connection failed: " . $mysqli->connect_error); }

$search = trim($_GET['search'] ?? '');
if(!$search){ 
    $result = $mysqli->query("SELECT * FROM research_documents ORDER BY uploaded_at DESC LIMIT 50");
    $data = [];
    while($row = $result->fetch_assoc()) $data[] = $row;
    echo json_encode($data);
    exit;
}

$stmt = $mysqli->prepare("SELECT * FROM research_documents 
    WHERE title LIKE ? OR author LIKE ? OR category LIKE ? OR adviser LIKE ? OR keywords LIKE ? 
    ORDER BY uploaded_at DESC LIMIT 50");
$param = "%$search%";
$stmt->bind_param("sssss",$param,$param,$param,$param,$param);
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while($row = $result->fetch_assoc()) $data[] = $row;
echo json_encode($data);
