<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "CentralizedResearchRepository_userdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// --- FETCH ADMINS Info from database ---
$sql    = "SELECT id, fullname, username, email, role, status, profile_image FROM admins";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Manage Admins</title>
<link rel="stylesheet" href=".//AllStyles/manage-admin.css" />
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000;}
.modal{background:#fff;padding:20px;border-radius:12px;width:350px;box-shadow:0 8px 25px rgba(0,0,0,0.2);animation:fadeIn 0.25s ease;text-align:center;}
.modal h4{margin-bottom:15px;font-weight:600;font-size:18px;}
.modal-actions{display:flex;justify-content:space-between;margin-top:20px;}
button{cursor:pointer;}

/* Action buttons */
.edit-btn,.delete-btn{border:none;padding:8px 12px;border-radius:6px;font-size:14px;cursor:pointer;transition:0.3s ease;}
.edit-btn{background-color:#3498db;color:white;}
.edit-btn:hover{background-color:#2980b9;transform:scale(1.05);}
.delete-btn{background-color:#e74c3c;color:white;margin-left:5px;}
.delete-btn:hover{background-color:#c0392b;transform:scale(1.05);}
.edit-btn i,.delete-btn i{margin-right:4px;}

/* Modal buttons */
.cancel-btn,.save-btn,.delete-confirm-btn{border:none;padding:8px 14px;border-radius:6px;font-size:14px;cursor:pointer;transition:0.3s ease;}
.cancel-btn{background:#bdc3c7;color:white;}
.cancel-btn:hover{background:#95a5a6;}
.save-btn{background:#27ae60;color:white;}
.save-btn:hover{background:#1e8449;}
.delete-confirm-btn{background:#e74c3c;color:white;}
.delete-confirm-btn:hover{background:#c0392b;}

/* Spinner */
.spinner{border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;width:40px;height:40px;margin:0 auto 15px;animation:spin 1s linear infinite;}
@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
@keyframes fadeIn{from{opacity:0;transform:scale(0.9);}to{opacity:1;transform:scale(1);}}
</style>
</head>
<body>
<div class="dashboard-container">
  <main class="main-content">
    <header class="top-header">
      <h2>Manage Admins</h2>
      <div class="top-right"><input type="text" id="searchInput" class="search-input" placeholder="Search admin email"></div>
    </header>

    <section class="manage-admins-section">
      <h3>Admin List</h3>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Profile</th><th>Full Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="adminTableBody">
          <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
          <tr id="row-<?= $row['id']; ?>" data-id="<?= $row['id']; ?>" data-fullname="<?= htmlspecialchars($row['fullname']); ?>" data-role="<?= htmlspecialchars($row['role']); ?>">
            <td><img src="<?= !empty($row['profile_image']) ? $row['profile_image'] : 'Photos/logoCtu.png'; ?>" class="admin-thumb" style="width:40px;height:40px;border-radius:50%;"></td>
            <td><?= htmlspecialchars($row['fullname']); ?></td>
            <td><?= htmlspecialchars($row['username']); ?></td>
            <td><?= htmlspecialchars($row['email']); ?></td>
            <td><?= htmlspecialchars($row['role']); ?></td>
            <td><span class="status <?= strtolower($row['status']); ?>"><?= ucfirst($row['status']); ?></span></td>
            <td>
              <button class="edit-btn"><i class="fa-solid fa-pen-to-square"></i></button>
              <button class="delete-btn"><i class="fa-solid fa-trash"></i></button>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php else: ?>
          <tr><td colspan="7">No admins found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <h4>Edit Admin</h4>
    <input type="hidden" id="editAdminId">
    <div class="form-group"><label>Full Name</label><input type="text" id="editFullName"></div>
    <div class="form-group"><label>Role</label>
      <select id="editRole">
        <option value="Super Admin">Super Admin</option>
        <option value="Editor">Department Admin</option>
      </select>
    </div>
    <div class="modal-actions">
      <button class="cancel-btn" onclick="closeModal('editModal')">Cancel</button>
      <button class="save-btn" id="saveEditBtn">Save Changes</button>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h4>Confirm Deletion</h4>
    <p>Are you sure you want to delete this admin?</p>
    <input type="hidden" id="deleteAdminId">
    <div class="modal-actions">
      <button class="cancel-btn" onclick="closeModal('deleteModal')">Cancel</button>
      <button class="delete-confirm-btn" id="confirmDeleteBtn">Delete</button>
    </div>
  </div>
</div>

<!-- Loading Modal -->
<div class="modal-overlay" id="loadingModal">
  <div class="modal">
    <div class="spinner"></div>
    <p>Deleting admin...</p>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="successModal">
  <div class="modal">
    <h4>✅ Success!</h4>
    <p>You have successfully deleted the admin.</p>
    <button class="save-btn" onclick="closeModal('successModal')">OK</button>
  </div>
</div>

<script>
// Search
document.getElementById('searchInput').addEventListener('input', function() {
  const filter = this.value.toLowerCase();
  document.querySelectorAll('#adminTableBody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
  });
});

// Edit
document.querySelectorAll('.edit-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const tr = this.closest('tr');
    document.getElementById('editAdminId').value = tr.dataset.id;
    document.getElementById('editFullName').value = tr.dataset.fullname;
    document.getElementById('editRole').value = tr.dataset.role;
    document.getElementById('editModal').style.display='flex';
  });
});

// Save edit
document.getElementById('saveEditBtn').addEventListener('click',function(){
  const id = document.getElementById('editAdminId').value;
  const fullname = document.getElementById('editFullName').value;
  const role = document.getElementById('editRole').value;

  fetch('update-admin.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`id=${id}&fullname=${encodeURIComponent(fullname)}&role=${encodeURIComponent(role)}`
  })
  .then(res=>res.text())
  .then(data=>{
    alert(data);
    window.location.reload();
  });
});

// Delete
document.querySelectorAll('.delete-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const tr = this.closest('tr');
    document.getElementById('deleteAdminId').value = tr.dataset.id;
    document.getElementById('deleteModal').style.display='flex';
  });
});

document.getElementById('confirmDeleteBtn').addEventListener('click',function(){
  const id = document.getElementById('deleteAdminId').value;
  closeModal('deleteModal');
  document.getElementById('loadingModal').style.display='flex';

  fetch('delete-admin.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`id=${id}`
  })
  .then(res=>res.text())
  .then(data=>{
    document.getElementById('loadingModal').style.display='none';
    if(data.includes("successfully")){
      document.getElementById('successModal').style.display='flex';
      document.querySelector(`#row-${id}`).remove();
    }else{
      alert("❌ Error: " + data);
    }
  })
  .catch(err=>{
    document.getElementById('loadingModal').style.display='none';
    alert("❌ Request failed: " + err);
  });
});

function closeModal(id){document.getElementById(id).style.display='none';}
</script>
</body>
</html>
<?php $conn->close(); ?>
