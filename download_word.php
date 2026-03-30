<?php
require_once "db_connect.php";
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') { die("Access Denied."); }

$attempt_id = (int)$_GET['attempt_id'];
$sql = "SELECT q.title, a.score, q.total_questions, u.full_name, a.contestant_number 
        FROM attempts a 
        JOIN quizzes q ON a.quiz_id = q.quiz_id 
        JOIN users u ON a.user_id = u.user_id WHERE a.attempt_id = $attempt_id";
$info = $conn->query($sql)->fetch_assoc();

header("Content-Type: application/vnd.ms-word");
header("content-disposition: attachment;filename=Report_" . $info['full_name'] . ".doc");
?>
<html>
<body>
    <h1 style="text-align:center">Quiz Report: <?php echo $info['title']; ?></h1>
    <p><strong>Student:</strong> <?php echo $info['full_name']; ?> (<?php echo $info['contestant_number']; ?>)</p>
    <p><strong>Score:</strong> <?php echo $info['score']; ?> / <?php echo $info['total_questions']; ?></p>
    <hr>
    <?php
    $res = $conn->query("SELECT q.question_text, ua.selected_option, q.correct_answer FROM user_answers ua JOIN questions q ON ua.question_id = q.question_id WHERE ua.attempt_id = $attempt_id");
    while($row = $res->fetch_assoc()) {
        echo "<p><strong>Question:</strong> {$row['question_text']}<br>";
        echo "Answer: {$row['selected_option']} | Correct: {$row['correct_answer']}</p>";
    }
    ?>
</body>
</html>