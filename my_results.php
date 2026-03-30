<?php
// my_results.php - Defensive Version with Percentage Fix
require_once "db_connect.php";

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'participant') {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$full_name = htmlspecialchars($_SESSION["full_name"] ?? "Student");

// 2. Fetch completed attempts
// Note: If this crashes, one of these columns (like class_at_time) doesn't exist in your table.
$sql = "SELECT 
            a.score, 
            a.total_questions, 
            a.end_time, 
            a.contestant_number, 
            a.quiz_id,
            q.title 
        FROM attempts a 
        JOIN quizzes q ON a.quiz_id = q.quiz_id 
        WHERE a.user_id = ? AND a.end_time IS NOT NULL 
        ORDER BY a.end_time DESC";

$stmt = $conn->prepare($sql);

// --- THE FIX FOR THE FATAL ERROR ---
if (!$stmt) {
    // If the database query fails, this will tell you why instead of crashing
    die("<div class='alert alert-danger'>Database Error: " . $conn->error . ". <br>Check if columns like 'score' or 'total_questions' exist in your 'attempts' table.</div>");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results | CBT Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .results-header { background: #fff; padding: 2rem 0; border-bottom: 1px solid #dee2e6; margin-bottom: 2rem; }
        .table-container { background: #fff; border-radius: 12px; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05); overflow: hidden; border: 1px solid #e9ecef; }
        .score-badge { font-size: 0.95rem; padding: 6px 16px; border-radius: 50px; font-weight: 600; min-width: 80px; display: inline-block; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="participant_dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>CBT PORTAL
            </a>
            <a href="participant_dashboard.php" class="btn btn-primary btn-sm">Back to Dashboard</a>
        </div>
    </nav>

    <header class="results-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-1">Performance History 🏆</h2>
                    <p class="text-muted mb-0">Review your grades from completed assessments.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="p-3 bg-light rounded border">
                        <small class="text-muted d-block">Student</small>
                        <span class="fw-bold text-dark"><?php echo $full_name; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container mb-5">
        <div class="table-container">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4 py-3">Assessment Title</th>
                        <th class="text-center py-3">Completion Date</th>
                        <th class="text-center py-3">Score</th>
                        <th class="text-center py-3">Percentage / Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($results)): ?>
                        <?php foreach ($results as $res): 
                            // --- THE FIX FOR THE PERCENTAGE ---
                            // Ensure variables are treated as numbers
                            $score = (float)($res['score'] ?? 0);
                            $total = (int)($res['total_questions'] ?? 0);
                            
                            // Prevent division by zero
                            $percent = ($total > 0) ? ($score / $total) * 100 : 0;
                            
                            if ($percent >= 70) {
                                $bg_class = 'bg-success text-white';
                                $remark = 'Excellent';
                            } elseif ($percent >= 45) {
                                $bg_class = 'bg-warning text-dark';
                                $remark = 'Pass';
                            } else {
                                $bg_class = 'bg-danger text-white';
                                $remark = 'Fail';
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold d-block text-dark"><?php echo htmlspecialchars($res['title']); ?></span>
                                <small class="text-muted">No: <?php echo htmlspecialchars($res['contestant_number']); ?></small>
                            </td>
                            <td class="text-center text-muted small">
                                <?php echo date("M d, Y", strtotime($res['end_time'])); ?>
                            </td>
                            <td class="text-center">
                                <div class="fw-bold fs-5"><?php echo $score; ?></div>
                                <div class="text-muted small">out of <?php echo $total; ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge score-badge <?php echo $bg_class; ?> mb-1">
                                    <?php echo round($percent); ?>%
                                </span>
                                <br>
                                <small class="fw-bold text-uppercase" style="font-size: 0.65rem;"><?php echo $remark; ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <h4 class="text-secondary">No Results Found</h4>
                                <p class="text-muted">You haven't completed any quizzes yet.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>