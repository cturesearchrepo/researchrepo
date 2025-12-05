<?php
// Redirect if not logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Pending Requests</title>
<link rel="stylesheet" href=".//AllStyles/user-managemen.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
.requests-container { 
    display: flex; 
    flex-direction: column; 
    gap: 25px; 
    margin-top: 20px; 
}
.request-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.request-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}
.request-card h3 { margin: 0; font-size: 18px; color: #333; font-weight: 600; }
.request-card p { margin: 4px 0 0 0; color: #666; font-size: 14px; }
.request-card i { font-size: 24px; color: #800000; }
</style>
</head>
<body>
<div class="dashboard-container">
<main class="main-content">
<header class="top-header">
    <h2>Pending Requests</h2>
</header>

<div class="requests-container">
    <!-- Research Access Requests -->
    <div class="request-card" onclick="location.href='AdminDashboard.php?page=request_access'">
        <div>
            <h3><i class="fa-solid fa-folder-open"></i> Research Access Requests</h3>
            <p>Review and approve access requests for research documents.</p>
        </div>
    </div>

    <!-- Research Upload Requests Approval -->
    <div class="request-card" onclick="location.href='AdminDashboard.php?page=upload_requests'">
        <div>
            <h3><i class="fa-solid fa-upload"></i> Research Upload Requests Approval</h3>
            <p>Approve or decline newly uploaded research submissions from students.</p>
        </div>
    </div>
</div>

</main>
</div>
</body>
</html>
