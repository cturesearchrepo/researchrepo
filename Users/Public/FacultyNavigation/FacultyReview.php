<?php

$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) die("Connection failed: " . $mysqli->connect_error);

if (!isset($_SESSION['faculty_id'])) {
    header("Location: ../login.php");
    exit;
}

$facultyQuery = $mysqli->prepare("SELECT id, fullname FROM faculty WHERE faculty_id = ?");
$facultyQuery->bind_param("i", $_SESSION['faculty_id']);
$facultyQuery->execute();
$facultyResult = $facultyQuery->get_result();
$faculty = $facultyResult->fetch_assoc();
$facultyDbId = $faculty['id'];
$facultyName = $faculty['fullname'] ?? 'Faculty';
$facultyQuery->close();
$advisoryQuery = "SELECT * FROM research_documents WHERE faculty_id = ? ORDER BY submitted_date DESC";
$stmtAdvisory = $mysqli->prepare($advisoryQuery);
$stmtAdvisory->bind_param("i", $facultyDbId);
$stmtAdvisory->execute();
$advisoryResults = $stmtAdvisory->get_result();
$stmtAdvisory->close();
$reviewQuery = "
    SELECT 
        rd.*, 
        rr.status AS reviewer_status,      /* Panelist's specific status */
        rr.feedback AS reviewer_feedback,  /* Panelist's specific feedback */
        rr.id AS reviewer_entry_id
    FROM research_documents rd
    INNER JOIN research_reviewers rr ON rr.research_id = rd.id
    WHERE rr.reviewer_id = ?
    ORDER BY rd.submitted_date DESC
";
$stmtReview = $mysqli->prepare($reviewQuery);
$stmtReview->bind_param("i", $facultyDbId);
$stmtReview->execute();
$reviewResults = $stmtReview->get_result();
$stmtReview->close();

// Close the main connection before outputting HTML
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Faculty Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; }
.container { padding: 30px; }
h2 { margin-bottom: 20px; color: #0d6efd; }
.table td, .table th { vertical-align: middle; }
/* Status classes for coloring the panelist's specific status */
.status-pending { color: orange; font-weight: bold; }
.status-approvedbypanelist, .status-approvedbyadvicer { color: green; font-weight: bold; }
.status-rejectedbypanelist, .status-rejectedbyadvicer { color: red; font-weight: bold; }
.status-revisionrequired { color: #ff9900; font-weight: bold; }
.btn-view { background: #0d6efd; color: #fff; }
.btn-view:hover { background: #0b5ed7; }
.modal-content { border-radius: 1rem; overflow: hidden; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }
.modal-header { border-bottom: none; background: linear-gradient(90deg, #0d6efd, #0a58ca); color: #fff; }
.modal-footer { border-top: none; }
</style>
</head>
<body>
<div class="container">
<h2>👨‍🏫 Faculty Dashboard - <?= htmlspecialchars($facultyName) ?></h2>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#advisory">Advisory Works</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#review">Review Submissions</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="advisory">
        <?php if ($advisoryResults->num_rows === 0): ?>
            <div class="alert alert-warning">No advisory works found.</div>
        <?php else: ?>
            <table class="table table-striped">
                <thead class="table-light"><tr><th>Title</th><th>Author</th><th>Department</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php while($row = $advisoryResults->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['author']) ?></td>
                        <td><?= htmlspecialchars($row['department']) ?></td>
                        <td><?= $row['submitted_date'] ?></td>
                        <td><button class="btn btn-sm btn-view" onclick='openModal(<?= json_encode($row) ?>, "advisory")'>View</button></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="review">
        <?php if ($reviewResults->num_rows === 0): ?>
            <div class="alert alert-warning">No review submissions assigned.</div>
        <?php else: ?>
            <table class="table table-striped">
                <thead class="table-light">
                    <tr><th>Title</th><th>Author</th><th>Department</th><th>Date</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php while($row = $reviewResults->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['author']) ?></td>
                        <td><?= htmlspecialchars($row['department']) ?></td>
                        <td><?= $row['submitted_date'] ?></td>
                        <td><span class="status-<?= strtolower($row['reviewer_status']) ?>"><?= htmlspecialchars($row['reviewer_status']) ?></span></td>
                        <td><button class="btn btn-sm btn-view" onclick='openModal(<?= json_encode($row) ?>, "review")'>View</button></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</div>

<div class="modal fade" id="facultyModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Research Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h4 id="modalTitle"></h4>
                <p><strong>Author:</strong> <span id="modalAuthor"></span></p>
                <p><strong>Department:</strong> <span id="modalDept"></span></p>
                <p><strong>Course:</strong> <span id="modalCourse"></span></p>
                <p><strong>Section:</strong> <span id="modalSection"></span></p>
                <p><strong>Year Completed:</strong> <span id="modalYear"></span></p>
                <p><strong>Keywords:</strong> <span id="modalKeywords"></span></p>
                <p><strong>Abstract:</strong></p>
                <div id="modalAbstract"></div>

                <a id="modalFile" href="#" target="_blank" class="btn btn-outline-primary btn-sm mt-3 me-1">📄 Download PDF</a>
                <a id="modalViewFile" href="#" target="_blank" class="btn btn-primary btn-sm mt-3">📖 View Research</a>
                <hr>
                <div id="modalActions"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmMessage">Are you sure you want to proceed?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmYesBtn" class="btn btn-primary btn-sm">Yes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-white">
                <h5 class="modal-title" id="notifTitle">System Message</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="notificationMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

function showNotification(title, message, isSuccess = false) {
    const notifModal = new bootstrap.Modal(document.getElementById('notificationModal'));
    const notifHeader = document.querySelector('#notificationModal .modal-header');
    const notifTitle = document.getElementById('notifTitle');
    const notifMessage = document.getElementById('notificationMessage');

    notifMessage.textContent = message;
    notifHeader.classList.remove('bg-success', 'bg-danger', 'bg-info');

    if (isSuccess) {
        notifHeader.classList.add('bg-success');
        notifTitle.textContent = title || "Success";
    } else if (message.toLowerCase().includes('error') || message.toLowerCase().includes('cannot')) {
        notifHeader.classList.add('bg-danger');
        notifTitle.textContent = title || "Error";
    } else {
        notifHeader.classList.add('bg-info');
        notifTitle.textContent = title || "Information";
    }

    notifModal.show();
    if (isSuccess) setTimeout(() => location.reload(), 2500);
}


function revertStatus(researchId, type) {
    // Requires a backend script named 'revert_status.php'
    bootstrap.Modal.getInstance(document.getElementById('facultyModal')).hide();

    const formData = new FormData();
    formData.append("id", researchId);
    formData.append("type", type); 
    formData.append("reviewer_id", <?= $facultyDbId ?>); 

    fetch("revert_status.php", { method: "POST", body: formData })
        .then(res => res.text())
        .then(message => {
            const isSuccess = message.toLowerCase().includes("success");
            showNotification(isSuccess ? "Reversal Successful" : "Reversal Failed", message, isSuccess);
        })
        .catch(err => {
            showNotification("Error", "An error occurred during status reversal: " + err, false);
        });
}

function showCancelConfirmation(researchId, type) {
    document.getElementById('confirmMessage').textContent = `Are you sure you want to revert this decision? The status will be set back to PENDING, allowing you to make a new decision.`;
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    
    confirmModal.show();

    document.getElementById('confirmYesBtn').onclick = () => {
        confirmModal.hide();
        revertStatus(researchId, type); 
    };
}

function openModal(data, type) {
    const modal = new bootstrap.Modal(document.getElementById('facultyModal'));
    const feedbackKey = `feedback_${data.id}`;
    
    // Use the fetched reviewer_feedback (for panelist) or local storage
    const savedFeedback = data.reviewer_feedback && type === "review" ? data.reviewer_feedback : localStorage.getItem(feedbackKey) || ''; 

    document.getElementById('modalTitle').textContent = data.title;
    document.getElementById('modalAuthor').textContent = data.author;
    document.getElementById('modalDept').textContent = data.department;
    document.getElementById('modalCourse').textContent = data.course ?? '';
    document.getElementById('modalSection').textContent = data.section ?? '';
    document.getElementById('modalYear').textContent = data.year_completed ?? '';
    document.getElementById('modalKeywords').textContent = data.keywords ?? '';
    document.getElementById('modalAbstract').textContent = data.abstract ?? '';

    const fileName = data.file_path ? data.file_path.split('/').pop() : '';
    const folder = data.year_completed && data.year_completed !== "null" ? data.year_completed + "/" : "";
    const pdfUrl = `/caps/Users/Public/StudentNavigations/uploads/${folder}${fileName}`; 
    document.getElementById('modalFile').href = pdfUrl;
    document.getElementById('modalViewFile').onclick = e => {
        e.preventDefault();
        window.open("/caps/web/viewer.html?file=" + encodeURIComponent(pdfUrl), "_blank");
    };

    const actions = document.getElementById('modalActions');
    actions.innerHTML = '';

    const role = (type === "review") ? "Panelist" : "Advicer";
    
    let currentStatus;
    if (type === "advisory") {
        // Advicer status is in research_documents.prev_status
        currentStatus = data.prev_status;
    } else { 
        // Panelist status is in research_reviewers.status (fetched as reviewer_status)
        currentStatus = data.reviewer_status;
    }
    
    const status = (currentStatus || 'Pending').toLowerCase();
    const cancelButtonFinalized = `<button type="button" class="btn btn-secondary btn-sm" onclick="showCancelConfirmation(${data.id}, '${type}')">↩️ Cancel</button>`;

    if (["approvedbypanelist", "approvedbyadvicer"].includes(status) || status === "revisionrequired" || ["rejectedbypanelist", "rejectedbyadvicer"].includes(status)) {
        
        // Finalized State: Display result and Revert button
        let messageHtml;
        let alertClass;
        let icon;

        if (status.includes("approved")) {
            alertClass = "alert-success";
            icon = "✅";
            messageHtml = `The research has been **Approved** by the ${role}.`;
        } else if (status === "revisionrequired") {
            alertClass = "alert-warning";
            icon = "📝";
            messageHtml = `Revision is required for this research.`;
        } else if (status.includes("rejected")) {
            alertClass = "alert-danger";
            icon = "❌";
            messageHtml = `The research has been **Rejected** by the ${role}.`;
        }
        
        actions.innerHTML = `
            <div class="alert alert-info d-flex align-items-center mb-3 p-2" role="alert">
                <h6 class="mb-0 me-3">ℹ️</h6>
                <div><strong>Your Role:</strong> You acted as **${role}**.</div>
            </div>
            <div class="alert ${alertClass} d-flex align-items-center mb-3 p-2" role="alert">
                <h6 class="mb-0 me-3">${icon}</h6>
                <div><strong>Action Finalized:</strong> ${messageHtml}. Click Cancel to revert your decision and act again.</div>
            </div>
            ${cancelButtonFinalized}
        `;
    } 
    else {
        // Pending State: Display action buttons
        
        const uploadField = `
            <div class="mb-3">
                <label for="modalFeedbackFile" class="form-label fw-bold">Upload Feedback File (Optional)</label>
                <input class="form-control form-control-sm" type="file" id="modalFeedbackFile" name="feedback_file">
            </div>`;
        
        const feedbackTextarea = `<textarea id="modalFeedback" class="form-control mb-2" rows="3" placeholder="Feedback / Comments...">${savedFeedback}</textarea>`;

        const decisionButtons = `
            <button class="btn btn-success btn-sm me-1" onclick="confirmAction(${data.id}, 'Approvedby${role}')">✅ Approve</button>
            <button class="btn btn-warning btn-sm me-1" onclick="confirmAction(${data.id}, 'RevisionRequired')">📝 Revision</button>
            <button class="btn btn-danger btn-sm me-1" onclick="confirmAction(${data.id}, 'Rejectedby${role}')">❌ Reject</button>`;
        
        actions.innerHTML = `
            <div class="alert alert-info d-flex align-items-center mb-3 p-2" role="alert">
                <h6 class="mb-0 me-3">✍️</h6>
                <div><strong>Action Required:</strong> You are acting as **${role}**. Please submit your decision.</div>
            </div>
            ${uploadField}${feedbackTextarea}${decisionButtons}`;

        const textarea = actions.querySelector('#modalFeedback');
        if (textarea) {
            textarea.addEventListener('input', () => { localStorage.setItem(feedbackKey, textarea.value); });
        }
    }

    modal.show();
}


let confirmData = { id: null, status: null };

function confirmAction(researchId, status) {
    confirmData = { id: researchId, status: status };
    const messages = {
        'ApprovedbyPanelist': 'Are you sure to Confirm approval?',
        'RejectedbyPanelist': 'Are you sure to Reject this research?',
        'RevisionRequired': 'Request revision for this research?',
        'ApprovedbyAdvicer': 'Are your sure to Approve this research?',
        'RejectedbyAdvicer': 'Are you sure to Reject this research?'
    };
    document.getElementById('confirmMessage').textContent = messages[status] || 'Proceed with this action?';
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
    document.getElementById('confirmYesBtn').onclick = () => {
        updateStatus(confirmData.id, confirmData.status);
        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
    };
}

function updateStatus(researchId, status) {
    // Requires a backend script named 'update_research_status.php'
    const feedback = document.getElementById('modalFeedback')?.value ?? '';
    const fileInput = document.getElementById('modalFeedbackFile');
    const formData = new FormData();
    formData.append("id", researchId);
    formData.append("feedback", feedback);

    if (fileInput && fileInput.files.length > 0) {
        formData.append("feedback_file", fileInput.files[0]);
    }
    
    // Distinguish between Advicer (prev_status) and Panelist (status)
    const adviserActions = ['ApprovedbyAdvicer','RejectedbyAdvicer','RevisionRequired'];
    if (adviserActions.includes(status)) formData.append("prev_status", status);
    else formData.append("status", status); 
    
    fetch("update_research_status.php", { method: "POST", body: formData })
        .then(res => res.text())
        .then(message => {
            const isSuccess = message.toLowerCase().includes("success");
            showNotification(isSuccess ? "Success" : "Error", message, isSuccess);
        })
        .catch(err => {
             showNotification("Error", "An error occurred: " + err, false);
        });
}
</script>
</body>
</html>