<?php
// view_results.php
require_once "db_connect.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------
// Security Check - UPDATED: Allow both Admin and Teacher
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_type = $_SESSION["user_type"];
$user_id = $_SESSION["user_id"];

if ($user_type !== 'admin' && $user_type !== 'teacher') {
    header("location: index.php");
    exit;
}
// ---------------------------

$selected_quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$quizzes = [];
$leaderboard = [];
$current_quiz_title = "";

// 1. Fetch available quizzes (Filtered by teacher if not admin)
if ($user_type === 'admin') {
    $sql_select_quizzes = "SELECT quiz_id, title, total_questions FROM quizzes ORDER BY quiz_id DESC";
    $stmt_q = $conn->prepare($sql_select_quizzes);
} else {
    // Teachers only see their own quizzes
    $sql_select_quizzes = "SELECT quiz_id, title, total_questions FROM quizzes WHERE created_by = ? ORDER BY quiz_id DESC";
    $stmt_q = $conn->prepare($sql_select_quizzes);
    $stmt_q->bind_param("i", $user_id);
}

$stmt_q->execute();
$result_quizzes = $stmt_q->get_result();

if ($result_quizzes && $result_quizzes->num_rows > 0) {
    while($row = $result_quizzes->fetch_assoc()) {
        $quizzes[] = $row;
        if ($row['quiz_id'] == $selected_quiz_id) {
            $current_quiz_title = $row['title'];
        }
    }
}

// 2. Fetch Leaderboard results if a quiz is selected
if ($selected_quiz_id) {
    // Safety Check: If teacher, ensure they own the quiz they are trying to view
    $can_view = true;
    if ($user_type === 'teacher') {
        $check_ownership = $conn->prepare("SELECT quiz_id FROM quizzes WHERE quiz_id = ? AND created_by = ?");
        $check_ownership->bind_param("ii", $selected_quiz_id, $user_id);
        $check_ownership->execute();
        if ($check_ownership->get_result()->num_rows === 0) {
            $can_view = false;
        }
    }

    if ($can_view) {
        $sql_leaderboard = "
            SELECT 
                a.attempt_id,
                a.score AS total_correct,      
                q.total_questions,             
                a.end_time AS submission_time, 
                a.contestant_number, 
                u.username,
                u.full_name
            FROM attempts a
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN quizzes q ON a.quiz_id = q.quiz_id 
            WHERE a.quiz_id = ?
            ORDER BY a.score DESC, submission_time ASC"; 
            
        if ($stmt = $conn->prepare($sql_leaderboard)) {
            $stmt->bind_param("i", $selected_quiz_id);
            if ($stmt->execute()) {
                $result_leaderboard = $stmt->get_result();
                while($row = $result_leaderboard->fetch_assoc()) {
                    $leaderboard[] = $row;
                }
            }
            $stmt->close();
        }
    } else {
        echo "<script>alert('Unauthorized access.'); window.location.href='teacher_dashboard.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Leaderboard | CBT Platform</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { padding-top: 56px; background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        #sidebar { position: fixed; top: 56px; bottom: 0; left: 0; z-index: 1000; padding: 1rem; width: 250px; background-color: #2c3e50; color: white; }
        #main-content { margin-left: 250px; padding: 2rem; }
        @media (max-width: 768px) { #sidebar { position: static; width: 100%; height: auto; } #main-content { margin-left: 0; } }
        .nav-link { color: rgba(255, 255, 255, 0.75); }
        .nav-link.active { color: #fff; background-color: #0d6efd; border-radius: 5px; }
        .participant-row { transition: background 0.2s; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas <?php echo ($user_type == 'admin') ? 'fa-tools' : 'fa-chalkboard-teacher'; ?>"></i> 
                <?php echo ($user_type == 'admin') ? 'Admin Panel' : 'Teacher Panel'; ?>
            </a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </nav>

    <nav id="sidebar" class="collapse d-md-block">
        <div class="position-sticky">
            <h5 class="text-white mt-2 mb-3 border-bottom pb-2">Navigation</h5>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo ($user_type == 'admin') ? 'admin_dashboard.php' : 'teacher_dashboard.php'; ?>">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link active" href="view_results.php"><i class="fas fa-trophy me-2"></i> Results</a></li>
                <li class="nav-item mt-3 pt-2 border-top"><a class="nav-link text-danger" href="logout.php"><i class="fas fa-power-off me-2"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <main id="main-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h2 class="mb-0 h4 fw-bold text-dark"><i class="fas fa-chart-line me-2 text-primary"></i> Quiz Results</h2>
                </div>
                <div class="card-body p-4">

                    <div class="bg-light p-4 rounded mb-4 shadow-sm border">
                        <div class="row justify-content-center text-center">
                            <div class="col-md-8">
                                <label class="form-label fw-bold mb-3">Select a Quiz to view Performance</label>
                                
                                <div class="input-group mb-2">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-filter text-muted"></i></span>
                                    <input type="text" id="quizSearchInput" class="form-control border-start-0" placeholder="Type to filter quizzes...">
                                </div>

                                <form action="view_results.php" method="get" id="quizForm">
                                    <select name="quiz_id" id="quizSelect" class="form-select form-select-lg" required onchange="this.form.submit()">
                                        <option value="" disabled <?php if(!$selected_quiz_id) echo 'selected'; ?>>-- Choose Quiz --</option>
                                        <?php foreach ($quizzes as $quiz): ?>
                                            <option value="<?php echo $quiz['quiz_id']; ?>" 
                                                    data-title="<?php echo strtolower(htmlspecialchars($quiz['title'])); ?>"
                                                    <?php echo ($quiz['quiz_id'] == $selected_quiz_id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($quiz['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if ($selected_quiz_id): ?>
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                            <h3 class="h5 mb-0 text-muted">Showing results for: <span class="text-primary fw-bold"><?php echo htmlspecialchars($current_quiz_title); ?></span></h3>
                            
                            <div class="input-group" style="max-width: 300px;">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="participantSearch" class="form-control" placeholder="Search student...">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle border" id="resultsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Student Name</th>
                                        <th>Score</th>
                                        <th>Accuracy</th>
                                        <th>Submission Time</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1; 
                                    foreach ($leaderboard as $attempt): 
                                        $accuracy = ($attempt['total_questions'] > 0) ? round(($attempt['total_correct'] / $attempt['total_questions']) * 100) : 0;
                                        // Display Full Name if available, else username
                                        $student_name = !empty($attempt['full_name']) ? $attempt['full_name'] : ($attempt['username'] ?: 'Anonymous');
                                    ?>
                                    <tr class="participant-row" data-name="<?php echo strtolower(htmlspecialchars($student_name)); ?>">
                                        <td class="fw-bold">#<?php echo $rank++; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($student_name); ?></td>
                                        <td><span class="badge bg-success"><?php echo $attempt['total_correct']; ?> / <?php echo $attempt['total_questions']; ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center" style="width: 120px;">
                                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                    <div class="progress-bar" style="width: <?php echo $accuracy; ?>%"></div>
                                                </div>
                                                <small><?php echo $accuracy; ?>%</small>
                                            </div>
                                        </td>
                                        <td class="small text-muted"><?php echo date("M j, g:i a", strtotime($attempt['submission_time'])); ?></td>
                                        <td class="text-center">
                                            <a href="review_attempt.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Details
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($leaderboard)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No students have taken this quiz yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter Quiz Dropdown
            const quizSearch = document.getElementById('quizSearchInput');
            const quizSelect = document.getElementById('quizSelect');
            if(quizSearch) {
                quizSearch.addEventListener('input', function() {
                    const filter = this.value.toLowerCase();
                    const options = quizSelect.options;
                    for (let i = 1; i < options.length; i++) {
                        const title = options[i].getAttribute('data-title') || "";
                        options[i].style.display = title.includes(filter) ? "" : "none";
                    }
                });
            }

            // Filter Student Table
            const participantSearch = document.getElementById('participantSearch');
            if (participantSearch) {
                participantSearch.addEventListener('input', function() {
                    const filter = this.value.toLowerCase();
                    document.querySelectorAll('.participant-row').forEach(row => {
                        const name = row.getAttribute('data-name');
                        row.style.display = name.includes(filter) ? '' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>