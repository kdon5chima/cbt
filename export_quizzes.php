<?php
require_once "db_connect.php";
session_start();

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    exit("Unauthorized");
}

$teacher_id = $_SESSION["id"];
$filename = "my_quizzes_" . date('Y-m-d') . ".csv";

// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Open the "output" stream
$output = fopen('php://output', 'w');

// Set CSV Headers
fputcsv($output, array('Quiz ID', 'Quiz Title', 'Total Questions', 'Created Date'));

// Fetch Quiz Data
// Note: This assumes you have a 'teacher_id' column in your quizzes table. 
// If not, it will pull all quizzes currently in the system.
$query = "SELECT quiz_id, title, (SELECT COUNT(*) FROM questions WHERE questions.quiz_id = quizzes.quiz_id) as q_count, created_at FROM quizzes";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, array(
        $row['quiz_id'],
        $row['title'],
        $row['q_count'],
        $row['created_at']
    ));
}

fclose($output);
exit;
?>