<?php
declare(strict_types=1);

// ✅ Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../vendor/autoload.php';
use Smalot\PdfParser\Parser;

$DB_HOST = 'sql207.infinityfree.com';
$DB_USER = 'if0_40577910';
$DB_PASS = 'CTURepo2025';
$DB_NAME = 'if0_40577910_repo_db';

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
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["research_file"]) && isset($_POST['action']) && $_POST['action'] === 'extract_abstract') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        sendJson(['ok'=>false,'message'=>"❌ Invalid session token."]);
    }

    $file = $_FILES["research_file"];
    if (!$file || !is_uploaded_file($file["tmp_name"])) sendJson(['ok'=>false,'message'=>"❌ No file uploaded."]);

    $text = extractPdfText($file["tmp_name"]);
    $abstract = null;

    if (preg_match('/\bABSTRACT\b\s*[:\-\s]*(.*?)(\bIntroduction\b|1\.)/si', $text, $matches)) {
        $abstract = trim($matches[1]);
    } elseif (preg_match('/\bABSTRACT\b\s*[:\-\s]*(.*?)(\bINTRODUCTION\b|1\.)/si', $text, $matches)) {
        $abstract = trim($matches[1]);
    }

    sendJson(['ok'=>true,'abstract'=>$abstract ?? '']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["research_file"])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        sendJson(['ok'=>false,'message'=>"❌ Invalid session token."]);
    }

    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$adminId) {
        sendJson(['ok'=>false,'message'=>"❌ Admin not logged in."]);
    }

    $title = cleanInput($_POST['title'] ?? '');
    $author = cleanInput($_POST['author'] ?? '');
    $category_id = (int)($_POST['category'] ?? 0);
    $keywords = cleanInput($_POST['keywords'] ?? '');
    $year_completed = (int)($_POST['year'] ?? 0);
    $adviser = cleanInput($_POST['adviser'] ?? '');
    $course = cleanInput($_POST['program'] ?? '');
    $department = cleanInput($_POST['department'] ?? '');
    $section = cleanInput($_POST['section'] ?? '');
    $approval_status = cleanInput($_POST['approval_status'] ?? '');
    $research_type = cleanInput($_POST['research_type'] ?? '');
    $research_design = cleanInput($_POST['research_design'] ?? '');
    $abstract = cleanInput($_POST['abstract'] ?? '');
    $uploaded_at = date("Y-m-d H:i:s");
    $submitted_date = (int)date("Y");
    $status = "Active";
    $file = $_FILES["research_file"] ?? null;
    $currentYear = (int)date("Y");
    if ($year_completed < 2023 || $year_completed > $currentYear) {
        sendJson(['ok'=>false,'message'=>"⚠️ Year Completed must be between 2023 and $currentYear."]);
    }

    if (!$file || !is_uploaded_file($file["tmp_name"])) sendJson(['ok'=>false,'message'=>"❌ No file uploaded."]);
    if ($file["size"] <= 0 || $file["size"] > 20*1024*1024) sendJson(['ok'=>false,'message'=>"❌ File size exceeds 20 MB."]);
    if ($category_id <= 0) sendJson(['ok'=>false,'message'=>"❌ Please select a valid category."]);

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

    $cleanName = preg_replace("/[^a-zA-Z0-9\-_.]/", "", $originalName);
    $uniqueFileName = time() . '_' . bin2hex(random_bytes(3)) . '_' . $cleanName;
    $targetFile = $yearFolder . $uniqueFileName;

    if (!move_uploaded_file($file["tmp_name"], $targetFile)) sendJson(['ok'=>false,'message'=>"❌ Failed to upload file."]);

    if (empty($abstract)) {
        $text = extractPdfText($targetFile);
        if (preg_match('/\bAbstract\b\s*[:\-\s]*(.*?)(\bIntroduction\b|1\.)/si', $text, $matches)) {
            $abstract = trim($matches[1]);
        } elseif (preg_match('/\bABSTRACT\b\s*[:\-\s]*(.*?)(\bINTRODUCTION\b|1\.)/si', $text, $matches)) {
            $abstract = trim($matches[1]);
        }
    }

    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) { logInternal($conn->connect_error); @unlink($targetFile); sendJson(['ok'=>false,'message'=>"❌ Server error."]); }

    $check = $conn->prepare("SELECT id FROM research_documents WHERE title=? AND author=? AND year_completed=?");
    $check->bind_param("ssi", $title, $author, $year_completed);
    $check->execute(); $check->store_result();
    if ($check->num_rows > 0) { $check->close(); $conn->close(); @unlink($targetFile); sendJson(['ok'=>false,'message'=>"❌ Document already exists."]); }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO research_documents
(title, author, category_id, keywords, abstract, adviser,
 course, department, section, research_type, approval_status,
 research_design, file_path, uploaded_at, year_completed, submitted_date, uploaded_by_admin, status)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$stmt->bind_param("ssisssssssssssiiis",
    $title,
    $author,
    $category_id,
    $keywords,
    $abstract,
    $adviser,
    $course,
    $department,
    $section,
    $research_type,
    $approval_status,
    $research_design,
    $targetFile,
    $uploaded_at,
    $year_completed,
    $submitted_date,
    $adminId,
    $status
);


    if ($stmt->execute()) {
        sendJson(['ok'=>true,'type'=>$research_type]);
    } else {
        logInternal($stmt->error); @unlink($targetFile);
        sendJson(['ok'=>false,'message'=>"❌ Could not save submission."]);
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upload Research Document</title>
<link rel="stylesheet" href="./AllStyles/uploadresearch.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
</head>
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
                <select name="category"required>
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
                       for ($year_completed = $current; $year_completed >= 2023; $year_completed--) {
                            echo "<option value='$year_completed'>$year_completed</option>";
                        }?>
                    <option value="other">Other...</option>
                </select>
                <input type="number" id="yearInput" name="year_other" min="2023" max="<?= date('Y') ?>" style="display:none;" placeholder="Enter year manually">
            </div>
        </div>
        <div class="form-group">
            <label>Adviser</label>
            <div class="input-icon">
                <i class="fa-solid fa-chalkboard-user"></i>
                <input type="text" name="adviser" placeholder="Enter adviser name" required>
            </div>
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
            <label>Approval Status</label>
            <div class="input-icon">
                <i class="fa-solid fa-check-to-slot"></i>
                <select name="approval_status" required>
                    <option value="">Select Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
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
      <div id="confirmLeft" style="display: flex; flex-direction: column; gap: 8px;"></div></div>
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

  fetch('upload-research.php', {
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
    <p><b>Adviser:</b> ${formData.get("adviser")}</p>
    <p><b>Program:</b> ${formData.get("program")}</p>
    <p><b>Department:</b> ${formData.get("department")}</p>
  `;
  confirmLeft.innerHTML = `
  <p><b>Section:</b> ${formData.get("section")}</p>
  <p><b>Research Type:</b> ${formData.get("research_type")}</p>
  <p><b>Approval Status:</b> ${formData.get("approval_status")}</p>
  <p><b>Research Design:</b> ${formData.get("research_design")}</p>
  <p><b>File:</b> ${formData.get("research_file").name}</p>
  <p><b>Abstract:</b></p>
  <div style="
      background-color: #f0f8ff;
      padding: 10px;
      border-left: 4px solid #007bff;
      border-radius: 5px;
      white-space: pre-wrap;
      max-height: 200px;
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
  fetch('upload-research.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(res => res.json())
    .then(data => {
      uploadingModal.style.display = "none";
      if (data.ok) {
        successMsg.innerText = `✅ You have successfully uploaded ${data.type} document.`;
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
</script>

</body>
</html>
