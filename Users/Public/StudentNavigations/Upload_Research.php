<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../../../vendor/autoload.php';
use Smalot\PdfParser\Parser;

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'CentralizedResearchRepository_userdb';

function cleanInput(?string $data): string {
    return htmlspecialchars(trim((string)$data ?? ''), ENT_QUOTES, 'UTF-8');
}
function logInternal(string $msg): void { error_log("[upload-research] " . $msg); }
function isAjax(): bool { return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'; }
function sendJson(array $data): void { header('Content-Type: application/json'); echo json_encode($data); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function extractPdfText(string $filePath): string {
    $parser = new Parser();
    try {
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        if (trim($text) === '' && shell_exec("which tesseract")) {
            $ocrText = shell_exec("tesseract " . escapeshellarg($filePath) . " stdout -l eng");
            if ($ocrText) $text = $ocrText;
        }
        return $text;
    } catch (\Exception $e) {
        logInternal("PDF parsing failed: " . $e->getMessage());
        return '';
    }}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["research_file"]) && ($_POST['action'] ?? '') === 'extract_abstract') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        sendJson(['ok'=>false,'message'=>"❌ Invalid session token."]);
    }

    $file = $_FILES["research_file"];
    if (!$file || !is_uploaded_file($file["tmp_name"])) sendJson(['ok'=>false,'message'=>"❌ No file uploaded."]);

    $text = extractPdfText($file["tmp_name"]);
    $abstract = '';
    if (preg_match('/\bABSTRACT\b\s*[:\-\s]*(.*?)(?=\bINTRODUCTION\b|^1\.|\Z)/si', $text, $matches)) {
        $abstract = trim($matches[1]);
    }
    sendJson(['ok'=>true,'abstract'=>$abstract ?? '']);
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["research_file"])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        sendJson(['ok'=>false,'message'=>"❌ Invalid session token."]);}

    $title = cleanInput($_POST['title'] ?? '');
    $author = cleanInput($_POST['author'] ?? '');
    $category_id = (int)($_POST['category'] ?? 0);
    $keywords = cleanInput($_POST['keywords'] ?? '');
    $year_completed = (int)($_POST['year'] ?? 0);
    $faculty_id = (int)($_POST['faculty_id'] ?? 0);
    $reviewer_ids = $_POST['reviewer_id'] ?? [];
    $course = cleanInput($_POST['program'] ?? '');
    $department = cleanInput($_POST['department'] ?? '');
    $section = cleanInput($_POST['section'] ?? '');
    $research_type = cleanInput($_POST['research_type'] ?? '');
    $research_design = cleanInput($_POST['research_design'] ?? '');
    $abstract = cleanInput($_POST['abstract'] ?? '');
    $uploaded_at = date("Y-m-d H:i:s");
    $submitted_date = (int)date("Y");
    $status = "Pending";
    $file = $_FILES["research_file"] ?? null;
    $currentYear = (int)date("Y");
    $student_id = isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0;
    if ($year_completed < 2023 || $year_completed > $currentYear) sendJson(['ok'=>false,'message'=>"⚠️ Year Completed must be between 2023 and $currentYear."]);
    if (!$file || !is_uploaded_file($file["tmp_name"])) sendJson(['ok'=>false,'message'=>"❌ No file uploaded."]);
    if ($file["size"] <= 0 || $file["size"] > 20*1024*1024) sendJson(['ok'=>false,'message'=>"❌ File size exceeds 20 MB."]);
    if ($category_id <= 0) sendJson(['ok'=>false,'message'=>"❌ Please select a valid category."]);
    if ($faculty_id <= 0) sendJson(['ok'=>false,'message'=>"❌ Please select a valid faculty adviser."]);
    if (empty($reviewer_ids) || !is_array($reviewer_ids)) sendJson(['ok'=>false,'message'=>"❌ Please select at least one reviewer."]);
    $targetDir = "uploads/";
    $originalName = basename($file["name"] ?? "");
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext !== "pdf") sendJson(['ok'=>false,'message'=>"❌ Only PDF files are allowed."]);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file["tmp_name"]) : null;
    if ($finfo) finfo_close($finfo);
    if ($mimeType !== "application/pdf") sendJson(['ok'=>false,'message'=>"❌ Invalid file type."]);

    $fh = fopen($file["tmp_name"], 'rb');
    $magic = $fh ? fread($fh, 4) : '';
    if ($fh) fclose($fh);
    if ($magic !== "%PDF") sendJson(['ok'=>false,'message'=>"❌ Not a valid PDF file."]);

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) sendJson(['ok'=>false,'message'=>"❌ Storage unavailable."]);
    $yearFolder = $targetDir . $year_completed . "/";
    if (!is_dir($yearFolder) && !mkdir($yearFolder, 0755, true)) sendJson(['ok'=>false,'message'=>"❌ Storage unavailable."]);

    $cleanName = strtolower(preg_replace("/[^a-zA-Z0-9\-_.]/", "", $originalName));
    $uniqueFileName = time() . '_' . bin2hex(random_bytes(3)) . '_' . $cleanName;
    $targetFile = $yearFolder . $uniqueFileName;
    if (!move_uploaded_file($file["tmp_name"], $targetFile)) sendJson(['ok'=>false,'message'=>"❌ Failed to upload file."]);

    if (empty($abstract)) {
        $text = extractPdfText($targetFile);
        if (preg_match('/\bAbstract\b[:\-\s]*(.*?)(?=\bIntroduction\b|\bCHAPTER\s+1\b|^1\.|\Z)/si', $text, $matches)) {
            $abstract = trim($matches[1]);
        }
    }

    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) { @unlink($targetFile); logInternal($conn->connect_error); sendJson(['ok'=>false,'message'=>"❌ Server error."]); }

    $check = $conn->prepare("SELECT id FROM research_documents WHERE title=? AND author=? AND year_completed=?");
    $check->bind_param("ssi", $title, $author, $year_completed);
    $check->execute(); $check->store_result();
    if ($check->num_rows > 0) { $check->close(); $conn->close(); @unlink($targetFile); sendJson(['ok'=>false,'message'=>"❌ Document already exists."]); }
    $check->close();

    foreach ($reviewer_ids as $rev_id) {
        $rev_id = (int)$rev_id;
        $res = $conn->query("SELECT id FROM faculty WHERE id=$rev_id LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            sendJson(['ok'=>false,'message'=>"❌ Invalid reviewer selected."]);
        }
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO research_documents 
        (title, author, category_id, keywords, abstract, faculty_id,
         course, department, section, research_type, research_design, 
         file_path, uploaded_at, year_completed, submitted_date, uploaded_by_student, status) 
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
         
        $stmt->bind_param("ssississsssssiiis",
            $title, $author, $category_id, $keywords, $abstract, $faculty_id,
            $course, $department, $section, $research_type, $research_design,
            $targetFile, $uploaded_at, $year_completed, $submitted_date, $student_id, $status
        );
        if (!$stmt->execute()) throw new \Exception($stmt->error);
        $research_id = $conn->insert_id;
        $stmt->close();

        $revStmt = $conn->prepare("INSERT INTO research_reviewers (research_id, reviewer_id) VALUES (?, ?)");
        foreach ($reviewer_ids as $rev_id) {
            $rev_id = (int)$rev_id;
            $revStmt->bind_param("ii", $research_id, $rev_id);
            if (!$revStmt->execute()) throw new \Exception($revStmt->error);
        }
        $revStmt->close();
        $conn->commit();
        $conn->close();
        sendJson(['ok'=>true,'type'=>$research_type]);
    } catch (\Exception $e) {
        $conn->rollback();
        $conn->close();
        @unlink($targetFile);
        logInternal("DB Transaction failed: " . $e->getMessage());
        sendJson(['ok'=>false,'message'=>"❌ Could not save submission."]);
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upload Research Document</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
</head>
<style>
.main-content {margin-left: 180px; padding: 0.5rem;    max-width: calc(100% - 170px); box-sizing: border-box;background: #f8f9fb;min-height: 100vh;}
.top-header h2 {margin: 0 0 25px 0;font-size: 28px;font-weight: 700;color: #8B0000; border-bottom: 2px solid #eee;padding-bottom: 12px;}
.upload-message {
    padding: 14px 22px;
    margin-bottom: 22px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
}
.upload-message.error {
    background-color: #ffe5e5;
    color: #d00000;
    border: 1px solid #d00000;
}

.upload-message.success {
    background-color: #e5ffe5;
    color: #007a00;
    border: 1px solid #007a00;
}

/* Form grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 22px 35px;
}

/* Single form group */
.form-group {
    display: flex;
    flex-direction: column;
    position: relative;
}

/* Label */
.form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

/* Input with icon wrapper */
.input-icon {
    position: relative;
}

.input-icon i {
    position: absolute;
    top: 50%;
    left: 12px;
    transform: translateY(-50%);
    color: #888;
    font-size: 16px;
}

.input-icon input,
.input-icon textarea { padding: 12px 12px 12px 38px; border-radius: 10px; border: 1px solid #ccc; outline: none; font-size: 14px; width: 80%;transition: all 0.3s ease;  background-color: #fafafa;}
.input-icon select{  padding: 12px 12px 12px 38px; border-radius: 10px; border: 1px solid #ccc;  outline: none;  font-size: 14px;  width: 92%; transition: all 0.3s ease; background-color: #fafafa;}
.input-icon input:focus,
.input-icon select:focus,
.input-icon textarea:focus {border-color: #8B0000;background-color: #fff;box-shadow: 0 0 5px rgba(139,0,0,0.3);}
.input-icon input[type="file"] {padding-left: 38px;}
button[type="submit"] {display: inline-flex;align-items: center;justify-content: center;margin-top: 25px;padding: 14px 28px;font-size: 16px;font-weight: 600;color: #fff;background: linear-gradient(135deg, #8B0000, #C00000);border: none;border-radius: 12px;cursor: pointer;transition: all 0.3s ease;}

button[type="submit"] i {
    margin-right: 8px;
}

button[type="submit"]:hover {
    background: linear-gradient(135deg, #C00000, #8B0000);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

@media screen and (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
.modal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  justify-content: center;
  align-items: center;
  z-index: 9999;
  animation: fadeIn 0.3s ease;
}

/* === Modal Box === */
.modal-content {background: rgba(255, 255, 255, 0.92);border-radius: 16px;padding: 25px 30px;width: 100%;max-width: 480px;text-align: left;box-shadow: 0 8px 24px rgba(0,0,0,0.25);animation: scaleIn 0.25s ease;font-family: "Segoe UI", sans-serif;}
.modal-content h3 { margin-bottom: 15px; font-size: 1.3rem; color: #222; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 10px;}
#confirmDetails p {margin: 8px 0; font-size: 0.95rem; color: #444;}
#confirmDetails b {  color: #111;}
.modal-actions {margin-top: 20px;display: flex;justify-content: flex-end;gap: 12px;}
.modal-actions button,
#closeSuccess {padding: 10px 18px;border: none;border-radius: 10px;cursor: pointer;font-size: 0.95rem;font-weight: 500;transition: all 0.25s ease;box-shadow: 0 3px 8px rgba(0,0,0,0.15);}
#editBtn { background: #f0f0f0;  color: #333;}
#editBtn:hover {  background: #e0e0e0;}
#proceedBtn { background: #4CAF50;  color: white;}
#proceedBtn:hover { background: #43a047;}
#closeSuccess { background: #2196F3; color: white; display: block; margin: 0 auto;}
#closeSuccess:hover { background: #1976d2;}
.loader { margin: 20px auto; width: 45px; height: 45px; border: 4px solid #e5e5e5; border-top: 4px solid #4CAF50; border-radius: 50%;animation: spin 1s linear infinite;}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }}
@keyframes scaleIn {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }}
@keyframes spin {100% { transform: rotate(360deg); }}
</style>
<body>
<div class="dashboard-container">
    <main class="main-content">
        <header class="top-header"><h2>Upload Research Document</h2></header>

        <form id="researchUploadForm" action="upload-research.php" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>
            <div class="form-grid">
                
        <div class="form-group">
            <label>Research Title</label>
            <div class="input-icon">
                <i class="fa-solid fa-book"></i>
                <input type="text" name="title" placeholder="Enter research title" required>
            </div>
        </div>
        <div class="form-group">
            <label>Author</label>
            <div class="input-icon">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="author" placeholder="Enter author name(s)" required>
            </div>
        </div>
        <div class="form-group">
            <label>Category</label>
            <div class="input-icon">
                <i class="fa-solid fa-tags"></i>
                <select name="category" id="category"required>
                    <option value="">Select Category</option>
                    <?php
                    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
                    if (!$conn->connect_error) {
                        $catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
                        while ($c = $catRes->fetch_assoc()) {
                            echo "<option value='{$c['id']}'>{$c['name']}</option>";
                        }
                        $conn->close();
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Keywords</label>
            <div class="input-icon">
                <i class="fa-solid fa-key"></i>
                <input type="text" name="keywords" placeholder="Enter keywords separated by commas">
            </div>
        </div>
        <div class="form-group">
            <label>Year Completed</label>
            <div class="input-icon">
                <i class="fa-solid fa-calendar"></i>
                <select id="year" name="year" required>
                    <?php 
                        $current = date("Y");
                       for ($y = $current; $y >= 2023; $y--) {
                            echo "<option value='$y'>$y</option>";
                        }?>
                    <option value="other">Other...</option>
                </select>
                <input type="number" id="yearInput" name="year_other" min="2018" max="<?= date('Y') ?>" style="display:none;" placeholder="Enter year manually">
            </div>
        </div>
        <div class="form-group">
    <label>Adviser</label>
    <div class="input-icon">
        <i class="fa-solid fa-chalkboard-user"></i>
       <select name="faculty_id" required>
            <option value="">Select Adviser</option>
            <?php
                    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
                    if (!$conn->connect_error) {
                        $catRes = $conn->query("SELECT id, fullname FROM faculty ORDER BY fullname ASC");
                        while ($c = $catRes->fetch_assoc()) {
                            echo "<option value='{$c['id']}'>{$c['fullname']}</option>";
                        }
                        $conn->close();
                    }
                    ?>
        </select>
          </div></div>
     <div class="form-group">
    <label>Panelist(s)</label>
    <div class="input-icon">
        <i class="fa-solid fa-user-check"></i>
        <select name="reviewer_id[]" id="reviewerSelect" multiple required>
            <?php
            $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
            if (!$conn->connect_error) {
                $res = $conn->query("SELECT id, fullname FROM faculty ORDER BY fullname ASC");
                while ($row = $res->fetch_assoc()) {
                    $id = (int)$row['id'];
                    $name = htmlspecialchars($row['fullname']);
                    echo "<option value=\"$id\">$name</option>";
                }
                $conn->close();
            }
            ?>
        </select>
    </div>
    <small style="color:#555;">Select multiple reviewers</small>
</div>
        <div class="form-group">
            <label>Program</label>
            <div class="input-icon">
                <i class="fa-solid fa-graduation-cap"></i>
                <select name="program" required> 
                    <option value="">Select Course</option> 
                    <option value="BTLED">BTLED</option> 
                    <option value="BSED Math">BSED Math</option> 
                    <option value="BSIE">BSIE</option> 
                    <option value="BIT Computer Tech">BIT Computer Tech</option> 
                    <option value="BIT Electronics">BIT Electronics</option> 
                    <option value="BSFI">BSFI</option> 
                    <option value="BSIT">BSIT</option> 
                    <option value="BEED">BEED</option> 
                    <option value="BSHM">BSHM</option> 
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Department</label>
            <div class="input-icon">
                <i class="fa-solid fa-building-columns"></i>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <option value="COED">COED</option>
                    <option value="COTE">COTE</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Section</label>
            <div class="input-icon">
                <i class="fa-solid fa-layer-group"></i>
                <input type="text" name="section" placeholder="Enter section (optional)">
            </div>
        </div>
        <div class="form-group">
            <label>Research Type</label>
            <div class="input-icon">
                <i class="fa-solid fa-file-lines"></i>
                <select name="research_type" required>
                    <option value="">Select Type</option>
                    <option value="Thesis">Thesis</option>
                    <option value="Capstone">Capstone</option>
                    <option value="Project">Project</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Research Design</label>
            <div class="input-icon">
                <i class="fa-solid fa-eye"></i> 
                <select name="research_design" required>
                    <option value="">Select Research Design</option>
                    <option value="Experimental">Experimental</option>
                    <option value="Descriptive">Descriptive</option>
                    <option value="Correlational">Correlational</option>
                    <option value="Exploratory">Exploratory</option>
                    <option value="Explanatory">Explanatory</option>
                </select>
            </div>
        </div>
                <div class="form-group">
                    <label>Upload PDF</label>
                    <div class="input-icon">
                        <i class="fa-solid fa-file-pdf"></i>
                        <input type="file" name="research_file" accept=".pdf" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Abstract</label>
                    <textarea name="abstract" id="abstractField" placeholder="Extracted abstract will appear here..." rows="6" required></textarea>
                </div>

            </div>
            <button type="submit"><i class="fa-solid fa-upload"></i> Upload</button>
        </form>
    </main>
</div>

<div id="confirmModal" class="modal">
  <div class="modal-content">
    <h3>Confirm Research Details</h3>
    <div id="confirmDetails" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
      <div id="confirmRight" style="display: flex; flex-direction: column; gap: 8px;"></div>
      <div id="confirmLeft" style="display: flex; flex-direction: column; gap: 8px;"></div>
    </div>
    <div class="modal-actions" style="margin-top: 20px; text-align: right;">
      <button id="editBtn">Edit</button>
      <button id="proceedBtn">Proceed</button>
    </div>
  </div>
</div>
<div id="uploadingModal" class="modal">
  <div class="modal-content">
    <h3>Uploading Research Document</h3>
    <div class="loader"></div>
    <p>Please wait...</p>
  </div>
</div>

<div id="successModal" class="modal">
  <div class="modal-content">
    <h3 id="successMsg">You have successfully uploaded the document.</h3>
    <button id="closeSuccess">Close</button>
  </div>
</div>
<script>
const form = document.getElementById('researchUploadForm');
const confirmModal = document.getElementById('confirmModal');
const uploadingModal = document.getElementById('uploadingModal');
const successModal = document.getElementById('successModal');
const confirmLeft = document.getElementById('confirmLeft');
const confirmRight = document.getElementById('confirmRight');
const successMsg = document.getElementById('successMsg');
const abstractField = document.getElementById('abstractField');
const fileInput = form.querySelector("input[name='research_file']");
fileInput.addEventListener('change', function () {
  const file = this.files[0];
  if (!file) return;

  if (file.type !== 'application/pdf') {
    alert("❌ Please upload a PDF file.");
    this.value = '';
    return;
  }

  abstractField.value = "⏳ Extracting abstract, please wait...";

  const formData = new FormData();
  formData.append("research_file", file);
  formData.append("csrf_token", "<?= $_SESSION['csrf_token'] ?>");
  formData.append("action", "extract_abstract");

  fetch('Upload_Research.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(res => res.json())
    .then(data => {
      if (data.ok) {
        abstractField.value = data.abstract || "❌ Abstract not found. You can enter manually.";
      } else {
        abstractField.value = "";
        alert(data.message || "❌ Could not extract abstract.");
      }
    })
    .catch(() => {
      abstractField.value = "";
      alert("❌ Network error while extracting abstract.");
    });
});

form.addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData(form);

  let yearValue;
if (yearSelect.value === 'other') {
    yearValue = yearInput.value;
} else {
    yearValue = yearSelect.value;
}
formData.set("year", yearValue);
  const year = parseInt(yearValue);
  const currentYear = new Date().getFullYear();

  if (isNaN(year) || year < 2023 || year > currentYear) {
    alert(`❌ Year must be between 2023 and ${currentYear}`);
    return;
  }

  let categoryText = form.querySelector("select[name='category'] option:checked").text;

  confirmRight.innerHTML = `
    <p><b>Title:</b> ${formData.get("title")}</p>
    <p><b>Author:</b> ${formData.get("author")}</p>
    <p><b>Category:</b> ${categoryText}</p>
    <p><b>Year:</b> ${year}</p>
    <p><b>Adviser:</b> ${form.querySelector("select[name='faculty_id'] option:checked").text}</p>
    <p><b>Program:</b> ${formData.get("program")}</p>
    <p><b>Department:</b> ${formData.get("department")}</p>
  `;

  confirmLeft.innerHTML = `
    <p><b>Section:</b> ${formData.get("section")}</p>
    <p><b>Research Type:</b> ${formData.get("research_type")}</p>
    <p><b>Research Design:</b> ${formData.get("research_design")}</p>
    <p><b>File:</b> ${formData.get("research_file").name}</p>
    <p><b>Abstract:</b></p>
    <div style="
        background-color: #f0f8ff; 
        padding: 10px; 
        border-left: 4px solid #007bff; 
        border-radius: 5px; 
        white-space: pre-wrap;
        max-height: 100px;
        overflow-y: auto;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 0.95rem;
    ">${formData.get("abstract")}</div>
  `;

  confirmModal.style.display = "flex";
});

document.getElementById('editBtn').onclick = () => {
  confirmModal.style.display = "none";
};

document.getElementById('proceedBtn').onclick = () => {
  confirmModal.style.display = "none";
  uploadingModal.style.display = "flex";
  const formData = new FormData(form);
  fetch('Upload_Research.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(res => res.json())
    .then(data => {
      uploadingModal.style.display = "none";
      if (data.ok) {
        successMsg.innerText = `✅ ${data.type} document uploaded. Your submission will be reviewed by panelist, adviser, and finally the admin.`;
        successModal.style.display = "flex";
        form.reset();
        abstractField.value = ""; 
      } else {
        alert(data.message || "❌ Upload failed.");
      }
    })
    .catch(() => {
      uploadingModal.style.display = "none";
      alert("❌ Network error.");
    });


    const selectedReviewers = Array.from(form.querySelector('#reviewerSelect').selectedOptions)
                               .map(opt => opt.text)
                               .join(", ");
confirmRight.innerHTML += `<p><b>Reviewer(s):</b> ${selectedReviewers || "None selected"}</p>`;
};

document.getElementById('closeSuccess').onclick = () => {
  successModal.style.display = "none";
};

const yearSelect = document.getElementById('year');
const yearInput = document.getElementById('yearInput');

yearSelect.addEventListener('change', function () {
  if (this.value === 'other') {
    this.style.display = 'none';
    yearInput.style.display = 'inline-block';
    yearInput.focus();
  }
});


document.addEventListener('DOMContentLoaded', () => {
    new Choices('#reviewerSelect', { removeItemButton: true });
});

</script>



</body>
</html>
