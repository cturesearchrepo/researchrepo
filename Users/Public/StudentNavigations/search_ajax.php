<?php
$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$selectedCategory = $_GET['category'] ?? 'all';
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$countSql = "SELECT COUNT(*) AS total
             FROM research_documents r
             LEFT JOIN categories c ON r.category_id = c.id
             WHERE r.status IN ('Active','ApprovedbyAdmin')";
$params = [];
$types = "";

if ($selectedCategory !== 'all') {
    $countSql .= " AND c.name = ?";
    $params[] = $selectedCategory;
    $types .= "s";
}

$stmt = $conn->prepare($countSql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$totalEntries = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT r.id, r.title, r.author, c.name AS category,
               r.department, r.research_type, r.year_completed
        FROM research_documents r
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE r.status IN ('Active','ApprovedbyAdmin')";

if ($selectedCategory !== 'all') { $sql .= " AND c.name = ?"; }

$sql .= " ORDER BY r.year_completed DESC, r.title ASC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($selectedCategory !== 'all') { $stmt->bind_param("sii", $selectedCategory, $limit, $offset); }
else { $stmt->bind_param("ii", $limit, $offset); }

$stmt->execute();
$result = $stmt->get_result();

echo '<style>
.action-btn { background-color: #a31820; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
.action-btn:hover { background-color: #7f1015; }
.pagination { margin-top: 15px; display: flex; gap: 5px; }
.pagination button { padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; background-color: white; cursor: pointer; }
.pagination button:hover:not(:disabled) { background-color: #f0f0f0; }
.pagination button.active { background-color: #a31820; color: white; border: 1px solid #a31820; }
.pagination button:disabled { background-color: #eee; color: #888; cursor: default; }
</style>';

echo '<table id="researchTable">
        <thead>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Category</th>
                <th>Department</th>
                <th>Type</th>
                <th>Year</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>';

while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>" . htmlspecialchars($row['title']) . "</td>
            <td>" . htmlspecialchars($row['author']) . "</td>
            <td>" . htmlspecialchars($row['category']) . "</td>
            <td>" . htmlspecialchars($row['department']) . "</td>
            <td>" . htmlspecialchars($row['research_type']) . "</td>
            <td>" . htmlspecialchars($row['year_completed']) . "</td>
            <td><button class='action-btn view-details' data-id='" . $row['id'] . "'>🔍 View Details</button></td>
          </tr>";
}
echo '</tbody></table>';

$totalPages = ceil($totalEntries / $limit);
echo '<div class="pagination">';
echo ($page > 1) ? "<button type='button' data-page='" . ($page - 1) . "'>Previous</button>"
                  : "<button type='button' disabled>Previous</button>";
echo "<button type='button' class='active'>$page</button>";
echo ($page < $totalPages) ? "<button type='button' data-page='" . ($page + 1) . "'>Next</button>"
                           : "<button type='button' disabled>Next</button>";
echo '</div>';

$conn->close();
?>

<script>
document.addEventListener("click", function(e) {
    if (e.target.classList.contains("view-details")) {
        let id = e.target.dataset.id;
        fetch("research_details.php?id=" + id)
            .then(res => res.text())
            .then(html => {
                document.getElementById("searchSection").style.display = "none";
                const detailsDiv = document.getElementById("detailsSection");
                detailsDiv.style.display = "block";
                detailsDiv.innerHTML = html;
                detailsDiv.querySelectorAll("script").forEach(oldScript => {
                    const newScript = document.createElement("script");
                    if (oldScript.src) newScript.src = oldScript.src;
                    else newScript.textContent = oldScript.textContent;
                    document.body.appendChild(newScript);
                });
            })
            .catch(err => console.error("Error:", err));
    }

    if (e.target.closest(".pagination button") && e.target.dataset.page) {
        let page = e.target.dataset.page;
        const category = "<?= urlencode($selectedCategory) ?>";
        fetch(`search_ajax.php?p=${page}&category=${category}`)
            .then(res => res.text())
            .then(html => { document.getElementById("researchContent").innerHTML = html; })
            .catch(err => console.error("Error:", err));
    }
});
</script>
