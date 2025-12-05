<?php
$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$studentId = intval($_SESSION['student_id'] ?? 0);
if (!$studentId) {
    echo "You must be logged in to see your favorites.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($action === 'remove') {
        $favId = intval($_POST['favorite_id'] ?? 0);
        if ($favId <= 0) {
            $resp = ['success'=>false,'message'=>'Invalid favorite id'];
            echo $isAjax ? json_encode($resp) : header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }

        $del = $mysqli->prepare("DELETE FROM favorites WHERE id = ? AND student_id = ?");
        $del->bind_param("ii", $favId, $studentId);
        $ok = $del->execute();
        $del->close();

        $resp = ['success'=>(bool)$ok,'message'=>$ok?'Removed from favorites':'Failed to remove'];
        echo $isAjax ? json_encode($resp) : header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'rate') {
        $researchId = intval($_POST['research_id'] ?? 0);
        $rating     = intval($_POST['rating'] ?? 0);
        if ($researchId <= 0 || $rating < 1 || $rating > 5) {
            $resp = ['success'=>false,'message'=>'Invalid rating or research id'];
            echo $isAjax ? json_encode($resp) : header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }

        $upsert = $mysqli->prepare("
            INSERT INTO ratings (research_id, student_id, rating, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), created_at = NOW()
        ");
        $upsert->bind_param("iii", $researchId, $studentId, $rating);
        $ok = $upsert->execute();
        $upsert->close();

        $avg = 0; $count = 0;
        $s = $mysqli->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM ratings WHERE research_id = ?");
        $s->bind_param("i", $researchId);
        $s->execute();
        $res = $s->get_result();
        if ($row = $res->fetch_assoc()) {
            $avg   = round($row['avg_rating'] ?? 0, 2);
            $count = intval($row['cnt'] ?? 0);
        }
        $s->close();

        $resp = ['success'=>(bool)$ok,'message'=>$ok?'Rating saved':'Failed to save rating','avg'=>$avg,'count'=>$count];
        echo $isAjax ? json_encode($resp) : header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }

    $resp = ['success'=>false,'message'=>'Unknown action'];
    echo $isAjax ? json_encode($resp) : header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

$sql = "
SELECT f.id AS fav_id, f.created_at,
       rd.id AS research_id, rd.title AS research_title, rd.author AS research_author,
       ra.id AS request_id, ra.status, ra.expire_at
FROM favorites f
JOIN research_documents rd ON f.research_id = rd.id
LEFT JOIN research_access_requests ra
       ON ra.research_id = rd.id AND ra.student_id = ?
WHERE f.student_id = ?
ORDER BY f.created_at DESC
";




$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $studentId, $studentId);
$stmt->execute();
$result = $stmt->get_result();

$favorites = [];
while ($row = $result->fetch_assoc()) {
    $favorites[] = $row;
}
$stmt->close();
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Favorites - CTU Library</title>
  <style>

main { padding: 25px; max-width: 1200px; }


/* === HEADER === */
.table-header {
  font-size: 28px;
  font-weight: 700;
  color: #8B0000;
  border-bottom: 3px solid #eee;
  padding-bottom: 10px;
  margin-bottom: 20px;
}

/* === TABLE === */
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

table thead {
  background: #fafafa;
}

table th, table td {
  padding: 12px 15px;
  text-align: left;
  font-size: 0.95rem;
}

table th {
  font-weight: 600;
  color: #444;
  border-bottom: 2px solid #eaeaea;
}

table tbody tr {
  border-bottom: 1px solid #f0f0f0;
  transition: background 0.2s ease;
}

table tbody tr:hover {
  background-color: #fff0f0;
}

.small-muted {
  color: #888;
  font-size: 0.9rem;
}

/* === BUTTONS === */
.btn-action, .btn-remove, .btn {
  display: inline-block;
  padding: 7px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  border: none;
  transition: all 0.3s ease;
}

.btn-action {
  background: linear-gradient(135deg, #9b141a, #b71c1c);
  color: #fff;
  text-decoration: none;
}

.btn-action:hover {
  background: linear-gradient(135deg, #b71c1c, #9b141a);
  transform: translateY(-2px);
}

.btn-remove {
  background: #eee;
  color: #333;
}

.btn-remove:hover {
  background: #f3d1d1;
  color: #8B0000;
}

/* === PAGINATION === */
.pagination button {
  background: #fff;
  border: 1px solid #8B0000;
  border-radius: 6px;
  padding: 5px 12px;
  font-weight: 600;
  color: #8B0000;
  cursor: pointer;
  transition: 0.3s;
}

.pagination button.active {
  background: #8B0000;
  color: #fff;
}

.pagination button:hover:not(.active) {
  background: #f9e6e7;
}

/* === MODALS === */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 999;
}

.modal-box {
  background: #fff;
  border-radius: 12px;
  padding: 25px 30px;
  text-align: center;
  max-width: 350px;
  box-shadow: 0 5px 20px rgba(0, 0, 0, 0.25);
  animation: fadeIn 0.2s ease-in;
}

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

/* === TOAST === */
#toast {
  position: fixed;
  bottom: 30px;
  right: 30px;
  background: #8B0000;
  color: #fff;
  padding: 12px 20px;
  border-radius: 8px;
  font-weight: 600;
  display: none;
  z-index: 9999;
  animation: fadeInUp 0.3s ease-in-out;
}

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* === TABLE CONTROLS === */
.table-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  font-size: 0.95rem;
}

.table-controls select,
.table-controls input[type="search"] {
  padding: 5px 8px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 0.9rem;
}

/* === FADE ANIMATION === */
.fade-out {
  opacity: 0;
  height: 0;
  transition: all 0.5s ease;
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
  table thead {
    display: none;
  }

  table tr {
    display: block;
    margin-bottom: 15px;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  }

  table td {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    border: none;
  }

  table td::before {
    content: attr(data-label);
    font-weight: 600;
    color: #8B0000;
  }
}

td:last-child {
  white-space: nowrap;
}

.btn-action, .btn-remove {
  margin-right: 5px;
  vertical-align: middle;
}


  </style>
</head>
<body>
<main role="main" aria-label="My Favorites Page">
  <section class="table-section" aria-label="Favorites Table">
    <div class="table-header">My Favorites</div>

    <div class="table-controls">
        <label>Show
          <select id="perPage">
              <option selected>5</option>
              <option>10</option>
              <option>25</option>
              <option>50</option>
              <option>100</option>
          </select>

        </label>
        <label>Search:
            <input type="search" id="searchBox" />
        </label>
    </div>

    <table id="favoritesTable" aria-describedby="favCount">
      <thead>
        <tr>
          <th>Research</th>
          <th>Author</th>
          <th>Date Added</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($favorites) > 0): ?>
       <?php foreach ($favorites as $fav): ?>
<tr id="fav-row-<?= htmlspecialchars($fav['fav_id']) ?>">
  <td><?= htmlspecialchars($fav['research_title']) ?></td>
  <td><?= htmlspecialchars($fav['research_author']) ?></td>
  <td class="small-muted"><?= date("M. d, Y h:i A", strtotime($fav['created_at'])) ?></td>
  <td>
    <?php if (!empty($fav['request_id'])
          && strtolower($fav['status']) === 'approved'
          && strtotime($fav['expire_at']) > time()): ?>
    <a href="view_research.php?id=<?= $fav['request_id'] ?>" target="_blank" class="btn-action">View</a>
<?php endif; ?>

    <button class="btn-remove remove-btn" data-id="<?= htmlspecialchars($fav['fav_id']) ?>">Remove</button>
  </td>
</tr>
<?php endforeach; ?>

      <?php else: ?>
        <tr>
          <td colspan="4" style="text-align:center; color:#666;">No favorites found.</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="pagination" style="margin-top:12px; display:flex; justify-content:flex-end; gap:8px;">
      <button disabled>Previous</button>
      <button class="active">1</button>
      <button>Next</button>
    </div>

    <p id="favCount" style="margin-top:12px; font-size:0.85rem;">Showing <?= count($favorites) ?> entries</p>
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

<div id="toast"></div>

<script>
(function() {
  function showToast(msg, timeout = 2000) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(() => (t.style.display = 'none'), timeout);
  }

  function showConfirm(message, callback) {
    const modal = document.getElementById("confirmModal");
    document.getElementById("confirmText").textContent = message;
    modal.style.display = "flex";

    document.getElementById("confirmYes").onclick = () => {
      modal.style.display = "none";
      callback(true);
    };
    document.getElementById("confirmNo").onclick = () => {
      modal.style.display = "none";
      callback(false);
    };
  }

  function showMessage(title, message) {
    document.getElementById("messageTitle").textContent = title;
    document.getElementById("messageText").textContent = message;
    document.getElementById("messageModal").style.display = "flex";
  }

  document.getElementById("messageClose")?.addEventListener("click", () => {
    document.getElementById("messageModal").style.display = "none";
  });

  document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const favId = this.dataset.id;
      if (!favId) {
        showMessage("Error", "Invalid favorite ID.");
        return;
      }

      showConfirm("Are you sure you want to remove this from favorites?", function(confirmed) {
        if (!confirmed) return;

        fetch("removefromfavorites.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
          },
          body: new URLSearchParams({
            action: "remove",
            favorite_id: favId
          })
        })
        .then(r => r.json())
        .then(resp => {
          showMessage("Remove Favorite", resp.message);
          if (resp.success) {
            const row = document.getElementById("fav-row-" + favId);
            if (row) {
              row.classList.add("fade-out");
              setTimeout(() => row.remove(), 500);
            }
          }
        })
        .catch(err => showMessage("Network Error", err.message));
      });
    });
  });

  const searchBox = document.getElementById('searchBox');
  if (searchBox) {
    searchBox.addEventListener('input', function() {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll('#favoritesTable tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
      });
    });
  }
})();

(function() {
  const table = document.getElementById('favoritesTable');
  const perPageSelect = document.getElementById('perPage');
  const pagination = document.querySelector('.pagination');
  const rows = Array.from(table.querySelectorAll('tbody tr'));

  function paginate() {
    const perPage = parseInt(perPageSelect.value);
    const totalRows = rows.length;
    const totalPages = Math.ceil(totalRows / perPage);
    let currentPage = 1;

    function render() {
      rows.forEach((r, i) => {
        r.style.display = (i >= (currentPage - 1) * perPage && i < currentPage * perPage) ? '' : 'none';
      });

      pagination.innerHTML = `
        <button ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
        ${Array.from({ length: totalPages }, (_, i) => `
          <button class="${i + 1 === currentPage ? 'active' : ''}">${i + 1}</button>
        `).join('')}
        <button ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
      `;

      pagination.querySelectorAll('button').forEach((btn, i) => {
        btn.addEventListener('click', () => {
          if (btn.textContent === 'Previous' && currentPage > 1) currentPage--;
          else if (btn.textContent === 'Next' && currentPage < totalPages) currentPage++;
          else if (!isNaN(parseInt(btn.textContent))) currentPage = parseInt(btn.textContent);
          render();
        });
      });
    }
    render();
  }

  perPageSelect.addEventListener('change', paginate);
  paginate();
})();
</script>
</body>
</html>







