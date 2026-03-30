<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security: Only allow admins
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

// --- 1. HANDLE RESET ACTION ---
if (isset($_POST['reset_attempt_id'])) {
    $aid = (int)$_POST['reset_attempt_id'];
    // Delete answers first then the attempt (Assuming InnoDB foreign keys aren't handling cascade)
    $conn->query("DELETE FROM user_answers WHERE attempt_id = $aid");
    $conn->query("DELETE FROM attempts WHERE attempt_id = $aid");
    $message = "The attempt has been successfully reset.";
}

// --- 2. SEARCH & PAGINATION SETUP ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10; // Records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// SQL conditions for searching
$whereClause = "";
$params = [];
$types = "";

if (!empty($search)) {
    $whereClause = " WHERE u.full_name LIKE ? OR q.title LIKE ? ";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
    $types = "ss";
}

// Get total records for pagination calculation
$countSql = "SELECT COUNT(*) FROM attempts a 
             JOIN users u ON a.user_id = u.user_id 
             JOIN quizzes q ON a.quiz_id = q.quiz_id $whereClause";
$stmtCount = $conn->prepare($countSql);
if (!empty($search)) { $stmtCount->bind_param($types, ...$params); }
$stmtCount->execute();
$totalRecords = $stmtCount->get_result()->fetch_row()[0];
$totalPages = ceil($totalRecords / $limit);

// --- 3. FETCH DATA ---
$sql = "SELECT a.attempt_id, u.full_name, q.title, a.score, q.total_questions, a.end_time 
        FROM attempts a 
        JOIN users u ON a.user_id = u.user_id 
        JOIN quizzes q ON a.quiz_id = q.quiz_id 
        $whereClause
        ORDER BY a.attempt_id DESC 
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$offset, $limit]));
} else {
    $stmt->bind_param("ii", $offset, $limit);
}
$stmt->execute();
$attempts = $stmt->get_result();

// Pagination Window Settings
$range = 2; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Attempt Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; }
        .table-hover tbody tr:hover { background-color: rgba(13, 110, 253, 0.05); }
        .pagination .page-link { color: #444; border: none; margin: 0 3px; border-radius: 8px !important; }
        .pagination .active .page-link { background-color: #0d6efd; color: white; box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3); }
    </style>
</head>
<body class="p-md-5">

<div class="container">
    <div class="card shadow-sm p-4">
        <div class="row align-items-center mb-4">
            <div class="col-md-4">
                <h2 class="fw-bold mb-0">Attempt Manager</h2>
            </div>
            <div class="col-md-8">
                <form action="" method="GET" class="d-flex gap-2 justify-content-md-end mt-3 mt-md-0">
                    <div class="input-group w-75">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search pupil or subject..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                    <?php if(!empty($search)): ?>
                        <a href="?" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                    <a href="admin_dashboard.php" class="btn btn-light border">Back</a>
                </form>
            </div>
        </div>

        <?php if(isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light text-secondary">
                    <tr>
                        <th>Pupil</th>
                        <th>Subject</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($attempts->num_rows > 0): ?>
                        <?php while($row = $attempts->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3 bg-light rounded-circle text-center" style="width:35px; height:35px; line-height:35px;">
                                        <i class="fas fa-user text-primary"></i>
                                    </div>
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td>
                                <span class="fw-bold"><?php echo $row['score'] ?? '0'; ?></span> 
                                <span class="text-muted">/ <?php echo $row['total_questions']; ?></span>
                            </td>
                            <td>
                                <?php echo $row['end_time'] 
                                    ? '<span class="badge rounded-pill bg-success-subtle text-success border border-success">Finished</span>' 
                                    : '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning">In Progress</span>'; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-danger btn-sm rounded-3" data-bs-toggle="modal" data-bs-target="#resetModal<?php echo $row['attempt_id']; ?>">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </button>
                            </td>
                        </tr>

                        <div class="modal fade" id="resetModal<?php echo $row['attempt_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <div class="modal-body text-center p-5">
                                        <div class="mb-4 text-danger">
                                            <i class="fas fa-exclamation-circle fa-4x"></i>
                                        </div>
                                        <h4 class="fw-bold">Confirm Reset</h4>
                                        <p class="text-muted">You are about to delete the score for <b><?php echo htmlspecialchars($row['full_name']); ?></b>. This allows them to retake the quiz but deletes all current progress.</p>
                                        <div class="d-grid gap-2 mt-4">
                                            <form method="POST">
                                                <input type="hidden" name="reset_attempt_id" value="<?php echo $row['attempt_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-lg w-100">Reset Attempt</button>
                                            </form>
                                            <button class="btn btn-light btn-lg" data-bs-dismiss="modal">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-3x mb-3 d-block"></i>
                                No matching records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>

                <?php
                $show_initial_dots = true;
                $show_end_dots = true;

                for ($i = 1; $i <= $totalPages; $i++):
                    if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)):
                ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                <?php 
                    elseif ($i < $page - $range && $show_initial_dots): 
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        $show_initial_dots = false;
                    elseif ($i > $page + $range && $show_end_dots):
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        $show_end_dots = false;
                    endif;
                endfor; 
                ?>

                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        
        <div class="text-center mt-2">
            <small class="text-muted">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> (Total: <?php echo $totalRecords; ?>)</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>