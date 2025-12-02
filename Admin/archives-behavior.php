<?php
header('Content-Type: application/json');
$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if($mysqli->connect_error){
    echo json_encode(['status'=>'error','message'=>'DB connection failed']);
    exit;
}

$id = $_POST['id'] ?? '';
if(!$id){
    echo json_encode(['status'=>'error','message'=>'No ID provided']);
    exit;
}

$stmt = $mysqli->prepare("UPDATE research_documents SET status='archived' WHERE id=?");
$stmt->bind_param('i', $id);
if($stmt->execute()){
    echo json_encode(['status'=>'success','message'=>'Document archived successfully']);
} else {
    echo json_encode(['status'=>'error','message'=>'Failed to archive document']);
}
$stmt->close();
$mysqli->close();
?>
