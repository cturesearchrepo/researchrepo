<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// Fetch faculty list for the Edit Modal dropdowns
$facultyList = $conn->query("SELECT id, fullname FROM faculty ORDER BY fullname ASC");

// Prepare and execute the main research documents query
$stmt = $conn->prepare("
    SELECT
        rs.id, rs.title, rs.author, rs.year_completed, rs.research_type,
        rs.status, rs.prev_status, rs.file_path, rs.transmittal_file, rs.uploaded_at, rs.abstract,
        rs.faculty_id AS adviser_id,
        f.fullname AS adviser,
        GROUP_CONCAT(
            CONCAT(
                rev.id, '||',
                rev.fullname, '||',
                IFNULL(rf.status, rev.status), '||',
                IFNULL(rf.feedback,''), '||',
                IFNULL(rf.feedback_file,''), '||',
                rev.role
            ) SEPARATOR '%%'
        ) AS reviewers_data,
        GROUP_CONCAT(rev.id SEPARATOR ',') AS reviewer_ids
    FROM research_documents rs
    LEFT JOIN faculty f ON rs.faculty_id = f.id
    LEFT JOIN (
        -- Adviser/Chairman
        SELECT f1.id, f1.fullname, rs1.id AS research_id, 'Adviser' AS role, rs1.status AS status
        FROM faculty f1
        JOIN research_documents rs1 ON rs1.faculty_id = f1.id
        UNION ALL
        -- Panelists
        SELECT f2.id, f2.fullname, rr.research_id, 'Panelist' AS role, rr.status
        FROM research_reviewers rr
        JOIN faculty f2 ON rr.reviewer_id = f2.id
    ) rev ON rev.research_id = rs.id
    LEFT JOIN research_feedback rf ON rf.research_id = rs.id AND rf.reviewer_id = rev.id
    WHERE rs.uploaded_by_student = ?
    GROUP BY rs.id
    ORDER BY rs.uploaded_at DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$uploads = [];
while ($row = $result->fetch_assoc()) {
    $row['reviewers'] = [];
    if (!empty($row['reviewers_data'])) {
        $revList = explode('%%', $row['reviewers_data']);
        foreach ($revList as $rev) {
            $parts = explode('||', $rev);
            $feedbackFilePath = $parts[4] ?? '';
           $feedbackFileUrl = !empty($feedbackFilePath)
    ? 'http://localhost/caps/Users/Public/FacultyNavigation/' . trim($feedbackFilePath, '/')
    : '';


            $row['reviewers'][] = [
                'id'       => $parts[0] ?? '', // Reviewer ID (faculty ID)
                'fullname' => $parts[1] ?? '',
                'status'   => $parts[2] ?? '',
                'feedback' => $parts[3] ?? '',
                'file'     => $feedbackFileUrl,
                'role'     => $parts[5] ?? 'Panelist'
            ];
        }
    }
    unset($row['reviewers_data']);
    $uploads[] = $row;
}
$stmt->close();


// Need to re-fetch faculty list for the second select in the HTML, since the first cursor is now exhausted
$facultyList2 = $conn->query("SELECT id, fullname FROM faculty ORDER BY fullname ASC");


function statusClass($status) {
    return match(strtolower($status)) {
        'pending' => 'status-Pending',
        'approvedbyadvicer' => 'status-Approved',
        'rejectedbyadvicer' => 'status-Rejected',
        'revisionrequired' => 'status-Revision',
        'approvedbyadmin' => 'status-Approved',
        'rejectedbyadmin' => 'status-Rejected',
        'active' => 'status-Active',
        'archive' => 'status-Archive',
        default => ''
    };
}

function truncateText($text, $max = 100) {
    return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Research Uploads</title>
<link rel="stylesheet" href="uploadresearchstyle.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>

.step-feedback-details {
    margin-top: 8px;
    border-top: 1px solid #eee;
    padding-top: 5px;
    text-align: left;
}

.step-feedback-details a {
    font-size: 0.8rem;
    color: #007bff;
    font-weight: bold;
    text-decoration: none;
    display: block;
    margin-top: 3px;
}
/* ... (Rest of existing styles) ... */
.main-content {margin-left:160px;padding:1rem;max-width:calc(100% - 180px);background:#f8f9fb;min-height:100vh;box-sizing:border-box;}
.top-header h2 {margin:0 0 25px 0;font-size:30px;font-weight:700;color:#8B0000;border-bottom:2px solid #eee;padding-bottom:12px;}
table {width:100%;border-collapse:collapse;margin-top:20px;table-layout:fixed;word-wrap:break-word;font-size:0.95rem;}
th, td {border:1px solid #ddd;padding:10px 12px;text-align:left;vertical-align:top;transition:all 0.2s ease;}
th {background-color:#f1f3f6;font-weight:600;color:#444;}
tr:nth-child(even){background-color:#fafafa;} tr:hover{background-color:#f0f7ff;}
.status-Pending{color:orange;font-weight:bold;}
.status-Approved{color:green;font-weight:bold;}
.status-Archive{color:#007bff;font-weight:bold;}
.status-Rejected{color:red;font-weight:bold;}
.action-buttons {display:flex;gap:6px;justify-content:center;align-items:center;}
.action-buttons a {display:inline-flex;align-items:center;justify-content:center;padding:10px;border-radius:6px;text-decoration:none;font-size:0.9rem;font-weight:500;color:#fff;box-shadow:0 2px 5px rgba(0,0,0,0.1);transition:0.2s ease;}
.action-buttons a.download-btn {background-color:#28a745;}
.action-buttons a.edit-link {background-color:#ffc107;color:#000;}
.action-buttons a.view-btn {background-color:#007bff;}
.action-buttons a:hover {transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,0.15);}
.modal {display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;overflow-y:auto;background-color:rgba(0,0,0,0.5);}
.modal-content {background-color:#fff;margin:5% auto;padding:20px 25px;border-radius:8px;width:90%;max-width:700px;box-shadow:0 5px 15px rgba(0,0,0,0.3);position:relative;}
.close-btn {position:absolute;top:10px;right:15px;font-size:1.5rem;font-weight:bold;cursor:pointer;color:#444;}
.close-btn:hover{color:#000;}
.steps-container {display:flex;flex-wrap:wrap;gap:10px;margin-top:20px;}
.step {text-align:center;flex:1;position:relative;min-width:100px;}
.step .circle {width:30px;height:30px;border-radius:50%;background:#eee;margin:0 auto;line-height:30px;font-weight:bold;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;}
.step.active .circle {background:#28a745;}
.step.rejected .circle {background:red!important;}
.step.revision .circle {background:orange!important;}
.step .label {margin-top:8px;font-size:0.85rem;}
.step::after {content:'';position:absolute;top:15px;right:-50%;height:4px;width:100%;background:#eee;z-index:-1;}
.step:last-child::after {display:none;}
.step.active::after {background:#28a745;}
#modalMessage {text-align:center;font-weight:bold;margin-top:15px;font-size:1rem;}
/* ===== EDIT MODAL STYLING ===== */
/* === Edit Modal Styling === */
#editModal .modal-content {
    width: 90%;
    max-width: 700px;
    border-radius: 10px;
    padding: 25px 30px;
}

#editModal h3 {
    margin-bottom: 15px;
    color: #333;
    text-align: center;
    font-size: 1.3rem;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}

#editForm {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

#editForm label {
    font-weight: 600;
    color: #444;
    font-size: 0.95rem;
}

#editForm input[type="text"],
#editForm input[type="number"],
#editForm input[type="file"],
#editForm textarea,
#editForm select {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 0.95rem;

    box-sizing: border-box;
    transition: all 0.2s ease-in-out;
    height: 34px; /* shorter input height */
}

#editForm textarea {
    height: 80px; /* short but wide textarea */
    resize: vertical;
}s

#editForm input:focus,
#editForm textarea:focus,
#editForm select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(99, 246, 101, 0.1);
}

#editForm button[type="submit"] {
    background: #28a745;
    color: #fff;
    padding: 8px 0;
    font-size: 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 10px;
    transition: background 0.2s;
}

#editForm button[type="submit"]:hover {
    background: #218838;
}


.close-modal {
    position: absolute;
    top: 12px;
    right: 15px;
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    color: #555;
    transition: color 0.2s ease;
}

.close-modal:hover {
    color: #000;
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
/* ===== MODAL STYLES (Approval Steps) ===== */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow-y: auto;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(2px);
}

.modal-content {
    background: #fff;
    margin: 5% auto;
    padding: 25px 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    position: relative;
    animation: fadeIn 0.3s ease-in-out;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3);
}

.close-btn {
    position: absolute;
    top: 12px;
    right: 18px;
    font-size: 1.5rem;
    color: #444;
    cursor: pointer;
    transition: 0.3s;
}
.close-btn:hover {
    color: #000;
}

#modalTitle {
    text-align: center;
    font-size: 1.4rem;
    color: #8B0000;
    font-weight: 700;
    margin-bottom: 15px;
    border-bottom: 2px solid #eee;
    padding-bottom: 8px;
}

/* ===== STEPS LAYOUT ===== */
.steps-container {
    margin-top: 20px;
    padding: 15px;
    background: #f9fafb;
    border-radius: 10px;
    border: 1px solid #ddd;
}

.steps-container h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #444;
    margin-bottom: 15px;
    border-left: 4px solid #8B0000;
    padding-left: 10px;
}

.steps-body {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
}

/* ===== INDIVIDUAL STEP ===== */
.step {
    position: relative;
    background: #fff;
    border-radius: 10px;
    padding: 12px;
    width: 150px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}
.step:hover {
    transform: translateY(-3px);
}

.step .circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #ccc;
    color: #fff;
    font-weight: bold;
    font-size: 1rem;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto 8px;
    transition: 0.3s;
}

/* STATUS COLORS */
.step.active .circle { background: #28a745; }      /* Green for approved */
.step.rejected .circle { background: #dc3545; }    /* Red for rejected */
.step.revision .circle { background: #ff9800; }    /* Orange for revision */
.step .label { font-size: 0.85rem; color: #333; }

/* CONNECTING LINES */
.step::after {
    content: '';
    position: absolute;
    top: 50%;
    right: -60px;
    height: 4px;
    width: 60px;
    background: #ddd;
    z-index: 0;
    transform: translateY(-50%);
}
.step:last-child::after { display: none; }
.step.active::after { background: #28a745; }

/* ===== MODAL BUTTONS ===== */
#prevStep, #nextStep {
    background: #8B0000;
    color: white;
    padding: 8px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    transition: 0.3s;
    margin: 5px;
}
#prevStep:hover, #nextStep:hover {
    background: #a30000;
}

#modalMessage {
    margin-top: 15px;
    text-align: center;
    font-weight: 600;
    color: #555;
    font-size: 0.95rem;
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.97); }
    to { opacity: 1; transform: scale(1); }
}

/* Highlighted (Active) Next Button */
#nextStep.active {
    background: #28a745 !important;  /* Bright green */
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.7);
    transform: scale(1.05);
}

#nextStep:disabled {
    background: #ccc;
    color: #666;
    box-shadow: none;
    cursor: not-allowed;
    transform: none;
}
/* Simple modern file input */
#transmittalForm input[type="file"] {
    display: block;
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #8B0000;
    border-radius: 6px;
    background-color: #fff7f7;
    color: #8B0000;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

#transmittalForm input[type="file"]:hover {
    border-color: #a30000;
    background-color: #fff0f0;
}

#transmittalForm input[type="file"]:focus {
    outline: none;
    border-color: #8B0000;
    box-shadow: 0 0 5px rgba(139, 0, 0, 0.4);
}



</style>
</head>
<body>
<div class="dashboard-container">
<main class="main-content">
<header class="top-header"><h2>My Research Uploads</h2></header>

<?php if(empty($uploads)): ?>
<p>You haven't uploaded any research documents yet.</p>
<?php else: ?>
<table>
<thead>
<tr>
<th>Title</th><th>Author</th><th>Year</th><th>Type</th><th>Adviser</th><th>Status</th><th>Uploaded</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($uploads as $u): ?>
<tr
    data-id="<?= htmlspecialchars($u['id'], ENT_QUOTES) ?>"
    data-abstract="<?= htmlspecialchars($u['abstract'], ENT_QUOTES) ?>"
    data-reviewers='<?= htmlspecialchars(json_encode($u['reviewers'], JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES) ?>'
    data-status="<?= htmlspecialchars(strtolower($u['status']), ENT_QUOTES) ?>"
    data-prev-status="<?= htmlspecialchars(strtolower($u['prev_status'] ?? ''), ENT_QUOTES) ?>"
    data-reviewer-ids="<?= htmlspecialchars($u['reviewer_ids'] ?? '', ENT_QUOTES) ?>"
    data-transmittal="<?= htmlspecialchars($u['transmittal_file'] ?? '', ENT_QUOTES) ?>"
    data-adviser-id="<?= htmlspecialchars($u['reviewer_id'] ?? '', ENT_QUOTES) ?>"
>

<td><?= htmlspecialchars($u['title'], ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($u['author'], ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($u['year_completed'], ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($u['research_type'], ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($u['adviser'] ?? 'N/A', ENT_QUOTES) ?></td>
<td class="<?= statusClass($u['prev_status']) ?>"><?= htmlspecialchars($u['status'], ENT_QUOTES) ?></td>
<td><?= date("M d, Y", strtotime($u['uploaded_at'])) ?></td>
<td class="action-buttons">
<?php
$statusLower = strtolower($u['status'] ?? '');
if(!empty($u['file_path'])) {
    echo '<a href="#" class="view-btn"><i class="fa-solid fa-eye"></i></a> ';
    echo '<a href="download.php?id=' . urlencode($u['id']) . '" target="_blank" class="download-btn"><i class="fa-solid fa-download"></i></a> ';
    if(in_array($statusLower, ['pending','revisionrequired','RejectedbyAdvicer','RejectedbyPanelist','ApprovedbyAdvicer','ApprovedbyPanelist'])) {
        echo '<a href="#" class="edit-link"><i class="fa-solid fa-edit"></i></a>';
    }
} else {
    echo 'N/A';
}
?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<div id="stepsModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3 id="modalTitle">Approval Steps</h3>

        <div id="step1" class="steps-container" data-step="1">
            <h4>Step 1: Panelist Approvals</h4>
            <div class="steps-body"></div>
        </div>

        <div id="step2" class="steps-container" data-step="2" style="display:none;">
            <h4>Step 2: Adviser Approval</h4>
            <div class="steps-body"></div>
        </div>

        <div id="step3" class="steps-container" data-step="3" style="display:none;">
            <h4>Step 3: Upload Transmittal</h4>
            <div class="steps-body">
                <p id="currentTransmittal"></p>
                <form id="transmittalForm" enctype="multipart/form-data">
                    <input type="hidden" name="research_id" id="transResearchId">
                    <input type="file" name="transmittal_file" required accept=".pdf"/>
                    <button type="submit">Upload / Replace Transmittal</button>
                </form>
                <p id="transMessage"></p>
            </div>
        </div>


        <div id="step4" class="steps-container" data-step="4" style="display:none;">
            <h4>Step 4: Admin Approval</h4>
            <div class="steps-body"></div>
        </div>


        <div style="text-align:center;margin-top:15px;">
            <button id="prevStep" style="display:none;">⬅ Back</button>
            <button id="nextStep">Next ➡</button>
        </div>

        <p id="modalMessage"></p>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Edit Research</h3>
        <form id="editForm" enctype="multipart/form-data">
            <input type="hidden" name="research_id" id="editResearchId">

            <label for="editTitle">Title:</label>
            <input type="text" name="title" id="editTitle" required>

            <label for="editType">Research Type:</label>
            <input type="text" name="research_type" id="editType" required>

            <label for="editAbstract">Abstract:</label>
            <textarea name="abstract" id="editAbstract" rows="4"></textarea>

            <label for="editAdviser">Adviser:</label>
            <select name="faculty_id" id="editAdviser" required>
                <option value="">-- Select Adviser --</option>
                <?php while ($f = $facultyList->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($f['id']) ?>">
                        <?= htmlspecialchars($f['fullname']) ?>
                    </option>
                <?php endwhile; ?>
            </select>


            <label for="editPanelists">Panelists:</label>
            <select name="panelists[]" id="editPanelists" multiple size="4" required>
                <?php
                $facultyList2 = $conn->query("SELECT id, fullname FROM faculty ORDER BY fullname ASC");
                while($f2 = $facultyList2->fetch_assoc()):
                ?>
                    <option value="<?= $f2['id'] ?>"><?= htmlspecialchars($f2['fullname']) ?></option>
                <?php endwhile; ?>
            </select>
            <small style="color:gray;">(Hold Ctrl/Cmd to select multiple)</small>

            <label for="editFile">Upload New File (optional):</label>
            <input type="file" id="editFile" name="file_path" accept=".pdf">

            <button type="submit">💾 Save Changes</button>
        </form>
    </div>
</div>



</main>
</div>
<script>

document.addEventListener('DOMContentLoaded', function() {
    const stepsModal = document.getElementById('stepsModal');
    const closeStepsBtn = stepsModal.querySelector('.close-btn');
    const nextBtn = document.getElementById('nextStep');
    const prevBtn = document.getElementById('prevStep');
    const modalMessage = document.getElementById('modalMessage');

    // Close modal
    closeStepsBtn.onclick = () => stepsModal.style.display = 'none';
    window.onclick = e => { if (e.target === stepsModal) stepsModal.style.display = 'none'; };

    // View Approval Steps
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const row = this.closest('tr');
            let reviewers = [];
            try { reviewers = JSON.parse(row.dataset.reviewers || '[]'); }
            catch { reviewers = []; }

            const panelists = reviewers.filter(r => r.role.toLowerCase() === 'panelist');
            const adviserChair = reviewers.filter(r => ['adviser', 'chairman'].includes(r.role.toLowerCase()));
            const adminStep = { fullname: 'Administrator', role: 'Admin', status: 'pending', feedback: '', file: '' };

            const createStepHTML = (list, container) => {
                container.innerHTML = '';
                list.forEach((rev, idx) => {
                    const stepDiv = document.createElement('div');
                    stepDiv.classList.add('step');

                    const st = rev.status?.toLowerCase() || 'pending';
                    if(st.includes('approved')) stepDiv.classList.add('active');
                    else if(st.includes('reject')) stepDiv.classList.add('rejected');
                    else if(st === 'revisionrequired') stepDiv.classList.add('revision');

                    const statusLabel =
                        st.includes('approved') ? '<span style="color:green;font-weight:bold;">Approved</span>' :
                        st.includes('reject') ? '<span style="color:red;font-weight:bold;">Rejected</span>' :
                        st === 'revisionrequired' ? '<span style="color:orange;font-weight:bold;">Needs Revision</span>' :
                        '<span style="color:gray;">Pending</span>';

                    let feedbackHtml = '';
                    if (rev.feedback || rev.file) {
                        feedbackHtml += '<div class="step-feedback-details">';
                        if (rev.feedback && rev.feedback.trim() !== '') {
                            feedbackHtml += `<strong style="font-size: 0.8rem;">Comment:</strong> <i style="font-size: 0.8rem;">${rev.feedback}</i><br>`;
                        }
                        if (rev.file && rev.file.trim() !== '') {
                            feedbackHtml += `<a href="${rev.file}" target="_blank">📄 View Feedback File</a>`;
                        }
                        feedbackHtml += '</div>';
                    }

                    stepDiv.innerHTML = `
                        <div class="circle">${idx + 1}</div>
                        <div class="label">
                            ${rev.fullname} <em>(${rev.role})</em><br>${statusLabel}
                            ${feedbackHtml}
                        </div>`;
                    container.appendChild(stepDiv);
                });
            };

            createStepHTML(panelists, document.querySelector('#step1 .steps-body'));
            createStepHTML(adviserChair, document.querySelector('#step2 .steps-body'));

            const adminStatusLower = row.dataset.status?.toLowerCase() || '';
            const adminReviewer = [
                {
                    fullname: 'Administrator',
                    role: 'Admin',
                    status: row.dataset.status,
                    id: 0,
                    feedback: adminStatusLower.includes('rejectedbyadmin') ? 'Rejected by system administrator.' : '',
                    file: ''
                }
            ];
            createStepHTML(adminReviewer, document.querySelector('#step4 .steps-body'));

            let currentStep = 1;
            const totalSteps = 4;
            const stepSections = [
                document.getElementById('step1'),
                document.getElementById('step2'),
                document.getElementById('step3'),
                document.getElementById('step4')
            ];

            const showStep = () => {
                stepSections.forEach((s,i) => s.style.display = (i+1 === currentStep ? 'block' : 'none'));
                prevBtn.style.display = currentStep > 1 ? 'inline-block' : 'none';
                nextBtn.textContent = currentStep === totalSteps ? 'Done' : 'Next ➡';

                nextBtn.disabled = false;
                nextBtn.classList.remove('active');

            if(currentStep === 1) {
                const allApproved = panelists.every(p => p.status?.toLowerCase().includes('approved'));
                 if(!allApproved){
                        modalMessage.textContent = '⚠ All panelists must approve before proceeding.';
                        nextBtn.disabled = true;
                    } else nextBtn.classList.add('active');
            }
                else if(currentStep === 2) {
                    const prevStatus = row.dataset.prevStatus.toLowerCase() || '';
                    const adviserSteps = document.querySelectorAll('#step2 .step');

                    if(adviserSteps.length > 0) {
                        const adviserStep = adviserSteps[0];
                        adviserStep.classList.remove('active', 'revision', 'rejected');

                        if(prevStatus.includes('approved')) {
                            modalMessage.textContent = '';
                            nextBtn.disabled = false;
                            nextBtn.classList.add('active');
                            adviserStep.classList.add('active');
                        } else if(prevStatus.includes('revision')) {
                            modalMessage.textContent = '⚠ Adviser requested revision.';
                            nextBtn.disabled = true;
                            nextBtn.classList.remove('active');
                            adviserStep.classList.add('revision');
                        } else if(prevStatus.includes('rejected')) {
                            modalMessage.textContent = '❌ Adviser rejected the research.';
                            nextBtn.disabled = true;
                            nextBtn.classList.remove('active');
                            adviserStep.classList.add('rejected');
                        } else {
                            modalMessage.textContent = '⚠ Adviser must approve before proceeding.';
                            nextBtn.disabled = true;
                            nextBtn.classList.remove('active');
                        }
                    } else {
                        modalMessage.textContent = 'ℹ️ No adviser assigned.';
                        nextBtn.disabled = false;
                    }
                }
                else if(currentStep === 3) {
                    const transmittalForm = document.getElementById('transmittalForm');
                    const transMessage = document.getElementById('transMessage');
                    const currentFileEl = document.getElementById('currentTransmittal');

                    const currentFilePath = row.dataset.transmittal || '';
                    if(currentFilePath) {
                        const viewUrl = `/caps/web/viewer.html?file=${encodeURIComponent(currentFilePath)}`;
                        currentFileEl.innerHTML = `Current File: <a href="${viewUrl}" target="_blank">View / Download</a>`;
                        transmittalForm.querySelector('button[type="submit"]').textContent = 'Replace Transmittal';
                    } else {
                        currentFileEl.textContent = 'No transmittal uploaded yet.';
                        transmittalForm.querySelector('button[type="submit"]').textContent = 'Upload Transmittal';
                    }

                    document.getElementById('transResearchId').value = row.dataset.id;

                    nextBtn.disabled = currentFilePath.length === 0;
                    nextBtn.classList.toggle('active', currentFilePath.length > 0);

                    modalMessage.textContent = currentFilePath.length > 0
                        ? '✅ Transmittal already uploaded.'
                        : '⚠ Please upload transmittal to proceed.';

                    transmittalForm.onsubmit = function(e) {
                        e.preventDefault();
                        const formData = new FormData(transmittalForm);
                        fetch('upload_transmittal.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if(data.status === 'success') {
                                row.dataset.transmittal = data.file_path;

                                const viewUrl = `/caps/web/viewer.html?file=${encodeURIComponent(data.file_path)}`;
                                currentFileEl.innerHTML = `Current File: <a href="${viewUrl}" target="_blank">View / Download</a>`;

                                transmittalForm.querySelector('button[type="submit"]').textContent = 'Replace Transmittal';
                                modalMessage.textContent = '✅ Transmittal uploaded successfully.';
                                nextBtn.disabled = false;
                                nextBtn.classList.add('active');
                                transMessage.textContent = '';
                            } else {
                                modalMessage.textContent = '❌ ' + data.message;
                            }
                        })
                        .catch(() => { modalMessage.textContent = '❌ Error uploading transmittal.'; });
                    };
                }

                else if(currentStep === 4){
                    const adminStepDiv = document.querySelector('#step4 .step');
                    const adminStatus = row.dataset.status?.toLowerCase() || '';

                    adminStepDiv.classList.remove('active','revision','rejected');
                    nextBtn.disabled = true;
                    nextBtn.classList.remove('active');

                    if(adminStatus.includes('approvedbyadmin')) {
                        adminStepDiv.classList.add('active');
                        modalMessage.textContent = '✅ Admin has approved this research. Process completed.';
                        nextBtn.disabled = false;
                        nextBtn.classList.add('active');
                    } else if(adminStatus.includes('rejectedbyadmin')) {
                        adminStepDiv.classList.add('rejected');
                        modalMessage.textContent = '❌ Admin has rejected this research.';
                    } else if(adminStatus.includes('revisionrequired')) {
                        adminStepDiv.classList.add('revision');
                        modalMessage.textContent = '⚠ Admin requested revision.';
                    } else {
                        modalMessage.textContent = '⚠ Admin approval pending.';
                    }
                }
            };

            nextBtn.onclick = () => {
                if(currentStep < totalSteps) {
                    currentStep++;
                    showStep();
                } else stepsModal.style.display = 'none';
            };
            prevBtn.onclick = () => { if(currentStep > 1) currentStep--; showStep(); };

            currentStep = 1;
            showStep();
            stepsModal.style.display = 'flex';
        });
    });

    const editModal = document.getElementById('editModal');
    const closeEditBtn = editModal.querySelector('.close-modal');

    closeEditBtn.onclick = () => editModal.style.display = 'none';
    window.onclick = e => { if (e.target === editModal) editModal.style.display = 'none'; };

    document.querySelectorAll('.edit-link').forEach(editBtn => {
        editBtn.addEventListener('click', function(e){
            e.preventDefault();
            const row = this.closest('tr');
            const id = row.dataset.id;
            const title = row.children[0].textContent.trim();
            const type = row.children[3].textContent.trim();
            const abstract = row.dataset.abstract;
            let reviewers = [];
            try { reviewers = JSON.parse(row.dataset.reviewers || '[]'); }
            catch { reviewers = []; }

            const currentPanelistIds = reviewers
                .filter(r => r.role.toLowerCase() === 'panelist')
                .map(r => r.id.toString());

            const adviserId = row.dataset.adviserId;


            document.getElementById('editResearchId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editType').value = type;
            document.getElementById('editAbstract').value = abstract;

            document.getElementById('editAdviser').value = adviserId || '';


            const panelistsSelect = document.getElementById('editPanelists');
            Array.from(panelistsSelect.options).forEach(opt => {
                opt.selected = currentPanelistIds.includes(opt.value);
            });

            editModal.style.display = 'flex';
        });
    });

    document.getElementById('editForm').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        fetch('save_research.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                alert('✅ Research updated successfully! Page will refresh.');
                window.location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'An unknown error occurred.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Network Error: Could not connect to the server.');
        });
    };
});
</script>
</body>
</html>