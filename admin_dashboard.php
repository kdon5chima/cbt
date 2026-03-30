<?php
// admin_dashboard.php 
require_once "db_connect.php"; 

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

/**
 * HELPER FUNCTION: Pagination sliding window
 */
function getPaginationRange($currentPage, $totalPages, $delta = 2) {
    $range = []; $rangeWithDots = []; $l = null;
    if ($totalPages <= 1) return [1];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $delta && $i <= $currentPage + $delta)) { $range[] = $i; }
    }
    foreach ($range as $i) {
        if ($l) {
            if ($i - $l === 2) { $rangeWithDots[] = $l + 1; } 
            else if ($i - $l !== 1) { $rangeWithDots[] = '...'; }
        }
        $rangeWithDots[] = $i; $l = $i;
    }
    return $rangeWithDots;
}

// --- SEARCH HANDLING ---
$u_search = isset($_GET['u_search']) ? $conn->real_escape_string($_GET['u_search']) : '';
$t_search = isset($_GET['t_search']) ? $conn->real_escape_string($_GET['t_search']) : ''; 
$q_search = isset($_GET['q_search']) ? $conn->real_escape_string($_GET['q_search']) : '';
$a_search = isset($_GET['a_search']) ? $conn->real_escape_string($_GET['a_search']) : '';

// --- 1. USER MANAGEMENT (PUPILS) ---
$user_limit = 10;
$user_current_page = isset($_GET['u_page']) ? (int)$_GET['u_page'] : 1;
$user_offset = ($user_current_page - 1) * $user_limit;
$u_where = "user_type = 'participant'"; 
if (!empty($u_search)) { $u_where .= " AND (username LIKE '%$u_search%' OR full_name LIKE '%$u_search%')"; }
$user_total_results = $conn->query("SELECT COUNT(user_id) FROM users WHERE $u_where")->fetch_row()[0];
$user_total_pages = ceil($user_total_results / $user_limit);
$all_users = $conn->query("SELECT * FROM users WHERE $u_where ORDER BY full_name ASC LIMIT $user_limit OFFSET $user_offset");

// --- 2. TEACHER MANAGEMENT ---
$teacher_limit = 10;
$teacher_current_page = isset($_GET['t_page']) ? (int)$_GET['t_page'] : 1;
$teacher_offset = ($teacher_current_page - 1) * $teacher_limit;
$t_where = "user_type = 'teacher'";
if (!empty($t_search)) { $t_where .= " AND (username LIKE '%$t_search%' OR full_name LIKE '%$t_search%')"; }
$teacher_total_results = $conn->query("SELECT COUNT(user_id) FROM users WHERE $t_where")->fetch_row()[0];
$teacher_total_pages = ceil($teacher_total_results / $teacher_limit);
$all_teachers = $conn->query("SELECT * FROM users WHERE $t_where ORDER BY full_name ASC LIMIT $teacher_limit OFFSET $teacher_offset");

// --- 3. QUIZ MANAGEMENT ---
$quiz_limit = 10;
$quiz_current_page = isset($_GET['q_page']) ? (int)$_GET['q_page'] : 1;
$quiz_offset = ($quiz_current_page - 1) * $quiz_limit;
$q_where = "1=1";
if (!empty($q_search)) { $q_where .= " AND title LIKE '%$q_search%'"; }
$quiz_total_results = $conn->query("SELECT COUNT(quiz_id) FROM quizzes WHERE $q_where")->fetch_row()[0];
$quiz_total_pages = ceil($quiz_total_results / $quiz_limit);
$all_quizzes = $conn->query("SELECT * FROM quizzes WHERE $q_where ORDER BY quiz_id DESC LIMIT $quiz_limit OFFSET $quiz_offset")->fetch_all(MYSQLI_ASSOC);

// --- 4. ATTEMPT HISTORY ---
$attempt_limit = 15;
$attempt_current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$attempt_offset = ($attempt_current_page - 1) * $attempt_limit;
$a_where = "1=1";
if (!empty($a_search)) { $a_where .= " AND (u.full_name LIKE '%$a_search%' OR q.title LIKE '%$a_search%')"; }
$attempt_total_results = $conn->query("SELECT COUNT(a.attempt_id) FROM attempts a JOIN users u ON a.user_id = u.user_id JOIN quizzes q ON a.quiz_id = q.quiz_id WHERE $a_where")->fetch_row()[0];
$attempt_total_pages = ceil($attempt_total_results / $attempt_limit);
$attempts_history = $conn->query("SELECT a.attempt_id, a.score, a.end_time, q.title AS quiz_title, u.full_name FROM attempts a JOIN users u ON a.user_id = u.user_id JOIN quizzes q ON a.quiz_id = q.quiz_id WHERE $a_where ORDER BY a.end_time DESC LIMIT $attempt_limit OFFSET $attempt_offset")->fetch_all(MYSQLI_ASSOC);

// --- STATS ---
$total_quizzes = $conn->query("SELECT COUNT(quiz_id) FROM quizzes")->fetch_row()[0];
$total_questions = $conn->query("SELECT COUNT(question_id) FROM questions")->fetch_row()[0];
$total_users = $conn->query("SELECT COUNT(user_id) FROM users WHERE user_type = 'participant'")->fetch_row()[0];
$total_staff = $conn->query("SELECT COUNT(user_id) FROM users WHERE user_type IN ('teacher', 'admin')")->fetch_row()[0];

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { padding-top: 56px; background-color: #f8f9fa; }
        #sidebar { position: fixed; top: 56px; bottom: 0; left: 0; z-index: 1000; padding: 1rem; width: 250px; background-color: #343a40; }
        #main-content { margin-left: 250px; padding: 1rem; }
        @media (max-width: 768px) { #sidebar { position: static; width: 100%; } #main-content { margin-left: 0; } }
        .nav-link { color: rgba(255, 255, 255, 0.75); }
        .nav-link.active, .nav-link:hover { color: #fff; background-color: #007bff; border-radius: 5px; }
        .table-responsive { background: white; border-radius: 8px; }
        .stat-card-link { text-decoration: none !important; color: inherit; transition: transform 0.2s; display: block; }
        .stat-card-link:hover { transform: translateY(-3px); }
        .badge { font-size: 0.85rem; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php"><i class="fas fa-tools"></i> Admin Control Panel</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm ms-auto"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <style>
    #sidebar {
        min-height: 100vh;
        width: 260px;
        background: #2c3e50; /* Professional Deep Navy */
        transition: all 0.3s;
        z-index: 1000;
        box-shadow: 4px 0 10px rgba(0,0,0,0.1);
    }

    #sidebar .sidebar-header {
        padding: 20px;
        background: #1a252f;
        text-align: center;
    }

    #sidebar h5 {
        font-size: 1.1rem;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 0;
    }

    #sidebar .nav-link {
        color: #bdc3c7;
        padding: 12px 20px;
        font-weight: 500;
        transition: 0.2s;
        border-left: 4px solid transparent;
    }

    #sidebar .nav-link:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.05);
        border-left-color: #e74c3c; /* Accent red line on hover */
    }

    #sidebar .nav-link.active {
        color: #fff;
        background: #e74c3c; /* Highlight color */
        border-left-color: #c0392b;
    }

    #sidebar .nav-link i {
        width: 25px;
    }

    .section-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #7f8c8d;
        padding: 20px 20px 10px;
        font-weight: bold;
        letter-spacing: 1.2px;
    }
    
    .sidebar-footer {
        padding: 15px;
        position: absolute;
        bottom: 0;
        width: 100%;
    }
    @media (max-width: 768px) {
    
    #sidebar.active {
        margin-left: 0; /* Slide in when active */
    }
    #content {
        width: 100%;
    }
}

/* Style for the Hamburger Button */
#sidebarCollapse {
    display: none;
}

@media (max-width: 768px) {
    #sidebarCollapse {
        display: block;
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1100;
    }
}
#sidebar {
    height: 100vh;      /* Full screen height */
    position: fixed;    /* Stay in place while the page scrolls */
    top: 0;
    left: 0;
    width: 250px;       /* Adjust based on your width */
    display: flex;
    flex-direction: column;
    background: #1e293b; /* Match your admin theme */
    z-index: 1000;
}

/* This makes the link area scrollable */
#sidebar .sidebar-content {
    flex-grow: 1;
    overflow-y: auto;   /* Enable vertical scroll */
    overflow-x: hidden;
}

/* Custom Scrollbar for a cleaner look */
#sidebar .sidebar-content::-webkit-scrollbar {
    width: 5px;
}
#sidebar .sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
}
</style>
<div class="d-flex">
<nav id="sidebar" class="collapse d-md-block shadow">
    <button type="button" id="sidebarCollapse" class="btn btn-primary">
    <i class="fas fa-bars"></i>
</button>
    <div class="sidebar-header">
        <h5 class="text-white fw-bold">Admin Panel</h5>
    </div>

    <div class="position-sticky">
        <ul class="nav flex-column">
            <div class="section-title">Core</div>
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>

            <div class="section-title">Academic & Quizzes</div>
            <li class="nav-item">
                <a class="nav-link" href="create_quiz.php">
                    <i class="fas fa-plus-circle me-2"></i> Create Quiz
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="add_questions.php">
                    <i class="fas fa-question-circle me-2"></i> Add Questions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_questions.php">
                    <i class="fas fa-tasks me-2"></i> Manage Question
                </a>
                <a class="nav-link" href="manage_students.php">
                    <i class="fas fa-tasks me-2"></i> Manage Pupils
                </a>
            </li>
<li class="nav-item">
    <a class="nav-link" href="student_list.php">
        <i class="fas fa-users me-2"></i> Student Directory
    </a>
</li>
            <div class="section-title">Reports</div>
            <li class="nav-item">
                <a class="nav-link" href="view_results.php">
                    <i class="fas fa-chart-line me-2"></i> Leaderboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_analytics.php">
                    <i class="fas fa-chart-pie me-2"></i> Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="view_all_attempts.php">
                    <i class="fas fa-history me-2"></i> Attempt Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_reset.php">
                    <i class="fas fa-undo me-2"></i> Reset Attempt
                </a>
            </li>

            <div class="section-title">Staff & Access</div>
            <li class="nav-item">
                <a class="nav-link" href="manage_teachers.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Teachers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_admins.php">
                    <i class="fas fa-user-shield me-2"></i> Admin List
                </a>
            </li>
        </ul>

        <div class="mt-4 p-3">
            <a href="system_settings.php" class="btn btn-outline-warning w-100 text-start btn-sm">
                <i class="fas fa-cogs me-2"></i> System Settings
            </a>
            <div class="section-title px-3 mt-3 text-warning">Maintenance</div>
<li class="nav-item">
    <a class="nav-link text-info" href="admin_backup_db.php">
        <i class="fas fa-database me-2"></i> Backup Database
    </a>
</li>
<li class="nav-item">
    <a class="nav-link text-info" href="admin_backup_files.php">
        <i class="fas fa-file-archive me-2"></i> Backup Code (.zip)
    </a>
</li>
            <a href="logout.php" class="btn btn-danger w-100 text-start btn-sm mt-2">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>
</nav>
<div id="content" class="w-100 p-4">
    <main id="main-content">
        

    <div class="container-fluid px-4">
    <div class="row g-4 mb-4">
        <a href="#quiz-management" class="col-xl-3 col-md-6 stat-card-link">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); color: white;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-0"><?php echo $total_quizzes; ?></h3>
                        <p class="text-white-50 small mb-0">Total Quizzes</p>
                    </div>
                    <div class="icon-shape bg-white-50 rounded-circle p-3">
                        <i class="fas fa-book-open fa-2x"></i>
                    </div>
                </div>
            </div>
        </a>

        <a href="manage_questions.php" class="col-xl-3 col-md-6 stat-card-link">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-0"><?php echo $total_questions; ?></h3>
                        <p class="text-white-50 small mb-0">Questions Bank</p>
                    </div>
                    <div class="icon-shape bg-white-50 rounded-circle p-3">
                        <i class="fas fa-layer-group fa-2x"></i>
                    </div>
                </div>
            </div>
        </a>

        <a href="#user-management" class="col-xl-3 col-md-6 stat-card-link">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #5ee7df 0%, #b490ca 100%); color: white;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-0"><?php echo $total_users; ?></h3>
                        <p class="text-white-50 small mb-0">Enrolled Pupils</p>
                    </div>
                    <div class="icon-shape bg-white-50 rounded-circle p-3">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                </div>
            </div>
        </a>

        <a href="manage_teachers.php" class="col-xl-3 col-md-6 stat-card-link">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-0"><?php echo $total_staff; ?></h3>
                        <p class="text-white-50 small mb-0">Staff (Teachers/Admins)</p>
                    </div>
                    <div class="icon-shape bg-white-50 rounded-circle p-3">
                        <i class="fas fa-user-shield fa-2x"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<style>
    .stat-card-link {
        text-decoration: none !important;
        transition: all 0.3s ease;
    }
    .stat-card-link:hover {
        transform: translateY(-5px);
    }
    .icon-shape {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.2); /* Glass effect */
        width: 60px;
        height: 60px;
    }
    .text-white-50 {
        color: rgba(255, 255, 255, 0.8) !important;
        font-weight: 500;
    }
</style>
<div class="container-fluid px-4 mb-5">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h5 class="text-dark fw-bold mb-4">
                <i class="fas fa-bolt text-warning me-2"></i> Quick Actions
            </h5>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <a href="create_quiz.php" class="btn btn-outline-primary w-100 py-3 shadow-sm border-2">
                        <i class="fas fa-plus-circle d-block mb-2 fa-lg"></i>
                        <span class="small fw-bold">New Quiz</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="add_questions.php" class="btn btn-outline-info w-100 py-3 shadow-sm border-2">
                        <i class="fas fa-question-circle d-block mb-2 fa-lg"></i>
                        <span class="small fw-bold">Add Questions</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="manage_teachers.php" class="btn btn-outline-success w-100 py-3 shadow-sm border-2">
                        <i class="fas fa-user-plus d-block mb-2 fa-lg"></i>
                        <span class="small fw-bold">Add Staff</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="system_settings.php" class="btn btn-outline-secondary w-100 py-3 shadow-sm border-2">
                        <i class="fas fa-sliders-h d-block mb-2 fa-lg"></i>
                        <span class="small fw-bold">Settings</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Styling to make the buttons pop */
    .btn-outline-primary:hover, .btn-outline-info:hover, 
    .btn-outline-success:hover, .btn-outline-secondary:hover {
        transform: scale(1.05);
        transition: all 0.2s ease;
    }
    
    .btn-group-vertical > .btn, .btn-group > .btn {
        border-radius: 10px !important;
    }
</style>

            <div class="card mb-5 shadow-sm" id="quiz-management">
    <div class="card-header bg-white">
        <h5 class="mb-3 text-primary fw-bold">Quiz Management</h5>
        
        <form class="row g-2" method="GET" action="#quiz-management">
            <div class="col-md-2">
                <input type="text" name="academic_year" class="form-control form-control-sm" placeholder="Year (2025/2026)" value="<?= htmlspecialchars($_GET['academic_year'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="term" class="form-select form-select-sm">
                    <option value="">-- Term --</option>
                    <option value="1st Term" <?= ($_GET['term'] ?? '') == '1st Term' ? 'selected' : '' ?>>1st Term</option>
                    <option value="2nd Term" <?= ($_GET['term'] ?? '') == '2nd Term' ? 'selected' : '' ?>>2nd Term</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="exam_type" class="form-select form-select-sm">
                    <option value="">-- Exam Type --</option>
                    <option value="Mid-Term" <?= ($_GET['exam_type'] ?? '') == 'Mid-Term' ? 'selected' : '' ?>>Mid-Term</option>
                    <option value="Term Examination" <?= ($_GET['exam_type'] ?? '') == 'Term Examination' ? 'selected' : '' ?>>Term Examination</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="q_search" class="form-control form-control-sm" placeholder="Search title..." value="<?= htmlspecialchars($q_search ?? '') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr><th>Quiz Title</th><th>Created At</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($all_quizzes as $quiz): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($quiz['title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($quiz['created_at']); ?></td>
                    <td>
                        <a href="edit_quiz.php?id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-edit"></i></a>
                        <form id="delete-quiz-<?php echo $quiz['quiz_id']; ?>" action="delete_quiz.php" method="POST" class="d-inline">
                            <input type="hidden" name="quiz_id" value="<?php echo $quiz['quiz_id']; ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmGenericDelete('delete-quiz-<?php echo $quiz['quiz_id']; ?>', 'Delete this quiz?')"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <nav><ul class="pagination pagination-sm justify-content-center">
            <?php 
            // Build query string for pagination links
            $query_params = $_GET; 
            unset($query_params['q_page']); 
            $queryString = http_build_query($query_params);
            
            foreach (getPaginationRange($quiz_current_page, $quiz_total_pages) as $p): ?>
                <li class="page-item <?php echo ($p == $quiz_current_page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?q_page=<?php echo $p; ?>&<?php echo $queryString; ?>#quiz-management"><?php echo $p; ?></a>
                </li>
            <?php endforeach; ?>
        </ul></nav>
    </div>
</div>



            <div class="card mb-5 shadow-sm" id="user-management">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary fw-bold">Pupil Management</h5>
                    <form class="d-flex" method="GET">
                        <input type="text" name="u_search" class="form-control form-control-sm me-2" placeholder="Search pupil..." value="<?php echo htmlspecialchars($u_search); ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Search</button>
                    </form>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr><th>Full Name</th><th>Username</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $all_users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($user['username']); ?></small></td>
                                <td>
                                    <a href="pupil_transcript.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-invoice"></i></a>
                                    <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-user-edit"></i></a>
                                    <form id="delete-user-<?php echo $user['user_id']; ?>" action="delete_user.php" method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmGenericDelete('delete-user-<?php echo $user['user_id']; ?>', 'This pupil account and history will be deleted!')"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <nav><ul class="pagination pagination-sm justify-content-center">
                        <?php foreach (getPaginationRange($user_current_page, $user_total_pages) as $p): ?>
                            <li class="page-item <?php echo ($p == $user_current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?u_page=<?php echo $p; ?>&u_search=<?php echo urlencode($u_search); ?>#user-management"><?php echo $p; ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul></nav>
                </div>
            </div>

            
            <div class="card mb-5 shadow-sm" id="attempt-history">
    <div class="card-header bg-white">
        <h5 class="mb-3 text-primary fw-bold">Recent Exam Attempts</h5>
        
        <form class="row g-2" method="GET" action="#attempt-history">
            <div class="col-md-2">
                <input type="text" name="academic_year" class="form-control form-control-sm" placeholder="Year" value="<?= htmlspecialchars($_GET['academic_year'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="term" class="form-select form-select-sm">
                    <option value="">-- Term --</option>
                    <option value="1st Term" <?= ($_GET['term'] ?? '') == '1st Term' ? 'selected' : '' ?>>1st Term</option>
                    <option value="2nd Term" <?= ($_GET['term'] ?? '') == '2nd Term' ? 'selected' : '' ?>>2nd Term</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="a_search" class="form-control form-control-sm" placeholder="Search student/quiz..." value="<?= htmlspecialchars($a_search ?? '') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-primary">
                <tr><th>Name</th><th>Quiz</th><th>Score</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($attempts_history as $at): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($at['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($at['quiz_title']); ?></td>
                    <td><span class="badge <?php echo ($at['score'] >= 50) ? 'bg-success' : 'bg-danger'; ?>"><?php echo $at['score']; ?>%</span></td>
                    <td><small><?php echo date("M d, H:i", strtotime($at['end_time'])); ?></small></td>
                    <td>
                        <form id="delete-attempt-<?php echo $at['attempt_id']; ?>" action="delete_attempt.php" method="POST" class="d-inline">
                            <input type="hidden" name="attempt_id" value="<?php echo $at['attempt_id']; ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmGenericDelete('delete-attempt-<?php echo $at['attempt_id']; ?>', 'Permanent delete?')"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <nav><ul class="pagination pagination-sm justify-content-center">
            <?php 
            $query_params = $_GET; 
            unset($query_params['page']); 
            $queryString = http_build_query($query_params);
            
            foreach (getPaginationRange($attempt_current_page, $attempt_total_pages) as $p): ?>
                <li class="page-item <?php echo ($p == $attempt_current_page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $p; ?>&<?php echo $queryString; ?>#attempt-history"><?php echo $p; ?></a>
                </li>
            <?php endforeach; ?>
        </ul></nav>
    </div>
</div>
    </main>
</div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmGenericDelete(formId, customText) {
        Swal.fire({
            title: 'Are you sure?',
            text: customText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById(formId).submit();
        })
    }
    <?php if ($message): ?> Swal.fire('Success!', '<?php echo $message; ?>', 'success'); <?php endif; ?>
    <?php if ($error): ?> Swal.fire('Error!', '<?php echo $error; ?>', 'error'); <?php endif; ?>
    </script>
    <script>
document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarCollapse');

    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('active');
    });

    // Close sidebar if user clicks outside of it on mobile
    document.addEventListener('click', function(event) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickInsideButton = toggleBtn.contains(event.target);

        if (!isClickInsideSidebar && !isClickInsideButton && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    });
});
</script>
</body>
</html>