<?php
// 1. DATABASE & SESSION INITIALIZATION
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. SECURITY CHECK
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

// 3. DATA AGGREGATION (THE ANALYTICS ENGINE)

// A. High-Level Summary Stats
$total_attempts = $conn->query("SELECT COUNT(*) as count FROM attempts")->fetch_assoc()['count'] ?? 0;

$avg_score_query = $conn->query("SELECT AVG(score / total_questions * 100) as avg FROM attempts");
$avg_score = $avg_score_query->fetch_assoc()['avg'] ?? 0;

$pass_rate_res = $conn->query("SELECT 
    (COUNT(CASE WHEN (score/total_questions) >= 0.5 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)) as rate 
    FROM attempts")->fetch_assoc();
$pass_rate = $pass_rate_res['rate'] ?? 0;

// B. Leaderboard (Top 10 Students)
$leaderboard_sql = "SELECT u.full_name, u.student_class, COUNT(a.attempt_id) as quizzes_taken, 
                    AVG(a.score / a.total_questions * 100) as overall_avg
                    FROM users u
                    JOIN attempts a ON u.id = a.user_id
                    WHERE u.user_type = 'student'
                    GROUP BY u.id 
                    ORDER BY overall_avg DESC LIMIT 10";
$leaderboard = $conn->query($leaderboard_sql);

// C. Class-wise Performance Breakdown
$class_sql = "SELECT u.student_class, AVG(a.score / a.total_questions * 100) as class_avg
              FROM users u
              JOIN attempts a ON u.id = a.user_id
              GROUP BY u.student_class
              ORDER BY class_avg DESC";
$class_performance = $conn->query($class_sql);

// D. Problematic Questions (Difficulty Index)
$tricky_sql = "SELECT q.question_text, 
               (SUM(ua.is_correct) / NULLIF(COUNT(ua.answer_id), 0) * 100) as success_rate
               FROM questions q
               JOIN user_answers ua ON q.question_id = ua.question_id
               GROUP BY q.question_id
               ORDER BY success_rate ASC LIMIT 5";
$tricky_questions = $conn->query($tricky_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Insights | CBT Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; color: #1e293b; }
        .sidebar { height: 100vh; background: #0f172a; color: white; position: fixed; width: 260px; z-index: 1000; }
        .main-content { margin-left: 260px; padding: 40px; }
        .stat-card { border: none; border-radius: 12px; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-3px); }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .progress { height: 7px; border-radius: 10px; background-color: #e2e8f0; }
        .table thead th { background-color: #f8fafc; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #64748b; }
        @media (max-width: 992px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <div class="sidebar d-flex flex-column p-4 shadow">
        <div class="mb-4">
            <h5 class="fw-bold text-info"><i class="fas fa-chart-pie me-2"></i>CBT ANALYTICS</h5>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="admin_dashboard.php" class="nav-link text-white-50"><i class="fas fa-home me-2"></i> Dashboard</a></li>
            <li class="nav-item"><a href="manage_users.php" class="nav-link text-white-50"><i class="fas fa-users me-2"></i> Students</a></li>
            <li class="nav-item"><a href="admin_analytics.php" class="nav-link active bg-info text-dark fw-bold"><i class="fas fa-chart-line me-2"></i> Reports</a></li>
        </ul>
        <hr>
        <div class="mt-auto">
            <a href="logout.php" class="text-danger text-decoration-none small fw-bold"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0">School Performance Overview</h3>
                <p class="text-muted">Academic data as of <?php echo date('F j, Y'); ?></p>
            </div>
            <button onclick="window.print()" class="btn btn-white border shadow-sm fw-bold">
                <i class="fas fa-print me-2 text-primary"></i> Print Report
            </button>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card stat-card shadow-sm bg-white p-4 border-start border-primary border-5">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small fw-bold mb-1">Global Average Score</p>
                            <h2 class="fw-bold mb-0"><?php echo number_format($avg_score, 1); ?>%</h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                            <i class="fas fa-percentage fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm bg-white p-4 border-start border-success border-5">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small fw-bold mb-1">Pass Rate (>=50%)</p>
                            <h2 class="fw-bold mb-0"><?php echo number_format($pass_rate, 1); ?>%</h2>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
                            <i class="fas fa-check-double fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm bg-white p-4 border-start border-dark border-5">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small fw-bold mb-1">Total Exams Taken</p>
                            <h2 class="fw-bold mb-0"><?php echo $total_attempts; ?></h2>
                        </div>
                        <div class="bg-dark bg-opacity-10 p-3 rounded-circle text-dark">
                            <i class="fas fa-file-alt fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-custom bg-white h-100">
                    <div class="card-header bg-transparent border-0 py-4 px-4">
                        <h5 class="fw-bold mb-0"><i class="fas fa-medal text-warning me-2"></i>Top Student Ranking</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Rank</th>
                                    <th>Full Name</th>
                                    <th>Class</th>
                                    <th>Attempts</th>
                                    <th class="text-end pe-4">Overall Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                if($leaderboard && $leaderboard->num_rows > 0):
                                    while($row = $leaderboard->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?php echo $rank++; ?></td>
                                        <td><span class="fw-bold"><?php echo htmlspecialchars($row['full_name']); ?></span></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['student_class']); ?></span></td>
                                        <td><?php echo $row['quizzes_taken']; ?></td>
                                        <td class="pe-4">
                                            <div class="d-flex align-items-center justify-content-end">
                                                <span class="me-3 fw-bold text-primary"><?php echo number_format($row['overall_avg'], 1); ?>%</span>
                                                <div class="progress" style="width: 80px;">
                                                    <div class="progress-bar bg-primary" style="width: <?php echo $row['overall_avg']; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">No student ranking data found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-custom bg-white mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0">Class Comparison</h6>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <?php 
                        if ($class_performance && $class_performance->num_rows > 0):
                            while($c = $class_performance->fetch_assoc()): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small fw-bold"><?php echo htmlspecialchars($c['student_class']); ?></span>
                                    <span class="small text-muted"><?php echo number_format($c['class_avg'], 1); ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: <?php echo $c['class_avg']; ?>%"></div>
                                </div>
                            </div>
                        <?php endwhile; 
                        else: ?>
                            <p class="text-center text-muted py-3 small">No class data available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card card-custom bg-white">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Tricky Questions</h6>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <p class="text-muted mb-3" style="font-size: 0.8rem;">Questions with the lowest global pass rates.</p>
                        <?php 
                        if ($tricky_questions && $tricky_questions->num_rows > 0):
                            while($q = $tricky_questions->fetch_assoc()): ?>
                            <div class="p-3 bg-light rounded-3 mb-2 border-start border-danger border-3">
                                <p class="small mb-1 text-truncate fw-bold" title="<?php echo htmlspecialchars($q['question_text']); ?>">
                                    <?php echo htmlspecialchars($q['question_text']); ?>
                                </p>
                                <span class="badge bg-danger bg-opacity-10 text-danger small">
                                    Pass Rate: <?php echo number_format($q['success_rate'], 0); ?>%
                                </span>
                            </div>
                        <?php endwhile;
                        else: ?>
                             <p class="text-center text-muted py-3 small">No question analytics available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>