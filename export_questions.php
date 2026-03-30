<?php
// export_questions.php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['admin', 'teacher'])) {
    die("Unauthorized access");
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;

if ($quiz_id) {
    // Fetch Quiz Title for the filename
    $q_stmt = $conn->prepare("SELECT title FROM quizzes WHERE quiz_id = ?");
    $q_stmt->bind_param("i", $quiz_id);
    $q_stmt->execute();
    $quiz_title = $q_stmt->get_result()->fetch_assoc()['title'] ?? 'quiz_export';
    $filename = str_replace(' ', '_', $quiz_title) . "_questions.csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    
    // Header Row
    fputcsv($output, array('Question Text', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer'));

    // Fetch Questions
    $stmt = $conn->prepare("SELECT question_text, option_a, option_b, option_c, option_d, correct_answer FROM questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}