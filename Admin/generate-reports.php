<?php
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

$total_requests = $mysqli->query("SELECT COUNT(*) as total FROM research_access_requests")->fetch_assoc()['total'];
$pending_requests = $mysqli->query("SELECT COUNT(*) as total FROM research_access_requests WHERE status='pending'")->fetch_assoc()['total'];
$total_contributors = $mysqli->query("SELECT COUNT(DISTINCT uploaded_by_student) as total FROM research_documents")->fetch_assoc()['total'];

$uploads_per_month = $mysqli->query("
    SELECT DATE_FORMAT(uploaded_at, '%Y-%m') AS month, COUNT(*) as total 
    FROM research_documents 
    WHERE status IN ('ApprovedbyAdmin','Active')
    GROUP BY month 
    ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);


$top_researches = $mysqli->query("
    SELECT r.title, COUNT(a.id) AS access_count 
    FROM research_documents r 
    JOIN research_access_requests a ON r.id = a.research_id 
    WHERE r.status IN ('ApprovedbyAdmin','Active')
    GROUP BY r.id 
    ORDER BY access_count DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);


$categories = $mysqli->query("
    SELECT c.name as category, COUNT(r.id) as total 
    FROM research_documents r 
    LEFT JOIN categories c ON r.category_id = c.id 
    WHERE r.status IN ('ApprovedbyAdmin','Active')
    GROUP BY r.category_id
")->fetch_all(MYSQLI_ASSOC);


$access_expiry = $mysqli->query("
    SELECT 
        SUM(CASE WHEN expire_at > NOW() THEN 1 ELSE 0 END) AS active, 
        SUM(CASE WHEN expire_at <= NOW() THEN 1 ELSE 0 END) AS expired 
    FROM research_access_requests
")->fetch_assoc();

$submissionTrends = $mysqli->query("
    SELECT DATE_FORMAT(uploaded_at, '%Y-%m') AS month, status, COUNT(*) as count
    FROM research_documents
    GROUP BY month, status
    ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);

$trendsData = [];
foreach ($submissionTrends as $row) {
    $status = strtolower($row['status']); 
    if ($status === 'approvedbyadmin') $status = 'approved';
    elseif ($status === 'rejectedbyadmin') $status = 'rejected';
    elseif ($status === 'pending') $status = 'pending';

    $trendsData[$row['month']][$status] = (int)$row['count'];
}

$topDepartments = $mysqli->query("
    SELECT department, COUNT(*) as total_uploads
    FROM research_documents
    WHERE status IN ('ApprovedbyAdmin','Active')
    GROUP BY department
    ORDER BY total_uploads DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);


$topRated = $mysqli->query("
    SELECT r.title, r.author, r.adviser, 
           ROUND(AVG(rt.rating), 2) AS avg_rating, 
           COUNT(rt.id) AS total_ratings
    FROM research_documents r
    JOIN ratings rt ON r.id = rt.research_id
    WHERE r.status IN ('ApprovedbyAdmin','Active')
    GROUP BY r.id
    HAVING total_ratings > 0
    ORDER BY avg_rating DESC, total_ratings DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$top_rated_data = json_encode($topRated);


$uploads_data     = json_encode($uploads_per_month);
$top_data         = json_encode($top_researches);
$departments_data = json_encode($topDepartments);
$trends_data      = json_encode($trendsData);
$categories_data  = json_encode($categories);
$expiry_data      = json_encode($access_expiry);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Analytics Reports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    h2 { text-align: center; margin-bottom: 20px; color: #333; }

    /* KPI Cards */
    .kpi-container { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
    .kpi-card { border-radius: 12px; padding: 20px; min-width: 200px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); text-align: center; color: #fff; flex: 1; max-width: 350px; height: 40px; position: relative; }
    .kpi-card .icon { position:absolute; font-size: 30px; margin-bottom: 10px; }
    .kpi-card h3 { font-size: 14px; margin-bottom: 6px; font-weight: normal; }
    .kpi-card p { font-size: 20px; font-weight: bold; margin: 0; }

    /* KPI Colors */
    .contributors { background: #007bff; }
    .requests { background: #28a745; }
    .pending { background: #ffc107; color: #333; }

    /* Charts */
    .charts-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
    .chart-box { background: #fff; border-radius: 12px; padding: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); text-align: center; }
    .chart-box h3 { font-size: 16px; margin-bottom: 12px; color: #555; }
    canvas { max-height: 220px !important; }
  </style>
</head>
<body>
  <h2>📊 Analytics Reports</h2>

  <!-- KPI Summary Cards -->
  <div class="kpi-container">
    <div class="kpi-card contributors">
      <div class="icon">👥</div>
      <h3>Total Contributors</h3>
      <p><?php echo $total_contributors; ?></p>
    </div>
    <div class="kpi-card requests">
      <div class="icon">📑</div>
      <h3>Total Access Requests</h3>
      <p><?php echo $total_requests; ?></p>
    </div>
    <div class="kpi-card pending">
      <div class="icon">⏳</div>
      <h3>Pending Requests</h3>
      <p><?php echo $pending_requests; ?></p>
    </div>
  </div>

  <div class="charts-container">
    <div class="chart-box">
      <h3>Uploads per Month</h3>
      <canvas id="uploadsChart"></canvas>
    </div>
    <div class="chart-box">
      <h3>Top Accessed Research</h3>
      <canvas id="topChart"></canvas>
    </div>
    <div class="chart-box">
      <h3>Top Courses / Departments Contributing Research</h3>
      <canvas id="studentsChart"></canvas>
    </div>
    <div class="chart-box">
      <h3>Submission Trends Over Time</h3>
      <canvas id="statusChart"></canvas>
    </div>
    <div class="chart-box">
      <h3>Research Categories</h3>
      <canvas id="categoryChart"></canvas>
    </div>
    <div class="chart-box">
      <h3>Access Requests (Active vs Expired)</h3>
      <canvas id="expiryChart"></canvas>
    </div>
  </div>

  <div class="chart-box">
  <h3>Top Rated Research</h3>
  <canvas id="ratedChart"></canvas>
</div>


  <script>
    const uploadsData = <?php echo $uploads_data; ?>;
    const topData = <?php echo $top_data; ?>;
    const departmentsData = <?php echo $departments_data; ?>;
    const trendsRaw = <?php echo $trends_data; ?>;
    const categoriesData = <?php echo $categories_data; ?>;
    const expiryData = <?php echo $expiry_data; ?>;
const ratedData = <?php echo $top_rated_data; ?>;

new Chart(document.getElementById("ratedChart"), {
  type: "bar",
  data: {
    labels: ratedData.map(r => r.title + " (" + r.author + ", " + r.adviser + ")"),
    datasets: [{
      label: "Average Rating",
      data: ratedData.map(r => r.avg_rating),
      backgroundColor: "#ff9800"
    }]
  },
  options: {
    responsive: true,
    plugins: {
      tooltip: {
        callbacks: {
          label: function(ctx) {
            let row = ratedData[ctx.dataIndex];
            return `⭐ ${row.avg_rating} (from ${row.total_ratings} ratings)`;
          }
        }
      }
    },
    scales: {
      y: { beginAtZero: true, max: 5 }
    }
  }
});

    new Chart(document.getElementById("uploadsChart"), {
      type: "line",
      data: {
        labels: uploadsData.map(u => u.month),
        datasets: [{
          label: "Uploads",
          data: uploadsData.map(u => u.total),
          borderColor: "#007bff",
          backgroundColor: "rgba(0,123,255,0.2)",
          fill: true
        }]
      },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });

    // Top Accessed Research
    new Chart(document.getElementById("topChart"), {
      type: "bar",
      data: {
        labels: topData.map(t => t.title),
        datasets: [{
          label: "Access Count",
          data: topData.map(t => t.access_count),
          backgroundColor: "#28a745"
        }]
      },
      options: { responsive: true }
    });

    // Top Courses / Departments Contributing Research
    new Chart(document.getElementById("studentsChart"), {
      type: "bar",
      data: {
        labels: departmentsData.map(d => d.department || "Unknown"),
        datasets: [{
          label: "Uploads",
          data: departmentsData.map(d => d.total_uploads),
          backgroundColor: "#007bff"
        }]
      },
      options: { responsive: true }
    });

    // Submission Trends Over Time
    const months = Object.keys(trendsRaw);
    const pending = months.map(m => trendsRaw[m].pending || 0);
    const approved = months.map(m => trendsRaw[m].approved || 0);
    const rejected = months.map(m => trendsRaw[m].rejected || 0);

    new Chart(document.getElementById("statusChart"), {
      type: "line",
      data: {
        labels: months,
        datasets: [
          { label: "Pending", data: pending, borderColor: "#ffc107", fill: false },
          { label: "Approved", data: approved, borderColor: "#28a745", fill: false },
          { label: "Rejected", data: rejected, borderColor: "#dc3545", fill: false }
        ]
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    // Research Categories
    new Chart(document.getElementById("categoryChart"), {
      type: "doughnut",
      data: {
        labels: categoriesData.map(c => c.category || "Uncategorized"),
        datasets: [{ data: categoriesData.map(c => c.total), backgroundColor: ["#17a2b8","#6f42c1","#fd7e14","#20c997","#6610f2"] }]
      }
    });

    // Expired vs Active Requests
    new Chart(document.getElementById("expiryChart"), {
      type: "doughnut",
      data: {
        labels: ["Active", "Expired"],
        datasets: [{ data: [expiryData.active, expiryData.expired], backgroundColor: ["#28a745","#dc3545"] }]
      }
    });
  </script>
</body>
</html>
