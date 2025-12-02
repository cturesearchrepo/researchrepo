<?php
if(!isset($_SESSION['student_id'])){
    header("Location: login.php");
    exit;
}
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$studentId = intval($_SESSION['student_id']);
$sql = "
    SELECT rar.id, rar.status, rar.requested_at, rar.expire_at,
           rar.admin_note, 
           rd.title AS research_title, rd.author AS research_author,
           rd.keywords, rd.abstract, rd.adviser, rd.file_path
    FROM research_access_requests rar
    JOIN research_documents rd ON rar.research_id = rd.id
    WHERE rar.student_id = ? AND rar.status != 'canceledbyUser'
    ORDER BY rar.requested_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$requests = [];
while ($row = $result->fetch_assoc()) $requests[] = $row;
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Research Accessed - Cebu Technological University Library</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
main { padding: 25px; max-width: 1200px; }
.table-header {font-size: 30px;font-weight: 700;color: #8B0000;border-bottom: 3px solid #f0f0f0;padding-bottom: 10px;margin-bottom: 25px;}
table.dataTable {width: 100% !important;border-collapse: separate;border-spacing: 0;table-layout: fixed;}
table.dataTable th, table.dataTable td {
    word-wrap: break-word;
    white-space: normal;
    padding: 12px 15px;
    font-size: 0.95rem;
    vertical-align: middle;}
table.dataTable thead th {
    background: #fafafa;
    font-weight: 600;
    color: #555;
    border-bottom: 2px solid #eee;}
table.dataTable tbody tr { transition: background 0.2s; }
table.dataTable tbody tr:hover { background: #fff0f0; transform: scale(1.01); transition: all 0.2s ease-in-out; }
.badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.08);}
.badge.approved { background: #e6f7ed; color: #087f23; }
.badge.pending { background: #eef3ff; color: #1a3b8b; }
.badge.declined { background: #fdeaea; color: #cc0000; }
.badge.expired { background: #fdeaea; color: #cc0000; }
.badge.canceled { background: #f0f0f0; color: #666; }
.btn-action {
    display: inline-block;
    background: linear-gradient(135deg,#9b141a,#b71c1c);
    color: #fff; padding: 7px 16px; border-radius: 8px;
    font-size: 0.9rem; font-weight: 600; border: none;
    cursor: pointer; transition: all 0.3s ease;
    text-decoration: none; text-align: center;}
.btn-action:hover {
    background: linear-gradient(135deg,#b71c1c,#9b141a);
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);}
.admin-note {
    color:#cc0000;
    font-weight:600;
    font-size:0.9rem;
    border:1px solid #f5c2c7;
    background:#f8d7da;
    padding:6px 10px;
    border-radius:6px;}
.dataTables_paginate { margin-top: 15px; text-align: center; }
.paginate_button { display: inline-block; padding: 6px 14px; margin: 0 3px; border-radius: 6px; border: 1px solid #9b141a; font-weight: 600; color: #9b141a !important; background: #fff; transition: 0.3s;}
.paginate_button:hover { background: #f9e6e7; }
.paginate_button.current { background: #9b141a !important; color: #fff !important; }
.countdown { font-size: 0.78rem; color: #1b7a1b; font-weight: 500; margin-top: 4px; display: block; }
.fade-out { transition: opacity 0.5s ease, height 0.5s ease; opacity: 0; height: 0; }
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex; justify-content: center; align-items: center;
  z-index: 9999;}
.modal-box {
  background: #fff;
  border-radius: 14px;
  padding: 25px 30px;
  width: 340px;
  text-align: center;
  box-shadow: 0 5px 20px rgba(0,0,0,0.2);
  animation: fadeIn 0.2s ease-in;}
.modal-box h3 {
  margin-top: 0; color: #8B0000; font-size: 1.3rem;}
.modal-box p {
  color: #333; font-size: 0.95rem; margin: 12px 0 20px;}
.modal-buttons {
  display: flex; justify-content: center; gap: 15px;}
.btn {
  padding: 7px 18px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;}
.btn.yes { background: #8B0000; color: #fff; }
.btn.no { background: #ccc; color: #222; }
.btn.ok { background: #8B0000; color: #fff; }
.btn:hover { opacity: 0.9; transform: scale(1.02); }
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }}
</style>
</head>
<body>

<main>
<section class="table-section">
    <div class="table-header">Research Accessed</div>
    <table id="requestsTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Research</th>
                <th>Author</th>
                <th>Date Requested</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $req):
                $remaining = ($req['expire_at'] && $req['expire_at'] !== "0000-00-00 00:00:00") 
                    ? (strtotime($req['expire_at']) - time()) / 86400 : null;
            ?>
            <tr>
                <td><?= htmlspecialchars($req['research_title']) ?></td>
                <td><?= htmlspecialchars($req['research_author']) ?></td>
                <td><?= date("M. d, Y h:i A", strtotime($req['requested_at'])) ?></td>
                <td>
                    <span class="badge <?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span>
                    <?php if ($req['status'] === 'approved' && $remaining !== null): ?>
                        <small class="countdown" data-until="<?= htmlspecialchars($req['expire_at']) ?>"></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($req['status']==='approved' && $remaining>0): ?>
                        <a href="view_research.php?id=<?= $req['id'] ?>" target="_blank" class="btn-action"> View</a>
                    <?php elseif ($req['status']==='approved'): ?>
                        <button class="btn-action extend-btn" data-id="<?= $req['id'] ?>"> Extend</button>
                    <?php elseif ($req['status']==='pending'): ?>
                        <button class="btn-action cancel-btn" data-id="<?= $req['id'] ?>"> Cancel</button>
                    <?php elseif ($req['status']==='rejected'): ?>
                        <div class="admin-note">
                            <?= !empty($req['admin_note']) ? htmlspecialchars($req['admin_note']) : "Request was rejected by admin." ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
</main>
<div id="confirmModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h3 id="confirmTitle">Confirm Action</h3>
    <p id="confirmText">Are you sure?</p>
    <div class="modal-buttons">
      <button id="confirmYes" class="btn yes">Yes</button>
      <button id="confirmNo" class="btn no">No</button>
    </div>
  </div>
</div>
<div id="messageModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h3 id="messageTitle">Message</h3>
    <p id="messageText"></p>
    <div class="modal-buttons">
      <button id="messageClose" class="btn ok">OK</button>
    </div>
  </div>
</div>
<script>
$(document).ready(function() {
    $('#requestsTable').DataTable({
        responsive: true,
        pageLength: 5,
        lengthMenu: [5,10,25,50,100],
        language: { emptyTable: "No research requests available.", paginate: { previous: "Prev", next: "Next" } }
    });});
function showConfirm(message, callback) {
    $("#confirmText").text(message);
    $("#confirmModal").fadeIn(200);

    $("#confirmYes").off().on("click", function() {
        $("#confirmModal").fadeOut(200);
        callback(true);
    });
    $("#confirmNo").off().on("click", function() {
        $("#confirmModal").fadeOut(200);
        callback(false);
    });}
function showMessage(title, message) {
    $("#messageTitle").text(title);
    $("#messageText").text(message);
    $("#messageModal").fadeIn(200);
}
$("#messageClose").on("click", function() {
    $("#messageModal").fadeOut(200);
});
$(document).on("click", ".cancel-btn", function(){
    let id = $(this).data("id");
    let row = $(this).closest("tr");
    showConfirm("Are you sure you want to cancel this request?", function(confirmed){
        if(!confirmed) return;
        $.post("cancelrequest.php", {id:id}, function(resp){
            try {
                let res = JSON.parse(resp);
                showMessage("Request Status", res.message);
                if(res.success){
                    row.addClass("fade-out");
                    setTimeout(() => row.remove(), 500);
                }
            } catch(e){ console.error("Invalid JSON:", resp); }
        });});});
$(document).on("click", ".delete-btn", function(){
    let id = $(this).data("id");
    let row = $(this).closest("tr");

    showConfirm("Delete this expired request permanently?", function(confirmed){
        if(!confirmed) return;
        $.post("deleteexpirerequest.php", {id:id}, function(resp){
            try {
                let res = JSON.parse(resp);
                showMessage("Delete Request", res.message);
                if(res.success){
                    row.addClass("fade-out");
                    setTimeout(()=> row.remove(), 500);
                }
            } catch(e){ console.error("Invalid JSON:", resp); }
        });});});
function updateCountdowns(){
    document.querySelectorAll('.countdown').forEach(span=>{
        const until = new Date(span.dataset.until).getTime();
        const now = Date.now();
        let diff = until-now;
        if(diff<=0){
            const row = span.closest("tr");
            const badge = row.querySelector(".badge");
            badge.textContent = "Expired";
            badge.className = "badge expired"; 

            const id = row.querySelector('.extend-btn, .cancel-btn, .request-again-btn')?.dataset.id;
            row.querySelector("td:last-child").innerHTML = `
                <button class="btn-action extend-btn" data-id="${id}">⏳ Extend</button>
                <button class="btn-action delete-btn" data-id="${id}">🗑 Delete</button>
            `;
            span.remove();
            return; }
        const d=Math.floor(diff/(1000*60*60*24)),
              h=Math.floor((diff%(1000*60*60*24))/(1000*60*60)),
              m=Math.floor((diff%(1000*60*60))/(1000*60)),
              s=Math.floor((diff%(1000*60))/1000);
        span.textContent=`${d}d ${h}h ${m}m ${s}s remaining`;
    });}
updateCountdowns();
setInterval(updateCountdowns,1000);
$(document).on("click", ".extend-btn", function(){
    let id = $(this).data("id");
    let row = $(this).closest("tr");

    showConfirm("Request an extension for this research access?", function(confirmed){
        if(!confirmed) return;
        $.post("extendrequest.php", {id:id}, function(resp){
            try {
                let res = JSON.parse(resp);
                showMessage("Extension Request", res.message);
                if(res.success){
                    let badge = row.find(".badge");
                    badge.text("Extend Requested").removeClass().addClass("badge pending");
                    row.find("td:last").html(""); 
                }
            } catch(e){ console.error("Invalid JSON:", resp); }
        });});});
</script></body></html>
