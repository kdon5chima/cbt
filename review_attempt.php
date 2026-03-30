<?php
// review_attempt.php - FULL FINAL VERSION
require_once "db_connect.php";
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Admin Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    die("Access Denied. Admin login required.");
}

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : null;

if (!$attempt_id) { 
    die("Error: No Attempt ID provided."); 
}

// 2. Fetch Quiz, Score, and Pupil Details (Contestant Number Removed)
$sql_info = "SELECT q.title, a.score, q.total_questions, u.full_name, u.username 
             FROM attempts a 
             LEFT JOIN quizzes q ON a.quiz_id = q.quiz_id 
             LEFT JOIN users u ON a.user_id = u.user_id
             WHERE a.attempt_id = ?";
$stmt_info = $conn->prepare($sql_info);
$stmt_info->bind_param("i", $attempt_id);
$stmt_info->execute();
$quiz_info = $stmt_info->get_result()->fetch_assoc();

if (!$quiz_info) { 
    die("Error: Attempt record not found."); 
}

// 3. Fetch Questions joined with the Pupil's Answers
$sql_review = "SELECT 
                q.question_text, 
                q.option_a, q.option_b, q.option_c, q.option_d, 
                q.correct_answer, 
                ua.selected_option,
                ua.is_correct
               FROM user_answers ua
               JOIN questions q ON ua.question_id = q.question_id
               WHERE ua.attempt_id = ?
               ORDER BY ua.answer_id ASC";

$stmt_review = $conn->prepare($sql_review);
$stmt_review->bind_param("i", $attempt_id);
$stmt_review->execute();
$results = $stmt_review->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review: <?php echo htmlspecialchars($quiz_info['full_name'] ?? $quiz_info['username']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-question { border-radius: 15px; border: none; margin-bottom: 25px; transition: 0.3s; }
        .correct-border { border-left: 10px solid #198754; }
        .wrong-border { border-left: 10px solid #dc3545; }
        .option-box { 
            padding: 15px; 
            border-radius: 10px; 
            border: 1px solid #dee2e6; 
            margin-bottom: 10px; 
            position: relative;
            background-color: #fff;
        }
        .correct-opt { background-color: #d1e7dd !important; border-color: #a3cfbb !important; color: #0a3622 !important; font-weight: bold; }
        .wrong-opt { background-color: #f8d7da !important; border-color: #f1aeb5 !important; color: #58151c !important; }
        .badge-choice { position: absolute; right: 15px; top: 15px; }
        
        @media print { 
            .no-print { display: none !important; } 
            body { background-color: white; padding: 0; }
            .container { max-width: 100%; width: 100%; }
            .card-question { border: 1px solid #eee; box-shadow: none !important; page-break-inside: avoid; }
            .display-6 { font-size: 24pt; }
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="card card-question shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-1 text-primary"><?php echo htmlspecialchars($quiz_info['title']); ?></h2>
                <p class="mb-0 fs-5">Pupil: <strong><?php echo htmlspecialchars($quiz_info['full_name'] ?? $quiz_info['username']); ?></strong></p>
                <p class="text-muted small no-print">Reviewing submitted answers and corrections.</p>
            </div>
            <div class="text-end">
                <div class="display-6 fw-bold text-dark mb-2"><?php echo $quiz_info['score']; ?> / <?php echo $quiz_info['total_questions']; ?></div>
                
                <div class="no-print">
                    <a href="download_word.php?attempt_id=<?php echo $attempt_id; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-word"></i> Word
                    </a>
                    <button onclick="window.print()" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <a href="view_all_attempts.php" class="btn btn-sm btn-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>

    <?php 
    if ($results->num_rows === 0): ?>
        <div class="alert alert-warning text-center">No answer details found for this attempt.</div>
    <?php else:
        $count = 1;
        while ($row = $results->fetch_assoc()): 
            $is_correct = ($row['is_correct'] == 1);
            $border_class = $is_correct ? 'correct-border' : 'wrong-border';
    ?>
        <div class="card card-question shadow-sm <?php echo $border_class; ?>">
            <div class="card-body">
                <h5 class="mb-4">
                    <span class="badge bg-light text-dark border me-2"><?php echo $count++; ?></span>
                    <?php echo htmlspecialchars($row['question_text']); ?>
                </h5>

                <div class="row g-3">
                    <?php 
                    $options = [
                        'A' => $row['option_a'],
                        'B' => $row['option_b'],
                        'C' => $row['option_c'],
                        'D' => $row['option_d']
                    ];

                    foreach ($options as $key => $text):
                        if (empty($text)) continue;
                        
                        $is_this_correct_key = ($key === strtoupper(trim($row['correct_answer'])));
                        $is_this_student_key = ($key === strtoupper(trim($row['selected_option'])));
                        
                        $box_class = "";
                        if ($is_this_correct_key) {
                            $box_class = "correct-opt";
                        } elseif ($is_this_student_key && !$is_correct) {
                            $box_class = "wrong-opt";
                        }
                    ?>
                        <div class="col-md-6">
                            <div class="option-box <?php echo $box_class; ?>">
                                <strong><?php echo $key; ?>.</strong> <?php echo htmlspecialchars($text); ?>
                                
                                <?php if ($is_this_student_key): ?>
                                    <span class="badge <?php echo $is_correct ? 'bg-success' : 'bg-danger'; ?> badge-choice">
                                        Pupil's Choice
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endwhile; endif; ?>

    <div class="text-center mt-5 text-muted d-none d-print-block">
        <hr>
        <p>End of Quiz Report - Generated on <?php echo date('Y-m-d H:i'); ?></p>
    </div>
</div>

</body>
</html>