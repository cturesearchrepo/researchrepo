<?php
header('Content-Type: application/json');

$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_errno) {
    echo json_encode(['status'=>'error','message'=>'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    $mysqli->begin_transaction();

    try {
        $stmt = $mysqli->prepare("
            UPDATE research_documents 
            SET prev_status = status, status = 'Archive' 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update status: " . $stmt->error);
        }

        $stmt->close();
        $mysqli->commit();

        echo json_encode(['status'=>'success','message'=>'Document archived successfully. Previous status saved.']);

    } catch (Exception $e) {
        $mysqli->rollback();
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

} else {
    echo json_encode(['status'=>'error','message'=>'Invalid request']);
}
?>
