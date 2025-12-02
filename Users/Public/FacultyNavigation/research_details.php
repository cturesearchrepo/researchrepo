<?php
session_start();
$facultyId = intval($_SESSION['faculty_id'] ?? 0);
if (!$facultyId) die("Unauthorized access");

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

$researchId = intval($_GET['id'] ?? 0);

$sql = "
  SELECT r.*, c.name AS category_name,
         COALESCE(NULLIF(r.adviser, ''), f.fullname) AS adviser_name
  FROM research_documents r
  LEFT JOIN categories c ON r.category_id = c.id
  LEFT JOIN faculty f ON r.faculty_id = f.id
  WHERE r.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $researchId);
$stmt->execute();
$result = $stmt->get_result();
$research = $result->fetch_assoc();
$stmt->close();

$panelists = [];
$panelSql = "
    SELECT f.fullname 
    FROM research_reviewers rr
    LEFT JOIN faculty f ON rr.reviewer_id = f.id
    WHERE rr.research_id = ?
";
$panelStmt = $conn->prepare($panelSql);
$panelStmt->bind_param("i", $researchId);
$panelStmt->execute();
$panelResult = $panelStmt->get_result();
while($p = $panelResult->fetch_assoc()){
    $panelists[] = $p['fullname'];
}
$panelStmt->close();

$rating = 0;
$ratingCount = 0;
$ratingSql = "SELECT AVG(rating) as avg_rating, COUNT(*) as count_rating FROM ratings WHERE research_id = ?";
$rateStmt = $conn->prepare($ratingSql);
$rateStmt->bind_param("i", $researchId);
$rateStmt->execute();
$rateStmt->bind_result($avgRating, $countRating);
if ($rateStmt->fetch()) {
    $rating = round($avgRating, 1);
    $ratingCount = $countRating;
}
$rateStmt->close();

$viewCount = 0;
$viewStmt = $conn->prepare("SELECT COUNT(*) as total_views FROM research_views WHERE research_id = ?");
$viewStmt->bind_param("i", $researchId);
$viewStmt->execute();
$viewStmt->bind_result($viewCount);
$viewStmt->fetch();
$viewStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($research['title'] ?? 'Research Details') ?> | CTU Library</title>
<style>
body { font-family: 'Segoe UI', Arial, sans-serif; margin:0; padding:0; background:#f5f7fa; }
.container { max-width:1100px; margin:30px auto; padding:20px; }
.research-details {background:#fff; padding:40px; border-radius:16px; box-shadow:0 6px 18px rgba(0,0,0,0.08); }
h2 { margin-bottom:20px; color:#2c3e50; font-size:28px; }
.row { display:flex; flex-wrap:wrap; gap:60px; }
.col-left { flex:1; min-width:280px; }
.col-right { flex:1.2; min-width:320px; }

.abstract-box {
  background:#fafafa;
  border:1px solid #e5e5e5;
  border-radius:12px;
  padding:20px;
  font-size:15px;
  margin-bottom:20px;
}
.abstract-box h3 { margin-bottom:10px; color:#2c3e50; font-size:18px; }

.info-box {
  background:#fafafa;
  border:1px solid #e5e5e5;
  border-radius:12px;
  padding:20px;
}
.info-box h3 {
  margin-bottom:15px;
  font-size:18px;
  color:#2c3e50;
  border-bottom:1px solid #e5e5e5;
  padding-bottom:8px;
}
.info-item { margin-bottom:12px; font-size:15px; }
.info-item strong { color:#34495e; display:inline-block; width:150px; }

.star-rating { display:inline-flex; gap:4px; font-size:18px; vertical-align:middle; }
.star.filled { color:#f5b301; }

.back-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #8B0000;
    color: #fff;
    padding: 6px 10px;
    border-radius: 6px;
    text-decoration: none;
    transition: background-color 0.2s, transform 0.2s;
}
.back-btn:hover {
    background-color: #a50000; 
    transform: translateX(-2px);
}
.back-btn svg { width: 22px; height: 22px; transition: transform 0.2s; }
.back-btn:hover svg { transform: translateX(-3px); }
</style>
</head>
<body>
<main class="container">
<?php if ($research): ?>
<div class="research-details">
  <h2 style="display:flex; align-items:center; gap:10px;">
    <a href="javascript:history.back()" class="back-btn" aria-label="Go back">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
    </a>
    <span><?= htmlspecialchars($research['title']) ?></span>
  </h2>

  <div class="row">
    <div class="col-left">   
      <div class="abstract-box">
        <h3>Abstract</h3>
        <p><?= nl2br(htmlspecialchars($research['abstract'])) ?></p>
      </div>
      <div class="info-item"><strong>Author:</strong> <?= htmlspecialchars($research['author']) ?></div>
    </div>

    <div class="col-right">
      <div class="info-box">
        <h3>Research Information</h3>
        <div class="info-item"><strong>Adviser:</strong> <?= htmlspecialchars($research['adviser_name'] ?? '-') ?></div>
        <div class="info-item"><strong>Panelists:</strong> <?= htmlspecialchars(!empty($panelists) ? implode(', ', $panelists) : '-') ?></div>
        <div class="info-item"><strong>School Year:</strong> <?= htmlspecialchars($research['year_completed']) ?></div>
        <div class="info-item"><strong>Category:</strong> <?= htmlspecialchars($research['category_name'] ?? 'Uncategorized') ?></div>
        <div class="info-item"><strong>Course:</strong> <?= htmlspecialchars($research['course']) ?></div>
        <div class="info-item"><strong>Department:</strong> <?= htmlspecialchars($research['department']) ?></div>
        <div class="info-item"><strong>Research Type:</strong> <?= htmlspecialchars($research['research_type']) ?></div>
        <div class="info-item"><strong>Views:</strong> <?= htmlspecialchars($viewCount) ?></div>
        <div class="info-item"><strong>Keywords:</strong> <?= htmlspecialchars($research['keywords']) ?></div>
        <div class="info-item">
          <strong>Rating:</strong> 
          <span class="star-rating">
              <?php for ($i=1; $i<=5; $i++): ?>
                <span class="star <?= ($i <= round($rating))?'filled':'' ?>"><?= ($i <= round($rating))?'★':'☆' ?></span>
              <?php endfor; ?>
          </span>
          <span>(<?= $rating ?> avg from <?= $ratingCount ?> ratings)</span>
        </div>
      </div>

      <div class="actions">
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<p>Research not found.</p>
<?php endif; ?>
</main>
</body>
</html>
