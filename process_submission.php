<?php
// process_submission.php - UPDATED VERSION WITH QUIZ TITLE
error_reporting(E_ALL); 
ini_set('display_errors', 1);
require_once "db_connect.php"; 

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["final_submission"])) {
    header("location: participant_dashboard.php");
    exit;
}

$sub = $_SESSION['final_submission'];
$attempt_id = (int)$sub['attempt_id'];
$quiz_id = (int)$sub['quiz_id'];
$user_answers = $sub['answers']; 

try {
    $conn->begin_transaction();

    // 1. Get the correct answer key column name
    $col_res = $conn->query("SHOW COLUMNS FROM questions LIKE 'correct_answer'");
    $ans_col = ($col_res->num_rows > 0) ? "correct_answer" : "correct_option";

    // 2. Fetch the Quiz Title for the results page (FIXES THE WARNING)
    $stmt_title = $conn->prepare("SELECT title FROM quizzes WHERE quiz_id = ?");
    $stmt_title->bind_param("i", $quiz_id);
    $stmt_title->execute();
    $quiz_data = $stmt_title->get_result()->fetch_assoc();
    $quiz_title = $quiz_data['title'] ?? "Quiz Result";

    // 3. Fetch questions to compare answers
    $stmt = $conn->prepare("SELECT question_id, $ans_col FROM questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_correct = 0;
    $total_q = $result->num_rows;

    // 4. Prepare the insert for user_answers
    $stmt_ans = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)");

    while ($row = $result->fetch_assoc()) {
        $q_id = $row['question_id'];
        $db_correct = trim(strtoupper($row[$ans_col])); 
        
        // Match the session answer to this question ID
        $student_choice = isset($user_answers[$q_id]) ? trim(strtoupper($user_answers[$q_id])) : "";

        // Determine if correct
        $is_right = ($student_choice !== "" && $student_choice === $db_correct) ? 1 : 0;
        if ($is_right === 1) { $total_correct++; }

        // SAVE TO DATABASE
        $stmt_ans->bind_param("iisi", $attempt_id, $q_id, $student_choice, $is_right);
        $stmt_ans->execute();
    }

    // 5. Update final score in attempts table
    $stmt_upd = $conn->prepare("UPDATE attempts SET score = ?, end_time = NOW() WHERE attempt_id = ?");
    $stmt_upd->bind_param("ii", $total_correct, $attempt_id);
    $stmt_upd->execute();

    $conn->commit();

    // 6. Store results for the display page (Now includes quiz_title)
    $_SESSION['latest_results'] = [
        'score' => $total_correct,
        'total' => $total_q,
        'percentage' => ($total_q > 0) ? round(($total_correct / $total_q) * 100) : 0,
        'quiz_title' => $quiz_title,
        'attempt_id' => $attempt_id
    ];

    unset($_SESSION['final_submission']);
    header("location: results.php");
    exit;

} catch (Exception $e) {
    if ($conn) { $conn->rollback(); }
    die("Submission Error: " . $e->getMessage());
}