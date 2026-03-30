<?php
// delete_question.php - Fixed Redirect Continuity
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"] ?? $_SESSION["user_id"];
$user_type = $_SESSION["user_type"];

// Validate Inputs
if (isset($_GET['id']) && isset($_GET['quiz_id'])) {
    $question_id = (int)$_GET['id'];
    $quiz_id = (int)$_GET['quiz_id']; // This is our key for continuity

    // 3. Ownership Verification
    if ($user_type === 'teacher') {
        $check_sql = "SELECT q.quiz_id FROM quizzes q 
                      JOIN questions qu ON q.quiz_id = qu.quiz_id 
                      WHERE qu.question_id = ? AND q.created_by = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $question_id, $user_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows === 0) {
            // Fails ownership? Send back to the QUIZ page with an error, not the dashboard
            header("Location: add_questions1.php?quiz_id=$quiz_id&status=danger&msg=" . urlencode("Unauthorized access."));
            exit;
        }
        $check_stmt->close();
    }

    // 4. Housekeeping: Delete associated image
    $sql_img = "SELECT image_path FROM questions WHERE question_id = ?";
    if ($stmt_img = $conn->prepare($sql_img)) {
        $stmt_img->bind_param("i", $question_id);
        $stmt_img->execute();
        $result = $stmt_img->get_result();
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['image_path']) && file_exists($row['image_path'])) {
                unlink($row['image_path']); 
            }
        }
        $stmt_img->close();
    }

    // 5. Delete the record
    $sql_del = "DELETE FROM questions WHERE question_id = ?";
    if ($stmt_del = $conn->prepare($sql_del)) {
        $stmt_del->bind_param("i", $question_id);
        if ($stmt_del->execute()) {
            // SUCCESS: Stays on the question page
            header("Location: add_questions1.php?quiz_id=$quiz_id&status=success&msg=" . urlencode("Question deleted successfully."));
            exit;
        } else {
            // FAILURE: Stays on the question page
            header("Location: add_questions1.php?quiz_id=$quiz_id&status=danger&msg=" . urlencode("Database error."));
            exit;
        }
    }
} else {
    // Only goes to dashboard if the script is accessed completely without IDs
    $redirect = ($user_type === 'admin') ? "admin_dashboard.php" : "teacher_dashboard.php";
    header("location: $redirect");
    exit;
}