<?php
session_start();
header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in as a student.']);
    exit;
}

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$studentId = intval($_SESSION['student_id']);

function sendJSON($data){
    echo json_encode($data);
    exit;
}

// ---------------- POST Actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['research_id'])) {
    $researchId = intval($_POST['research_id']);
    if (!$researchId) sendJSON(['success'=>false,'message'=>'Invalid research ID.']);

    $action = $_POST['action'] ?? '';

    // Favorite / Unfavorite
    if ($action === 'favorite') {
        $check = $conn->prepare("SELECT id FROM favorites WHERE student_id=? AND research_id=?");
        $check->bind_param("ii", $studentId, $researchId);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $del = $conn->prepare("DELETE FROM favorites WHERE student_id=? AND research_id=?");
            $del->bind_param("ii", $studentId, $researchId);
            $del->execute();
            sendJSON(['success'=>true,'message'=>'Removed from favorites.']);
        } else {
            $ins = $conn->prepare("INSERT INTO favorites(student_id,research_id,created_at) VALUES(?,?,NOW())");
            $ins->bind_param("ii", $studentId, $researchId);
            $ins->execute();
            sendJSON(['success'=>true,'message'=>'Added to favorites.']);
        }
    }

    // Request Access
if ($action === 'request_access') {
    $check = $conn->prepare("SELECT status, expire_at FROM research_access_requests WHERE student_id=? AND research_id=?");
    $check->bind_param("ii", $studentId, $researchId);
    $check->execute();
    $check->bind_result($status, $expireAt);
    $check->fetch();
    $check->close();

    $now = date('Y-m-d H:i:s');

    if ($status) {
        if ($status === 'pending') sendJSON([
            'success'=>false,
            'message'=>'You already requested access.',
            'new_status'=>'pending'
        ]);
        if ($status === 'approved' && $expireAt > $now) sendJSON([
            'success'=>false,
            'message'=>'You already have access.',
            'new_status'=>'approved'
        ]);

        // Resubmit
        $upd = $conn->prepare("UPDATE research_access_requests SET status='pending', requested_at=NOW(), expire_at=NULL WHERE student_id=? AND research_id=?");
        $upd->bind_param("ii", $studentId, $researchId);
        $upd->execute();
        sendJSON([
            'success'=>true,
            'message'=>'Request resubmitted.',
            'new_status'=>'pending'
        ]);
    }

    // New request
    $ins = $conn->prepare("INSERT INTO research_access_requests(student_id,research_id,status,requested_at) VALUES(?,?,'pending',NOW())");
    $ins->bind_param("ii", $studentId, $researchId);
    $ins->execute();
    sendJSON([
        'success'=>true,
        'message'=>'Access request submitted.',
        'new_status'=>'pending'
    ]);
}
}
// ---------------- GET: List Research ----------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {

    // Get student preferences (interests only)
    $prefStmt = $conn->prepare("SELECT interests FROM student_preferences WHERE student_id=?");
    $prefStmt->bind_param("i", $studentId);
    $prefStmt->execute();
    $prefStmt->bind_result($interests);
    $prefStmt->fetch();
    $prefStmt->close();

    // Prepare interests as array (assuming comma-separated category IDs)
    $interestIds = array_filter(array_map('trim', explode(',', $interests)));

    $category = $_GET['category'] ?? 'all';
    $search = $_GET['search'] ?? '';

    $sql = "SELECT r.id, r.title, r.author, r.abstract, r.faculty_id,
                   c.name AS category_name, f.fullname AS adviser_name,
                   (SELECT id FROM favorites WHERE student_id = ? AND research_id = r.id) AS is_favorite,
                   (SELECT status FROM research_access_requests WHERE student_id = ? AND research_id = r.id) AS access_status,
                   (SELECT expire_at FROM research_access_requests WHERE student_id = ? AND research_id = r.id) AS expire_at
            FROM research_documents r
            LEFT JOIN categories c ON r.category_id = c.id
            LEFT JOIN faculty f ON r.faculty_id = f.id";

    $params = [$studentId, $studentId, $studentId];
    $types = "iii";
    $conditions = [];

    // Filter by student interests (category IDs)
    if (!empty($interestIds)) {
        $placeholders = implode(',', array_fill(0, count($interestIds), '?'));
        $conditions[] = "r.category_id IN ($placeholders)";
        foreach ($interestIds as $id) {
            $params[] = $id;
            $types .= "i";
        }
    }

    // Optional category filter from GET
    if ($category !== 'all') {
        $conditions[] = "c.name = ?";
        $params[] = $category;
        $types .= "s";
    }

    // Optional search filter
    if ($search) {
        $conditions[] = "(r.title LIKE ? OR r.author LIKE ? OR r.abstract LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "sss";
    }

    if ($conditions) $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " ORDER BY r.title ASC";

    $stmt = $conn->prepare($sql);
    $bind = [];
    $bind[] = &$types;
    foreach ($params as $k => $v) $bind[] = &$params[$k];
    call_user_func_array([$stmt, "bind_param"], $bind);

    $stmt->execute();
    $result = $stmt->get_result();

    $html = '<div class="research-list">';
    $now = date('Y-m-d H:i:s');
    while ($row = $result->fetch_assoc()) {
        $favTxt = $row['is_favorite'] ? '❤️ Remove from Favorites' : '⭐ Add to Favorites';
        $reqTxt = 'Request Access'; $reqDisable = '';

        if ($row['access_status']) {
            switch ($row['access_status']) {
                case 'pending': $reqTxt = '⏳ Pending Approval'; $reqDisable = 'disabled'; break;
                case 'approved': if ($row['expire_at'] > $now) { $reqTxt = '✔ Access Approved'; $reqDisable = 'disabled'; } break;
                case 'rejected': $reqTxt = '❌ Rejected (Resubmit)'; break;
                case 'canceled': $reqTxt = '⚠ Canceled Request'; break;
            }
        }

        $html .= '<div class="research-card">';
        $html .= '<h4>' . htmlspecialchars($row['title']) . '</h4>';
        $html .= '<p><strong>Author:</strong> ' . htmlspecialchars($row['author']) . '</p>';
        $html .= '<p><strong>Category:</strong> ' . htmlspecialchars($row['category_name']) . '</p>';
        $html .= '<div class="card-actions">';
        $html .= '<button class="favoriteBtn" data-id="' . $row['id'] . '">' . $favTxt . '</button>';
        $html .= '<button class="requestBtn" data-id="' . $row['id'] . '" ' . $reqDisable . '>' . $reqTxt . '</button>';
        $html .= '<button class="showModalBtn" data-title="' . htmlspecialchars($row['title']) . '" data-author="' . htmlspecialchars($row['author']) . '" data-category="' . htmlspecialchars($row['category_name']) . '" data-adviser="' . htmlspecialchars($row['adviser_name']) . '" data-abstract="' . htmlspecialchars($row['abstract']) . '">Show Details</button>';
        $html .= '</div></div>';
    }
    $html .= '</div>';

    echo $html;
    exit;
}




echo json_encode(['success'=>false,'message'=>'Invalid API endpoint.']);
?>
