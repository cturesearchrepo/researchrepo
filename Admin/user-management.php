<?php
$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "CentralizedResearchRepository_userdb"; 

$conn = new mysqli($servername, $username, $password, $dbname); 
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); } 

$successMsg = $errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_type'], $_POST['user_id'])) {
    $action = $_POST['action'];
    $userType = $_POST['user_type'];
    $userId = (int)$_POST['user_id'];
    $table = $userType === 'faculty' ? 'faculty' : 'students';

    if ($action === 'approve') {
        $sql = "UPDATE $table SET status='Active' WHERE id=?";
    } elseif ($action === 'decline') {
        $sql = "UPDATE $table SET status='Declined' WHERE id=?";
    } elseif ($action === 'suspend') {
        $sql = "UPDATE $table SET status='Suspended' WHERE id=?";
    } elseif ($action === 'remove') {
        $sql = "DELETE FROM $table WHERE id=?";
    }

    if (isset($sql)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $successMsg = ucfirst($action) . " action completed successfully!";
        } else {
            $errorMsg = "Failed to perform action.";
        }
        $stmt->close();
    }
}

$facultyResult = $conn->query("SELECT id, faculty_id, fullname, email, department, profile_image, status FROM faculty WHERE status IN ('Active','Pending')");
$studentResult = $conn->query("SELECT id, student_id, fullname, email, year_level, profile_image, status FROM students WHERE status IN ('Active','Pending')");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>User Management</title>
<link rel="stylesheet" href=".//AllStyles/user-managemen.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
.modal-profile {
    width: 100px;         
    height: 100px;            
    border-radius: 50%;       
    display: block;         
    margin: 0 auto 15px auto; 
    object-fit: cover;      
    border: 2px solid #ccc;   
    box-shadow: 0 2px 8px rgba(0,0,0,0.2); 
}

</style>
</head>
<body>
<div class="dashboard-container">
<main class="main-content">
<header class="top-header">
    <h2>User Management</h2>
    <div class="top-right">
        <input type="text" id="searchInput" class="search-input" placeholder="Search users ID...">
    </div>
</header>
<section class="user-management-section">
<div class="tabs">
    <button class="tab-button active" data-tab="facultyTab">Faculty Accounts</button>
    <button class="tab-button" data-tab="studentTab">Student Accounts</button>
</div>

<!-- Faculty Tab -->
<div id="facultyTab" class="tab-content active">
    <h3>Faculty Accounts</h3>
    <div class="table-wrapper">
        <table class="user-table">
            <thead>
                <tr><th>Profile</th><th>Faculty ID</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if($facultyResult && $facultyResult->num_rows>0): ?>
                <?php while($f=$facultyResult->fetch_assoc()): ?>
                <tr>
                    <td><img src="<?= !empty($f['profile_image']) 
                        ? '../Users/Public/uploads/faculty/' . htmlspecialchars($f['profile_image']) 
                        : '../Photos/logoCtu.png'; ?>" class="user-thumb"></td>
                    <td><?= htmlspecialchars($f['faculty_id']); ?></td>
                    <td><?= htmlspecialchars($f['fullname']); ?></td>
                    <td><?= htmlspecialchars($f['email']); ?></td>
                    <td><?= htmlspecialchars($f['department'] ?? 'N/A'); ?></td>
                    <td><span class="status <?= strtolower($f['status']); ?>"><?= htmlspecialchars($f['status']); ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <?php if(strtolower($f['status'])==='pending'): ?>
                            <button type="button" class="open-approve approve-btn" 
                                data-id="<?= $f['id']; ?>" 
                                data-type="faculty" 
                                data-name="<?= htmlspecialchars($f['fullname']); ?>" 
                                data-email="<?= htmlspecialchars($f['email']); ?>" 
                                data-userid="<?= htmlspecialchars($f['faculty_id']); ?>" 
                                data-profile="<?= !empty($f['profile_image']) ? '../Users/Public/uploads/faculty/' . htmlspecialchars($f['profile_image']) : '../Photos/logoCtu.png'; ?>">
                                <i class="fa-solid fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="open-confirm suspend-btn" data-action="suspend" data-type="faculty" data-id="<?= $f['id']; ?>"><i class="fa-solid fa-user-slash"></i></button>
                            <button type="button" class="open-confirm remove-btn" data-action="remove" data-type="faculty" data-id="<?= $f['id']; ?>"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Student Tab -->
<div id="studentTab" class="tab-content">
    <h3>Student Accounts</h3>
    <div class="table-wrapper">
        <table class="user-table">
            <thead>
                <tr><th>Profile</th><th>Student ID</th><th>Name</th><th>Email</th><th>Year</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if($studentResult && $studentResult->num_rows>0): ?>
                <?php while($s=$studentResult->fetch_assoc()): ?>
                <tr>
                    <td><img src="<?= !empty($s['profile_image']) 
                        ? '../Users/Public/uploads/students/' . htmlspecialchars($s['profile_image']) 
                        : '../Photos/logoCTU.png'; ?>" class="user-thumb"></td>
                    <td><?= htmlspecialchars($s['student_id']); ?></td>
                    <td><?= htmlspecialchars($s['fullname']); ?></td>
                    <td><?= htmlspecialchars($s['email']); ?></td>
                    <td><?= htmlspecialchars($s['year_level'] ?? 'N/A'); ?></td>
                    <td><span class="status <?= strtolower($s['status']); ?>"><?= htmlspecialchars($s['status']); ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <?php if(strtolower($s['status'])==='pending'): ?>
                            <button type="button" class="open-approve approve-btn" 
                                data-id="<?= $s['id']; ?>" 
                                data-type="student" 
                                data-name="<?= htmlspecialchars($s['fullname']); ?>" 
                                data-email="<?= htmlspecialchars($s['email']); ?>" 
                                data-userid="<?= htmlspecialchars($s['student_id']); ?>" 
                                data-profile="<?= !empty($s['profile_image']) ? '../Users/Public/uploads/students/' . htmlspecialchars($s['profile_image']) : '../Photos/logoCTU.png'; ?>">
                                <i class="fa-solid fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="open-confirm suspend-btn" data-action="suspend" data-type="student" data-id="<?= $s['id']; ?>"><i class="fa-solid fa-user-slash"></i></button>
                            <button type="button" class="open-confirm remove-btn" data-action="remove" data-type="student" data-id="<?= $s['id']; ?>"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</section>
</main>
</div>

<div id="approveModal" class="modal">
    <div class="modal-content">
        <img id="modalProfile" src="../Photos/logoCTU.png" alt="Profile" class="modal-profile">
        <p><b>ID:</b> <span id="modalUserIdTxt"></span></p>
        <p><b>Name:</b> <span id="modalName"></span></p>
        <p><b>Email:</b> <span id="modalEmail"></span></p>
        <form method="POST">
            <input type="hidden" name="user_id" id="modalUserId">
            <input type="hidden" name="user_type" id="modalUserType">
            <button type="submit" name="action" value="approve" class="approve-btn">Approve</button>
            <button type="submit" name="action" value="decline" class="decline-btn">Decline</button>
            <button type="button" id="closeApprove" class="cancel-btn">Cancel</button>
        </form>
    </div>
</div>

<div id="confirmModal" class="modal">
    <div class="modal-content">
        <h3 id="confirmTitle"></h3>
        <p id="confirmMsg"></p>
        <form method="POST">
            <input type="hidden" name="user_id" id="confirmUserId">
            <input type="hidden" name="user_type" id="confirmUserType">
            <button type="submit" name="action" id="confirmActionBtn"></button>
            <button type="button" id="closeConfirm" class="cancel-btn">Cancel</button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.tab-button').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        document.querySelectorAll('.tab-button').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

const approveModal = document.getElementById('approveModal');
const modalProfile = document.getElementById('modalProfile');
const modalUserIdTxt = document.getElementById('modalUserIdTxt');
const modalName = document.getElementById('modalName');
const modalEmail = document.getElementById('modalEmail');
const modalUserId = document.getElementById('modalUserId');
const modalUserType = document.getElementById('modalUserType');
document.querySelectorAll('.open-approve').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        approveModal.style.display='flex';
        modalProfile.src = btn.dataset.profile || '../Photos/logoCTU.png';
        modalUserIdTxt.textContent = btn.dataset.userid;
        modalName.textContent = btn.dataset.name;
        modalEmail.textContent = btn.dataset.email;
        modalUserId.value = btn.dataset.id;
        modalUserType.value = btn.dataset.type;
    });
});
document.getElementById('closeApprove').addEventListener('click', ()=> approveModal.style.display='none');

const confirmModal = document.getElementById('confirmModal');
const confirmTitle = document.getElementById('confirmTitle');
const confirmMsg = document.getElementById('confirmMsg');
const confirmUserId = document.getElementById('confirmUserId');
const confirmUserType = document.getElementById('confirmUserType');
const confirmActionBtn = document.getElementById('confirmActionBtn');
document.querySelectorAll('.open-confirm').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        confirmModal.style.display='flex';
        confirmUserId.value = btn.dataset.id;
        confirmUserType.value = btn.dataset.type;
        confirmActionBtn.name = "action";
        confirmActionBtn.value = btn.dataset.action;
        if(btn.dataset.action === "suspend"){
            confirmTitle.textContent="Suspend User";
            confirmMsg.textContent="Are you sure you want to suspend this account?";
            confirmActionBtn.textContent="Suspend";
            confirmActionBtn.className="suspend-btn";
        } else {
            confirmTitle.textContent="Delete User";
            confirmMsg.textContent="Are you sure you want to permanently delete this account?";
            confirmActionBtn.textContent="Delete";
            confirmActionBtn.className="remove-btn";
        }
    });
});
document.getElementById('closeConfirm').addEventListener('click', ()=> confirmModal.style.display='none');

const searchInput = document.getElementById('searchInput');
searchInput.addEventListener('keyup', () => {
    const filter = searchInput.value.toLowerCase();
    document.querySelectorAll('#facultyTab tbody tr').forEach(row => {
        row.style.display = row.cells[1].textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
    document.querySelectorAll('#studentTab tbody tr').forEach(row => {
        row.style.display = row.cells[1].textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
