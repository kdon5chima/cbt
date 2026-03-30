<?php
// teacher_dashboard.php - Content Creation Focus
require_once "db_connect.php";

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// 1. Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'teacher') {
    header("location: teacher_login.php");
    exit;
}

$teacher_id = $_SESSION["user_id"];

// 2. Fetch Fresh Teacher Data (Ensures Full Name is always accurate)
$stmt_user = $conn->prepare("SELECT full_name, username FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $teacher_id);
$stmt_user->execute();
$user_res = $stmt_user->get_result()->fetch_assoc();

// Logic: Use full_name if it exists, otherwise fall back to username
$display_name = !empty($user_res['full_name']) ? $user_res['full_name'] : $user_res['username'];

// --- Data Fetching ---
// 3. Total Quizzes created by this teacher
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE created_by = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$total_quizzes = $stmt->get_result()->fetch_assoc()['count'];

// 4. Recent Quizzes List
$sql_recent = "SELECT * FROM quizzes WHERE created_by = ? ORDER BY created_at DESC LIMIT 5";
$stmt_recent = $conn->prepare($sql_recent);
$stmt_recent->bind_param("i", $teacher_id);
$stmt_recent->execute();
$quizzes = $stmt_recent->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | <?php echo htmlspecialchars($display_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        #sidebar { width: 260px; height: 100vh; position: fixed; background: #2c3e50; color: white; padding: 20px; z-index: 1000; }
        #main-content { margin-left: 260px; padding: 40px; }
        
        /* Action Cards Styling */
        .action-card { 
            border: none; 
            border-radius: 15px; 
            transition: all 0.3s ease; 
            text-decoration: none !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 2rem !important;
        }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .icon-box { 
            width: 70px; height: 70px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; margin-bottom: 15px; 
        }
        
        .impersonation-bar { background: #ffc107; color: #000; padding: 12px 30px; position: sticky; top: 0; z-index: 2000; border-bottom: 3px solid #e0a800; }
        
        @media (max-width: 992px) {
            #sidebar { display: none; }
            #main-content { margin-left: 0; padding: 20px; }
            .action-card { padding: 1.5rem !important; }
        }
    </style>
</head>
<body>

    <?php if (isset($_SESSION["is_impersonating"])): ?>
        <div class="impersonation-bar d-flex justify-content-between align-items-center shadow no-print">
            <div><i class="fas fa-user-secret me-2"></i><strong>Admin Mode</strong></div>
            <a href="stop_impersonating.php" class="btn btn-dark btn-sm rounded-pill">Exit Session</a>
        </div>
    <?php endif; ?>

    <div id="sidebar" class="shadow">
        <div class="text-center mb-4">
            <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
            <h5 class="fw-bold">Teacher Portal</h5>
            <div class="small text-white-50"><?php echo htmlspecialchars($display_name); ?></div>
        </div>
        <hr>
        <nav class="nav flex-column">
            <a class="nav-link text-white active mb-2" href="teacher_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link text-white-50 mb-2" href="view_results.php"><i class="fas fa-chart-bar me-2"></i> Results</a>
            
             <a class="nav-link text-white-50 mb-2" href="add_questions1.php"><i class="fas fa-chart-bar me-2"></i>Mobile Question</a>

            <a class="nav-link text-danger mt-4" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>
add_questions1.php
    <div id="main-content">
        <div class="container-fluid">
            <header class="mb-5">
                <h2 class="fw-bold">Welcome, <?php echo htmlspecialchars($display_name); ?></h2>
                <p class="text-muted">You have created <strong><?php echo $total_quizzes; ?></strong> assessments so far.</p>
            </header>

            <h5 class="fw-bold mb-4">Quick Actions</h5>
            <div class="row g-4 mb-5 justify-content-start">
                <div class="col-md-4">
                    <a href="create_quiz.php" class="card action-card bg-white shadow-sm h-100">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-plus-circle fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Create Quiz</h5>
                        <p class="text-muted small mb-0">Set up a new assessment, title, and target class.</p>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="add_questions1.php" class="card action-card bg-white shadow-sm h-100">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-question-circle fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Add Questions<br>(using mobile phone)</h5>
                        <p class="text-muted small mb-0">Populate your quizzes with MCQs and set correct answers.</p>
                    </a>
                </div>
 <div class="col-md-4">
                    <a href="teacher_dashboard1.php" class="card action-card bg-white shadow-sm h-100">
                        <div class="icon-box bg-danger bg-opacity-10 text-warning">
                            <i class="fas fa-question-circle fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Add and Manage Questions</h5>
                        <p class="text-muted small mb-0">View the list of all the Assessnets in this portal and add questions to them</p>
                    </a>
                </div>
<div class="col-md-4">
                    <a href="view_results.php" class="card action-card bg-white shadow-sm h-100">
                        <div class="icon-box bg-success bg-opacity-10 text-success">
                            <i class="fas fa-poll-h fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">View Results</h5>
                        <p class="text-muted small mb-0">Monitor student performance and export score sheets.</p>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="add_questions.php" class="card action-card bg-white shadow-sm h-100">
                        <div class="icon-box bg-danger bg-opacity-10 text-warning">
                            <i class="fas fa-question-circle fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Add Questions</h5>
                        <p class="text-muted small mb-0">Populate your quizzes with MCQs and set correct answers.</p>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="logout.php" class="card action-card bg-white shadow-sm h-100">
                        <div class="icon-box bg-information bg-opacity-10 text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Log Out</h5>
                        <p class="text-muted small mb-0">Done, Close the portal</p>
                    </a>
                </div>

                
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">My Recent Assessments</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Quiz Title</th>
                                <th>Target Class</th>
                                <th>Date Created</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($quizzes->num_rows > 0): ?>
                                <?php while($row = $quizzes->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><span class="badge rounded-pill bg-info text-dark px-3"><?php echo htmlspecialchars($row['target_class']); ?></span></td>
                                    <td class="small text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <a href="add_questions.php?quiz_id=<?php echo $row['quiz_id']; ?>" class="btn btn-sm btn-outline-primary">Add Questions</a>
                                            <a href="view_results.php?quiz_id=<?php echo $row['quiz_id']; ?>" class="btn btn-sm btn-primary">Results</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">No quizzes found. Start by clicking "Create Quiz" above.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>