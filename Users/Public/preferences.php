<?php
session_start();

// Determine user role and ID
if (isset($_SESSION['student_id']) && isset($_SESSION['faculty_id'])) {
    unset($_SESSION['student_id']); // Remove student if both exist
}

if (isset($_SESSION['student_id'])) {
    $role = 'Student';
    $user_id = (int) $_SESSION['student_id'];
} elseif (isset($_SESSION['faculty_id'])) {
    $role = 'Faculty';
    $user_id = (int) $_SESSION['faculty_id'];
} else {
    header('Location: login.php');
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'CentralizedResearchRepository_userdb');
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);

// Fetch student preferences
$stmt = $conn->prepare("
    SELECT department, interests, theme, notifications, privacy
    FROM student_preferences 
    WHERE student_id = ? 
    LIMIT 1
");
$stmt->bind_param('i', $user_id); 
$stmt->execute();
$stmt->bind_result($department, $interests, $theme, $notifications_json, $privacy_json);
$stmt->fetch();
$stmt->close();

// Decode JSON fields safely
$notifications = $notifications_json ? json_decode($notifications_json, true) : [];
$privacy       = $privacy_json ? json_decode($privacy_json, true) : [];

// Determine if preferences exist
$has_preferences = !empty($department) || !empty($interests) || !empty($theme);

// Helper function for <select> options
function selected_opt($value, $match) {
    return ($value === $match) ? "selected" : "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Preferences — CTU Repository</title>
<style>
:root {
    --ctu-maroon: #861f41;
    --ctu-gold: #f2c94c;
    --light-bg: #fafafa;
    --border: #e0e0e0;
}
body { margin: 0; background: var(--light-bg); font-family: "Segoe UI", sans-serif; }
.container { display: flex; min-height: 100vh; }

/* LEFT */
.left-side { flex: 1; background: url("Photos/Ctu.jpg") center/cover no-repeat; position: relative; display: flex; align-items: center; justify-content: center; }
.left-side::before { content: ""; position: absolute; inset: 0; background: rgba(134, 31, 65, 0.80); }
.left-side img { width: 230px; z-index: 10; }

/* RIGHT */
.right-side { flex: 1.2; background: white; padding: 40px 10px; overflow-y: auto; }

h1 { color: var(--ctu-maroon); text-align: center; margin-bottom: 20px; }

.card { background: white; padding: 20px 24px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.06); margin-bottom: 25px; }
.card h2 { font-size: 17px; color: var(--ctu-maroon); border-left: 4px solid var(--ctu-gold); padding-left: 10px; margin-bottom: 15px; }

/* Checkboxes layout */
.checkbox-group { display: grid; grid-template-columns: repeat(auto-fit,minmax(230px,1fr)); gap: 12px; }
.checkbox-group label { display: flex; gap: 8px; align-items: center; font-size: 14px; }

/* Radio buttons */
.radio-group { display: flex; gap: 20px; font-size: 15px; }

/* Save Button */
button { background: var(--ctu-gold); border: none; padding: 12px 150px; margin-left: 250px; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 10px; display: block; }
button:hover { background: #ddb33f; }

.note { color: #777; font-size: 13px; margin-top: 8px; display: block; }

/* Saved preference box */
.saved-box { background: #fff6d7; border: 1px solid var(--border); padding: 22px; border-radius: 12px; }
.tag { background: var(--ctu-maroon); color: white; padding: 5px 10px; border-radius: 6px; margin: 3px; display: inline-block; font-size: 12px; }

/* ALERT */
#alertBox { width: 100%; background: #e91919; color: white; padding: 12px; text-align: center; border-radius: 6px; margin-bottom: 15px; display:none; }
</style>
</head>
<body>

<div class="container">

    <!-- LEFT SIDE -->
    <div class="left-side">
        <img src="Photos/logoCtu.png" alt="CTU Logo">
    </div>

    <!-- RIGHT SIDE -->
    <div class="right-side">

        <div id="alertBox">You can select up to <b>5</b> research interests only.</div>

        <?php if ($has_preferences && !isset($_GET['edit'])): ?>
            <h1>Your Preferences</h1>
            <div class="saved-box">
                <p><strong>Department:</strong> <?= htmlspecialchars($department) ?></p>
                <p><strong>Interests:</strong><br>
                <?php foreach (explode(",", $interests) as $i): ?>
                    <span class="tag">Interest <?= $i ?></span>
                <?php endforeach; ?>
                </p>
                <p><strong>Theme:</strong> <?= htmlspecialchars($theme) ?></p>
                <br><form><button name="edit" value="1">Edit Preferences</button></form>
            </div>
        <?php else: ?>
            <h1><?= $has_preferences ? "Edit Your Preferences" : "Set Your Preferences" ?></h1>

            <form id="prefForm" method="POST" action="save_preferences.php">

                <!-- Department -->
                <div class="card">
                    <h2>Profile Information</h2>
                    <select name="department" style="width:100%;padding:10px;border-radius:6px;border:1px solid var(--border);">
                        <?php
                        $programs = [
                            'Bachelor of Technology and Livelihood Education',
                            'Bachelor of Secondary Education Math',
                            'Bachelor of Science in Industrial Education',
                            'BIT Major in Computer Technology',
                            'BIT Major in Electronics',
                            'Bachelor of Science in Fisheries Industry',
                            'Bachelor of Science in Information Technology',
                            'Bachelor of Elementary Education',
                            'Bachelor of Science in Hospitality Management'
                        ];
                        foreach ($programs as $p) {
                            echo "<option value='$p' ".selected_opt($department, $p).">$p</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Research Interests -->
                <div class="card">
                    <h2>Research Interest</h2>
                    <span class="note">Select up to 5 interests.</span>
                    <div class="checkbox-group" id="interestGroup">
                    <?php
                        $selected = $interests ? explode(',', $interests) : [];
                        $cats = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
                        while ($c = $cats->fetch_assoc()):
                            $chk = in_array($c['id'], $selected) ? "checked" : "";
                    ?>
                        <label><input type="checkbox" class="interestBox" name="interests[]" value="<?= $c['id'] ?>" <?= $chk ?>> <?= $c['name'] ?></label>
                    <?php endwhile; ?>
                    </div>
                </div>

                <!-- Theme -->
                <div class="card">
                    <h2>Appearance</h2>
                    <div class="radio-group">
                        <label><input type="radio" name="theme" value="Light" <?= $theme === 'Light' ? 'checked' : '' ?>> Light</label>
                        <label><input type="radio" name="theme" value="Dark" <?= $theme === 'Dark' ? 'checked' : '' ?>> Dark</label>
                    </div>
                </div>

                <button type="submit">Save Preferences</button>
            </form>
        <?php endif; ?>

    </div>
</div>

<script>
// Limit to 5 checkboxes
const maxSelect = 5;
const boxes = document.querySelectorAll(".interestBox");
const alertBox = document.getElementById("alertBox");
boxes.forEach(box => {
    box.addEventListener("change", () => {
        let checked = document.querySelectorAll(".interestBox:checked");
        if (checked.length > maxSelect) {
            box.checked = false;
            alertBox.style.display = "block";
            setTimeout(() => alertBox.style.display = "none", 2500);
        }
    });
});
</script>

</body>
</html>
