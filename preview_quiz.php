<?php
// preview_quiz.php - Teacher/Admin Accuracy Check
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security: Only Teachers/Admins
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;

if (!$quiz_id) {
    die("Invalid Quiz ID.");
}

// Fetch Quiz Meta
$stmt = $conn->prepare("SELECT title, subject_name, target_class FROM quizzes WHERE quiz_id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();

// Fetch All Questions
$stmt_q = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY question_id ASC");
$stmt_q->bind_param("i", $quiz_id);
$stmt_q->execute();
$questions = $stmt_q->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accuracy Review: <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .correct-opt { background-color: #d1e7dd; border: 1px solid #0f5132; font-weight: bold; }
        .q-card { border-left: 5px solid #6c757d; }
    </style>
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">Accuracy Review</h2>
                <p class="text-muted"><?php echo $quiz['subject_name']; ?> - <?php echo $quiz['target_class']; ?></p>
            </div>
            <a href="add_questions1.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-secondary">Back to Manager</a>
        </div>

        <?php foreach ($questions as $index => $q): ?>
            <div class="card mb-3 q-card shadow-sm">
                <div class="card-body">
                    <h5>Q<?php echo $index + 1; ?>: <?php echo htmlspecialchars($q['question_text']); ?></h5>
                    <div class="row mt-3">
                        <div class="col-6 mb-2 <?php echo ($q['correct_answer'] == 'A') ? 'correct-opt p-2 rounded' : ''; ?>">A) <?php echo htmlspecialchars($q['option_a']); ?></div>
                        <div class="col-6 mb-2 <?php echo ($q['correct_answer'] == 'B') ? 'correct-opt p-2 rounded' : ''; ?>">B) <?php echo htmlspecialchars($q['option_b']); ?></div>
                        <div class="col-6 mb-2 <?php echo ($q['correct_answer'] == 'C') ? 'correct-opt p-2 rounded' : ''; ?>">C) <?php echo htmlspecialchars($q['option_c']); ?></div>
                        <div class="col-6 mb-2 <?php echo ($q['correct_answer'] == 'D') ? 'correct-opt p-2 rounded' : ''; ?>">D) <?php echo htmlspecialchars($q['option_d']); ?></div>
                    </div>
                    <div class="mt-2 small text-success">
                        <strong>Correct Key: <?php echo $q['correct_answer']; ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>