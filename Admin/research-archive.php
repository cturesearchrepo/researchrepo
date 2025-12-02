<?php
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) { 
    die("Connection failed: " . $mysqli->connect_error); 
}

// Fetch archived research documents from research_documents table
$query = "
    SELECT rd.*,
           c.name AS category_name,
           COALESCE((SELECT COUNT(*) FROM research_views rv WHERE rv.research_id = rd.id), 0) AS total_views,
           COALESCE((SELECT AVG(r.rating) FROM ratings r WHERE r.research_id = rd.id), 0) AS avg_rating
    FROM research_documents rd
    LEFT JOIN categories c ON rd.category_id = c.id
    WHERE rd.status = 'Archive'
    ORDER BY rd.id DESC
";
$result = $mysqli->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Archived Research Documents</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
/* Global Styles */
.container { margin: 30px auto; max-width: 1100px; }
.card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
.card-header { font-size: 1.5rem; font-weight: 600; color: #8B0000; }

/* Table Styling */
.table thead th { background: #8B0000; color: #fff; text-align: center; white-space: nowrap; }
.table tbody td { vertical-align: middle; text-align: center; word-wrap: break-word; max-width: 200px; }

/* Buttons inline */
.action-buttons { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
.btn-action { padding: 6px 12px; border-radius: 5px; border: none; cursor: pointer; font-weight: 600; transition: 0.3s; font-size: 0.85rem; }
.btn-view { background: #9b141a; color:white; }
.btn-view:hover { background: #7a1012; }
.btn-restore { background: #28a745; color:white; }
.btn-restore:hover { background: #218838; }

/* Stars */
.stars { color: #FFD700; font-size: 1.2rem; }

/* Responsive Fix */
@media (max-width: 768px) {
  .table thead { font-size: 0.85rem; }
  .table tbody td { font-size: 0.85rem; }
  .btn-action { padding: 5px 10px; font-size: 0.8rem; }
}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="card-header">📦 Archived Research Documents</div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="archivedTable" class="table table-striped align-middle w-100">
          <thead>
            <tr>
              <th>#</th>
              <th>Title</th>
              <th>Author</th>
              <th>Category</th>
              <th>Course</th>
              <th>Adviser</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php $i=1; while($row = $result->fetch_assoc()): ?>
            <tr id="row-<?= $row['id'] ?>">
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($row['title']) ?></td>
              <td><?= htmlspecialchars($row['author']) ?></td>
              <td><?= htmlspecialchars($row['category_name']) ?></td>
              <td><?= htmlspecialchars($row['course']) ?></td>
              <td><?= htmlspecialchars($row['adviser']) ?></td>
              <td>
                <div class="action-buttons">
                  <!-- View button -->
                  <button class="btn-action btn-view" 
                      data-id="<?= $row['id'] ?>"
                      data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>"
                      data-author="<?= htmlspecialchars($row['author'], ENT_QUOTES, 'UTF-8') ?>"
                      data-adviser="<?= htmlspecialchars($row['adviser'], ENT_QUOTES, 'UTF-8') ?>"
                      data-category="<?= htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8') ?>"
                      data-abstract="<?= !empty($row['abstract']) ? htmlspecialchars($row['abstract'], ENT_QUOTES, 'UTF-8') : '' ?>"
                      data-file="<?= !empty($row['file_path']) ? htmlspecialchars($row['file_path'], ENT_QUOTES, 'UTF-8') : '' ?>"
                      data-views="<?= (int)$row['total_views'] ?>"
                      data-rating="<?= round($row['avg_rating'],2) ?>"
                  >View</button>

                  <!-- Restore button -->
                  <button class="btn-action btn-restore" onclick="restoreDocument(<?= $row['id'] ?>)">
                      Restore
                  </button>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="researchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Research Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Author:</strong> <span id="modalAuthor"></span></p>
        <p><strong>Adviser:</strong> <span id="modalAdviser"></span></p>
        <p><strong>Category:</strong> <span id="modalCategory"></span></p>
        <p><strong>Views:</strong> <span id="modalViews"></span></p>
        <p><strong>Rating:</strong> <span id="modalRating"></span></p>
        <p><strong>Abstract:</strong></p>
        <p id="modalAbstract"></p>
        <hr>
        <div id="modalPDFContainer" style="height:500px; overflow:auto;"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.min.js"></script>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.worker.min.js';

function renderStars(rating) {
    if (!rating || rating <= 0) return '<span class="text-muted">No ratings yet</span>';
    const fullStars = Math.floor(rating);
    const halfStar = (rating - fullStars) >= 0.5 ? 1 : 0;
    const emptyStars = 5 - fullStars - halfStar;
    let stars = "★".repeat(fullStars);
    if (halfStar) stars += "☆"; 
    stars += "✩".repeat(emptyStars);
    return `<span class="stars">${stars}</span> <span class="text-muted">(${rating})</span>`;
}

$(document).ready(function() {
    $('#archivedTable').DataTable({ pageLength: 5, autoWidth: false, responsive: true });

    $(".btn-view").on("click", function() {
        const file = $(this).data("file");
        const title = $(this).data("title");
        const author = $(this).data("author");
        const adviser = $(this).data("adviser");
        const category = $(this).data("category");
        const abstract = $(this).data("abstract");
        const views = $(this).data("views");
        const rating = parseFloat($(this).data("rating"));

        $("#modalTitle").text(title);
        $("#modalAuthor").text(author);
        $("#modalAdviser").text(adviser);
        $("#modalCategory").text(category);
        $("#modalViews").text(views);
        $("#modalRating").html(renderStars(rating));
        $("#modalAbstract").html(abstract ? abstract.replace(/\n/g, '<br>') : 'No abstract available.');
        $("#modalPDFContainer").html('');

        if(file){
            const url = 'view_file.php?file=' + encodeURIComponent(file);
            pdfjsLib.getDocument(url).promise.then(pdf => {
                for (let i = 1; i <= pdf.numPages; i++) {
                    pdf.getPage(i).then(page => {
                        const viewport = page.getViewport({scale: 1.2});
                        const canvas = document.createElement('canvas');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        $("#modalPDFContainer").append(canvas);
                        page.render({canvasContext: canvas.getContext('2d'), viewport: viewport});
                    });
                }
            }).catch(() => {
                $("#modalPDFContainer").html('<p class="text-danger">PDF not available.</p>');
            });
        } else {
            $("#modalPDFContainer").html('<p class="text-danger">PDF not available.</p>');
        }

        const modal = new bootstrap.Modal(document.getElementById('researchModal'));
        modal.show();
    });
});
function restoreDocument(id) {
    if(confirm("Are you sure you want to restore this document?")) {
        fetch('restore_research.php', { 
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if(data.status === 'success'){
                const table = $('#archivedTable').DataTable();
                table.row($('#row-' + id)).remove().draw();
            }
        })
        .catch(err => {
            console.error(err);
            alert("An error occurred while restoring the document.");
        });
    }
}

</script>
</body>
</html>
