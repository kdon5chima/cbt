<?php
// view_score.php
require_once "db_connect.php";
 // Ensure session is started for security checks

// ---------------------------
// Security Check (Ensure participant is logged in)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'participant') {
    header("location: login.php");
    exit;
}
// ---------------------------

$user_id = $_SESSION["user_id"];
$username = htmlspecialchars($_SESSION["username"]);

// 1. Get the Quiz ID from the URL
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if ($quiz_id === 0) {
    // Redirect if no quiz ID is provided
    header("location: participant_dashboard.php");
    exit;
}

// Initialize an array to hold the scores
$scores = [];
$quiz_title = "Unknown Quiz";

// 2. Fetch Quiz Title and Details
$sql_quiz = "SELECT title, total_questions FROM quizzes WHERE quiz_id = ?";
if ($stmt_quiz = $conn->prepare($sql_quiz)) {
    $stmt_quiz->bind_param("i", $quiz_id);
    $stmt_quiz->execute();
    $result_quiz = $stmt_quiz->get_result();
    if ($result_quiz->num_rows === 1) {
        $quiz_data = $result_quiz->fetch_assoc();
        $quiz_title = htmlspecialchars($quiz_data['title']);
        $total_questions = $quiz_data['total_questions'];
    }
    $stmt_quiz->close();
}

// 3. Fetch Scores for Contestant 1 and Contestant 2
// We assume the 'attempts' table has columns: user_id, quiz_id, contestant_number, score, total_questions.
// NOTE: We fetch both contestant scores, if they exist for this user and quiz.
$sql_scores = "
    SELECT contestant_number, score
    FROM attempts
    WHERE user_id = ? AND quiz_id = ?
    ORDER BY contestant_number ASC
";

if ($stmt_scores = $conn->prepare($sql_scores)) {
    $stmt_scores->bind_param("ii", $user_id, $quiz_id);
    $stmt_scores->execute();
    $result_scores = $stmt_scores->get_result();

    while($row = $result_scores->fetch_assoc()) {
        $scores[$row['contestant_number']] = $row['score'];
    }
    $stmt_scores->close();
}

// Close the connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Score for <?php echo $quiz_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .score-card { border: none; border-radius: 1rem; transition: transform 0.3s; }
        .score-card-1 { border-left: 5px solid #007bff; }
        .score-card-2 { border-left: 5px solid #28a745; }
        .score-display { font-size: 3rem; font-weight: bold; }
    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid container">
            <a class="navbar-brand fw-bold" href="participant_dashboard.php"><i class="fas fa-home me-1"></i> Quiz Platform</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <main class="container mt-5">

        <h1 class="mb-4 text-primary"><i class="fas fa-poll me-2"></i> Quiz Results: <?php echo $quiz_title; ?></h1>
        <p class="lead text-muted">Total Questions: **<?php echo $total_questions ?? 'N/A'; ?>**</p>
        
        <div class="row g-4 mt-4">
            
            <?php for ($i = 1; $i <= 2; $i++): ?>
                <div class="col-md-6">
                    <div class="card score-card shadow-lg <?php echo 'score-card-' . $i; ?> h-100">
                        <div class="card-header bg-light border-bottom">
                            <h4 class="mb-0 fw-bold">
                                <?php if ($i == 1): ?>
                                    <i class="fas fa-user-tag text-primary me-2"></i> Contestant 1 Score
                                <?php else: ?>
                                    <i class="fas fa-user-tag text-success me-2"></i> Contestant 2 Score
                                <?php endif; ?>
                            </h4>
                        </div>
                        <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                            
                            <?php if (isset($scores[$i])): 
                                $score_value = $scores[$i];
                                // Determine text color based on score (optional)
                                $score_color = ($score_value / $total_questions) >= 0.7 ? 'text-success' : 'text-danger';
                                if (($score_value / $total_questions) < 0.5) $score_color = 'text-warning';
                            ?>
                                <p class="score-display <?php echo $score_color; ?> mb-2">
                                    <?php echo $score_value; ?> / <?php echo $total_questions ?? '?'; ?>
                                </p>
                                <p class="card-text fw-bold">
                                    <i class="fas fa-trophy me-1"></i> Total Correct Answers
                                </p>
                            <?php else: ?>
                                <p class="score-display text-muted">N/A</p>
                                <p class="card-text text-muted">This contestant did not submit a score for this quiz.</p>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            <?php endfor; ?>

        </div>
        
        <div class="text-center mt-5 mb-5">
            <a href="participant_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Return to Dashboard</a>
        </div>

    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>