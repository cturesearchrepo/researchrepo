<?php
$faculty_id = intval($_SESSION['faculty_id'] ?? 0);
if (!$faculty_id) die("Unauthorized access");
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_errno) die("Failed to connect: " . $mysqli->connect_error);

$query = "SELECT * FROM research_documents 
          WHERE status IN ('Active', 'ApprovedbyAdmin')
          ORDER BY year_completed DESC";
$result = $mysqli->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Research Documents - Faculty</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
.card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
.card-header { font-size: 1.5rem; font-weight: 600; color: #8B0000; }
.btn-action { padding: 6px 12px; border-radius: 5px; border: none; cursor: pointer; font-weight: 600; transition: 0.3s; }
.btn-view { background: #9b141a; color:white; }
.btn-view:hover { background: #7a1012; }
.btn-archive { background: #dc3545; color:white; }
.btn-archive:hover { background: #b02a37; }

.modal-dialog {
    max-width: 90% !important;
    resize: both;
    overflow: auto;
}
iframe { width: 100%; height: 80vh; border: none; }
.btn-info { 
    background: #17a2b8; 
    color: white; 
    font-style: none;
}
.btn-info:hover { 
    background: #117a8b; 
}

</style>
</head>
<body>

<div class="container">
  <div class="card p-3">
    <div class="card-header mb-3">Research Documents</div>
    <div class="table-responsive">
      <table id="researchTable" class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Title</th>
            <th>Adviser</th>
            <th>Author</th>
            <th>Course</th>
            <th>Year</th>
            <th style="width:180px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr id="row-<?= $row['id'] ?>">
                <td><?= htmlspecialchars($row['title']); ?></td>
                <td><?= htmlspecialchars($row['adviser']); ?></td>
                <td><?= htmlspecialchars($row['author']); ?></td>
                <td><?= htmlspecialchars($row['course']); ?></td>
                <td><?= htmlspecialchars($row['year_completed']); ?></td>
                <td>

                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-danger" onclick="viewPDF(<?= $row['id'] ?>)">
                    <i class="bi bi-eye-fill"></i> View
                  </button>
                  <a class="btn btn-sm btn-info text-white" href="research_details.php?id=<?= $row['id'] ?>" target="_blank">
                    <i class="bi bi-info-circle-fill"></i> Info
                  </a>
                </div>
              </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content position-relative">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">PDF Viewer</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="pdfFrame" src=""></iframe>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('#researchTable').DataTable({
      pageLength: 5,
      lengthMenu: [5, 10, 25, 50],
      language: { search:"Search research:", emptyTable:"No research documents found" }
    });
});

function viewPDF(id) {
    $('#pdfFrame').attr('src', 'serve-pdf.php?id=' + encodeURIComponent(id));
    var pdfModal = new bootstrap.Modal(document.getElementById('pdfModal'));
    pdfModal.show();

    $.post('track-view-faculty.php', { research_id: id }, function(response) {
        console.log(response);
    });
}

function archiveDocument(id) {
    if(confirm("Are you sure you want to archive this document?")) {
        fetch('./archive-research.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'id=' + id
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if(data.status === 'success') {
                var table = $('#researchTable').DataTable();
                table.row($('#row-'+id)).remove().draw();
            }
        });
    }
}
</script>

</body>
</html>
