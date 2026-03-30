<?php
// list_subjects.php - Teacher's View of Subjects
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: teacher_login.php");
    exit;
}

// Fetch subjects and count how many quizzes exist for each
$sql = "SELECT s.subject_name, s.subject_code, 
        (SELECT COUNT(*) FROM quizzes q WHERE q.subject_id = s.subject_id) as quiz_count 
        FROM subjects s ORDER BY s.subject_name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject List | Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        #sidebar { width: 260px; height: 100vh; position: fixed; background: #2c3e50; color: white; padding: 20px; }
        #main-content { margin-left: 260px; padding: 40px; }
        .nav-link { color: #bdc3c7; }
        .nav-link.active { background: #34495e; color: #fff; border-radius: 10px; }
        .subject-card { border: none; border-radius: 15px; background: #fff; transition: 0.3s; }
        .subject-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="text-center mb-4"><i class="fas fa-chalkboard-teacher fa-2x"></i><h5 class="fw-bold">Teacher Portal</h5></div>
        <nav class="nav flex-column">
            <a class="nav-link" href="teacher_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link active" href="list_subjects.php"><i class="fas fa-book-open me-2"></i> Subjects</a>
            <a class="nav-link" href="view_results.php"><i class="fas fa-chart-bar me-2"></i> Results</a>
            <a class="nav-link text-danger mt-4" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div id="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Assigned Subjects</h2>
            <button class="btn btn-primary rounded-pill px-4"><i class="fas fa-plus me-2"></i>Request New Subject</button>
        </div>

        <div class="row g-4">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card subject-card p-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($row['subject_name']); ?></h5>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['subject_code']); ?></span>
                            </div>
                            <i class="fas fa-book text-primary opacity-25 fa-2x"></i>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted"><?php echo $row['quiz_count']; ?> Quizzes Created</small>
                            <a href="manage_quizzes.php?subject=<?php echo urlencode($row['subject_name']); ?>" class="btn btn-sm btn-link text-decoration-none p-0">Manage →</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">No subjects have been assigned to your portal yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>