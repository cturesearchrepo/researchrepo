<?php
$userName = $_SESSION['user_name'] ?? 'Admin';

$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$totalStudents = $mysqli->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'")->fetch_assoc()['total'];
$result = $mysqli->query("
    SELECT 
        CASE 
            WHEN status IN ('ApprovedbyAdmin','Active') THEN 'approved'
            WHEN status = 'RejectedbyAdmin' THEN 'rejected'
            WHEN status = 'pending' THEN 'pending'
            ELSE 'other'
        END as mapped_status,
        COUNT(*) as count
    FROM research_documents
    GROUP BY mapped_status
");

$approvalCounts = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
while ($row = $result->fetch_assoc()) {
    $approvalCounts[$row['mapped_status']] = $row['count'];
}


$totalResearch = $mysqli->query("SELECT COUNT(*) as total FROM research_documents")->fetch_assoc()['total'];

$recentUploads = $mysqli->query("
    SELECT COUNT(*) as count 
    FROM research_documents 
    WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch_assoc()['count'];

$topViewed = $mysqli->query("
    SELECT r.id, r.title, r.adviser, r.year_completed, COUNT(v.id) AS total_views,
           IFNULL(AVG(rt.rating), 0) AS avg_rating, COUNT(rt.rating) AS rating_count
    FROM research_documents r
    LEFT JOIN research_views v ON r.id = v.research_id
    LEFT JOIN ratings rt ON r.id = rt.research_id
    WHERE r.status IN ('ApprovedbyAdmin','Active')
    GROUP BY r.id
    ORDER BY total_views DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .top-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .top-header h2 { margin:0; }
    .search-input { padding:5px; border-radius:4px; border:1px solid #ccc; }
    .notif-icon { font-size:18px; cursor:pointer; margin-left:10px; }
    .quick-actions a { display:flex; align-items:center; justify-content:center; gap:5px; }
    .top-cards { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
    .card { padding:8px; border-radius:6px; text-align:center; flex:1; min-width:120px; background:#f3f3f3; }
    .most-viewed table { width:100%; border-collapse: collapse; margin-top:10px; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05);}
    .most-viewed th, .most-viewed td { border:1px solid #ddd; padding:8px; text-align:left; }
    .most-viewed th { background:#f0f0f0; }
    .view-btn { padding:4px 6px; border:none; background:#2563eb; color:#fff; border-radius:4px; cursor:pointer; }
    .welcome-section { margin-bottom:20px; padding:15px; background:#e0f2fe; border-radius:8px; }
    .tips-section { margin-bottom:20px; padding:15px; background:#fff3cd; border-left:5px solid #ffeeba; border-radius:6px; }
    .notif-icon {font-size: 22px;cursor: pointer;margin-left: 10px;color: #2563eb; position: relative;transition: transform 0.2s;}
    .notif-icon:hover {transform: scale(1.2);}
    .notif-icon::after {content: '';position: absolute;top: 0px;right: 0px;width: 8px;height: 8px;background: red;border-radius: 50%;border: 1px solid #fff;display: inline-block; }
</style>
</head>
<body>

<header class="top-header">
    <h2>Home</h2>
    <div class="top-right">
        <div class="notif-icon"><i class="fa-solid fa-bell"></i></div>
    </div>
</header>

<section class="tips-section">
    <strong>Tip:</strong> Remember to review pending research submissions daily and generate weekly reports to monitor student activities effectively.
</section>

<section class="welcome-section">
    <h3>Welcome, <?= htmlspecialchars($userName) ?>!</h3>
    <p>
        This system helps you manage research documents efficiently, monitor approvals, 
        and keep track of recent uploads. Stay updated with the latest student submissions.
    </p>
</section>

<section class="quick-actions" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
    <a href="AdminDashboard.php?page=manage-categories" class="action-btn" style="flex:1; min-width:120px; padding:8px; border-radius:6px; background:#2563eb; color:#fff; text-align:center; text-decoration:none;">
         <i class="fa-solid fa-layer-group"></i> Manage Categories
    </a>

    <a href="AdminDashboard.php?page=generate-reports" class="action-btn" style="flex:1; min-width:120px; padding:8px; border-radius:6px; background:#16a34a; color:#fff; text-align:center; text-decoration:none;">
        <i class="fa-solid fa-chart-line"></i> Analytics Reports
    </a>
</section>

<section class="top-cards">

    <div class="card card-blue" data-type="approved">
        <h5 style="font-size:12px; margin:0 0 2px;">Approved Research</h5>
        <p style="font-size:16px; font-weight:bold; color:#10b981; margin:0;"><?= $approvalCounts['approved'] ?? 0 ?></p>
        <canvas id="sparkApproved" height="30"></canvas>
    </div>

    <div class="card card-yellow" data-type="pending">
        <h5 style="font-size:12px; margin:0 0 2px;">Pending Research</h5>
        <p style="font-size:16px; font-weight:bold; color:#f59e0b; margin:0;"><?= $approvalCounts['pending'] ?? 0 ?></p>
        <canvas id="sparkPending" height="30"></canvas>
    </div>

    <div class="card card-red" data-type="rejected">
        <h5 style="font-size:12px; margin:0 0 2px;">Rejected Research</h5>
        <p style="font-size:16px; font-weight:bold; color:#ef4444; margin:0;"><?= $approvalCounts['rejected'] ?? 0 ?></p>
        <canvas id="sparkRejected" height="30"></canvas>
    </div>

    <div class="card card-green" data-type="total">
        <h5 style="font-size:12px; margin:0 0 2px;">Total Research</h5>
        <p style="font-size:16px; font-weight:bold; color:#3b82f6; margin:0;"><?= $totalResearch ?></p>
        <canvas id="sparkTotal" height="30"></canvas>
    </div>

    <div class="card card-purple" data-type="recent">
        <h5 style="font-size:12px; margin:0 0 2px;">Recent Uploads</h5>
        <p style="font-size:16px; font-weight:bold; color:#8b5cf6; margin:0;"><?= $recentUploads ?></p>
        <canvas id="sparkRecent" height="30"></canvas>
    </div>

</section>

<section id="analytics-section" style="display:none; margin-top:30px; background:#fff; padding:15px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <h3 id="analytics-title">Analytics Report</h3>

        <div>
            <select id="filter-range" style="padding:4px; border-radius:4px;">
                <option value="6">Last 6 Months</option>
                <option value="12">Last 12 Months</option>
                <option value="custom">Custom Range</option>
            </select>

            <span id="custom-range" style="display:none;">
                <input type="date" id="startDate"> -
                <input type="date" id="endDate">
                <button id="applyFilter" style="padding:4px 8px; background:#2563eb; color:#fff; border:none; border-radius:4px; cursor:pointer;">Apply</button>
            </span>
        </div>
    </div>

    <canvas id="analyticsChart" height="100"></canvas>
</section>



<section class="most-viewed">
    <h3>Top 5 Most Viewed Research</h3>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Adviser/s</th>
                <th>Year</th>
                <th>Views</th>
                <th>Rating</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topViewed as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['adviser']) ?></td>
                <td><?= htmlspecialchars($row['year_completed']) ?></td>
                <td><?= $row['total_views'] ?></td>
                <td>
                    <?php 
                        $avg = round($row['avg_rating'], 1);
                        $fullStars = floor($avg);
                        $halfStar = ($avg - $fullStars) >= 0.5 ? 1 : 0;
                        $emptyStars = 5 - $fullStars - $halfStar;

                        for ($i=0; $i<$fullStars; $i++) echo '★';
                        if ($halfStar) echo '☆';
                        for ($i=0; $i<$emptyStars; $i++) echo '✩';

                        echo " ({$row['rating_count']})";
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>




<script>
function createSpark(ctx, data, color) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map((_, i)=>i+1),
            datasets: [{
                data: data,
                borderColor: color,
                backgroundColor: 'transparent',
                tension: 0.3,
                pointRadius: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false }, 
                tooltip: { enabled: true, mode: 'index', intersect: false } 
            },
            scales: { y: { display: false }, x: { display: false } },
            elements: { line: { borderWidth: 2 } }
        }
    });
}

createSpark(document.getElementById('sparkApproved'), [5,10,7,8,6], '#10b981');
createSpark(document.getElementById('sparkPending'), [3,2,4,1,5], '#f59e0b');
createSpark(document.getElementById('sparkRejected'), [1,0,2,1,0], '#ef4444');
createSpark(document.getElementById('sparkTotal'), [10,15,12,14,11], '#3b82f6');
createSpark(document.getElementById('sparkRecent'), [2,3,1,4,2], '#8b5cf6');
let analyticsChart = null;
let currentType = null; 

async function loadAnalytics(type, title, color) {
    currentType = type;

    const range = document.getElementById("filter-range").value;
    let url = `getAnalytics.php?type=${type}&range=${range}`;

    if (range === "custom") {
        const startDate = document.getElementById("startDate").value;
        const endDate = document.getElementById("endDate").value;
        if (startDate && endDate) {
            url += `&startDate=${startDate}&endDate=${endDate}`;
        }
    }

    const response = await fetch(url);
    const result = await response.json();

    document.getElementById("analytics-section").style.display = "block";
    document.getElementById("analytics-title").textContent = title;

    if (analyticsChart) analyticsChart.destroy();

    const chartType = (type === "recent") ? "bar" : "line";

    const ctx = document.getElementById("analyticsChart").getContext("2d");
    analyticsChart = new Chart(ctx, {
        type: chartType,
        data: {
            labels: result.labels,
            datasets: [{
                label: title,
                data: result.data,
                backgroundColor: chartType === "bar" ? color : "transparent",
                borderColor: color,
                borderWidth: 2,
                pointBackgroundColor: color,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

document.querySelectorAll(".card").forEach(card => {
    card.addEventListener("click", () => {
        const type = card.dataset.type;
        const titles = {
            approved: "Approved Research Analytics",
            pending: "Pending Research Analytics",
            rejected: "Rejected Research Analytics",
            total: "Total Research Analytics",
            recent: "Recent Uploads Analytics"
        };
        const colors = {
            approved: "#10b981",
            pending: "#f59e0b",
            rejected: "#ef4444",
            total: "#3b82f6",
            recent: "#8b5cf6"
        };
        loadAnalytics(type, titles[type], colors[type]);
    });
});

document.getElementById("filter-range").addEventListener("change", function() {
    if (this.value === "custom") {
        document.getElementById("custom-range").style.display = "inline";
    } else {
        document.getElementById("custom-range").style.display = "none";
        if (currentType) {
            document.querySelector(`.card[data-type="${currentType}"]`).click();
        }
    }
});

document.getElementById("applyFilter").addEventListener("click", function() {
    if (currentType) {
        document.querySelector(`.card[data-type="${currentType}"]`).click();
    }
});


</script>

</body>
</html>
