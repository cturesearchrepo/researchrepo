<?php
session_start();

$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$loginMessage = '';
$loginMessageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $loginMessage = "Please enter both email and password.";
        $loginMessageType = "error";
    } else {
        $stmt = $mysqli->prepare("SELECT id, fullname, username, email, password_hash, role, profile_image FROM admins WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['admin_id']     = $row['id'];
                $_SESSION['admin_name']   = $row['fullname'];
                $_SESSION['admin_user']   = $row['username'];
                $_SESSION['admin_email']  = $row['email'];
                $_SESSION['admin_role']   = $row['role'];
                $_SESSION['admin_avatar'] = $row['profile_image'] ?: "Photos/default.png";

                header("Location: AdminDashboard.php");
                exit;
            } else {
                $loginMessage = "Invalid password.";
                $loginMessageType = "error";
            }
        } else {
            $loginMessage = "Account not found or inactive.";
            $loginMessageType = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Administrator Login</title>
  <link rel="stylesheet" href="./AllStyles/adminloginstyle.css" />
</head>
<body>
  <div class="container">
    <div class="left-side">
      <img src="Photos/logoCtu.png" alt="CTU Logo" />
    </div>

    <div class="right-side">
      <div class="heading">
        <div class="bar"></div>
        <h1><span>Administrator</span> Access</h1>
      </div>

      <p>Online Research Repoistory Library System – Cebu Technological University</p>
      <br>

      <?php if ($loginMessage):
          $color = ($loginMessageType === 'success') ? 'green' : 'red';
      ?>
      <div style="color:<?= $color ?>; margin-bottom: 15px;">
          <?= htmlspecialchars($loginMessage) ?>
      </div>
      <?php endif; ?>

      <form action="" method="POST" class="login-form">
        <input type="email" name="email" placeholder="Administrator Email" required />

        <div class="input-with-icon">
          <input type="password" id="login-password" name="password" placeholder="Your Password" required />
          <img src="Photos/icons8-closed-eye-24.png" alt="Toggle Password" id="toggle-eye" onclick="togglePassword()" />
        </div>

        <div class="form-extra">
          <label class="checkbox-label">
            <input type="checkbox" name="remember" />
            Remember me
          </label>
          <a href="../Users/Public/LoginUser.php">User Access</a>
        </div>

        <button type="submit" class="login-btn">Login</button>
      </form>

    </div>
  </div>

  <script>
    function togglePassword() {
      const password = document.getElementById("login-password");
      const eye = document.getElementById("toggle-eye");
      if (password.type === "password") {
        password.type = "text";
        eye.src = "Photos/icons8-eye-24.png";
      } else {
        password.type = "password";
        eye.src = "Photos/icons8-closed-eye-24.png";
      }
    }
  </script>
</body>
</html>
