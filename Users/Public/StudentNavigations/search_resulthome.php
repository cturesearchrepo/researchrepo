<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $sql = "
        SELECT r.id, r.title, r.author, c.name AS category, r.keywords
        FROM research_documents r
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE r.status IN ('Active','ApprovedbyAdmin')
          AND (
                r.title LIKE ? 
                OR r.author LIKE ? 
                OR c.name LIKE ?
                OR r.keywords LIKE ?
              )
        ORDER BY r.title ASC
        LIMIT 15
    ";

    $stmt = $conn->prepare($sql);
    if(!$stmt){
        echo json_encode(["error" => "SQL error: " . $conn->error]);
        exit;
    }

    $like = "%" . $q . "%";
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $results[] = $row;
    }
}

echo json_encode($results);
$conn->close();
?>
