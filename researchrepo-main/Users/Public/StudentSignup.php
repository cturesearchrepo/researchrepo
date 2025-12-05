<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Registration</title>
  <link rel="stylesheet" href="UsersStyle.css"/>
  <style>
    .error-message-global { color: red; font-weight: 500; margin-bottom: 15px; display: none; }
    .valid { color: green !important; }
    .left-side img { max-width: 270px; border-radius: 50%; cursor: pointer; }
  </style>
</head>
<body>
  <div class="container">
    <div class="left-side">
      <img id="preview-img" src="Photos/logoCtu.png" alt="CTU Logo"/>
    </div>
    <div class="right-side">
      <div class="heading"><div class="bar"></div><h1><span>Student</span> Registration</h1></div>
      <div id="formError" class="error-message-global"></div>
      <form class="registration-form" id="studentForm" action="student_registration.php" method="POST" enctype="multipart/form-data" novalidate>
        <input type="text" id="fullname" name="fullname" placeholder="Full Name" required/>
        <input type="text" id="username" name="username" placeholder="Username" required/>
        <input type="text" id="studentid" name="studentid" placeholder="Student ID" required/>
        <input type="email" id="email" name="email" placeholder="Email (e.g. user@gmail.com)" required/>
        <input type="text" id="number" name="number" placeholder="Phone Number (11 digits)" required/>
        <select id="year_level" name="year_level" required>
          <option value="" disabled <?= empty($_POST['year_level']) ? 'selected' : '' ?>>Select Year Level</option>
          <option value="First Year"  <?= (isset($_POST['year_level']) && $_POST['year_level'] === 'First Year')  ? 'selected' : '' ?>>First Year</option>
          <option value="Second Year" <?= (isset($_POST['year_level']) && $_POST['year_level'] === 'Second Year') ? 'selected' : '' ?>>Second Year</option>
          <option value="Third Year"  <?= (isset($_POST['year_level']) && $_POST['year_level'] === 'Third Year')  ? 'selected' : '' ?>>Third Year</option>
          <option value="Fourth Year" <?= (isset($_POST['year_level']) && $_POST['year_level'] === 'Fourth Year') ? 'selected' : '' ?>>Fourth Year</option>
        </select>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" placeholder="Password" required oncopy="return false" onpaste="return false" oncut="return false" oncontextmenu="return false"/>
          <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
            <img src="./Photos/icons8-closed-eye-24.png" alt="Toggle Password"/>
          </button>
        </div>
        <div class="password-criteria" id="passwordCriteria" style="display:none;">
          <p>Password must include:</p>
          <ul>
            <li id="length" class="criteria">🔴 At least 18 characters</li>
            <li id="uppercase" class="criteria">🔴 At least one uppercase letter</li>
            <li id="special" class="criteria">🔴 At least one special character (!@#$...)</li>
          </ul>
          <p id="strength-text">Password Strength: <span id="strength-level">Weak</span></p>
        </div>
        <div class="password-wrapper">
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required/>
          <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">
            <img src="./Photos/icons8-closed-eye-24.png" alt="Toggle Password"/>
          </button></div>
        <input type="file" id="profile" name="profile" accept="image/*" required/>
        <button type="submit" class="register-btn"><span class="btn-text">Register</span></button>
      </form>
      <div class="login-link">Already registered? <a href="./UsersSignup.php">Go back</a></div>
    </div>
  </div>
  <script>
    function togglePassword(fieldId, button) {
      const field = document.getElementById(fieldId);
      const img = button.querySelector("img");
      if (field.type === "password") {
        field.type = "text";
        img.src = "./Photos/icons8-eye-24.png";
      } else {
        field.type = "password";
        img.src = "./Photos/icons8-closed-eye-24.png"; }}
    document.addEventListener("DOMContentLoaded", () => {
      const profileInput = document.getElementById("profile");
      const previewImg = document.getElementById("preview-img");
      const passwordInput = document.getElementById("password");
      const confirmPasswordInput = document.getElementById("confirm_password");
      const strengthLevel = document.getElementById("strength-level");
      const lengthCriteria = document.getElementById("length");
      const uppercaseCriteria = document.getElementById("uppercase");
      const specialCriteria = document.getElementById("special");
      const passwordCriteria = document.getElementById("passwordCriteria");
      const errorMessage = document.getElementById("formError");
      const registerBtn = document.querySelector(".register-btn");
      const btnText = registerBtn.querySelector(".btn-text");
      let loadingInterval;
      profileInput.addEventListener("change", () => {
        const file = profileInput.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = e => previewImg.src = e.target.result;
          reader.readAsDataURL(file);
        } else previewImg.src = "Photos/logoCtu.png";
      });
      function showFormError(message, isSuccess=false) {
        errorMessage.textContent = message;
        errorMessage.style.display = "block";
        errorMessage.style.color = isSuccess ? "green" : "red";}
      passwordInput.addEventListener("focus", () => passwordCriteria.style.display = "block");
      passwordInput.addEventListener("blur", () => setTimeout(() => {
        if (!passwordInput.matches(":focus")) passwordCriteria.style.display = "none";
      }, 200));
      passwordInput.addEventListener("input", () => {
        const value = passwordInput.value;
        const hasLength = value.length >= 18;
        const hasUppercase = /[A-Z]/.test(value);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(value);
        const hasNumber = /[0-9]/.test(value);
        lengthCriteria.classList.toggle("valid", hasLength);
        uppercaseCriteria.classList.toggle("valid", hasUppercase);
        specialCriteria.classList.toggle("valid", hasSpecial);
        const passed = [hasLength, hasUppercase, hasSpecial, hasNumber].filter(Boolean).length;
        if (passed === 4) {
          strengthLevel.textContent = "Strong";
          strengthLevel.style.color = "green";
        } else if (passed >= 2) {
          strengthLevel.textContent = "Medium";
          strengthLevel.style.color = "orange";
        } else {
          strengthLevel.textContent = "Weak";
          strengthLevel.style.color = "red";
        }});
      ["password","confirm_password"].forEach(id=>{
        document.getElementById(id).addEventListener("keydown", e=>{
          if ((e.ctrlKey||e.metaKey)&&["c","v","x","a"].includes(e.key.toLowerCase())) e.preventDefault();});});
      function startLoading(){
        registerBtn.disabled=true;
        let dotCount=0;
        btnText.textContent="Registering";
        loadingInterval=setInterval(()=>{
          dotCount=(dotCount+1)%4;
          btnText.textContent="Registering"+".".repeat(dotCount);
        },500);}
      function stopLoading(){
        clearInterval(loadingInterval);
        registerBtn.disabled=false;
        btnText.textContent="Register";}
      document.getElementById("studentForm").addEventListener("submit", function(e){
        e.preventDefault();
        errorMessage.style.display="none";
        const fullname=document.getElementById("fullname").value.trim();
        const username=document.getElementById("username").value.trim();
        const studentid=document.getElementById("studentid").value.trim();
        const email=document.getElementById("email").value.trim();
        const number=document.getElementById("number").value.trim();
        const password=passwordInput.value;
        const confirmPassword=confirmPasswordInput.value;
        if (!/^[A-Za-z\s]+$/.test(fullname)) return showFormError("Fullname must only contain letters and spaces.");
        if (fullname.length<10) return showFormError("Fullname must be at least 10 characters.");
        if (!/^[A-Za-z0-9]+$/.test(username)) return showFormError("Username must only contain letters and numbers.");
        if (username.length<10||username.length>15) return showFormError("Username must be between 10 and 15 characters.");
        if (!/^\d+$/.test(studentid)) return showFormError("Student ID must contain only numbers.");
        if (!/^[a-zA-Z0-9._%+-]+@gmail\.com$/.test(email)) return showFormError("Email must be a valid @gmail.com address.");
        if (!/^09\d{9}$/.test(number)) return showFormError("Phone number must start with 09 and be 11 digits long.");
        if (password.length<18 || strengthLevel.textContent!=="Strong") return showFormError("Password must be at least 18 characters and rated Strong.");
        if (password!==confirmPassword) return showFormError("Passwords do not match.");
        const formData=new FormData(this);
        startLoading();
        fetch("register_student.php",{method:"POST",body:formData})
          .then(res=>res.text())
          .then(result=>{
            stopLoading();
            if(result.toLowerCase().includes("success")){
              showFormError(result,true);
              this.reset();
              strengthLevel.textContent="Weak";
              strengthLevel.style.color="red";
              previewImg.src="Photos/logoCtu.png";
            } else showFormError(result);})
          .catch(err=>{
            stopLoading();
            showFormError("Something went wrong.");
            console.error(err);
          });});});
  </script></body></html>
