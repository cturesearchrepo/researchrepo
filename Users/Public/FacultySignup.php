<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Faculty Registration</title>
<link rel="stylesheet" href="facultystyle.css" />
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
    <div class="heading">
      <div class="bar"></div>
      <h1><span>Faculty</span> Registration</h1>
    </div>
      <div style="display:none; margin:10px 0;">
        <img id="preview-img" src="Photos/logoCtu.png" alt="Profile Preview" style="max-width:120px; border-radius:50%;" />
      </div>
    <div id="formError" class="error-message-global"></div>
    <form class="registration-form" id="facultyForm" action="#" method="POST" enctype="multipart/form-data">
      <input type="text" id="fullname" name="fullname" placeholder="Full Name" required />
      <input type="text" id="username" name="username" placeholder="Username" required />
      <input type="text" id="facultyid" name="facultyid" placeholder="Faculty ID" required />
      <input type="email" id="email" name="email" placeholder="Email" required />
      <div id="emailError" class="error-message-global"></div>
      <input type="text" id="number" name="phone" placeholder="Phone Number" required />
      <select id="department" name="department" required>
        <option value="" disabled selected>Select Department</option>
        <option value="COTE">COTE</option>
        <option value="COED">COED</option>
      </select>
      <div class="password-wrapper">
        <input type="password" id="password" name="password" placeholder="Password" required oncopy="return false" onpaste="return false" oncut="return false" oncontextmenu="return false" />
        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
          <img src="./Photos/icons8-closed-eye-24.png" alt="Toggle Password"/>
        </button></div>
      <div class="password-criteria" id="passwordCriteria" style="display:none;">
        <p>Password must include:</p>
        <ul>
          <li id="length" class="criteria">🔴 At least 18 characters</li>
          <li id="uppercase" class="criteria">🔴 At least one uppercase letter</li>
          <li id="special" class="criteria">🔴 At least one special character (!@#$...)</li>
        </ul>
        <p id="strength-text">Password Strength: <span id="strength-level">Weak</span></p></div>
      <div class="password-wrapper">
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required/>
        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">
          <img src="./Photos/icons8-closed-eye-24.png" alt="Toggle Password"/>
        </button> </div>
      <input type="file" id="profile_image" name="profile" accept="image/*"/>
      <button type="submit" class="register-btn">
        <span class="btn-text">Register</span>
      </button>
    </form><br>
    <div class="login-link">
      Already registered? <a href="./UsersSignup.php">Go back</a>
    </div></div></div>
<script>
function togglePassword(fieldId, button) {
  const field = document.getElementById(fieldId);
  const img = button.querySelector("img");
  if (field.type === "password") {
    field.type = "text";
    img.src = "./Photos/icons8-eye-24.png";
  } else {
    field.type = "password";
    img.src = "./Photos/icons8-closed-eye-24.png";}}
document.addEventListener("DOMContentLoaded", function () {
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirm_password");
  const strengthLevel = document.getElementById("strength-level");
  const lengthCriteria = document.getElementById("length");
  const uppercaseCriteria = document.getElementById("uppercase");
  const specialCriteria = document.getElementById("special");
  const passwordCriteria = document.getElementById("passwordCriteria");
  const errorMessage = document.getElementById("formError");
  const emailInput = document.getElementById("email");
  const emailError = document.getElementById("emailError");
  const registerBtn = document.querySelector(".register-btn");
  const btnText = registerBtn.querySelector(".btn-text");
  let loadingInterval;
  function showFormError(message, isSuccess = false) {
    errorMessage.textContent = message;
    errorMessage.style.display = "block";
    errorMessage.style.color = isSuccess ? "green" : "red";}
  function startLoading() {
    registerBtn.disabled = true;
    let dotCount = 0;
    btnText.textContent = "Registering";
    loadingInterval = setInterval(() => {
      dotCount = (dotCount + 1) % 4;
      btnText.textContent = "Registering" + ".".repeat(dotCount);
    }, 500);}
  function stopLoading() {
    clearInterval(loadingInterval);
    registerBtn.disabled = false;
    btnText.textContent = "Register";}
  const profileInput = document.getElementById('profile_image');
  const previewImg = document.getElementById('preview-img');
  profileInput.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        previewImg.src = e.target.result;
        document.getElementById("preview-container").style.display = "block";};
      reader.readAsDataURL(file);
    } else {
      previewImg.src = "Photos/logoCtu.png";
      document.getElementById("preview-container").style.display = "none";
    } });
  passwordInput.addEventListener("focus", () => passwordCriteria.style.display = "block");
  passwordInput.addEventListener("blur", () => { setTimeout(() => { if (!passwordInput.matches(":focus")) passwordCriteria.style.display = "none"; }, 200); });
  passwordInput.addEventListener("input", function () {
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
    }
  });
  ["password","confirm_password"].forEach(id => {
    document.getElementById(id).addEventListener("keydown", e => {
      if ((e.ctrlKey || e.metaKey) && ["c","v","x","a"].includes(e.key.toLowerCase())) e.preventDefault();
    });
  });
  document.getElementById("facultyForm").addEventListener("submit", function (e) {
    e.preventDefault();
    errorMessage.style.display = "none";
    const formData = new FormData(this);
    startLoading();
    fetch("./register_faculty.php", { method: "POST", body: formData })
      .then(response => response.text())
      .then(result => {
        stopLoading();
        const lower = result.toLowerCase();
        if (lower.includes("success")) {
          showFormError(result, true);
          this.reset();
          previewImg.src = "Photos/logoCtu.png";
          strengthLevel.textContent = "Weak";
          strengthLevel.style.color = "red";
        } else {
          showFormError(result);
        }})
      .catch(error => {
        console.error("Error:", error);
        stopLoading();
        showFormError("Something went wrong while submitting.");
      });});});
</script></body></html>
