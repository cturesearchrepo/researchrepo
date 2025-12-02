<?php
session_start();

$mysqli = new mysqli("localhost", "root", "", "CentralizedResearchRepository_userdb");
if ($mysqli->connect_error) {
    echo "Error connecting to database.";
    exit;
}

if (!isset($_SESSION['faculty_id'])) {
    echo "Authentication error.";
    exit;
}

$facultyQuery = $mysqli->prepare("SELECT id FROM faculty WHERE faculty_id = ?");
$facultyQuery->bind_param("i", $_SESSION['faculty_id']);
$facultyQuery->execute();
$facultyResult = $facultyQuery->get_result();
$faculty = $facultyResult->fetch_assoc();
$facultyDbId = $faculty['id'];
$facultyQuery->close();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id']) && isset($_POST['type'])) {
    $researchId = intval($_POST['id']);
    $type = $_POST['type']; 

    if ($type === 'advisory') {
        $updateQuery = "UPDATE research_documents SET prev_status = 'Pending' WHERE id = ? AND faculty_id = ?";
        $stmt = $mysqli->prepare($updateQuery);
        $stmt->bind_param("ii", $researchId, $facultyDbId);
        
        if ($stmt->execute()) {
            echo "Success: Advicer status reverted to Pending.";
        } else {
            echo "Error: Could not revert Advicer status. " . $stmt->error;
        }
        $stmt->close();
        
    } elseif ($type === 'review') {
        $updateQuery = "UPDATE research_reviewers SET status = 'Pending', feedback = NULL WHERE research_id = ? AND reviewer_id = ?";
        $stmt = $mysqli->prepare($updateQuery);
        $stmt->bind_param("ii", $researchId, $facultyDbId);
        
        if ($stmt->execute()) {
            echo "Success: Panelist status reverted to Pending.";
        } else {
            echo "Error: Could not revert Panelist status. " . $stmt->error;
        }
        $stmt->close();
        
    } else {
        echo "Error: Invalid action type.";
    }
} else {
    echo "Error: Invalid request.";
}

$mysqli->close();
?>