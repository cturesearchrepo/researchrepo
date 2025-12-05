<?php
session_start();

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) die("Database connection failed.");

if (isset($_GET['file'])) {
    $decodedPath = base64_decode($_GET['file']);
    if (!$decodedPath) die("Invalid parameter");

    $userPath  = __DIR__ . '/' . $decodedPath;
    $adminPath = __DIR__ . '/../../../Admin/' . $decodedPath;

    if (file_exists($userPath)) $filePath = realpath($userPath);
    elseif (file_exists($adminPath)) $filePath = realpath($adminPath);
    else { http_response_code(404); die("File not found"); }

    if (ob_get_length()) ob_end_clean();

    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=\"" . basename($filePath) . "\"");
    header("Content-Length: " . filesize($filePath));
    flush();
    readfile($filePath);
    exit;
}

$studentId = intval($_SESSION['student_id'] ?? 0);
$requestId = intval($_GET['id'] ?? 0);
if (!$studentId || !$requestId) die("Unauthorized access.");

$sql = "
    SELECT rar.status, rar.expire_at, rd.file_path, rd.title, rd.id AS research_id
    FROM research_access_requests rar
    JOIN research_documents rd ON rar.research_id = rd.id
    WHERE rar.id = ? AND rar.student_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $requestId, $studentId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("Request not found or unauthorized.");
$data = $result->fetch_assoc();
$stmt->close();

if ($data['status'] !== 'approved') die("This request is not approved.");

$remaining = null;
$expired = false;
if ($data['expire_at']) {
    $remaining = ceil((strtotime($data['expire_at']) - time()) / 86400);
    if ($remaining <= 0) $expired = true;
}

$encodedPath = urlencode(base64_encode($data['file_path']));

if ($studentId) {
    $viewStmt = $conn->prepare("INSERT IGNORE INTO research_views (research_id, student_id) VALUES (?, ?)");
    $viewStmt->bind_param("ii", $data['research_id'], $studentId);
    $viewStmt->execute();
    $viewStmt->close();
}

$viewCountResult = $conn->query("SELECT COUNT(*) AS total_views FROM research_views WHERE research_id={$data['research_id']}");
$totalViews = $viewCountResult->fetch_assoc()['total_views'] ?? 0;

$ratingQuery = $conn->prepare("SELECT rating FROM ratings WHERE research_id=? AND student_id=?");
$ratingQuery->bind_param("ii", $data['research_id'], $studentId);
$ratingQuery->execute();
$ratingResult = $ratingQuery->get_result();
$userRating = 0;
if ($ratingResult->num_rows > 0) {
    $row = $ratingResult->fetch_assoc();
    $userRating = intval($row['rating']);
}
$ratingQuery->close();

$avgResult = $conn->query("SELECT AVG(rating) AS avg_rating FROM ratings WHERE research_id={$data['research_id']}");
$initialAvg = round($avgResult->fetch_assoc()['avg_rating'] ?? 0, 2);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Research - <?= htmlspecialchars($data['title']) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.7.107/pdf.min.js"></script>
<style>
body { margin:0; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f0f2f5; color:#333; user-select:none; }
header { padding:20px; background: linear-gradient(90deg,#8B0000,#a50000); color:white; font-size:24px; text-align:center; font-weight:600; box-shadow:0 4px 8px rgba(0,0,0,0.1); }
.info-panel { max-width:900px; margin:20px auto; padding:15px 20px; background:#fff; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.05); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; }
.info-panel span { font-weight:500; font-size:1rem; }
.rating-wrapper { display:flex; align-items:center; gap:10px; }
.rate-label { font-weight:500; font-size:1rem; color:#333; }
.rating { display:flex; gap:5px; }
.rating span { font-size:1.8rem; cursor:pointer; color:#ccc; transition:0.3s; }
.rating span:hover, .rating span.selected, .rating span.hover { color:#f1c40f; }
#avgRating { font-size: 0.95rem; color:#555; margin-left:10px; }
#pdfContainer { width:90%; max-width:1000px; margin:20px auto; padding:15px; background:#e8e8e8; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.08); text-align:center; }
canvas { margin:15px 0; border-radius:5px; box-shadow:0 3px 12px rgba(0,0,0,0.1); pointer-events:none; }
#toast { visibility:hidden; min-width:200px; background:#333; color:#fff; text-align:center; border-radius:5px; padding:10px; position:fixed; z-index:999; left:50%; bottom:30px; transform:translateX(-50%); opacity:0; transition:opacity 0.5s ease, visibility 0.5s; }
#toast.show { visibility:visible; opacity:1; }
footer { text-align:center; font-size:0.9rem; color:#666; padding:15px; margin-top:30px; }
</style>
</head>
<body>

<header><?= htmlspecialchars($data['title']) ?></header>

<div class="info-panel">
<?php if (!$expired): ?>
    <span id="countdown"></span>
    <span>Views: <?= $totalViews ?></span>
    <div class="rating-wrapper">
        <span class="rate-label">Rate this research:</span>
        <div class="rating" id="ratingStars">
            <span data-value="1">&#9733;</span>
            <span data-value="2">&#9733;</span>
            <span data-value="3">&#9733;</span>
            <span data-value="4">&#9733;</span>
            <span data-value="5">&#9733;</span>
        </div>
        <span id="avgRating">(Avg: <?= $initialAvg ?>)</span>
    </div>
<?php endif; ?>
</div>

<div id="pdfContainer"></div>
<div id="toast">Thank you for rating!</div>
<footer>Cebu Technological University Research Library | Secure PDF Viewer | Access Controlled</footer>

<script>
const url = "<?= $_SERVER['PHP_SELF'] ?>?file=<?= $encodedPath ?>";
const container = document.getElementById('pdfContainer');

pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.7.107/pdf.worker.min.js';

const userName = "<?= htmlspecialchars($_SESSION['student_name'] ?? 'CTU User') ?>";
const userId = "<?= htmlspecialchars($_SESSION['student_id'] ?? '0000') ?>";
const now = new Date().toLocaleString();
const watermarkText = `CTU Daanbantayan | ${userName} | ID: ${userId} | ${now}`;

pdfjsLib.getDocument(url).promise.then(pdf => {
    const totalPages = pdf.numPages;
    for (let i = 1; i <= totalPages; i++) {
        pdf.getPage(i).then(page => {
            const viewport = page.getViewport({ scale: 1.5 });
            const canvas = document.createElement('canvas');
            container.appendChild(canvas);
            const ctx = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            page.render({ canvasContext: ctx, viewport: viewport }).promise.then(() => {
                const text = "Cebu Technological University\nDaanbantayan Campus";
                ctx.save();
                ctx.globalAlpha = 0.12;
                ctx.fillStyle = "#000";
                ctx.textAlign = "center";
                ctx.textBaseline = "middle";
                ctx.font = "bold 50px Arial";
                ctx.translate(canvas.width / 2, canvas.height / 2);
                ctx.rotate(-Math.PI / 4);
                ctx.fillText(text, 0, 0);
                ctx.restore();

                for (let x = 50; x < canvas.width; x += 250) {
                    for (let y = 50; y < canvas.height; y += 150) {
                        ctx.save();
                        ctx.globalAlpha = 0.08;
                        ctx.translate(x, y);
                        ctx.rotate(-Math.PI / 4);
                        ctx.fillStyle = "#000";
                        ctx.font = "bold 16px Arial";
                        ctx.fillText(watermarkText, 0, 0);
                        ctx.restore();
                    }
                }

                const logo = new Image();
                logo.src = "../Photos/logoCtu.png";
                logo.onload = () => {
                    const logoSize = Math.min(canvas.width, canvas.height) / 1.5;
                    const logoX = (canvas.width - logoSize) / 2;
                    const logoY = (canvas.height - logoSize) / 2;
                    ctx.save();
                    ctx.globalAlpha = 0.08;
                    ctx.filter = "blur(1.5px)";
                    ctx.drawImage(logo, logoX, logoY, logoSize, logoSize);
                    ctx.restore();
                };
            });
        });
    }
});

<?php if (!$expired && $data['expire_at']): ?>
const expireAt = new Date("<?= date('Y-m-d H:i:s', strtotime($data['expire_at'])) ?>").getTime();
const countdownEl = document.getElementById("countdown");

function updateCountdown() {
    const now = new Date().getTime();
    const distance = expireAt - now;
    if (distance <= 0) {
        countdownEl.innerHTML = "<span style='color:#cc0000; font-weight:600;'>Access expired</span>";
        clearInterval(timer);
        return;
    }
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    countdownEl.textContent = `Access remaining: ${days}d ${hours}h ${minutes}m ${seconds}s`;
}
const timer = setInterval(updateCountdown, 1000);
updateCountdown();
<?php endif; ?>

const ratingStars = document.querySelectorAll('#ratingStars span');
let currentRating = <?= $userRating ?>;
highlightRating(currentRating);

ratingStars.forEach(star => {
    star.addEventListener('mouseenter', () => {
        ratingStars.forEach(s => s.classList.remove('hover'));
        for (let i = 0; i < star.dataset.value; i++) ratingStars[i].classList.add('hover');
    });

    star.addEventListener('mouseleave', () => {
        ratingStars.forEach(s => s.classList.remove('hover'));
        highlightRating(currentRating);
    });

    star.addEventListener('click', () => {
        const value = star.dataset.value;
        fetch('submit_rating.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `research_id=<?= $data['research_id'] ?>&rating=${value}`
        })
        .then(res => res.json())
        .then(resp => {
            if (resp.success) {
                currentRating = value;
                highlightRating(currentRating);
                document.getElementById("avgRating").textContent = `(Avg: ${resp.avg})`;
                showToast(resp.message);
            } else {
                showToast(resp.message);
            }
        })
        .catch(() => showToast('Failed to submit rating.'));
    });
});

function highlightRating(value){
    ratingStars.forEach(s => s.classList.remove('selected'));
    for (let i=0; i<value; i++) ratingStars[i].classList.add('selected');
}

function showToast(msg){
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(()=>toast.classList.remove('show'),2000);
}

document.addEventListener("contextmenu", e => e.preventDefault());
["copy","cut","paste"].forEach(evt => document.addEventListener(evt, e => e.preventDefault()));
document.addEventListener("keydown", e => {
  if ((e.ctrlKey && ["u","s","c","x","v"].includes(e.key.toLowerCase())) ||
      (e.ctrlKey && e.shiftKey && ["i","j"].includes(e.key.toLowerCase())) ||
      e.key === "F12") {
      e.preventDefault();
      e.stopPropagation();
  }
});

document.addEventListener("keydown", e => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "p") {
    e.preventDefault();
    alert("Printing is disabled for this document.");
  }
});

document.addEventListener("keyup", e => {
  if (e.key === "PrintScreen") {
    alert("Screenshots are disabled for this document.");
    navigator.clipboard.writeText("");
  }
});

window.addEventListener("blur", () => {
  document.body.style.filter = "blur(10px)";
});
window.addEventListener("focus", () => {
  document.body.style.filter = "none";
});
</script>
</body>
</html>
