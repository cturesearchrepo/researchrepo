<?php
if(!isset($_SESSION)){
    session_start();
}

if(!isset($_SESSION['student_id'])){
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if($conn->connect_error) die("DB Connection failed: ".$conn->connect_error);

$stmt = $conn->prepare("SELECT * FROM students WHERE student_id=?");
$stmt->bind_param("i",$student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Profile & Settings</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>
.container{
  padding: 25px;
  max-width: 1100px;
  margin: auto;
  font-family: "Segoe UI", sans-serif;
}
h3{
  color:#8B0000;
  margin-top:25px;
}
label{
  display:block;
  margin-top:10px;
  font-weight:600;
}
input, select{
  width:100%;
  padding:8px;
  margin-top:5px;
  border:1px solid #ccc;
  border-radius:4px;
}
button{
  padding:10px 15px;
  background:#28a745;
  color:#fff;
  border:none;
  border-radius:5px;
  cursor:pointer;
  margin-top:15px;
}
button:hover{
  background:#218838;
}
.profile-img{
  width:100px;
  height:100px;
  border-radius:50%;
  object-fit:cover;
}
#message, #pwdMessage {
  font-size:14px;
  margin-top:10px;
}
.settings-card{
  background:#fff;
  border:1px solid #ddd;
  border-radius:8px;
  padding:20px;
  margin-top:20px;
  box-shadow:0 2px 5px rgba(0,0,0,0.1);
}
.edit-pref-btn{
  display:inline-block;
  padding:10px 15px;
  background-color:#8B0000;
  color:white;
  border-radius:6px;
  text-decoration:none;
  font-weight:600;
  margin-top:10px;
}
.edit-pref-btn:hover{
  background-color:#a52a2a;
  transform:scale(1.03);
  transition:0.2s ease;
}
</style>
</head>
<body>
<div class="container">
  <h3>My Profile</h3>
  <div class="settings-card">
    <form id="profileForm" enctype="multipart/form-data">
        <div style="text-align:center; margin-bottom:15px;">
            <img src="../uploads/students/<?= !empty($student['profile_image']) ? htmlspecialchars($student['profile_image']) : 'default.png' ?>"
                 class="profile-img" alt="Profile">
        </div>

        <label>Full Name</label>
        <input type="text" name="fullname" value="<?= htmlspecialchars($student['fullname']) ?>" required>

        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($student['username']) ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>

        <label>Phone</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($student['phone']) ?>">

        <label>Year Level</label>
        <select name="year_level">
            <?php
            $years = ['First Year','Second Year','Third Year','Fourth Year'];
            foreach($years as $y){
                $sel = $student['year_level']==$y?'selected':'';
                echo "<option value='$y' $sel>$y</option>";
            }
            ?>
        </select>

        <label>Profile Image (optional)</label>
        <input type="file" name="profile_image" accept="image/*">

        <button type="submit">Update Profile</button>
        <p id="message"></p>
    </form>
  </div>

  <h3>Change Password</h3>
  <div class="settings-card">
    <form id="passwordForm">
        <label>Current Password</label>
        <input type="password" name="current_password" required>
        <label>New Password</label>
        <input type="password" name="new_password" required>
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>
        <button type="submit">Change Password</button>
        <p id="pwdMessage"></p>
    </form>
  </div>

  <h3>Preferences</h3>
  <div class="settings-card">
      <p>You can customize how your CTU Repository experience looks and works.</p>
      <a href="../preferences.php" class="edit-pref-btn">
          <i class="fa fa-sliders-h"></i> Edit My Preferences
      </a>
  </div>
</div>

<script>
document.getElementById('profileForm').addEventListener('submit', function(e){
    e.preventDefault();
    let formData = new FormData(this);
    fetch('update_profile.php', {method:'POST', body:formData})
    .then(res=>res.json())
    .then(data=>{
        document.getElementById('message').textContent = data.message;
        if(data.status=='success') setTimeout(()=>location.reload(),1000);
    });
});

document.getElementById('passwordForm').addEventListener('submit', function(e){
    e.preventDefault();
    let formData = new FormData(this);
    fetch('change_password.php', {method:'POST', body:formData})
    .then(res=>res.json())
    .then(data=>{
        document.getElementById('pwdMessage').textContent = data.message;
        if(data.status=='success') this.reset();
    });
});
</script>
</body>
</html>
