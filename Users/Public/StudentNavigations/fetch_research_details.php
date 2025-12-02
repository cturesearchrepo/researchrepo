<?php
session_start();
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die(json_encode(["error"=>"DB connection failed"])); }

$researchId = intval($_GET['id'] ?? 0);
$data = [];

$sql = "SELECT r.*, c.name AS category_name,
           COALESCE(NULLIF(r.adviser,''), f.fullname) AS adviser_name
        FROM research_documents r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN faculty f ON r.faculty_id = f.id
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $researchId);
$stmt->execute();
$res = $stmt->get_result();
$research = $res->fetch_assoc();
$stmt->close();

if($research){
    $panelists=[];
    $pstmt=$conn->prepare("SELECT f.fullname FROM research_reviewers rr LEFT JOIN faculty f ON rr.reviewer_id=f.id WHERE rr.research_id=?");
    $pstmt->bind_param("i",$researchId);
    $pstmt->execute();
    $pr=$pstmt->get_result();
    while($row=$pr->fetch_assoc()){ $panelists[]=$row['fullname']; }
    $pstmt->close();

    $rating=0; $ratingCount=0;
    $rs=$conn->prepare("SELECT AVG(rating), COUNT(*) FROM ratings WHERE research_id=?");
    $rs->bind_param("i",$researchId);
    $rs->execute();
    $rs->bind_result($avg,$cnt);
    if($rs->fetch()){ $rating=round($avg,1); $ratingCount=$cnt; }
    $rs->close();

    $views=0;
    $vs=$conn->prepare("SELECT COUNT(*) FROM research_views WHERE research_id=?");
    $vs->bind_param("i",$researchId);
    $vs->execute();
    $vs->bind_result($views);
    $vs->fetch();
    $vs->close();

    $data = [
        "id"=>$research['id'],
        "title"=>$research['title'],
        "author"=>$research['author'],
        "abstract"=>$research['abstract'],
        "adviser"=>$research['adviser_name'],
        "panelists"=>$panelists,
        "year_completed"=>$research['year_completed'],
        "category"=>$research['category_name'] ?? 'Uncategorized',
        "course"=>$research['course'],
        "department"=>$research['department'],
        "research_type"=>$research['research_type'],
        "keywords"=>$research['keywords'],
        "views"=>$views,
        "rating"=>$rating,
        "ratingCount"=>$ratingCount
    ];
}

echo json_encode($data);
$conn->close();
?>
