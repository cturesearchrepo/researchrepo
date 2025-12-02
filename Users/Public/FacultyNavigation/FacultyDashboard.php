<?php
session_start();
if (!isset($_SESSION['faculty_id'])) {
    header("Location: logout.php");
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) die("Connection failed: " . $mysqli->connect_error);

date_default_timezone_set("Asia/Manila"); 
$hour = date("H");
$greeting = ($hour < 12) ? "Good Morning" : (($hour < 18) ? "Good Afternoon" : "Good Evening");

$stmt = $mysqli->prepare("SELECT fullname, department, profile_image FROM faculty WHERE faculty_id=?");
$stmt->bind_param("s", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc();
$stmt->close();
$facultyName = $faculty['fullname'] ?? 'Faculty';

$page = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faculty Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; transition: all 0.3s ease; }
.sidebar { min-height: 100vh; background: #8b0000; color: #fff; padding: 0; transition: all 0.3s ease; }
.sidebar .logo-section { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 20px 10px; border-bottom: 2px solid #a52a2a; }
.sidebar .logo-section img { height: 70px; width: 70px; object-fit: contain; margin-bottom: 10px; }
.sidebar .logo-text h1 { font-size: 16px; margin: 0; font-weight: bold; color: #fff; }
.sidebar .logo-text h2 { font-size: 12px; margin: 0; font-weight: 500; color: #f1f1f1; }
.sidebar h4 { font-weight: 600; font-size: 18px; margin-top: 15px; }
.sidebar a { color: #fff; text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 12px 20px; border-radius: 8px; margin: 5px 10px; transition: all 0.3s; font-size: 15px; }
.sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); transform: translateX(5px); }
.topbar { background: #fff; border-bottom: 1px solid #dee2e6; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
.content { padding: 20px; max-width: 1200px; margin: auto; }
.card { border: none; border-radius: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); transition: transform 0.2s ease; cursor: pointer; }
.card:hover { transform: translateY(-3px); }
.card-icon { font-size: 28px; margin-bottom: 5px; }
body.dark-mode { background-color: #1e1e1e; color: #e4e4e4; }
body.dark-mode .topbar { background: #2b2b2b; border-bottom: 1px solid #444; }
body.dark-mode .card { background: #2b2b2b; color: #e4e4e4; }
.sidebar.collapsed { width: 60px !important; }
.sidebar.collapsed a { justify-content: center; gap: 0; text-align: center; }
.sidebar.collapsed .logo-text h1, .sidebar.collapsed .logo-text h2, .sidebar.collapsed h4 { display: none; }
#logoutModal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.55);
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

#logoutModal .modal-content {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}

#logoutModal .modal-buttons {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 15px;
}

#logoutModal button {
    padding: 8px 18px;
    border: none;
    border-radius: 8px;
    background: #ccc;
    cursor: pointer;
}

#logoutModal a {
    padding: 8px 18px;
    border-radius: 8px;
    background: #dc3545;
    color: #fff;
    text-decoration: none;
}
</style>
</head>
<body>

<div class="d-flex">
  <div class="sidebar" id="sidebar">
    <div class="logo-section">
      <img src="Photos/logoCtu.png" alt="CTU Logo">
      <div class="logo-text">
        <h1>CEBU TECHNOLOGICAL UNIVERSITY</h1>
        <h2>ONLINE RESEARCH LIBRARY</h2>
      </div>
    </div>
    <h4 class="text-center">Faculty Panel</h4>
    <a href="FacultyDashboard.php?page=home" class="<?= $page=='home'?'active':'' ?>"><i class="bi bi-house-door-fill"></i> Dashboard</a>
    <a href="FacultyDashboard.php?page=repository" class="<?= $page=='repository'?'active':'' ?>"><i class="bi bi-archive"></i> Research Repository</a>
    <a href="FacultyDashboard.php?page=review" class="<?= $page=='review'?'active':'' ?>"><i class="bi bi-people-fill"></i> Advisory Works</a>
    <a href="FacultyDashboard.php?page=profile" class="<?= $page=='profile'?'active':'' ?>"><i class="bi bi-person-circle"></i> Profile & Settings</a>
    <a href="#" id="logoutBtn"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>

  <div class="flex-grow-1">
    <div class="topbar d-flex justify-content-between align-items-center">
  <button class="btn btn-sm btn-outline-secondary me-3" onclick="toggleDarkMode()">
    <i class="bi bi-moon-fill"></i>
  </button>
  <button class="btn btn-outline-primary d-lg-none" onclick="toggleSidebar()">☰</button>
  
  <div class="d-flex align-items-center justify-content-center flex-grow-1">
    <img src="../uploads/faculty/<?= htmlspecialchars($faculty['profile_image'] ?? 'default.png'); ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile">
    <span style="font-weight: 500; font-size: 16px;"><?= $greeting . ', ' . htmlspecialchars($facultyName); ?> 👋</span>
  </div>
</div>


    <div class="content">
      <?php
      switch($page){
          case 'home': include __DIR__ . '/FacultyHome.php'; break;
          case 'upload': include __DIR__ . '/FacultyUpload.php'; break;
          case 'repository': include __DIR__ . '/ResearchRepository.php'; break;
          case 'submissions': include __DIR__ . '/MySubmissions.php'; break;
          case 'review': include __DIR__ . '/FacultyReview.php'; break;
          case 'profile': include __DIR__ . '/Profile.php'; break;
          default: echo "<h2>Page not found!</h2>";
      }
      ?>
    </div>
  </div>
</div>

<div id="logoutModal">
    <div class="modal-content">
        <h3>You are about to sign out</h3>
        <p>Are you sure you want to log out?</p>
        <div class="modal-buttons">
            <button id="cancelLogout">No</button>
            <a href="logout.php" id="confirmLogout">Yes</a>
        </div>
    </div>
</div>

<script>
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('collapsed'); }
function toggleDarkMode(){ document.body.classList.toggle('dark-mode'); localStorage.setItem("darkMode", document.body.classList.contains("dark-mode")); }
window.onload = () => { if(localStorage.getItem("darkMode")==="true") document.body.classList.add("dark-mode"); }

const logoutBtn = document.getElementById('logoutBtn'),
      logoutModal = document.getElementById('logoutModal'),
      cancelLogout = document.getElementById('cancelLogout');

logoutBtn.addEventListener('click', e => { 
    e.preventDefault(); 
    logoutModal.style.display = 'flex'; 
});

cancelLogout.addEventListener('click', () => logoutModal.style.display = 'none');

window.addEventListener('click', e => { 
    if (e.target === logoutModal) logoutModal.style.display = 'none'; 
});

</script>

</body>
</html>
