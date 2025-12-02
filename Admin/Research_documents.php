<?php
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_errno) {
    die("Failed to connect: " . $mysqli->connect_error);
}

$query = "SELECT * FROM research_documents 
          WHERE status IN ('Active','ApprovedbyAdmin')
          ORDER BY year_completed DESC";

$result = $mysqli->query($query);

$documents = [];
while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Research Documents - Admin</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>

h2 { color:#8B0000; margin-bottom:20px; font-weight:600; }

table.dataTable thead th {
    background: #f0f0f0; color: #333; font-weight:600;
}

.action-buttons { display: flex; gap: 6px; }

.btn { 
    padding:6px 10px; 
    border:none; 
    border-radius:4px; 
    cursor:pointer; 
    font-size:1rem; 
    display:inline-flex;
    align-items:center;
    justify-content:center;
    transition: all 0.2s ease;
}
.btn-view { background:#007bff; color:white; }
.btn-view:hover { background:#0056b3; }
.btn-archive { background:#dc3545; color:white; }
.btn-archive:hover { background:#a71d2a; }
.badge { padding:4px 8px; border-radius:12px; font-size:0.85rem; font-weight:bold; text-transform:capitalize; }
.badge.Active { background:#e6f7ed; color:#087f23; }
.badge.ApprovedbyAdmin { background:#eef3ff; color:#00008b; }

</style>
</head>
<body>

<div class="container">
    <h2>Research Documents</h3>
    <table id="researchTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Title</th>
                <th>Adviser</th>
                <th>Author</th>
                <th>Course</th>
                <th>Year</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($documents as $doc): ?>
            <tr id="row-<?= $doc['id'] ?>">
                <td><?= htmlspecialchars($doc['title']); ?></td>
                <td><?= htmlspecialchars($doc['adviser']); ?></td>
                <td><?= htmlspecialchars($doc['author']); ?></td>
                <td><?= htmlspecialchars($doc['course']); ?></td>
                <td><?= htmlspecialchars($doc['year_completed']); ?></td>
                <td><span class="badge <?= htmlspecialchars($doc['status']); ?>"><?= htmlspecialchars($doc['status']); ?></span></td>
                <td>
                    <div class="action-buttons">
                        <a class="btn btn-view" title="View Document" href="view_file.php?id=<?= urlencode($doc['id']); ?>" target="_blank">🔍</a>
                        <button class="btn btn-archive" title="Archive Document" onclick="archiveDocument(<?= $doc['id']; ?>)">🗄️</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function() {
    $('#researchTable').DataTable({
        pageLength: 5,
        lengthMenu: [5, 10, 25, 50],
        language: { search:"Search research:", emptyTable:"No research documents found" }
    });
});

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
