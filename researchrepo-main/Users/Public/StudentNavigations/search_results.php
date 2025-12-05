<?php
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

// Load categories for filter
$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$categories = [];
$catResult = $conn->query("SELECT name FROM categories ORDER BY name ASC");
while ($row = $catResult->fetch_assoc()) $categories[] = $row['name'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advanced Research Search</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f9fc; margin:0; color:#333;}
h3 { text-align:center; color:#264653; margin-bottom:20px; position: relative; top: 100px; left: 20px;}
#filterForm {display:flex; flex-wrap:wrap; justify-content:center; gap:10px; margin-bottom:30px;position: relative; top: 100px; left: 20px;}
#filterForm input[type="search"], #filterForm button {padding:10px 15px; border-radius:8px; border:1px solid #ccc; outline:none;}
#filterForm button {background:#2a9d8f; color:#fff; border:none; cursor:pointer;}
#filterForm button:hover {background:#21857a;}

.research-list {display:grid; grid-template-columns:repeat(3, minmax(210px,1fr)); gap:40px; padding:30px; position: relative; top: 110px; left: 20px;}
.research-card {
    background:
        linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)),
        url('Photos/book.jpg');
    background-size: cover;
    background-position: center;
    border-radius:8px;
    padding:20px;
    flex-direction:column;
}
.research-card h4 { font-family:"Merriweather",serif; font-size:1.1rem; margin-bottom:10px; }
.research-card p { font-size:0.93rem; margin:4px 0; }
.card-actions { margin-top:auto; display:flex; gap:10px; flex-wrap:wrap; }
.card-actions button {
    flex: 1;
    padding: 10px 12px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    background: #fff;
    color: #3d2f22;
    box-shadow: 0 3px 6px rgba(0,0,0,0.12);
    transition: all 0.25s ease;
}
.card-actions button:hover:not(:disabled) {
    background: #3d2f22;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.18);
}
.card-actions button:active:not(:disabled) {
    transform: scale(0.97);
}
.card-actions button:disabled {
    cursor: default;
    opacity: 0.6;
    box-shadow: none;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
    z-index: 999;
}
.modal-content {
    background: #fff;
    padding: 25px;
    top:20px;
    left:110px;
    border-radius: 10px;
    width: 90%;
    max-width: 650px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    display: flex;
    gap: 25px;
    position: relative;
}
.modal-left { width: 45%; }
.modal-right { width: 55%; max-height: 300px; overflow-y: auto; padding-right: 10px; }
.modal-right::-webkit-scrollbar { width: 6px; }
.modal-right::-webkit-scrollbar-thumb { background: #999; border-radius: 4px; }
.closeBtn { position: absolute; top: 10px; right: 15px; font-size: 22px; font-weight: bold; background: none; border: none; cursor: pointer; color: #444; }

/* Confirmation / Message Modals */
.modal-overlay { display:flex; justify-content:center; align-items:center; position:fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index:1000; }
.modal-box { background:#fff; padding:20px 25px; border-radius:10px; width:90%; max-width:450px; text-align:center; }
.modal-buttons { margin-top:20px; display:flex; justify-content:center; gap:10px; }
.modal-buttons .btn { padding:8px 16px; border-radius:6px; border:none; cursor:pointer; font-weight:600; }
.modal-buttons .yes { background:#2a9d8f; color:#fff; }
.modal-buttons .no { background:#e76f51; color:#fff; }
.modal-buttons .ok { background:#264653; color:#fff; }

@media(max-width:768px){ .research-list{grid-template-columns:repeat(2,1fr);} }
@media(max-width:480px){ .research-list{grid-template-columns:1fr;} }
</style>
</head>
<body>

<div class="container">
    <h3>Advanced Research Search</h3>
    <form id="filterForm">
        <input type="search" id="search" placeholder="Search research...">
        <button type="button" id="filterBtn">Show All</button>
    </form>
    <div id="researchContent"></div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <button class="closeBtn">&times;</button>
        <div class="modal-left">
            <h4 id="modalTitle"></h4>
            <p><strong>Author:</strong> <span id="modalAuthor"></span></p>
            <p><strong>Category:</strong> <span id="modalCategory"></span></p>
            <p><strong>Adviser:</strong> <span id="modalAdviser"></span></p>
        </div>
        <div class="modal-right">
            <p><strong>Abstract:</strong></p>
            <p id="modalAbstract"></p>
        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div id="confirmModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h2 id="confirmTitle">Confirm Action</h2>
    <p id="confirmText">Are you sure?</p>
    <div class="modal-buttons">
      <button id="confirmYes" class="btn yes">Yes</button>
      <button id="confirmNo" class="btn no">No</button>
    </div>
  </div>
</div>

<!-- Message Modal -->
<div id="messageModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h2 id="messageTitle">Message</h2>
    <p id="messageText"></p>
    <div class="modal-buttons">
      <button id="messageClose" class="btn ok">OK</button>
    </div>
  </div>
</div>

<script>
// ===== JS =====
function showProcessing(message) {
    document.getElementById("messageTitle").textContent = "Please wait...";
    document.getElementById("messageText").textContent = message;
    document.getElementById("messageModal").style.display = "flex";
}

function showConfirm(message, callback) {
    const modal = document.getElementById("confirmModal");
    document.getElementById("confirmText").textContent = message;
    modal.style.display = "flex";
    document.getElementById("confirmYes").onclick = () => { modal.style.display="none"; callback(true); };
    document.getElementById("confirmNo").onclick = () => { modal.style.display="none"; callback(false); };
}

function showMessage(title, message) {
    document.getElementById("messageTitle").textContent = title;
    document.getElementById("messageText").textContent = message;
    document.getElementById("messageModal").style.display = "flex";
}

document.getElementById("messageClose")?.addEventListener("click",()=>{
    document.getElementById("messageModal").style.display="none";
});

function loadResearch(category='all') {
    fetch('advancedsearch_api.php?ajax=list&category='+category)
    .then(r=>r.text())
    .then(html=>{
        document.getElementById('researchContent').innerHTML = html;
        attachEvents();
    });
}

document.getElementById('filterBtn').addEventListener('click',()=>{
    const search = document.getElementById('search').value.trim();
    loadResearch('all', search); // send search term
});

function loadResearch(category='all', search='') {
    fetch(`advancedsearch_api.php?ajax=list&category=${category}&search=${encodeURIComponent(search)}`)
    .then(r=>r.text())
    .then(html=>{
        document.getElementById('researchContent').innerHTML = html;
        attachEvents();
    });
}


function attachEvents() {
    document.querySelectorAll('.favoriteBtn').forEach(btn=>{
        btn.onclick = e=>{
            e.stopPropagation();
            const researchId = btn.dataset.id;
            const confirmMsg = btn.innerText.includes('Remove') ? "Remove this research from Favorites?" : "Add this research to Favorites?";
            showConfirm(confirmMsg, ok=>{
                if(!ok) return;
                showProcessing(btn.innerText.includes('Remove') ? "Removing from favorites..." : "Adding to favorites...");
                btn.disabled = true;
                const fd = new FormData();
                fd.append('research_id', researchId);
                fd.append('action', 'favorite');
                fetch('advancedsearch_api.php',{method:'POST',body:fd})
                .then(r=>r.json())
                .then(d=>{
                    showMessage("Favorites Action", d.message);
                    if(d.success) btn.innerText = d.message.includes('Added') ? '❤️ Remove from Favorites' : '⭐ Add to Favorites';
                }).finally(()=> btn.disabled = false);
            });
        };
    });

  document.querySelectorAll('.requestBtn').forEach(btn=>{
    btn.onclick = e=>{
        e.stopPropagation();
        const researchId = btn.dataset.id;

        showConfirm("Request access to this research?", ok=>{
            if(!ok) return;

            showProcessing("Requesting access...");
            btn.disabled = true;
            const fd = new FormData();
            fd.append('research_id', researchId);
            fd.append('action', 'request_access');

            fetch('advancedsearch_api.php', {method:'POST', body:fd})
            .then(r=>r.json())
            .then(d=>{
                showMessage("Access Request", d.message);

                if(d.success){
                    // Update button dynamically
                    switch(d.new_status){
                        case 'pending': btn.innerText='⏳ Pending Approval'; btn.disabled=true; break;
                        case 'approved': btn.innerText='✔ Access Approved'; btn.disabled=true; break;
                        case 'rejected': btn.innerText='❌ Rejected (Resubmit)'; btn.disabled=false; break;
                        case 'canceled': btn.innerText='⚠ Canceled Request'; btn.disabled=false; break;
                        default: btn.innerText='Request Access'; btn.disabled=false;
                    }
                } else {
                    btn.disabled = false;
                }
            }).finally(()=> btn.disabled = false);
        });
    };
});



    document.querySelectorAll('.showModalBtn').forEach(btn=>{
        btn.onclick = e=>{
            e.stopPropagation();
            document.getElementById("modalTitle").textContent = btn.dataset.title;
            document.getElementById("modalAuthor").textContent = btn.dataset.author;
            document.getElementById("modalCategory").textContent = btn.dataset.category;
            document.getElementById("modalAdviser").textContent = btn.dataset.adviser;
            document.getElementById("modalAbstract").textContent = btn.dataset.abstract;
            document.getElementById("detailsModal").style.display = "flex";
        };
    });
    document.querySelector(".closeBtn").onclick = ()=>{ document.getElementById("detailsModal").style.display="none"; };
}

// Initial load
loadResearch();
</script>
</body>
</html>
