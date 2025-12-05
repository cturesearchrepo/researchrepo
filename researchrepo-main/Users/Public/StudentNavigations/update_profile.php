<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['student_id'])){
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

$student_id = $_SESSION['student_id'];

$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if($conn->connect_error){
    echo json_encode(['status'=>'error','message'=>'DB connection failed']);
    exit;
}

$fullname = $_POST['fullname'] ?? '';
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$year_level = $_POST['year_level'] ?? '';

$profile_image = null;
if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0){
    $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','gif'];
    if(!in_array(strtolower($ext), $allowed)){
        echo json_encode(['status'=>'error','message'=>'Invalid image format']);
        exit;
    }

    $newFileName = 'student_'.$student_id.'_'.time().'.'.$ext;
    $uploadDir = '../uploads/students/';
    if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if(move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir.$newFileName)){
        $profile_image = $newFileName;
    } else {
        echo json_encode(['status'=>'error','message'=>'Failed to upload image']);
        exit;
    }
}

if($profile_image){
    $stmt = $conn->prepare("UPDATE students SET fullname=?, username=?, email=?, phone=?, year_level=?, profile_image=? WHERE student_id=?");
    $stmt->bind_param("ssssssi", $fullname, $username, $email, $phone, $year_level, $profile_image, $student_id);
} else {
    $stmt = $conn->prepare("UPDATE students SET fullname=?, username=?, email=?, phone=?, year_level=? WHERE student_id=?");
    $stmt->bind_param("sssssi", $fullname, $username, $email, $phone, $year_level, $student_id);
}

if($stmt->execute()){
    echo json_encode(['status'=>'success','message'=>'Profile updated successfully']);
} else {
    echo json_encode(['status'=>'error','message'=>'Failed to update profile']);
}

$stmt->close();
$conn->close();
?>
