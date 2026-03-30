<?php
require_once "db_connect.php";
session_start();

$user_id = $_SESSION['user_id'];

// 1. Get Overall Stats
$stats_sql = "SELECT 
                COUNT(*) as total_quizzes, 
                AVG(score / total_questions * 100) as avg_percentage,
                MAX(score / total_questions * 100) as highest_score
              FROM attempts WHERE user_id = ?";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// 2. Get Performance Trend (Last 5 Quizzes)
$trend_sql = "SELECT score, total_questions, end_time 
              FROM attempts WHERE user_id = ? 
              ORDER BY end_time DESC LIMIT 10";
$stmt_t = $conn->prepare($trend_sql);
$stmt_t->bind_param("i", $user_id);
$stmt_t->execute();
$trend_data = $stmt_t->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-primary text-white">
            <small>Average Score</small>
            <h2 class="fw-bold"><?php echo number_format($stats['avg_percentage'], 1); ?>%</h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-success text-white">
            <small>Quizzes Completed</small>
            <h2 class="fw-bold"><?php echo $stats['total_quizzes']; ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-dark text-white">
            <small>Personal Best</small>
            <h2 class="fw-bold"><?php echo number_format($stats['highest_score'], 1); ?>%</h2>
        </div>
    </div>
</div>