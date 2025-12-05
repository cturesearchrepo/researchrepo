<?php
// portal_registration.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Portal Registration</title>
  <link rel="stylesheet" href="UsersStyle.css" />
</head>
<body>
  <div class="container">
    <!-- Left Side -->
    <div class="left-side">
      <img src="./Photos/logoCtu.png" alt="CTU Logo" />
    </div>

    <!-- Right Side -->
    <div class="right-side">
      <div class="heading">
        <div class="bar"></div>
        <h1><span>Portal</span> Registration</h1>
      </div>

      <p>Online Research Library System – Cebu Technological University</p>

      <div class="registration-options">
        <div class="option" id="facultyOption">
          <img src="./Photos/logoCtu.png" alt="Faculty Icon" />
          <span>FACULTY REGISTRATION</span>
        </div>

        <div class="option" id="studentOption">
          <img src="./Photos/logoCtu.png" alt="Student Icon" />
          <span>STUDENT REGISTRATION</span>
        </div>
      </div>

      <div class="login-link">
        Already have an account? <a href="#" id="loginLink">Login here</a>
      </div>
    </div>
  </div>

  <script>
    // Redirect buttons
    document.getElementById("facultyOption").addEventListener("click", function () {
      window.location.href = "./FacultySignup.php";
    });

    document.getElementById("studentOption").addEventListener("click", function () {
      window.location.href = "./StudentSignup.php";
    });

    document.getElementById("loginLink").addEventListener("click", function (e) {
      e.preventDefault();
      window.location.href = "./LoginUser.php";
    });
  </script>
</body>
</html>
