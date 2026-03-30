<?php
// review_answers.php - Internal Accuracy Check for Teachers/Admins
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. STRICT SECURITY: Only Teacher or Admin
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : null;
$user_id = $_SESSION["id"] ?? $_SESSION["user_id"];

if (!$attempt_id) {
    die("Error: No test run data found.");
}

// 2. Fetch Attempt Meta
$sql_attempt = "
    SELECT a.score, a.quiz_id, q.title, q.total_questions
    FROM attempts a
    JOIN quizzes q ON a.quiz_id = q.quiz_id
    WHERE a.attempt_id = ? AND a.user_id = ?";

$stmt = $conn->prepare($sql_attempt);
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$attempt_data = $stmt->get_result()->fetch_assoc();

if (!$attempt_data) {
    die("Error: This test run does not exist or you don't have permission to view it.");
}

$quiz_id = $attempt_data['quiz_id'];

// 3. Fetch Question Details + User Choices
$sql_review = "
    SELECT q.question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
           ua.selected_option, ua.is_correct, q.correct_answer
    FROM user_answers ua 
    JOIN questions q ON ua.question_id = q.question_id
    WHERE ua.attempt_id = ?";

$stmt_review = $conn->prepare($sql_review);
$stmt_review->bind_param("i", $attempt_id);
$stmt_review->execute();
$review_details = $stmt_review->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Run Review | CBT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; }
        .q-card { border-radius: 12px; border: none; }
        .wrong-key { border: 2px solid #dc3545; background-color: #fff8f8; }
        .correct-key { border: 2px solid #198754; background-color: #f8fff9; }
        .option-box { padding: 8px; border-radius: 6px; margin-bottom: 5px; font-size: 0.9rem; border: 1px solid #dee2e6; }
        .user-selected { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold"><i class="fas fa-vial text-warning me-2"></i>Test Run Accuracy Check</h2>
                    <p class="text-muted mb-0">Quiz: <strong><?php echo htmlspecialchars($attempt_data['title']); ?></strong></p>
                </div>
                <div class="text-end">
                    <div class="h4 mb-0"><?php echo $attempt_data['score']; ?> / <?php echo $attempt_data['total_questions']; ?></div>
                    <small class="text-muted">Accuracy Score</small>
                </div>
            </div>

            <?php if (empty($review_details)): ?>
                <div class="alert alert-danger">No answers were recorded during this test.</div>
            <?php else: ?>
                <?php $n = 1; foreach ($review_details as $q): ?>
                    <div class="card q-card shadow-sm mb-4 <?php echo $q['is_correct'] ? 'correct-key' : 'wrong-key'; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="fw-bold">Q<?php echo $n++; ?>: <?php echo htmlspecialchars($q['question_text']); ?></h5>
                                <a href="edit_question.php?id=<?php echo $q['question_id']; ?>&quiz_id=<?php echo $quiz_id; ?>" class="btn btn-sm btn-danger px-3">
                                    <i class="fas fa-edit me-1"></i> FIX ERROR
                                </a>
                            </div>
                            
                            <hr>

                            <div class="row mt-3">
                                <?php 
                                $opts = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']];
                                foreach ($opts as $key => $val):
                                    $is_correct_key = ($key == $q['correct_answer']);
                                    $is_user_choice = ($key == $q['selected_option']);
                                    $bg = $is_correct_key ? 'bg-success text-white border-success' : 'bg-white';
                                    if ($is_user_choice && !$is_correct_key) $bg = 'bg-danger text-white border-danger';
                                ?>
                                <div class="col-md-6">
                                    <div class="option-box <?php echo $bg; ?>">
                                        <strong><?php echo $key; ?>:</strong> <?php echo htmlspecialchars($val); ?>
                                        <?php if($is_user_choice) echo " <small>(Your Choice)</small>"; ?>
                                        <?php if($is_correct_key) echo " <i class='fas fa-check-circle float-end'></i>"; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!$q['is_correct']): ?>
                                <div class="mt-3 p-2 bg-white border border-danger rounded text-danger small">
                                    <i class="fas fa-exclamation-triangle me-2"></i> 
                                    <strong>Mistake Detected:</strong> You chose <b><?php echo $q['selected_option']; ?></b>, but the database says <b><?php echo $q['correct_answer']; ?></b> is correct.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="text-center mt-5">
                <a href="add_questions.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-dark btn-lg shadow">
                    <i class="fas fa-check-circle me-2"></i>Accuracy Confirmed - Return
                </a>
            </div>

        </div>
    </div>
</div>

</body>
</html>