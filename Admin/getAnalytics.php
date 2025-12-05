<?php
header("Content-Type: application/json");

$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die(json_encode(["error" => $mysqli->connect_error]));
}

$type = $_GET['type'] ?? 'approved';
$range = $_GET['range'] ?? '6';
$startDate = $_GET['startDate'] ?? null;
$endDate   = $_GET['endDate'] ?? null;

$dateFilter = "";

if ($range === "custom" && $startDate && $endDate) {
    $dateFilter = "AND uploaded_at BETWEEN '$startDate' AND '$endDate'";
} else {
    $dateFilter = "AND uploaded_at >= DATE_SUB(CURDATE(), INTERVAL $range MONTH)";
}

switch ($type) {
    case "approved":
        $statusCondition = "status = 'ApprovedbyAdmin'";
        break;
    case "pending":
        $statusCondition = "status = 'Pending'";
        break;
    case "rejected":
        $statusCondition = "status = 'RejectedbyAdmin'";
        break;
    case "total":
        $statusCondition = "1=1";
        break;
    case "recent":
        $statusCondition = "1=1";
        break;
    default:
        echo json_encode(["labels" => [], "data" => []]);
        exit;
}

if ($type === "recent") {
    $sql = "
        SELECT DAYNAME(uploaded_at) as day, COUNT(*) as count
        FROM research_documents
        WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND $statusCondition
        GROUP BY DAYNAME(uploaded_at)
        ORDER BY FIELD(DAYNAME(uploaded_at),
            'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')
    ";
} else {
    $sql = "
        SELECT DATE_FORMAT(uploaded_at, '%Y-%m') as month, COUNT(*) as count
        FROM research_documents
        WHERE $statusCondition $dateFilter
        GROUP BY DATE_FORMAT(uploaded_at, '%Y-%m')
        ORDER BY month ASC
    ";
}

$result = $mysqli->query($sql);
$labels = [];
$data = [];
while ($row = $result->fetch_assoc()) {
    $labels[] = $row['month'] ?? $row['day'];
    $data[]   = $row['count'];
}

echo json_encode([
    "labels" => $labels,
    "data"   => $data
]);
