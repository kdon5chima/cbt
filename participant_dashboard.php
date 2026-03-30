<?php
// participant_dashboard.php - Gray-out Completed & Searchable Grid
require_once "db_connect.php";

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'participant') {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$full_name = htmlspecialchars($_SESSION["full_name"] ?? "Student");

// --- 1. GET CLASS & SESSION ---
if (!isset($_SESSION["class_year"]) || empty($_SESSION["class_year"])) {
    $stmt_u = $conn->prepare("SELECT class_year FROM users WHERE user_id = ?");
    $stmt_u->bind_param("i", $user_id);
    $stmt_u->execute();
    $user_data = $stmt_u->get_result()->fetch_assoc();
    $class_year = $user_data['class_year'] ?? "Not Assigned";
    $_SESSION["class_year"] = $class_year;
    $stmt_u->close();
} else {
    $class_year = $_SESSION["class_year"];
}

$year_setting_res = $conn->query("SELECT setting_value FROM academic_settings WHERE setting_key = 'current_session' LIMIT 1");
$current_session = ($year_setting_res->num_rows > 0) ? $year_setting_res->fetch_assoc()['setting_value'] : "2025/2026";

// --- 2. GET ATTEMPT STATUS ---
$finished_ids = [];
$ongoing_attempts = [];
$res_a = $conn->query("SELECT quiz_id, end_time, attempt_id FROM attempts WHERE user_id = '$user_id'");
while($row = $res_a->fetch_assoc()) {
    if ($row['end_time'] === null) { 
        $ongoing_attempts[$row['quiz_id']] = $row['attempt_id']; 
    } else { 
        $finished_ids[] = $row['quiz_id']; 
    }
}

// --- 3. FETCH ALL TERM EXAMS (Year 5 & 6 Merged) ---
$where_clause = ($class_year === 'Year 5' || $class_year === 'Year 6') 
                ? "(target_class = 'Year 5' OR target_class = 'Year 6')" 
                : "target_class = '$class_year'";

$sql_q = "SELECT * FROM quizzes 
          WHERE $where_clause 
          AND academic_year = '$current_session' 
          AND exam_type = 'Term Examination' 
          AND is_active = 1 
          ORDER BY title ASC";
$res_q = $conn->query($sql_q);
$all_quizzes = $res_q->fetch_all(MYSQLI_ASSOC);

$remaining_count = count($all_quizzes) - count($finished_ids);
// Use LOWER() in SQL to ensure 'Year 5' matches 'year 5'
$where_clause = ($class_year === 'Year 5' || $class_year === 'Year 6') 
                ? "(LOWER(target_class) = 'year 5' OR LOWER(target_class) = 'year 6')" 
                : "LOWER(target_class) = LOWER('$class_year')";

$sql_q = "SELECT * FROM quizzes 
          WHERE $where_clause 
          AND academic_year = '$current_session' 
          AND exam_type = 'Term Examination' 
          AND is_active = 1 
          ORDER BY title ASC";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Term Exam Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .search-container { max-width: 500px; margin: 0 auto 30px; }
        .search-input { border-radius: 50px; padding: 12px 25px; border: 2px solid #e2e8f0; font-size: 1.1rem; }
        
        /* Card Styles */
        .exam-card { border: none; border-radius: 20px; transition: 0.3s; background: white; height: 100%; border-bottom: 4px solid #3b82f6; }
        .exam-card.completed { 
            background-color: #f8fafc; 
            opacity: 0.6; 
            border-bottom: 4px solid #94a3b8; 
            filter: grayscale(0.8);
        }
        
        .btn-action { border-radius: 50px; font-weight: 700; width: 100%; padding: 10px; }
        .status-badge { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4 shadow">
    <div class="container">
        <span class="navbar-brand fw-bold">TERM EXAM PORTAL</span>
        <div class="d-flex align-items-center gap-3">
             <span class="badge bg-secondary rounded-pill d-none d-md-inline"><?php echo $current_session; ?></span>
             <a href="my_results.php" class="btn btn-outline-primary btn-sm px-3 rounded-pill">
                <i class="fas fa-history me-1"></i> Performance History
            </a><a href="logout.php" class="btn btn-sm btn-outline-light rounded-pill">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-2">
    <div class="text-center mb-4">
        <h2 class="fw-bold">Welcome, <?php echo htmlspecialchars($full_name); ?>!</h2>
         <p class="text-muted mb-0">Class: <strong><?php echo ($class_year === 'Year 5' || $class_year === 'Year 6') ? "Year 5 & 6" : $class_year; ?></strong> | Session: <?php echo $current_session; ?></p>
    </div>

    <div class="search-container">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="subjectSearch" class="form-control border-start-0 rounded-end-pill search-input" placeholder="Search for a subject (e.g. Maths)...">
        </div>
    </div>

    <div class="row g-4" id="examGrid">
        <?php foreach($all_quizzes as $quiz): 
            $is_done = in_array($quiz['quiz_id'], $finished_ids);
            $ongoing = isset($ongoing_attempts[$quiz['quiz_id']]);
        ?>
        <div class="col-md-6 col-lg-4 exam-item" data-title="<?php echo strtolower(htmlspecialchars($quiz['title'])); ?>">
            <div class="card exam-card shadow-sm p-3 <?php echo $is_done ? 'completed' : ''; ?>">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                            <?php if($is_done): ?>
                                <i class="fas fa-check-circle text-success fa-lg"></i>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small">
                            <i class="far fa-clock me-1"></i> <?php echo $quiz['time_limit_minutes']; ?> Mins | 
                            <i class="far fa-question-circle me-1"></i> <?php echo $quiz['total_questions']; ?> Qs
                        </p>
                    </div>

                    <div class="mt-3">
                        <?php if($is_done): ?>
                            <button class="btn btn-secondary btn-action disabled" disabled>Done!</button>
                        <?php elseif($ongoing): ?>
                            <a href="start_quiz.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-warning btn-action">Resume Exam</a>
                        <?php else: ?>
                            <a href="start_quiz.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-primary btn-action">Start Exam</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="noResults" class="text-center py-5 d-none">
        <i class="fas fa-search fa-3x text-muted mb-3"></i>
        <h4>No subjects found with that name.</h4>
    </div>
</div>

<script>
document.getElementById('subjectSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.exam-item');
    let found = false;

    items.forEach(item => {
        const title = item.getAttribute('data-title');
        if(title.includes(term)) {
            item.classList.remove('d-none');
            found = true;
        } else {
            item.classList.add('d-none');
        }
    });

    const noRes = document.getElementById('noResults');
    if(!found) noRes.classList.remove('d-none');
    else noRes.classList.add('d-none');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>