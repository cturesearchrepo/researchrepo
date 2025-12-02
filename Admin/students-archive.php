<?php
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}


$mysqli->query("
    UPDATE students
    SET status = 'active', lock_until = NULL, failed_attempts = 0
    WHERE status = 'deactivated'
      AND lock_until IS NOT NULL
      AND lock_until <= NOW()
");

$query = "
    SELECT id, fullname, username, student_id, email, phone, status, profile_image, year_level, failed_attempts, lock_until
    FROM students
    WHERE status IN ('suspended', 'declined', 'deactivated', 'archived')
    ORDER BY id DESC
";
$result = $mysqli->query($query);
$rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) $rows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Archive</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<style>
.viewer-panel { display:none; }
.btn-back { background:#dc3545;color:#fff;border:none;padding:6px 12px;border-radius:5px;cursor:pointer }
.btn-restore { background:#28a745;color:#fff;border:none;padding:6px 12px;border-radius:5px;cursor:pointer }
.btn-view { background:#8b0000;color:#fff;border:none;padding:6px 12px;border-radius:5px;cursor:pointer }
</style>
</head>
<body>
<div class="container py-3">
  <h3>📦 Archived Students (Suspended / Declined / Deactivated)</h3>

  <div id="tableSection" class="card p-3 mb-3">
    <div class="table-responsive">
      <table id="archivedStudentsTable" class="table align-middle table-striped">
        <thead>
          <tr>
            <th>#</th><th>Student Name</th><th>Email</th><th>Year</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php $i = 1; foreach ($rows as $row): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['fullname']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['year_level'] ?? 'N/A') ?></td>
            <td>
              <?= htmlspecialchars($row['status']) ?>
              <?php if (strtolower($row['status']) === 'deactivated' && !empty($row['lock_until'])): ?>
                <br><small class="text-danger">Unlocks in: 
                  <span class="countdown" data-until="<?= htmlspecialchars($row['lock_until']) ?>"></span>
                </small>
              <?php endif; ?>


            </td>
            <td>
              <button class="btn btn-view"
                      data-name="<?= htmlspecialchars($row['fullname']) ?>"
                      data-email="<?= htmlspecialchars($row['email']) ?>"
                      data-year="<?= htmlspecialchars($row['year_level'] ?? 'N/A') ?>"
                      data-status="<?= htmlspecialchars($row['status']) ?>"
                      onclick="viewProfile(this)">
                  View
              </button>

             <?php if (strtolower($row['status']) === 'deactivated'): ?>
                <button class="btn btn-warning" onclick="unlockStudent(<?= (int)$row['id'] ?>)">Unlock (Admin)</button>
              <?php endif; ?>


              <?php if (in_array(strtolower($row['status']), ['suspended','declined','archived'])): ?>
                <button class="btn btn-restore" onclick="restoreStudent(<?= (int)$row['id'] ?>)">Make Active</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="viewerPanel" class="card viewer-panel p-3">
    <div class="d-flex justify-content-between">
      <h5 id="studentName">Student Profile</h5>
      <button class="btn-back" id="btnBack" onclick="hideViewer()">X</button>
    </div>
    <div id="studentDetails" class="mt-3">
      <p><strong>Email:</strong> <span id="studentEmail"></span></p>
      <p><strong>Year:</strong> <span id="studentYear"></span></p>
      <p><strong>Status:</strong> <span id="studentStatus"></span></p>
    </div>
  </div>
</div>

<div class="modal fade" id="generalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="modalTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-footer" id="modalFooter"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
  $('#archivedStudentsTable').DataTable({ pageLength:5, lengthMenu:[5,10,25,50], language:{search:"Search students:"} });
});

function viewProfile(btn){
  $('#tableSection').hide();
  $('#viewerPanel').show();
  $('#studentName').text($(btn).data('name'));
  $('#studentEmail').text($(btn).data('email'));
  $('#studentYear').text($(btn).data('year'));
  $('#studentStatus').text($(btn).data('status'));
}

function hideViewer(){
  $('#viewerPanel').hide();
  $('#tableSection').show();
}

function showMessage(title, message){
  $('#modalTitle').text(title);
  $('#modalBody').html(message);
  const m = new bootstrap.Modal(document.getElementById('generalModal'));
  m.show();
}

function restoreStudent(id){
  if(!confirm('Restore this student to Active?')) return;
  fetch('restore_students.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'id='+encodeURIComponent(id)
  }).then(r=>r.json()).then(data=>{
    showMessage(data.status==='success' ? 'Success':'Error', data.message);
    if(data.status==='success') setTimeout(()=>location.reload(),800);
  }).catch(e=>showMessage('Error', 'Request failed.'));
}

function unlockStudent(id){
  if(!confirm('Unlock this deactivated student?')) return;
  fetch('unlock_student.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'id='+encodeURIComponent(id)
  }).then(r=>r.json()).then(data=>{
    showMessage(data.status==='success' ? 'Success':'Error', data.message);
    if(data.status==='success') setTimeout(()=>location.reload(),800);
  }).catch(e=>showMessage('Error', 'Request failed.'));
}

function updateCountdowns() {
    document.querySelectorAll('.countdown').forEach(span => {
        const until = new Date(span.dataset.until).getTime();
        const now = new Date().getTime();
        let diff = until - now;

        if(diff <= 0) {
            span.textContent = "00:00:00";
            return;
        }

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        span.textContent = 
            String(hours).padStart(2,'0') + ":" + 
            String(minutes).padStart(2,'0') + ":" + 
            String(seconds).padStart(2,'0');
    });
}

updateCountdowns();
setInterval(updateCountdowns, 1000);
</script>
</body>
</html>
<?php $mysqli->close(); ?>
