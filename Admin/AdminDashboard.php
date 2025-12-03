<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: Adminlogin.php");
    exit();
}

$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$adminData = null;
$stmt = $mysqli->prepare("
    SELECT fullname, email, profile_image, role
    FROM admins
    WHERE id = ?
");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$adminData = $result->fetch_assoc();
$stmt->close();

if (!$adminData) {
    $adminData = [
        "fullname" => "Administrator",
        "email" => "admin@example.com",
        "profile_image" => "Photos/logoCtu.png",
        "role" => "Super Admin"
    ];}
$totalStudents = 0;
$result = $mysqli->query("SELECT COUNT(*) AS total FROM students WHERE status = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    $totalStudents = $row['total'];}
$yearCounts = [];
for ($i = 1; $i <= 5; $i++) {
    $yearLevel = "$i";
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS count FROM students WHERE year_level = ? AND status = 'active'");
    $stmt->bind_param("s", $yearLevel);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $yearCounts[$i] = $count;
    $stmt->close();}
$page = $_GET['page'] ?? 'home';
$type = $_GET['type'] ?? 'research'; // Archive type dropdown default
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body {
  margin: 0;
  font-family: 'Roboto', sans-serif;
  background: #f8f9fb;
  color: #333;
}
.dashboard-container {
  display: flex;
  min-height: 100vh;
  transition: all 0.3s ease;
}

.sidebar {
  width: 270px;
  background: linear-gradient(180deg, #8b0000 0%, #a52a2a 100%);
  color: #fff;
  display: flex;
  flex-direction: column;
  padding: 15px;
  align-items: stretch;
  box-shadow: 2px 0 6px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}

.logo-section {
  display: flex;
  align-items: center;
  margin-bottom: 25px;
  background: rgba(255,255,255,0.1);
  border-radius: 12px;
  padding: 10px;
}
.logo-wrapper {
  border-radius: 50%;
  padding: 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 10px;
}
.logo-img {
  width: 50px;
  height: 50px;
  object-fit: contain;
}
.logo-text h1 {
  font-size: 14px;
  margin: 0;
  font-weight: 700;
}
.logo-text h3 {
  font-size: 11px;
  margin: 0;
  font-weight: 400;
}

.admin-card {
  position: relative;
  border-radius: 15px;
  text-align: center;
  padding: 20px 15px;
  margin-bottom: 25px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.3s, box-shadow 0.3s;
  background-image: url("../Photos/Ctu.jpg");
  background-size: cover;
  background-position: center;
}

.admin-card::before {
  content: "";
  position: absolute;
  top: 0; left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.1);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border-radius: 15px;
  transition: all 0.3s ease;
  z-index: 0;
}

.admin-card:hover::before {
  background: rgba(255,255,255,0.2);
  backdrop-filter: blur(12px);
}

.admin-avatar {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(255,255,255,0.8);
  margin-bottom: 10px;
  position: relative;
  z-index: 1;
  transition: transform 0.3s, box-shadow 0.3s;
}
.admin-card:hover .admin-avatar {
  transform: scale(1.1);
  box-shadow: 0 6px 15px rgba(0,0,0,0.3);
}

.admin-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  color: #fff;
  position: relative;
  z-index: 1;
}
.admin-name { font-weight: 700; font-size: 1rem; }
.admin-email { font-size: 12px; opacity: 0.8; }
.admin-role { font-size: 10px; opacity: 0.7; }

.profile-btn {
  margin-top: 10px;
  background: #fff;
  color: #8b0000;
  border: none;
  padding: 6px 12px;
  font-size: 13px;
  border-radius: 20px;
  cursor: pointer;
  transition: background 0.3s;
}
.profile-btn:hover { background: #f1f1f1; }

.nav-menu {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  text-decoration: none;
  color: #fff;
  padding: 10px 14px;
  border-radius: 10px;
  font-weight: 500;
  position: relative;
  transition: all 0.3s;
}
.nav-item span { display: inline-flex; align-items: center; }
.nav-icon { margin-right: 10px; }
.nav-item:hover { background: rgba(255,255,255,0.15); padding-left: 18px; }
.nav-item.active { background: rgba(255,255,255,0.25); color: #fff; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.25); }
.badge { background: #fff; color: #8b0000; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 12px; }

.nav-item.dropdown {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  padding: 10px 14px;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 500;
  position: relative;
  transition: all 0.3s;
  color: #fff;
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(6px);
}

.nav-item.dropdown span {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
}

.nav-item.dropdown:hover { background: rgba(255,255,255,0.15); }
.nav-item.dropdown.active { background: rgba(255,255,255,0.2); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

.nav-item.dropdown .dropdown-content {
  display: none;
  flex-direction: column;
  width: 100%;
  margin-top: 5px;
  border-radius: 10px;
  overflow: hidden;
  background: rgba(255,255,255,0.1);
  backdrop-filter: blur(10px);
  box-shadow: 0 6px 15px rgba(0,0,0,0.2);
  transition: all 0.3s;
}

.nav-item.dropdown.active .dropdown-content { display: flex; }

.nav-item.dropdown .dropdown-content a {
  padding: 10px 14px;
  color: #fff;
  text-decoration: none;
  font-weight: 500;
  transition: background 0.3s;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.nav-item.dropdown .dropdown-content a:hover,
.nav-item.dropdown .dropdown-content a.active { background: rgba(255,255,255,0.2); }

.dropdown-icon { font-size: 0.8em; margin-left: auto; transition: transform 0.3s; }
.nav-item.dropdown.active .dropdown-icon { transform: rotate(180deg); }

.main-content { flex: 1; padding: 25px; background: #f8f9fb; overflow-x: auto; transition: all 0.3s; }

.top-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px,1fr));
  gap: 15px;
  text-align: center;
}

.card {
  background: rgba(255,255,255,0.75);
  backdrop-filter: blur(8px);
  border-radius: 12px;
  padding: 15px;
  font-size: 12px;
  height: 70px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.card-blue { border-bottom: 5px solid #1e90ff; }
.card-yellow { border-bottom: 5px solid #f0ad4e; }
.card-red { border-bottom: 5px solid #d9534f; }
.card-green { border-bottom: 5px solid #5cb85c; }

.action-btn, .update-btn, .upload-btn, .view-btn { transition: all 0.3s ease; }
.action-btn:hover { background: #5848e8; }
.update-btn:hover { background: #1c7ed6; }
.upload-btn:hover { background: #218838; }
.view-btn:hover { background: #c9302c; }

@media (max-width: 1024px) {
  .sidebar { width: 230px; }
  .admin-card { padding: 15px 10px; }
  .admin-avatar { width: 60px; height: 60px; }
}

@media (max-width: 768px) {
  .dashboard-container { flex-direction: column; }
  .sidebar { width: 100%; flex-direction: row; overflow-x: auto; padding: 10px; justify-content: space-around; }
  .logo-section { display: none; }
  .nav-menu { flex-direction: row; gap: 8px; }
  .nav-item { padding: 8px 12px; font-size: 0.9rem; }
  .admin-card { flex-direction: row; justify-content: space-between; padding: 12px; }
  .admin-info { text-align: left; margin-left: 10px; }
}

</style>
</head>
<body>
<div class="dashboard-container">

    <aside class="sidebar">
        <div class="logo-section">
            <div class="logo-wrapper">
                <img src="Photos/logoCtu.png" alt="Logo" class="logo-img">
            </div>
            <div class="logo-text">
                <h1 class="head">CEBU TECHNOLOGICAL UNIVERSITY</h1>
                <h3 class="head">ONLINE RESEARCH LIBRARY</h2>
            </div>
        </div>

        <div class="admin-card">
            <img src="<?= htmlspecialchars($adminData['profile_image'] ?: 'Photos/logoCtu.png') ?>"
                 alt="Admin" class="admin-avatar">
            <div class="admin-info">
                <p class="admin-name"><?= htmlspecialchars($adminData['fullname']) ?></p>
                <p class="admin-email"><?= htmlspecialchars($adminData['email']) ?></p>
                <small class="admin-role"><?= htmlspecialchars($adminData['role']) ?></small><br>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="AdminDashboard.php?page=home" class="nav-item <?= $page=='home'?'active':'' ?>"><i class="fa-solid fa-house nav-icon"></i> Home</a>
            <a href="AdminDashboard.php?page=pending" class="nav-item <?= $page=='pending'?'active':'' ?>"><i class="fa-solid fa-file-circle-check nav-icon"></i> Pending Requests</a>
            <a href="AdminDashboard.php?page=upload" class="nav-item <?= $page=='upload'?'active':'' ?>"><i class="fa-solid fa-upload nav-icon"></i> Upload Research</a>
            <a href="AdminDashboard.php?page=documents" class="nav-item <?= $page=='documents'?'active':'' ?>"><i class="fa-solid fa-folder-open nav-icon"></i> Research Documents</a>
            <a href="AdminDashboard.php?page=user" class="nav-item <?= $page=='user'?'active':'' ?>"><i class="fa-solid fa-users nav-icon"></i> User Management</a>
            <a href="AdminDashboard.php?page=add-admin" class="nav-item <?= $page=='add-admin'?'active':'' ?>"><i class="fa-solid fa-user-plus nav-icon"></i> Add New Admin</a>
            <a href="AdminDashboard.php?page=manage-admin" class="nav-item <?= $page=='manage-admin'?'active':'' ?>"><i class="fa-solid fa-users-gear nav-icon"></i> Manage Admins</a>

            <div class="nav-item dropdown <?= $page=='archives'?'active':'' ?>">
                <span><i class="fa-solid fa-box-archive nav-icon"></i> Archives</span>
                <i class="fa-solid fa-chevron-down dropdown-icon"></i>
                <div class="dropdown-content">
                    <a href="AdminDashboard.php?page=archives&type=research" class="<?= $type=='research'?'active':'' ?>">Research Documents</a>
                    <a href="AdminDashboard.php?page=archives&type=students" class="<?= $type=='students'?'active':'' ?>">Students</a>
                    <a href="AdminDashboard.php?page=archives&type=faculty" class="<?= $type=='faculty'?'active':'' ?>">Faculty</a>
                </div>
            </div>

            <a href="AdminDashboard.php?page=settings" class="nav-item <?= $page=='settings'?'active':'' ?>"><i class="fa-solid fa-gear nav-icon"></i> Settings</a>
            <a href="#" id="logoutBtn" class="nav-item"><i class="fa-solid fa-right-from-bracket nav-icon"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <?php
        switch($page){
            case 'home': include __DIR__ . '/home.php'; break;
            case 'manage-categories': include __DIR__ . '/manage-categories.php'; break;
            case 'generate-reports': include __DIR__ . '/generate-reports.php'; break;
           case 'pending':
            include __DIR__ . '/pending-request.php';
            break;

        case 'request_access':
            include __DIR__ . '/Request_access_management.php';
            break;

        case 'upload_requests':
            include __DIR__ . '/research_upload_requests.php';
        break;
            case 'upload': include __DIR__ . '/upload-research.php'; break;
            case 'documents': include __DIR__ . '/Research_documents.php'; break;
            case 'user': include __DIR__ . '/user-management.php'; break;
            case 'add-admin': include __DIR__ . '/add-admin.php'; break;
            case 'manage-admin': include __DIR__ . '/manage-admin.php'; break;
            case 'archives':
                if ($type == 'research') {
                    include __DIR__ . '/research-archive.php';
                } elseif ($type == 'students') {
                    include __DIR__ . '/students-archive.php';
                } elseif ($type == 'faculty') {
                    include __DIR__ . '/faculty-archive.php';
                } else {
                    echo "<h2>Archive type not found!</h2>";
                }
                break;
            case 'settings': include __DIR__ . '/settings.php'; break;
            default: echo "<h2>Page not found!</h2>";
        }
        ?>
    </main>
</div>

<div id="logoutModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
     background: rgba(0,0,0,0.55); justify-content:center; align-items:center; z-index:9999;">
    <div style="background:#fff; padding:25px; border-radius:12px; max-width:400px; width:90%; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,0.25);">
        <h3>You are about to sign out</h3>
        <p>Are you sure you want to log out?</p>
        <div style="margin-top:20px; display:flex; justify-content:center; gap:15px;">
            <button id="cancelLogout" style="padding:8px 18px; border:none; border-radius:8px; background:#ccc; cursor:pointer;">No</button>
            <a href="Adminlogin.php" id="confirmLogout" style="padding:8px 18px; border-radius:8px; background:#dc3545; color:#fff; text-decoration:none;">Yes</a>
        </div>
    </div>
</div>

<script>
const logoutBtn = document.getElementById('logoutBtn');
const logoutModal = document.getElementById('logoutModal');
const cancelLogout = document.getElementById('cancelLogout');
logoutBtn.addEventListener('click', (e) => {
    e.preventDefault();
    logoutModal.style.display = 'flex';
});
cancelLogout.addEventListener('click', () => {
    logoutModal.style.display = 'none';
});
window.addEventListener('click', (e) => {
    if (e.target === logoutModal) logoutModal.style.display = 'none';
});

document.querySelectorAll('.nav-item.dropdown').forEach(drop => {
    drop.addEventListener('click', (e) => {
        e.stopPropagation();
        const isActive = drop.classList.contains('active');
        document.querySelectorAll('.nav-item.dropdown').forEach(d => d.classList.remove('active'));
        if (!isActive) drop.classList.add('active');
    });
});

window.addEventListener('click', () => {
    document.querySelectorAll('.nav-item.dropdown').forEach(d => d.classList.remove('active'));
});
</script>
</body>
</html>
