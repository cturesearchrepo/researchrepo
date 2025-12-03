<?php
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    $status_final = null;
    $message = '';

    $stmtFetch = $conn->prepare("SELECT * FROM research_documents WHERE id=?");
    $stmtFetch->bind_param("i", $id);
    $stmtFetch->execute();
    $submission = $stmtFetch->get_result()->fetch_assoc();
    $stmtFetch->close();

    if ($submission && $submission['prev_status'] === 'ApprovedbyAdvicer') {
    $stmtCheck = $conn->prepare("
        SELECT COUNT(*) AS pending_or_rejected
        FROM research_reviewers
        WHERE research_id = ?
          AND status != 'ApprovedbyPanelist'
    ");
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ($resultCheck['pending_or_rejected'] == 0) {
        if ($action === 'approve') {
            $status_final = 'ApprovedbyAdmin';
            $message = "Research successfully approved!";
        } elseif ($action === 'reject') {
            $status_final = 'RejectedbyAdmin';
            $message = "Research successfully rejected!";
        }

        if ($status_final) {
            $stmtUpdate = $conn->prepare("UPDATE research_documents SET status=? WHERE id=?");
            $stmtUpdate->bind_param("si", $status_final, $id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            echo "<div style='background:#dff0d8;padding:10px;margin:10px 0;border:1px solid green;color:green;'>{$message}</div>";
        }
    } else {
        echo "<div style='background:#fff3cd;padding:10px;margin:10px 0;border:1px solid #ffecb5;color:#856404;'>⚠ Cannot approve: some panelists have not approved yet.</div>";
    }
} else {
    echo "<div style='background:#fdeaea;padding:10px;margin:10px 0;border:1px solid #cc0000;color:#cc0000;'>⚠ Cannot approve: adviser has not approved yet.</div>";
}}
$result = $conn->query("
    SELECT r.*
    FROM research_documents r
    WHERE r.prev_status = 'ApprovedbyAdvicer'
      AND r.status != 'ApprovedbyAdmin'
      AND NOT EXISTS (
          SELECT 1
          FROM research_reviewers rr
          WHERE rr.research_id = r.id
            AND rr.status != 'ApprovedbyPanelist'
      )
    ORDER BY r.uploaded_at DESC
");
$uploads = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();
function statusClass($status) {
    return match(strtolower($status)) {
        'approvedbyadvicer'   => 'badge pending',
        'approvedbypanelist'  => 'badge pending',
        'approvedbyadmin'     => 'badge approved',
        'rejectedbyadmin'     => 'badge declined',
        'rejectedbyadvicer'   => 'badge declined',
        'rejectedbypanelist'  => 'badge declined',
        'revisionrequired'    => 'badge revision',
        default               => ''
    };}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Research Upload Requests</title>
<link rel="stylesheet" href=".//AllStyles/user-managemen.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>
.badge.pending { background:#eef3ff; color:#00008b; }
.badge.approved { background:#e6f7ed; color:#087f23; }
.badge.declined { background:#fdeaea; color:#cc0000; }
.badge.revision { background:#fff3cd; color:#8a6d3b; }
td.abstract { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
td.feedback { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-style: italic; color:#555; }
.action-buttons { display: flex; flex-wrap: wrap; gap: 6px; }
.action-buttons button { display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 6px 14px; border-radius: 5px; text-decoration: none; font-size: 0.9em; transition: all 0.2s; cursor: pointer; border: none; }
.view-btn { background:#007bff; color:#fff; } .view-btn:hover { background:#0056b3; }
.approve-btn { background:#28a745; color:#fff; } .approve-btn:hover { background:#1e7e34; }
.reject-btn { background:#dc3545; color:#fff; } .reject-btn:hover { background:#a71d2a; }
.badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; text-transform: capitalize; }
#searchInput { padding: 8px 12px; border-radius: 8px; border: 1px solid #ccc; width: 250px; margin-bottom: 15px; font-size: 14px; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); }
.modal-content { background: #fff; margin: 10% auto; padding: 20px; width: 400px; border-radius: 8px; text-align: center; position: relative; }
.modal-content h3 { margin-top: 0; }
.modal-content .close { position: absolute; right: 10px; top: 10px; font-size: 20px; cursor: pointer; }
.modal-content button { margin: 10px 5px; padding: 6px 14px; border: none; border-radius: 5px; cursor: pointer; }
</style>
</head>
<body>
<div class="dashboard-container">
<main class="main-content">
<header class="top-header">
    <h2>Research Upload Requests (Pending Admin Approval)</h2>
    <input type="text" id="searchInput" placeholder="Search by Title, Author, or Student ID">
</header>

<?php if (empty($uploads)): ?>
    <p>No research uploads pending admin approval at the moment.</p>
<?php else: ?>
    <div class="table-wrapper">
    <table class="user-table" id="uploadsTable">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>Year</th>
                <th>Type</th>
                <th>Faculty Feedback</th>
                <th>Status</th>
                <th>Uploaded</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($uploads as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['uploaded_by_student']) ?></td>
                <td title="<?= htmlspecialchars($u['abstract']) ?>"><?= htmlspecialchars($u['title']) ?></td>
                <td><?= htmlspecialchars($u['author']) ?></td>
                <td><?= htmlspecialchars($u['year_completed']) ?></td>
                <td><?= htmlspecialchars($u['research_type']) ?></td>
                <td class="feedback"><?= htmlspecialchars($u['faculty_feedback'] ?? '-') ?></td>
                <td><span class="<?= statusClass($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                <td><?= date("M d, Y", strtotime($u['uploaded_at'])) ?></td>
                <td class="action-buttons">
                    <?php if (!empty($u['file_path']) && file_exists($u['file_path'])): ?>
                        <a href="<?= htmlspecialchars($u['file_path']) ?>" target="_blank" class="view-btn"><i class="fa-solid fa-file-pdf"></i> View</a>
                    <?php endif; ?>

                    <button type="button" class="approve-btn" onclick="openModal('approveModal<?= $u['id'] ?>')">
                        <i class="fa-solid fa-check"></i> Approve
                    </button>

                    <button type="button" class="reject-btn" onclick="openModal('rejectModal<?= $u['id'] ?>')">
                        <i class="fa-solid fa-xmark"></i> Reject
                    </button></td></tr>
            <div id="approveModal<?= $u['id'] ?>" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('approveModal<?= $u['id'] ?>')">&times;</span>
                    <h3>Confirm Approval</h3>
                    <p>Are you sure you want to approve this research?</p>
                    <form method="post">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="approve-btn">Yes, Approve</button>
                        <button type="button" onclick="closeModal('approveModal<?= $u['id'] ?>')">Cancel</button>
                    </form></div></div>
            <div id="rejectModal<?= $u['id'] ?>" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('rejectModal<?= $u['id'] ?>')">&times;</span>
                    <h3>Confirm Rejection</h3>
                    <p>Are you sure you want to reject this research?</p>
                    <form method="post">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="reject-btn">Yes, Reject</button>
                        <button type="button" onclick="closeModal('rejectModal<?= $u['id'] ?>')">Cancel</button>
                    </form></div></div>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</main>
</div>
<script>
const searchInput = document.getElementById('searchInput');
searchInput.addEventListener('keyup', function() {
    const filter = searchInput.value.toLowerCase();
    const trs = document.querySelectorAll("#uploadsTable tbody tr");
    trs.forEach(tr => {
        const studentId = tr.cells[0].textContent.toLowerCase();
        const title = tr.cells[1].textContent.toLowerCase();
        const author = tr.cells[2].textContent.toLowerCase();
        tr.style.display = (studentId.includes(filter) || title.includes(filter) || author.includes(filter)) ? '' : 'none';
    });
});
function openModal(id) {
    document.getElementById(id).style.display = "block";
}
function closeModal(id) {
    document.getElementById(id).style.display = "none";
}
window.onclick = function(event) {
    document.querySelectorAll(".modal").forEach(modal => {
        if (event.target === modal) {
            modal.style.display = "none";
        }});}
</script></body></html>
