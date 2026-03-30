<?php
// view_all_attempts.php
require_once "db_connect.php";
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

/**
 * Helper function for sliding window pagination
 */
function getPaginationRange($currentPage, $totalPages, $delta = 2) {
    $range = [];
    $rangeWithDots = [];
    $l = null;

    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $delta && $i <= $currentPage + $delta)) {
            $range[] = $i;
        }
    }

    foreach ($range as $i) {
        if ($l) {
            if ($i - $l === 2) {
                $rangeWithDots[] = $l + 1;
            } else if ($i - $l !== 1) {
                $rangeWithDots[] = '...';
            }
        }
        $rangeWithDots[] = $i;
        $l = $i;
    }
    return $rangeWithDots;
}

// --- Filter & Search Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 20; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $limit;

// 1. Get total records (Filtered)
$count_query = "SELECT COUNT(a.attempt_id) FROM attempts a 
                LEFT JOIN users u ON a.user_id = u.user_id 
                LEFT JOIN quizzes q ON a.quiz_id = q.quiz_id
                WHERE u.username LIKE ? OR q.title LIKE ?";
$stmt_count = $conn->prepare($count_query);
$search_param = "%$search%";
$stmt_count->bind_param("ss", $search_param, $search_param);
$stmt_count->execute();
$total_results = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_results / $limit);

// 2. Fetch Limited Results
$sql_all_attempts = "
    SELECT a.attempt_id, q.title AS quiz_title, u.username, 
           a.score AS total_correct, q.total_questions, 
           a.end_time AS submission_time, a.contestant_number
    FROM attempts a
    LEFT JOIN users u ON a.user_id = u.user_id
    LEFT JOIN quizzes q ON a.quiz_id = q.quiz_id
    WHERE u.username LIKE ? OR q.title LIKE ?
    ORDER BY submission_time DESC
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql_all_attempts);
$stmt->bind_param("ssii", $search_param, $search_param, $limit, $offset);
$stmt->execute();
$all_attempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Attempt Log (Admin)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { padding-top: 56px; background-color: #f8f9fa; }
        #sidebar { position: fixed; top: 56px; bottom: 0; left: 0; width: 250px; background-color: #343a40; padding: 1rem; }
        #main-content { margin-left: 250px; padding: 2rem; }
        @media (max-width: 768px) { #sidebar { position: static; width: 100%; } #main-content { margin-left: 0; } }
        .table-card { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .pagination .page-link { min-width: 40px; text-align: center; }
    </style>
    <script>
        function toggleSelectAll(source) {
            checkboxes = document.getElementsByName('delete_ids[]');
            for(var i=0; i<checkboxes.length; i++) checkboxes[i].checked = source.checked;
        }
    </script>
</head>
<body>
    <nav id="sidebar" class="collapse d-md-block bg-dark no-print">
        <h5 class="text-white border-bottom pb-2">Admin Menu</h5>
        <ul class="nav flex-column mt-3">
            <li class="nav-item"><a class="nav-link text-white-50" href="admin_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white active" href="view_all_attempts.php"><i class="fas fa-list me-2"></i> All Attempts</a></li>
            <li class="nav-item"><a class="nav-link text-white-50" href="view_results.php"><i class="fas fa-trophy me-2"></i> Leaderboard</a></li>
        </ul>
    </nav>

    <main id="main-content">
        <div class="container-fluid">
            <div class="table-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-database text-danger"></i> Master Attempt Log</h2>
                    <form class="d-flex" method="GET">
                        <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search Quiz or User..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                    </form>
                </div>

                <form action="bulk_delete_attempts.php" method="POST" onsubmit="return confirm('DANGER: Delete all selected entries?');">
                    <div class="mb-3 d-flex justify-content-between">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete Selected</button>
                        <span class="text-muted small">Total Found: <?php echo $total_results; ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><input type="checkbox" onClick="toggleSelectAll(this)"></th>
                                    <th>ID</th>
                                    <th>Quiz</th>
                                    <th>Participant</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($all_attempts) > 0): ?>
                                    <?php foreach ($all_attempts as $attempt): ?>
                                    <tr>
                                        <td><input type="checkbox" name="delete_ids[]" value="<?php echo $attempt['attempt_id']; ?>"></td>
                                        <td><?php echo $attempt['attempt_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($attempt['quiz_title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($attempt['username']); ?> <small class="text-muted">(#<?php echo $attempt['contestant_number']; ?>)</small></td>
                                        <td><span class="badge bg-success"><?php echo $attempt['total_correct']; ?> / <?php echo $attempt['total_questions']; ?></span></td>
                                        <td class="small"><?php echo $attempt['submission_time'] ? date("M j, H:i", strtotime($attempt['submission_time'])) : 'N/A'; ?></td>
                                        <td class="text-center">
                                            <a href="review_attempt.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-outline-primary btn-sm">Review</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No attempts found matching your search.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php 
                        $pages = getPaginationRange($current_page, $total_pages);
                        foreach ($pages as $p): 
                            if ($p === '...'): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php else: ?>
                                <li class="page-item <?php echo ($p == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $p; ?>
                                    </a>
                                </li>
                            <?php endif; 
                        endforeach; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>