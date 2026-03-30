<?php
// delete_question.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

// Check if question_id and quiz_id are provided
if (isset($_GET["question_id"]) && isset($_GET["quiz_id"])) {
    $question_id = (int)$_GET["question_id"];
    $quiz_id = (int)$_GET["quiz_id"];

    // Prepare a DELETE statement
    $sql = "DELETE FROM questions WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Bind the ID of the question to delete
        $stmt->bind_param("i", $question_id);
        
        if ($stmt->execute()) {
            // Success: Redirect back to the add_questions page for the same quiz
            $_SESSION['delete_message'] = "Question ID {$question_id} successfully deleted.";
            $_SESSION['delete_message_type'] = "success";
        } else {
            // Error
            $_SESSION['delete_message'] = "Error deleting question: " . $conn->error;
            $_SESSION['delete_message_type'] = "danger";
        }
        $stmt->close();
    } else {
        $_SESSION['delete_message'] = "Database preparation error.";
        $_SESSION['delete_message_type'] = "danger";
    }

    // Redirect back to the question management page
    header("location: add_questions.php?quiz_id=" . $quiz_id);
    exit;

} else {
    // If IDs are missing
    $_SESSION['delete_message'] = "Invalid request: Missing Question ID or Quiz ID.";
    $_SESSION['delete_message_type'] = "danger";
    header("location: admin_dashboard.php"); // Fallback to dashboard
    exit;
}
?>
