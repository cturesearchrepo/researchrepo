<?php
// faculty-archive.php
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Auto-restore deactivated faculty
$mysqli->query("
    UPDATE faculty
    SET status = 'active', lock_until = NULL, failed_attempts = 0
    WHERE status = 'deactivated'
      AND lock_until IS NOT NULL
      AND lock_until <= NOW()
");

// Fetch archived faculty records
$query = "
    SELECT id, faculty_id, fullname, username, email, department, profile_image, status, lock_until
    FROM faculty
    WHERE status IN ('suspended', 'declined', 'deactivated', 'archived')
    ORDER BY id DESC
";
$result = $mysqli->query($query);
$rows = [];
if ($result) {
    while($r = $result->fetch_assoc()) $rows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Faculty Archive</title>
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
  <h3>📦 Archived Faculty (Suspended / Declined / Deactivated)</h3>

  <div id="tableSection" class="card p-3 mb-3">
    <div class="table-responsive">
      <table id="archivedFacultyTable" class="table align-middle table-striped">
        <thead>
          <tr>
            <th>#</th><th>Faculty Name</th><th>Email</th><th>Department</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach($rows as $row): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['fullname']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['department'] ?? 'N/A') ?></td>
            <td>
              <?= htmlspecialchars($row['status']) ?>
              <?php if(strtolower($row['status'])==='deactivated' && !empty($row['lock_until'])): ?>
                <br><small class="text-danger">Unlocks in: 
                  <span class="countdown" data-until="<?= htmlspecialchars($row['lock_until']) ?>"></span>
                </small>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-view"
                      data-name="<?= htmlspecialchars($row['fullname']) ?>"
                      data-email="<?= htmlspecialchars($row['email']) ?>"
                      data-department="<?= htmlspecialchars($row['department'] ?? 'N/A') ?>"
                      data-status="<?= htmlspecialchars($row['status']) ?>"
                      onclick="viewProfile(this)">
                  View
              </button>

              <?php if(strtolower($row['status'])==='deactivated'): ?>
                <button class="btn btn-warning" onclick="unlockFaculty(<?= (int)$row['id'] ?>)">Unlock (Admin)</button>
              <?php endif; ?>

              <?php if(in_array(strtolower($row['status']), ['suspended','declined','archived'])): ?>
                <button class="btn btn-restore" onclick="restoreFaculty(<?= (int)$row['id'] ?>)">Make Active</button>
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
      <h5 id="facultyName">Faculty Profile</h5>
      <button class="btn-back" id="btnBack" onclick="hideViewer()">X</button>
    </div>
    <div id="facultyDetails" class="mt-3">
      <p><strong>Email:</strong> <span id="facultyEmail"></span></p>
      <p><strong>Department:</strong> <span id="facultyDept"></span></p>
      <p><strong>Status:</strong> <span id="facultyStatus"></span></p>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    $('#archivedFacultyTable').DataTable({ pageLength:5, lengthMenu:[5,10,25,50], language:{search:"Search faculty:"} });
});

function viewProfile(btn){
    $('#tableSection').hide();
    $('#viewerPanel').show();
    $('#facultyName').text($(btn).data('name'));
    $('#facultyEmail').text($(btn).data('email'));
    $('#facultyDept').text($(btn).data('department'));
    $('#facultyStatus').text($(btn).data('status'));
}

function hideViewer(){
    $('#viewerPanel').hide();
    $('#tableSection').show();
}

function restoreFaculty(id){
    if(!confirm('Restore this faculty to Active?')) return;
    fetch('restore_faculty.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'id='+encodeURIComponent(id)
    }).then(r=>r.json()).then(data=>{
        alert(data.message);
        if(data.status==='success') setTimeout(()=>location.reload(),800);
    });
}

function unlockFaculty(id){
    if(!confirm('Unlock this deactivated faculty?')) return;
    fetch('unlock_faculty.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'id='+encodeURIComponent(id)
    }).then(r=>r.json()).then(data=>{
        alert(data.message);
        if(data.status==='success') setTimeout(()=>location.reload(),800);
    });
}

function updateCountdowns(){
    document.querySelectorAll('.countdown').forEach(span=>{
        const until = new Date(span.dataset.until).getTime();
        const now = new Date().getTime();
        let diff = until - now;
        if(diff <= 0){ span.textContent="00:00:00"; return; }
        const hours = Math.floor(diff/(1000*60*60));
        const minutes = Math.floor((diff%(1000*60*60))/(1000*60));
        const seconds = Math.floor((diff%(1000*60))/1000);
        span.textContent = String(hours).padStart(2,'0')+":"+String(minutes).padStart(2,'0')+":"+String(seconds).padStart(2,'0');
    });
}
updateCountdowns();
setInterval(updateCountdowns,1000);
</script>
</body>
</html>
<?php $mysqli->close(); ?>
