<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Add New Admin</title>
<link rel="stylesheet" href=".//AllStyles/addadmin.css" />
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
  <main class="main-content">
    <header class="top-header">
      <h2>Add New Admin</h2>
    </header>

    <section class="add-admin-card">
      <h3><i class="fa-solid fa-user-plus"></i> Register a New Administrator</h3>
      <p class="subtitle">Fill out the form below to create a new admin account.</p>

      <div id="formMessage"></div>

      <form id="addAdminForm" class="add-admin-form" action="../Admin/handle-add-admin.php" method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-grid">
          <div class="form-group">
            <label for="adminFullName"><i class="fa-solid fa-id-card"></i> Full Name</label>
            <input type="text" id="adminFullName" name="fullname" placeholder="Enter full name" minlength="5" required>
            <small class="hint" id="nameHint">Full name must be at least 5 characters.</small>
          </div>
          <div class="form-group">
            <label for="adminUsername"><i class="fa-solid fa-user"></i> Username</label>
            <input type="text" id="adminUsername" name="username" placeholder="Enter username" minlength="5" maxlength="15" required>
            <small class="hint" id="userHint">Username must be 5–15 characters.</small>
          </div>
        </div>
        
        <div class="form-grid">
          <div class="form-group">
            <label for="adminEmail"><i class="fa-solid fa-envelope"></i> Email</label>
            <input type="email" id="adminEmail" name="email" placeholder="Enter email" required>
            <small class="hint" id="emailHint">Must be a valid email (e.g. name@gmail.com).</small>
          </div>
          <div class="form-group">
            <label for="adminRole"><i class="fa-solid fa-briefcase"></i> Role</label>
            <select id="adminRole" name="role" required>
              <option value="">Select role</option>
              <option value="Super Admin">Super Admin</option>
              <option value="Editor">Department Admin</option>
            </select>
          </div>
        </div>
        
        <div class="form-group password-wrapper">
          <label for="adminPassword"><i class="fa-solid fa-lock"></i> Password</label>
          <div class="password-input">
            <input type="password" id="adminPassword" name="password" placeholder="Enter password" autocomplete="new-password" required>
            <span class="toggle-password" data-target="adminPassword">
              <img src="./Photos/icons8-eye-24.png" alt="Show Password">
            </span>
          </div>
          <div class="strength-meter"><div id="strengthBar"></div></div>
          <small class="hint" id="passHint">Use 8+ characters with letters, numbers & symbols.</small>
        </div>
        
        <div class="form-group password-wrapper">
          <label for="adminConfirmPassword"><i class="fa-solid fa-lock"></i> Confirm Password</label>
          <div class="password-input">
            <input type="password" id="adminConfirmPassword" name="confirmPassword" placeholder="Re-enter password" autocomplete="new-password" required>
            <span class="toggle-password" data-target="adminConfirmPassword">
              <img src="./Photos/icons8-eye-24.png" alt="Show Password">
            </span>
          </div>
          <small class="hint" id="confirmHint">Passwords must match.</small>
        </div>
        
        <div class="form-group">
          <label for="adminProfilePic"><i class="fa-solid fa-image"></i> Profile Picture (optional)</label>
          <input type="file" id="adminProfilePic" name="adminProfilePic" accept="image/*">
          <div class="profile-preview">
            <img id="previewImage" src="" alt="Profile Preview">
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-user-circle"></i> Choose Avatar (optional)</label>
          <div class="avatar-options">
            <?php
            $avatarsDir = "uploads/admins/avatars/";
            if (is_dir($avatarsDir)) {
                $avatars = glob($avatarsDir . "*.{png,jpg,jpeg,gif}", GLOB_BRACE);
                if ($avatars) {
                    foreach ($avatars as $avatar) {
                        $file = basename($avatar);
                        echo '<label>
                                <input type="radio" name="selectedAvatar" value="'.$file.'">
                                <img src="'.$avatarsDir.$file.'" alt="'.$file.'" class="avatar-thumb">
                              </label>';
                    }
                }
            }
            ?>
          </div>
          <small class="hint">You can either upload your own picture or select an avatar.</small>
        </div>

        <button type="submit" class="submit-btn"><i class="fa-solid fa-plus"></i> Add Admin</button>
      </form>
    </section>
  </main>
</div>

<div id="confirmModal" class="modal">
  <div class="modal-content">
    <h2>Confirm Submission</h2>
    <p>Are you sure you want to add this new admin?</p>
    <button id="confirmYes" class="confirm-btn">Yes</button>
    <button id="confirmNo" class="cancel-btn">No</button>
  </div>
</div>

<div id="uploadingModal" class="modal">
  <div class="modal-content">
    <div class="spinner"></div>
    <p>Uploading... Please wait</p>
  </div>
</div>

<div id="successModal" class="modal">
  <div class="modal-content">
    <h2>✅ Success</h2>
    <p id="successText">Admin account successfully added!</p>
    <button class="close-btn" onclick="closeModal('successModal')">OK</button>
  </div>
</div>

<div id="errorModal" class="modal">
  <div class="modal-content">
    <h2>❌ Error</h2>
    <p id="errorText">Something went wrong.</p>
    <button class="close-btn" onclick="closeModal('errorModal')">OK</button>
  </div>
</div>



<script>
const profileInput = document.getElementById('adminProfilePic');
const previewImage = document.getElementById('previewImage');
profileInput.addEventListener('change', e => {
  const file = e.target.files[0];
  if (file) {
    previewImage.src = URL.createObjectURL(file);
    previewImage.style.display = 'block';
  } else {
    previewImage.style.display = 'none';
  }
});

const passField = document.getElementById('adminPassword');
const confirmField = document.getElementById('adminConfirmPassword');
const confirmHint = document.getElementById('confirmHint');
function checkPasswordMatch() {
  if (confirmField.value === passField.value && passField.value !== '') {
    confirmHint.textContent = 'Passwords match ✔';
    confirmHint.style.color = 'green';
    return true;
  } else {
    confirmHint.textContent = 'Passwords do not match ✖';
    confirmHint.style.color = '#d9534f';
    return false;}}
confirmField.addEventListener('input', checkPasswordMatch);
document.querySelectorAll('.toggle-password').forEach(toggle => {
  toggle.addEventListener('click', () => {
    const target = document.getElementById(toggle.dataset.target);
    const icon = toggle.querySelector('img');
    if (target.type === 'password') {
      target.type = 'text';
      icon.src = './Photos/icons8-eye-24.png';
    } else {
      target.type = 'password';
      icon.src = './Photos/icons8-closed-eye-24.png';
    } });});
function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }
const form = document.getElementById('addAdminForm');
const roleField = document.getElementById('adminRole');
const formMessage = document.getElementById('formMessage');

form.addEventListener('submit', e => {
  e.preventDefault();
  if (!checkPasswordMatch()) { showMessage('❌ Passwords do not match.', false); return; }
  if (roleField.value === '') { showMessage('❌ Please select a role.', false); return; }
  const fullname = document.getElementById('adminFullName').value.trim();
  const username = document.getElementById('adminUsername').value.trim();
  const email = document.getElementById('adminEmail').value.trim();
  const password = passField.value.trim();
  if (!fullname || !username || !email || !password) { showMessage('❌ Please fill in all required fields.', false); return; }

  openModal('confirmModal');
});

document.getElementById('confirmYes').addEventListener('click', () => {
  closeModal('confirmModal');
  openModal('uploadingModal');
  const formData = new FormData(form);
  fetch('../Admin/handle-add-admin.php', { method:'POST', body:formData })
  .then(res=>res.json())
  .then(data=>{
    closeModal('uploadingModal');
    if (data.status === "success") {
      document.getElementById('successText').textContent = data.message;
      openModal('successModal');
      form.reset();
      previewImage.style.display='none';
    } else {
      document.getElementById('errorText').textContent = data.message;
      openModal('errorModal');
    }
  })
  .catch(err=>{
    closeModal('uploadingModal');
    document.getElementById('errorText').textContent = "An error occurred: " + err;
    openModal('errorModal');
  });
});
document.getElementById('confirmNo').addEventListener('click', ()=>closeModal('confirmModal'));

function showMessage(text, success){
  formMessage.style.display='block';
  formMessage.textContent=text;
  if(success){
    formMessage.style.color='#155724';
    formMessage.style.background='#d4edda';
    formMessage.style.border='1px solid #c3e6cb';
  } else {
    formMessage.style.color='#721c24';
    formMessage.style.background='#f8d7da';
    formMessage.style.border='1px solid #f5c6cb';
  }
}
document.addEventListener("DOMContentLoaded", () => {
    const passwordFields = ["adminPassword", "adminConfirmPassword"];

    passwordFields.forEach(id => {
        const field = document.getElementById(id);
        if (field) {
            field.addEventListener("paste", e => {
                e.preventDefault();
            });
        }
    });
});

// --- Password Strength Meter ---
const strengthBar = document.getElementById('strengthBar');

passField.addEventListener('input', updateStrengthBar);

function updateStrengthBar() {
  const value = passField.value;
  let strength = 0;

  // Criteria
  if (value.length >= 8) strength += 1;               // Minimum length
  if (/[A-Z]/.test(value)) strength += 1;            // Uppercase letters
  if (/[a-z]/.test(value)) strength += 1;            // Lowercase letters
  if (/[0-9]/.test(value)) strength += 1;            // Numbers
  if (/[\W]/.test(value)) strength += 1;             // Special characters

  // Update strength bar
  const percent = (strength / 5) * 100;
  strengthBar.style.width = percent + '%';

  // Change color based on strength
  if (strength <= 2) {
    strengthBar.style.background = '#dc3545'; // Weak: red
  } else if (strength === 3 || strength === 4) {
    strengthBar.style.background = '#ffc107'; // Medium: yellow
  } else if (strength === 5) {
    strengthBar.style.background = '#28a745'; // Strong: green
  }
}


</script>
</body>
</html>