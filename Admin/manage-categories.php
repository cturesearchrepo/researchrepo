<?php
$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) { die("Connection failed: " . $mysqli->connect_error); }

// Initialize message variables
$messageTitle = "";
$messageContent = "";

// Add Category
if (isset($_POST['addCategory'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if (empty($name)) {
        $messageTitle = "⚠ Error!";
        $messageContent = "Category name cannot be empty!";
    } else {
        // Check if category already exists
        $stmt = $mysqli->prepare("SELECT id FROM categories WHERE name=?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $messageTitle = "⚠ Error!";
            $messageContent = "Category name already exists!";
        } else {
            $stmt_insert = $mysqli->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $name, $description);
            if ($stmt_insert->execute()) {
                $messageTitle = "✅ Success!";
                $messageContent = "Category added successfully!";
            } else {
                $messageTitle = "⚠ Error!";
                $messageContent = "Failed to add category!";
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
}

// Edit Category
if (isset($_POST['editCategory'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if (empty($name)) {
        $messageTitle = "⚠ Error!";
        $messageContent = "Category name cannot be empty!";
    } else {
        // Check if another category with same name exists
        $stmt_check = $mysqli->prepare("SELECT id FROM categories WHERE name=? AND id<>?");
        $stmt_check->bind_param("si", $name, $id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $messageTitle = "⚠ Error!";
            $messageContent = "Another category with this name already exists!";
        } else {
            $stmt_update = $mysqli->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
            $stmt_update->bind_param("ssi", $name, $description, $id);
            if ($stmt_update->execute()) {
                $messageTitle = "✅ Success!";
                $messageContent = "Category updated successfully!";
            } else {
                $messageTitle = "⚠ Error!";
                $messageContent = "Failed to update category!";
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}

// Delete Category
if (isset($_POST['deleteCategory'])) {
    $id = $_POST['id'];
    $stmt_delete = $mysqli->prepare("DELETE FROM categories WHERE id=?");
    $stmt_delete->bind_param("i", $id);
    if ($stmt_delete->execute()) {
        $messageTitle = "✅ Success!";
        $messageContent = "Category deleted successfully!";
    } else {
        $messageTitle = "⚠ Error!";
        $messageContent = "Failed to delete category!";
    }
    $stmt_delete->close();
}

// Fetch Categories
$result = $mysqli->query("SELECT * FROM categories ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Categories</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
h3 { margin-bottom:25px; color:#1f2937; }
.action-btn { background:#2563eb; color:#fff; padding:10px 18px; border:none; border-radius:8px; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; gap:6px; transition:0.2s; }
.action-btn:hover { background:#1d4ed8; }
table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.08); border-radius:8px; overflow:hidden; margin-top:20px; }
th, td { padding:14px 16px; text-align:left; }
th { background:#2563eb; color:#fff; font-weight:600; }
tr:nth-child(even){ background:#f9fafb; }
tr:hover { background:#e0e7ff; }
.btn { padding:8px 14px; border:none; border-radius:6px; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; gap:5px; transition:0.2s; }
.btn-edit { background:#facc15; color:#1e293b; }
.btn-edit:hover { background:#eab308; color:#1e293b; transform:scale(1.05);}
.btn-delete { background:#ef4444; color:#fff; }
.btn-delete:hover { background:#dc2626; transform:scale(1.05);}
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000; }
.modal { background:#fff; padding:25px; border-radius:12px; width:400px; max-width:90%; position:relative; box-shadow:0 8px 24px rgba(0,0,0,0.25); text-align:center; }
.modal h4 { margin-bottom:15px; font-weight:600; font-size:18px; }
.modal-actions { display:flex; justify-content:space-between; margin-top:20px; }
.save-btn, .cancel-btn, .delete-confirm-btn { padding:8px 14px; border:none; border-radius:6px; font-size:14px; cursor:pointer; transition:0.3s ease; }
.save-btn { background:#10b981; color:white; }
.save-btn:hover { background:#059669; }
.cancel-btn { background:#6c757d; color:white; }
.cancel-btn:hover { background:#5a6268; }
.delete-confirm-btn { background:#ef4444; color:white; }
.delete-confirm-btn:hover { background:#dc2626; }
.spinner { border:4px solid #f3f3f3; border-top:4px solid #3498db; border-radius:50%; width:40px; height:40px; margin:0 auto 15px; animation:spin 1s linear infinite; }
@keyframes spin {0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}

/* Input Styles */
input, textarea { width:100%; padding:8px 10px; margin:5px 0; border-radius:6px; border:1px solid #ccc; font-size:14px; }
</style>
</head>
<body>

<h3>📂 Manage Categories</h3>
<button class="action-btn" onclick="openModal('addModal')"><i class="fa fa-plus"></i> Add Category</button>

<table>
<thead>
<tr>
<th>#</th>
<th>Category Name</th>
<th>Description</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php $i=1; while($row=$result->fetch_assoc()): ?>
<tr id="row-<?= $row['id'] ?>">
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($row['name']) ?></td>
<td><?= htmlspecialchars($row['description']) ?></td>
<td style="display:flex; gap:6px;">
<button class="btn btn-edit" onclick="openEditModal(<?= $row['id'] ?>,'<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>','<?= htmlspecialchars($row['description'],ENT_QUOTES) ?>')"><i class="fa fa-pen"></i> Edit</button>
<button class="btn btn-delete" onclick="openDeleteModal(<?= $row['id'] ?>,'<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>')"><i class="fa fa-trash"></i> Delete</button>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<!-- Hidden Delete Form -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="id" id="delete-id-input">
    <input type="hidden" name="deleteCategory" value="1">
</form>

<!-- Add Modal -->
<div id="addModal" class="modal-overlay">
<div class="modal">
<h4>Add Category</h4>
<form method="POST">
<div class="form-group">
<label>Category Name</label>
<input type="text" name="name" required>
</div>
<div class="form-group">
<label>Description</label>
<textarea name="description"></textarea>
</div>
<div class="modal-actions">
<button type="button" class="cancel-btn" onclick="closeModal('addModal')">Cancel</button>
<button type="submit" name="addCategory" class="save-btn">Save</button>
</div>
</form>
</div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay">
<div class="modal">
<h4>Edit Category</h4>
<form method="POST">
<input type="hidden" name="id" id="edit-id">
<div class="form-group">
<label>Category Name</label>
<input type="text" name="name" id="edit-name" required>
</div>
<div class="form-group">
<label>Description</label>
<textarea name="description" id="edit-description"></textarea>
</div>
<div class="modal-actions">
<button type="button" class="cancel-btn" onclick="closeModal('editModal')">Cancel</button>
<button type="submit" name="editCategory" class="save-btn">Update</button>
</div>
</form>
</div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
<div class="modal">
<h4>Confirm Deletion</h4>
<p id="deleteMessage">Are you sure you want to delete this category?</p>
<input type="hidden" id="delete-id">
<div class="modal-actions">
<button class="cancel-btn" onclick="closeModal('deleteModal')">Cancel</button>
<button class="delete-confirm-btn" onclick="confirmDelete()">Delete</button>
</div>
</div>
</div>

<!-- Success / Error Message Modal -->
<div id="messageModal" class="modal-overlay">
<div class="modal">
<h4 id="messageTitle"></h4>
<p id="messageContent"></p>
<div class="modal-actions">
<button class="save-btn" onclick="closeModal('messageModal')">OK</button>
</div>
</div>
</div>

<script>
function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }

function openEditModal(id,name,desc){
    document.getElementById('edit-id').value=id;
    document.getElementById('edit-name').value=name;
    document.getElementById('edit-description').value=desc;
    openModal('editModal');
}

function openDeleteModal(id,name){
    document.getElementById('delete-id').value=id;
    document.getElementById('deleteMessage').innerText = `Are you sure you want to delete "${name}"?`;
    openModal('deleteModal');
}

// Submit hidden delete form
function confirmDelete(){
    document.getElementById('delete-id-input').value = document.getElementById('delete-id').value;
    document.getElementById('deleteForm').submit();
}

// Show PHP message
<?php if(!empty($messageTitle) && !empty($messageContent)): ?>
openModal('messageModal');
document.getElementById('messageTitle').innerText = "<?= $messageTitle ?>";
document.getElementById('messageContent').innerText = "<?= $messageContent ?>";
<?php endif; ?>

// Close modal if clicked outside
window.addEventListener('click', e=>{
    if(e.target.classList.contains('modal-overlay')) e.target.style.display='none';
});
</script>

</body>
</html>
