<?php
// Note: This code assumes $mysqli (your database connection object) is already defined and available 
// at the start of this file.

if (!isset($_SESSION['faculty_id'])) {
    exit("Unauthorized access.");
}

// 1. Get the School ID from the session (e.g., 8220916)
$sessionFacultyId = intval($_SESSION['faculty_id']);

// 2. CRITICAL FIX: Fetch the Internal Database ID (the primary key 'id') 
// Based on the SQL dump, research_documents.faculty_id links to faculty.id (e.g., 17), not faculty.faculty_id (e.g., 8220916).
$idQuery = $mysqli->prepare("SELECT id FROM faculty WHERE faculty_id = ?");
$idQuery->bind_param('i', $sessionFacultyId);
$idQuery->execute();
$result = $idQuery->get_result()->fetch_assoc();

if (!$result) {
    exit("Faculty user ID not found in the database.");
}

// Use this internal ID for all subsequent queries
$facultyInternalId = $result['id'];


// --- 1. STATS QUERIES (Optimized and using the correct Internal ID) ---

// 1A. Get counts for documents where the faculty is the primary supervisor (faculty_id)
// Constraints for research_documents_ibfk_1 show faculty_id links to faculty.id.
$docStatsQuery = $mysqli->prepare("
    SELECT
        COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) AS approved_docs,
        COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) AS pending_docs,
        COUNT(CASE WHEN approval_status = 'rejected' THEN 1 END) AS rejected_docs,
        COUNT(*) AS total_docs
    FROM research_documents
    WHERE faculty_id = ?
");
$docStatsQuery->bind_param('i', $facultyInternalId); 
$docStatsQuery->execute();
$docStats = $docStatsQuery->get_result()->fetch_assoc();


// 1B. Get counts for research where the faculty is a reviewer (reviewer_id)
// Constraints for research_reviewers_ibfk_2 show reviewer_id links to faculty.id.
$reviewerStatsQuery = $mysqli->prepare("
    SELECT
        COUNT(CASE WHEN status = 'ApprovedbyPanelist' THEN 1 END) AS approved_reviews,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) AS pending_reviews,
        COUNT(CASE WHEN status = 'RejectedbyPanelist' THEN 1 END) AS rejected_reviews,
        COUNT(*) AS total_reviews
    FROM research_reviewers
    WHERE reviewer_id = ?
");
$reviewerStatsQuery->bind_param('i', $facultyInternalId); 
$reviewerStatsQuery->execute();
$reviewerStats = $reviewerStatsQuery->get_result()->fetch_assoc();


// 1C. Combine the counts for the final stats array
$stats = [
    'total_supervised' => $docStats['total_docs'] + $reviewerStats['total_reviews'], 
    'pending'          => $docStats['pending_docs'] + $reviewerStats['pending_reviews'], 
    'approved'         => $docStats['approved_docs'] + $reviewerStats['approved_reviews'], 
    'rejected'         => $docStats['rejected_docs'] + $reviewerStats['rejected_reviews'] 
];


// --- 2. RECENT SUBMISSIONS QUERY ---
$recent = $mysqli->prepare("
    SELECT title, author, course, department, section, approval_status, submitted_date
    FROM research_documents
    WHERE faculty_id = ?
    ORDER BY submitted_date DESC
    LIMIT 10
");
$recent->bind_param('i', $facultyInternalId); 
$recent->execute();
$recentSubmissions = $recent->get_result();

?>

<h4 class="fw-bold mb-3">Action Priority Overview</h4>

<div class="row g-4 mb-4">
    
    <div class="col-12 col-md-6">
        <div class="row g-2">
            <?php 
            $cards = [
                ['label'=>'Action Needed','count'=>$stats['pending'],'color'=>'danger'],
                ['label'=>'Active Projects','count'=>$stats['total_supervised'],'color'=>'primary'],
                ['label'=>'Ready for Final','count'=>$stats['approved'],'color'=>'success'],
                ['label'=>'Student Revision','count'=>$stats['rejected'],'color'=>'warning']
            ];
            
            foreach ($cards as $card): ?>
                <div class="col-6">
                    <div class="card shadow-sm p-2 text-center border-0">
                        <h6 class="fw-bold text-<?= $card['color']; ?> mb-0" data-count="<?= $card['count']; ?>">0</h6>
                        <small class="text-muted"><?= $card['label']; ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="col-12 col-md-6">
        <div class="card shadow-sm p-3 position-relative h-100">
            <h6 class="fw-semibold mb-2">Status Distribution</h6>
            <canvas id="statusChart" height="150" style="max-height: 250px;"></canvas> 
            <div id="chartCenterText"
                style="position:absolute;top:60%;left:50%;transform:translate(-50%,-50%);
                font-size:1.5rem;font-weight:bold;color:#333;"></div>
        </div>
    </div>

</div>
<div class="card p-3 mt-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0">Recent Student Submissions</h6>
        <select id="statusFilter" class="form-select w-auto">
            <option value="">All</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
        </select>
    </div>
    <input type="text" id="searchInput" class="form-control mb-2" placeholder="Search submissions...">
    
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle" id="submissionsTable">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Course</th>
                    <th>Department</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Submitted Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentSubmissions->num_rows > 0): ?>
                    <?php while ($row = $recentSubmissions->fetch_assoc()): 
                        $statusText = $row['approval_status'];
                        $statusClass = match(strtolower($statusText)) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning'
                        };
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['title']); ?></td>
                            <td><?= htmlspecialchars($row['author']); ?></td>
                            <td><?= htmlspecialchars($row['course']); ?></td>
                            <td><?= htmlspecialchars($row['department']); ?></td>
                            <td><?= htmlspecialchars($row['section']); ?></td>
                            <td><span class="badge bg-<?= $statusClass; ?>"><?= ucfirst($statusText); ?></span></td>
                            <td><?= htmlspecialchars($row['submitted_date']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted">No submissions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Count Animation
document.querySelectorAll('[data-count]').forEach(el => {
    const target = +el.dataset.count;
    let count = 0;
    const increment = Math.max(1, Math.floor(target / 60));
    const timer = setInterval(() => {
        count += increment;
        if (count >= target) { count = target; clearInterval(timer); }
        el.textContent = count;
    }, 15);
});

// Table Filtering and Searching
const searchInput = document.getElementById('searchInput');
const filterSelect = document.getElementById('statusFilter');
const rows = document.querySelectorAll('#submissionsTable tbody tr');

function filterTable() {
    const term = searchInput.value.toLowerCase();
    const status = filterSelect.value.toLowerCase();
    rows.forEach(row => {
        const matchesSearch = [...row.cells].some(td => td.textContent.toLowerCase().includes(term));
        const statusText = row.cells[5].textContent.toLowerCase();
        const matchesStatus = !status || statusText.includes(status);
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}
searchInput.addEventListener('input', filterTable);
filterSelect.addEventListener('change', filterTable);

// Chart Data (uses combined stats)
const pending = <?= $stats['pending']; ?>;
const approved = <?= $stats['approved']; ?>;
const rejected = <?= $stats['rejected']; ?>;
const total = pending + approved + rejected;
const ctx = document.getElementById('statusChart');
const centerText = document.getElementById('chartCenterText');

const chart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Need My Action', 'Ready for Final Approval', 'Awaiting Student Revision'],
        datasets: [{
            data: [pending, approved, rejected],
            backgroundColor: ['#e74c3c', '#4caf50', '#f7c948'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        cutout: '70%',
        animation: {
            animateRotate: true,
            onProgress: () => updateCenterText()
        }
    }
});

function updateCenterText() {
    if (total === 0) {
        centerText.textContent = 'No Data';
        return;
    }
    const max = Math.max(pending, approved, rejected);
    let label = '';
    
    if (max === pending) label = 'Action Needed'; 
    else if (max === approved) label = 'Approved';
    else label = 'Revision';
    
    const percent = ((max / total) * 100).toFixed(1);
    centerText.textContent = `${percent}% ${label}`;
}
updateCenterText();
</script>