<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: LoginUser.php");
    exit();
}

$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$studentData = null;
$stmt = $mysqli->prepare("SELECT fullname, profile_image FROM students WHERE student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();
$stmt->close();

if (!$studentData) {
    $studentData = [
        "fullname" => "Student",
        "profile_image" => "Photos/logoCtu.png"
    ];
}

$page = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<style>
body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; transition: all 0.3s ease; }


.dashboard-container {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 270px;
    display: flex;
    flex-direction: column;
    background: linear-gradient(180deg, #8b0000 0%, #a52a2a 100%);
    color: #fff;
    padding: 15px;
    box-shadow: 2px 0 6px rgba(0,0,0,0.1);
}



.logo-section {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
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
.logo-text h2 {
    font-size: 11px;
    margin: 0;
    font-weight: 400;
}


.student-card {
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    text-align: center;
    padding: 10px 5px;
    margin-bottom: 15px;
}
.student-card .student-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    margin: 0 auto;
}
.student-name {
    font-weight: 700;
    margin: 5px 0;
}
.student-id {
    font-size: 13px;
    opacity: 0.9;
}

.sidebar-menu-container {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

.sidebar-menu-scroll {
    flex: 1;
    overflow-y: auto;
    padding-right: 5px;
    scroll-behavior: smooth;
}

.nav-menu {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.nav-item {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: #fff;
    padding: 12px 15px;
    border-radius: 12px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s ease;
    position: relative;
}

.nav-item .nav-icon {
    margin-right: 12px;
    font-size: 16px;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.15);
    padding-left: 18px;
    box-shadow: inset 3px 0 0 #fff;
}

.nav-item.active {
    background: #fff;
    color: #8b0000;
    font-weight: 700;
    box-shadow: inset 4px 0 0 #8b0000;
}

.sidebar-menu-scroll::-webkit-scrollbar {
    width: 6px;
}
.sidebar-menu-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.25);
    border-radius: 3px;
}
.sidebar-menu-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-logout {
    flex-shrink: 0;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.2);
}

.main-content {
    /* Remove margin-left and let Flexbox handle the placement */
    margin-left: 270px; /* Keeping this since it works, but removing 'flex: 1' from here to avoid conflict */
    background: #f8f9fb;
    flex-grow: 1; /* Ensures it takes up all remaining width */
}

#logoutModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
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

@media (max-width: 768px) {
    .sidebar {
        width: 220px;
    }
    .main-content {
        margin-left: 220px;
    }
}


    </style>
<body>
<div class="dashboard-container">

    <aside class="sidebar">
    <div class="logo-section">
        <div class="logo-wrapper">
            <img src="Photos/logoCtu.png" alt="Logo" class="logo-img">
        </div>
        <div class="logo-text">
            <h1>CEBU TECHNOLOGICAL UNIVERSITY</h1>
            <h2>ONLINE RESEARCH LIBRARY</h2>
        </div>
    </div>

    <div class="student-card">
        <img src="../uploads/students/<?= htmlspecialchars($studentData['profile_image'] ?: 'logoCtu.png') ?>"
     alt="Student" class="student-avatar">
        <div class="student-info">
            <p class="student-name"><?= htmlspecialchars($studentData['fullname']) ?></p>
            <small class="student-id">ID: <?= htmlspecialchars($_SESSION['student_id']) ?></small>
        </div>
    </div>


    <div class="sidebar-menu-container">
        <div class="sidebar-menu-scroll">
            <nav class="nav-menu">
                <a href="Student_Dashboard.php?page=home" class="nav-item <?= $page=='home'?'active':'' ?>">
                    <i class="fa-solid fa-house nav-icon"></i> Home
                </a>
                <a href="Student_Dashboard.php?page=accessed" class="nav-item <?= $page=='accessed'?'active':'' ?>">
                    <i class="fa-solid fa-file-circle-check nav-icon"></i> Research Accessed
                </a>
                <a href="Student_Dashboard.php?page=favorites" class="nav-item <?= $page=='favorites' ? 'active' : '' ?>">
                    <i class="fa-solid fa-star nav-icon"></i> Favorites
                </a>
                <a href="Student_Dashboard.php?page=upload" class="nav-item <?= $page=='upload'?'active':'' ?>">
                    <i class="fa-solid fa-upload nav-icon"></i> Upload Research
                </a>
                <a href="Student_Dashboard.php?page=advancedsearch" class="nav-item <?= $page=='advancedsearch'?'active':'' ?>">
                    <i class="fa-solid fa-folder-open nav-icon"></i> Advanced Search
                </a>
                <a href="Student_Dashboard.php?page=uploads" class="nav-item <?= $page=='uploads'?'active':'' ?>">
                    <i class="fa-solid fa-users nav-icon"></i> My Uploads
                </a>
                <a href="Student_Dashboard.php?page=settings" class="nav-item <?= $page=='settings'?'active':'' ?>">
                    <i class="fa-solid fa-gear nav-icon"></i> Settings
                </a>
                 <a href="#" id="logoutBtn" class="nav-item">
                <i class="fa-solid fa-right-from-bracket nav-icon"></i> Logout
            </a>
            </nav>
        </div>

        <div class="sidebar-logout">
            <a class="nav-item">
                <i></i>
            </a>
        </div>
    </div>
</aside>

    <main class="main-content">
        <?php
        switch($page){
            case 'home': include __DIR__ . '/Home.php'; break;
            case 'accessed': include __DIR__ . '/Research_Accessed.php'; break;
            case 'favorites': include __DIR__ . '/favorites.php'; break;
            case 'upload': include __DIR__ . '/Upload_Research.php'; break;
            case 'advancedsearch': include __DIR__ . '/search_results.php'; break;
            case 'uploads': include __DIR__ . '/my-uploads.php'; break;
            case 'settings': include __DIR__ . '/profile.php'; break;
            default: echo "<h2>Page not found!</h2>";
        }
        ?>
    </main>
</div>

<div id="logoutModal">
    <div class="modal-content">
        <h3>You are about to sign out</h3>
        <p>Are you sure you want to log out?</p>
        <div class="modal-buttons">
            <button id="cancelLogout">No</button>
            <a href="../LoginUser.php" id="confirmLogout">Yes</a>
        </div>
    </div>
</div>




<section id="detailsSection" style="display: none;"></section>
<script>
const logoutBtn = document.getElementById('logoutBtn');
const logoutModal = document.getElementById('logoutModal');
const cancelLogout = document.getElementById('cancelLogout');

logoutBtn.addEventListener('click', e => {
    e.preventDefault();
    logoutModal.style.display = 'flex';
});
cancelLogout.addEventListener('click', () => logoutModal.style.display = 'none');
window.addEventListener('click', e => { if(e.target === logoutModal) logoutModal.style.display='none'; });

document.querySelectorAll('.nav-item.dropdown').forEach(drop => {
    drop.addEventListener('click', e => {
        e.stopPropagation();
        const isActive = drop.classList.contains('active');
        document.querySelectorAll('.nav-item.dropdown').forEach(d => d.classList.remove('active'));
        if(!isActive) drop.classList.add('active');
    });
});
window.addEventListener('click', () => {
    document.querySelectorAll('.nav-item.dropdown').forEach(d => d.classList.remove('active'));
});
</script>
</body>
</html>
