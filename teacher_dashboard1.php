<?php
require_once "db_connect.php";

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: teacher_login.php");
    exit;
}

$teacher_id = $_SESSION["user_id"];

// 2. Fetch Teacher Info (Matching your 'user_id' column)
$stmt_user = $conn->prepare("SELECT full_name, username FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $teacher_id);
$stmt_user->execute();
$user_res = $stmt_user->get_result()->fetch_assoc();
$display_name = !empty($user_res['full_name']) ? $user_res['full_name'] : $user_res['username'];

// --- Pagination & Filtering Logic ---
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Filter inputs
$f_year = $_GET['academic_year'] ?? '';
$f_term = $_GET['term'] ?? '';
$f_type = $_GET['exam_type'] ?? '';

// Build Query
$query_parts = ["created_by = ?"];
$params = [$teacher_id];
$types = "i";

// Matching your ENUM and Varchar values
if ($f_year) { $query_parts[] = "academic_year = ?"; $params[] = $f_year; $types .= "s"; }
if ($f_term) { $query_parts[] = "term = ?"; $params[] = $f_term; $types .= "s"; }
if ($f_type) { $query_parts[] = "exam_type = ?"; $params[] = $f_type; $types .= "s"; }

$where_clause = implode(" AND ", $query_parts);

// Count total records
$count_sql = "SELECT COUNT(*) as count FROM quizzes WHERE $where_clause";
$stmt_count = $conn->prepare($count_sql);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Fetch Quizzes
$sql_recent = "SELECT * FROM quizzes WHERE $where_clause ORDER BY created_at DESC LIMIT ?, ?";
$stmt_recent = $conn->prepare($sql_recent);
$final_params = array_merge($params, [$start, $limit]);
$final_types = $types . "ii";
$stmt_recent->bind_param($final_types, ...$final_params);
$stmt_recent->execute();
$quizzes = $stmt_recent->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | CBT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        #sidebar { width: 260px; height: 100vh; position: fixed; background: #1e293b; color: white; padding: 20px; z-index: 1000; }
        #main-content { margin-left: 260px; padding: 40px; }
        .nav-link { color: #94a3b8; transition: 0.3s; }
        .nav-link.active { color: #fff; background: #334155; border-radius: 8px; }
        .filter-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; }
        .table-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        .badge-soft-info { background-color: #e0f2fe; color: #0369a1; }
        @media (max-width: 992px) { #sidebar { display: none; } #main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <div id="sidebar" class="shadow">
        <div class="text-center mb-4">
            <h5 class="fw-bold text-info"><i class="fas fa-graduation-cap me-2"></i>CBT Portal</h5>
            <small class="text-muted"><?php echo htmlspecialchars($display_name); ?></small>
        </div>
        <nav class="nav flex-column gap-2">
            <a class="nav-link active px-3 py-2" href="teacher_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link px-3 py-2" href="view_results.php"><i class="fas fa-chart-line me-2"></i> Student Results</a>
            <hr class="text-secondary">
            <a class="nav-link text-danger px-3 py-2" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div id="main-content">
        <header class="mb-4">
            <h3 class="fw-bold">Assessment Management</h3>
            <p class="text-muted">Create, filter, and manage your quizzes below.</p>
        </header>

        <div class="filter-card p-4 mb-4 shadow-sm">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="small fw-bold mb-1">Academic Year</label>
                    <input type="text" name="academic_year" class="form-select" list="years" placeholder="Select/Type Year" value="<?php echo htmlspecialchars($f_year); ?>">
                    <datalist id="years">
                        <option value="2024/2025">
                        <option value="2025/2026">
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold mb-1">Term</label>
                    <select name="term" class="form-select">
                        <option value="">All Terms</option>
                        <option value="1st Term" <?php echo $f_term == '1st Term' ? 'selected' : ''; ?>>1st Term</option>
                        <option value="2nd Term" <?php echo $f_term == '2nd Term' ? 'selected' : ''; ?>>2nd Term</option>
                        <option value="3rd Term" <?php echo $f_term == '3rd Term' ? 'selected' : ''; ?>>3rd Term</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold mb-1">Exam Type</label>
                    <select name="exam_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="Mid-Term" <?php echo $f_type == 'Mid-Term' ? 'selected' : ''; ?>>Mid-Term</option>
                        <option value="Term Examination" <?php echo $f_type == 'Term Examination' ? 'selected' : ''; ?>>Term Examination</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    <a href="teacher_dashboard.php" class="btn btn-outline-secondary px-3"><i class="fas fa-sync"></i></a>
                </div>
            </form>
        </div>

        <div class="table-card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Quiz Details</th>
                            <th>Class & Subject</th>
                            <th>Session Info</th>
                            <th>Questions</th>
                            <th class="text-end pe-4">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($quizzes->num_rows > 0): ?>
                            <?php while($row = $quizzes->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['title']); ?></div>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($row['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="badge badge-soft-info px-2 py-1 mb-1"><?php echo htmlspecialchars($row['target_class']); ?></div>
                                    <div class="small text-dark fw-semibold"><?php echo htmlspecialchars($row['subject_name'] ?? $row['subject']); ?></div>
                                </td>
                                <td>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($row['academic_year']); ?></small>
                                    <small class="fw-bold text-uppercase" style="font-size: 0.7rem;"><?php echo htmlspecialchars($row['term']); ?></small>
                                </td>
                                <td>
                                    <span class="text-muted small"><i class="far fa-file-alt me-1"></i><?php echo $row['total_questions']; ?> Qs</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="add_questions.php?quiz_id=<?php echo $row['quiz_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit me-1"></i>Questions</a>
                                        <a href="view_results.php?quiz_id=<?php echo $row['quiz_id']; ?>" class="btn btn-sm btn-dark">Results</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No quizzes found for your current selection.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="p-3 border-top bg-light d-flex justify-content-center">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&academic_year=<?php echo urlencode($f_year); ?>&term=<?php echo urlencode($f_term); ?>&exam_type=<?php echo urlencode($f_type); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>