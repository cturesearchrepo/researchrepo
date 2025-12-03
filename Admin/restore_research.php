<?php
header('Content-Type: application/json');

$mysqli = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($mysqli->connect_error) {
    die(json_encode(['status'=>'error','message'=>'DB connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    $mysqli->begin_transaction();

    try {
        // Get prev_status
        $stmt = $mysqli->prepare("SELECT prev_status FROM research_documents WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if($res->num_rows === 0){
            throw new Exception("Document not found");
        }

        $prev_status = $res->fetch_assoc()['prev_status'];
        if(empty($prev_status)){
            throw new Exception("Previous status is empty, cannot restore");
        }

        // Update with explicit value
        $stmt = $mysqli->prepare("
            UPDATE research_documents
            SET status = ?, prev_status = NULL
            WHERE id = ?
        ");
        $stmt->bind_param("si", $prev_status, $id);

        if(!$stmt->execute()){
            throw new Exception("Failed to restore status: " . $stmt->error);
        }

        $stmt->close();
        $mysqli->commit();

        echo json_encode(['status'=>'success','message'=>'Document restored successfully to its previous status!']);

    } catch(Exception $e){
        $mysqli->rollback();
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

} else {
    echo json_encode(['status'=>'error','message'=>'Invalid request']);
}
?>
