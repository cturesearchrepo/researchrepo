<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";
$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);}
$sql = "
    SELECT rar.id, rar.status, rar.requested_at, rar.expire_at, rar.admin_note,
           rd.title AS research_title,
           rd.author AS research_author,
           s.fullname AS student_name, s.student_id, s.email
    FROM research_access_requests rar
    JOIN research_documents rd ON rar.research_id = rd.id
    JOIN students s ON rar.student_id = s.student_id
    WHERE rar.status != 'CanceledbyUser'
    ORDER BY rar.requested_at DESC
";
$result = $conn->query($sql);
$requests = [];
$now = new DateTime();
while ($row = $result->fetch_assoc()) {
    $expire = new DateTime($row['expire_at']);
    $diff_seconds = $expire->getTimestamp() - $now->getTimestamp();
    $row['remaining_seconds'] = $diff_seconds > 0 ? $diff_seconds : 0;
    $requests[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Research Access Requests</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #f5f6fa; margin:0; }
.container { margin: 30px auto; max-width: 1200px; }
h2 { color:#8B0000; margin-bottom:20px; font-weight:600; }

.action-buttons { display: flex; gap: 6px; }
.btn {
    padding:6px 10px; border:none; border-radius:4px; cursor:pointer;
    font-size:1rem; display:inline-flex; align-items:center; justify-content:center; transition: all 0.2s ease;
}
.btn-view { background:#007bff; color:white; }
.btn-view:hover { background:#0056b3; }
.btn-approve { background:#28a745; color:white; }
.btn-approve:hover { background:#1e7e34; }
.btn-reject { background:#dc3545; color:white; }
.btn-reject:hover { background:#a71d2a; }
.btn-cancel { background:#ff9800; color:white; }
.btn-cancel:hover { background:#e67e22; }

.badge { padding:4px 8px; border-radius:12px; font-size:0.85rem; font-weight:bold; text-transform:capitalize; }
.badge.pending { background:#eef3ff; color:#00008b; }
.badge.approved { background:#e6f7ed; color:#087f23; }
.badge.declined { background:#fdeaea; color:#cc0000; }
table.dataTable {
    table-layout: fixed;
    word-wrap: break-word;
    width: 90%;
}

table.dataTable td,
table.dataTable th {
    white-space: normal;
    word-break: break-word;
    max-width: 200px;
    overflow-wrap: anywhere;}
.modal {
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    z-index:1000;
}
.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-content { background:white; padding:25px; border-radius:8px; width:550px; max-width:95%; box-shadow:0 4px 20px rgba(0,0,0,0.3); position:relative; }
.modal-header { font-size:1.4rem; font-weight:bold; margin-bottom:15px; color:#8B0000; }
.modal-close { cursor:pointer; color:#666; font-size:1.2rem; position:absolute; top:15px; right:15px; }
.modal-body { display:flex; flex-direction:column; gap:10px; font-family:Arial,sans-serif; line-height:1.4; }
.modal-body div { display:flex; gap:10px; }
.modal-body div span.label { font-weight:600; color:#333; min-width:140px; text-align:right; }
.modal-body div span.value { font-weight:normal; color:#555; word-break:break-word; }
/* === MODALS (Favorites-style) === */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 999;
}
.modal-box {background: #fff;border-radius: 12px;padding: 25px 30px;text-align: center;max-width: 350px;box-shadow: 0 5px 20px rgba(0, 0, 0, 0.25);animation: fadeIn 0.2s ease-in;}
.modal-box h3 {
  color: #8B0000;
  margin-bottom: 10px;
}

.modal-buttons {
  margin-top: 20px;
  display: flex;
  justify-content: center;
  gap: 12px;
}
.btn.yes {
  background: #8B0000;
  color: #fff;
}

.btn.no {
  background: #ccc;
  color: #222;
}

.btn.ok {
  background: #8B0000;
  color: #fff;
}

.btn:hover {
  transform: scale(1.03);
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }}
</style>
</head>
<body>
<div class="container">
    <h2>Research Access Requests</h2>
    <table id="adminTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Student</th>
                <th>Student ID</th>
                <th>Email</th>
                <th>Research</th>
                <th>Requested</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $req): ?>
            <tr data-remaining="<?= $req['remaining_seconds'] ?>" data-note="<?= htmlspecialchars($req['admin_note']) ?>">
                <td><?= htmlspecialchars($req['student_name']) ?></td>
                <td><?= htmlspecialchars($req['student_id']) ?></td>
                <td><?= htmlspecialchars($req['email']) ?></td>
                <td><?= htmlspecialchars($req['research_title']) ?></td>
                <td><?= date("M d, Y h:i A", strtotime($req['requested_at'])) ?></td>
                <td><span class="badge <?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></td>
                <td>
                   <div class="action-buttons">
                        <button class="btn btn-view"
        data-id="<?= $req['id'] ?>"
        data-author="<?= htmlspecialchars($req['research_author']) ?>"
        title="View">🔍</button>
                        <?php if ($req['status'] === 'pending' || $req['status'] === 'extendRequested') : ?>
                            <button class="btn btn-approve" data-id="<?= $req['id'] ?>" data-status="<?= $req['status'] ?>" title="Approve">✅</button>
                            <button class="btn btn-reject" data-id="<?= $req['id'] ?>" data-status="<?= $req['status'] ?>" title="Reject">❌</button>
                        <?php endif; ?>
                        <?php if ($req['status'] === 'approved' || $req['status'] === 'rejected'): ?>
                            <button class="btn btn-cancel" data-id="<?= $req['id'] ?>" title="Cancel">✖️</button>
                        <?php endif; ?>
                    </div></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="modal" id="detailsModal">
    <div class="modal-content">
        <span class="modal-close" onclick="$('#detailsModal').removeClass('show')">✖</span>
        <div class="modal-header">Request Details</div>
        <div id="modalBody" class="modal-body">Loading...</div>
    </div>
</div>
<div id="actionModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h3 id="actionTitle">Confirm Action</h3>
    <div id="actionMessage" style="margin-top:10px; text-align:left;"></div>
    <input type="text" id="actionInput" placeholder="Enter reason" style="display:none; width:100%; padding:5px; margin-top:10px;"/>
    <div class="modal-buttons">
      <button class="btn yes" id="actionYes">Yes</button>
      <button class="btn no" id="actionNo">No</button>
    </div>
  </div>
</div>
<div id="messageModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h3 id="messageTitle">Message</h3>
    <p id="messageText" style="margin-top:10px;"></p>
    <div class="modal-buttons">
      <button class="btn ok" id="messageClose">OK</button>
    </div>
  </div>
</div>
<script>
$(document).ready(function() {
    $('#adminTable').DataTable();

    function startCountdown(seconds, element) {
        let remaining = seconds;
        function update() {
            let days = Math.floor(remaining / 86400);
            let hours = Math.floor((remaining % 86400) / 3600);
            let minutes = Math.floor((remaining % 3600) / 60);
            let secs = remaining % 60;
            if (remaining <= 0) {
                element.text('Expired').css('color', 'red');
            } else {
                element.text(`${days}d ${hours}h ${minutes}m ${secs}s`);
                element.css('color', days >= 1 ? 'green' : 'orange');
                remaining--;
                setTimeout(update, 1000);
            }}update();}
    function showActionModal(message, showInput=false, callback) {
        $('#actionMessage').text(message);
        $('#actionInput').val('').toggle(showInput);
        $('#actionModal').fadeIn(150);

        $('#actionYes').off('click').on('click', function() {
            let input = $('#actionInput').val();
            if (showInput && !input) {
                showAlertModal("Reason is required!");
                return;
            }
            callback(input);
            closeActionModal();});
        $('#actionNo').off('click').on('click', function() {
            closeActionModal();
        });}
    function closeActionModal() {
        $('#actionModal').fadeOut(150);}
    function showAlertModal(message) {
        $('#messageText').text(message);
        $('#messageModal').fadeIn(150);

        $('#messageClose').off('click').on('click', function() {
            $('#messageModal').fadeOut(150);
        });}
$('.btn-view').on('click', function() {
    let row = $(this).closest('tr');
    let status = row.find('td:nth-child(6) .badge').text().toLowerCase();
    let remaining_seconds = parseInt(row.data('remaining')) || 0;
    let researchAuthor = $(this).data('author');
    let data = {
        research_title: row.find('td:nth-child(4)').text(),
        research_author: researchAuthor,
        student_name: row.find('td:nth-child(1)').text(),
        student_id: row.find('td:nth-child(2)').text(),
        email: row.find('td:nth-child(3)').text(),
        requested_at: row.find('td:nth-child(5)').text(),
        remaining_seconds: remaining_seconds,
        status: status
    };
    let accessExpiryText = (status === 'pending') ? 'N/A' : 'Loading...';
    let content = `
        <div><span class="label">Research Title:</span> <span class="value">${data.research_title}</span></div>
        <div><span class="label">Author:</span> <span class="value">${data.research_author}</span></div>
        <div><span class="label">Requested By:</span> <span class="value">${data.student_name} (${data.student_id})</span></div>
        <div><span class="label">Email:</span> <span class="value">${data.email}</span></div>
        <div><span class="label">Requested At:</span> <span class="value">${data.requested_at}</span></div>
        <div><span class="label">Access Expiry:</span> <span class="value" id="countdown">${accessExpiryText}</span></div>
    `;
    $('#modalBody').html(content);
    $('#detailsModal').addClass('show');
    if (status !== 'pending') {
        startCountdown(data.remaining_seconds, $('#countdown'));
    }});
    $('.btn-approve').on('click', function() {
        let row = $(this).closest('tr');
        let id = $(this).data('id');
        let status = $(this).data('status');
        let researchTitle = row.find('td:nth-child(4)').text();
        let msg = status === 'extendRequested'
            ? `Approve the extension request for "${researchTitle}"?`
            : `Approve the request for "${researchTitle}"?`;
        showActionModal(msg, false, function() {
            $.post('approve_request.php', { id: id, status: status }, function(res) {
                showAlertModal(res);
                location.reload();
            });
        });
    });
    $('.btn-reject').on('click', function() {
        let row = $(this).closest('tr');
        let id = $(this).data('id');
        let status = $(this).data('status');
        let researchTitle = row.find('td:nth-child(4)').text();
        showActionModal(`Enter reason for rejecting "${researchTitle}":`, true, function(reason) {
            $.post('reject_request.php', { id: id, reason: reason, status: status }, function(res) {
                showAlertModal(res);
                location.reload();
            }); });});
    $('.btn-cancel').on('click', function() {
        let row = $(this).closest('tr');
        let id = $(this).data('id');
        let researchTitle = row.find('td:nth-child(4)').text();
        let status = row.find('td:nth-child(6) .badge').text().toLowerCase();
        let msg = `Are you sure you want to cancel the ${status} request "${researchTitle}"? This will revoke access immediately.`;
        showActionModal(msg, false, function() {
            $.post('cancel_request.php', { id: id }, function(res) {
                showAlertModal(res);
                location.reload();
            });    });});
    $('.modal').on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $(this).removeClass('show');
        }});
    $('.modal-close').on('click', function() {
        $(this).closest('.modal').removeClass('show');
    });});
</script></body></html>
