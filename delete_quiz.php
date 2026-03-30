<?php
// delete_quiz.php
session_start();
require_once "db_connect.php";

// ---------------------------
// Security Check
// 1. Must be logged in
// 2. Must be an admin
// 3. Must be a POST request
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin' || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: admin_dashboard.php");
    exit;
}
// ---------------------------

if (isset($_POST["quiz_id"]) && is_numeric($_POST["quiz_id"])) {
    $quiz_id = (int)$_POST["quiz_id"];
    
    // --- Start Transaction ---
    $conn->begin_transaction();
    $success = true;

    try {
        // --- 1. Get IDs of all questions in this quiz ---
        $question_ids = [];
        if ($stmt_qids = $conn->prepare("SELECT question_id FROM questions WHERE quiz_id = ?")) {
            $stmt_qids->bind_param("i", $quiz_id);
            $stmt_qids->execute();
            $result = $stmt_qids->get_result();
            while ($row = $result->fetch_assoc()) {
                $question_ids[] = $row['question_id'];
            }
            $stmt_qids->close();
        } else {
            throw new Exception("Error preparing question ID fetch: " . $conn->error);
        }

        // --- 2. Delete associated user_answers ---
        // This is necessary because user_answers links to question_id
        if (!empty($question_ids)) {
            // Create a comma-separated list of placeholders for the IN clause
            $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
            $sql_delete_answers = "DELETE FROM user_answers WHERE question_id IN ($placeholders)";
            
            if ($stmt_answers = $conn->prepare($sql_delete_answers)) {
                // 'i' repeated for each question ID
                $types = str_repeat('i', count($question_ids));
                // Bind parameters using call_user_func_array
                $stmt_answers->bind_param($types, ...$question_ids);
                $stmt_answers->execute();
                $stmt_answers->close();
            } else {
                 throw new Exception("Error preparing answers delete: " . $conn->error);
            }
        }

        // --- 3. Delete associated questions ---
        if ($stmt_questions = $conn->prepare("DELETE FROM questions WHERE quiz_id = ?")) {
            $stmt_questions->bind_param("i", $quiz_id);
            $stmt_questions->execute();
            $stmt_questions->close();
        } else {
            throw new Exception("Error preparing questions delete: " . $conn->error);
        }
        
        // --- 4. Delete associated attempts ---
        if ($stmt_attempts = $conn->prepare("DELETE FROM attempts WHERE quiz_id = ?")) {
            $stmt_attempts->bind_param("i", $quiz_id);
            $stmt_attempts->execute();
            $stmt_attempts->close();
        } else {
            throw new Exception("Error preparing attempts delete: " . $conn->error);
        }

        // --- 5. Delete the quiz itself ---
        if ($stmt_quiz = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ?")) {
            $stmt_quiz->bind_param("i", $quiz_id);
            $stmt_quiz->execute();
            $stmt_quiz->close();
        } else {
            throw new Exception("Error preparing quiz delete: " . $conn->error);
        }

        // If all statements ran without exceptions, commit the transaction
        $conn->commit();
        $_SESSION['message'] = "Quiz and all related data deleted successfully.";

    } catch (Exception $e) {
        // If any error occurred, rollback all changes
        $conn->rollback();
        $_SESSION['error'] = "Critical error during quiz deletion: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Invalid quiz ID provided for deletion.";
}

header("location: admin_dashboard.php");
exit;
?>