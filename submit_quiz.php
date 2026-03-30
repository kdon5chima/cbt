<?php
ob_start(); 
require_once "db_connect.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Validate Session Data
$submission = $_SESSION['final_submission'] ?? null;
if (!$submission) {
    header("Location: participant_dashboard.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$attempt_id = (int)$submission['attempt_id'];
$quiz_id = (int)$submission['quiz_id'];
$user_answers = $submission['answers']; 
$contestant_number = $submission['contestant_number'] ?? "0";
$final_score = 0;

try {
    // START TRANSACTION: Keeps the database safe
    $conn->begin_transaction();

    // 2. Get REAL total questions and Metadata
    $sql_meta = "SELECT z.academic_year, z.target_class, 
                (SELECT COUNT(*) FROM questions WHERE quiz_id = ?) as real_total 
                FROM quizzes z WHERE z.quiz_id = ?";
    $stmt_meta = $conn->prepare($sql_meta);
    $stmt_meta->bind_param("ii", $quiz_id, $quiz_id);
    $stmt_meta->execute();
    $meta = $stmt_meta->get_result()->fetch_assoc();
    $total_questions_count = $meta['real_total'];

    // 3. Fetch Correct Answers from DB
    $questions = [];
    $stmt_q = $conn->prepare("SELECT question_id, correct_answer FROM questions WHERE quiz_id = ?");
    $stmt_q->bind_param("i", $quiz_id);
    $stmt_q->execute();
    $res_q = $stmt_q->get_result();
    while ($row = $res_q->fetch_assoc()) {
        $questions[$row['question_id']] = $row['correct_answer'];
    }

    // 4. Process Answers & Bulk Insert Preparation
    // We reuse one prepared statement for performance
    $sql_ins = "INSERT INTO user_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)";
    $stmt_ins = $conn->prepare($sql_ins);

    foreach ($questions as $q_id => $correct_val) {
        $ans = $user_answers[$q_id] ?? ""; // Use empty string instead of null for consistency
        
        $clean_user_ans = strtoupper(trim($ans));
        $clean_db_ans = strtoupper(trim($correct_val));

        $is_correct = (!empty($clean_user_ans) && $clean_user_ans === $clean_db_ans) ? 1 : 0;
        
        if ($is_correct) { 
            $final_score++; 
        }

        $stmt_ins->bind_param("iisi", $attempt_id, $q_id, $ans, $is_correct);
        $stmt_ins->execute();
    }

    // 5. Update the Attempt Record
    $sql_upd = "UPDATE attempts SET 
                    score = ?, 
                    total_questions = ?, 
                    end_time = NOW(), 
                    contestant_number = ?, 
                    class_at_time = ?, 
                    academic_year = ? 
                WHERE attempt_id = ?";

    $stmt_upd = $conn->prepare($sql_upd);
    $stmt_upd->bind_param("iisssi", 
        $final_score, 
        $total_questions_count, 
        $contestant_number, 
        $meta['target_class'], 
        $meta['academic_year'], 
        $attempt_id
    );
    $stmt_upd->execute();

    // COMMIT: Save everything to DB permanently
    $conn->commit();

    // 6. Cleanup and Redirect
    unset($_SESSION['final_submission']);
    // Optional: Clear the quiz session too if it exists
    unset($_SESSION['current_quiz']); 
    
    ob_end_clean();
    header("Location: results.php?id=" . $attempt_id);
    exit;

} catch (Exception $e) {
    // ROLLBACK: If anything failed, undo all inserts to prevent partial data
    $conn->rollback();
    die("Submission Failed: " . $e->getMessage());
}