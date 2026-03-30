<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') { 
    header("location: login.php"); 
    exit; 
}

// --- PAGINATION & SEARCH LOGIC ---
$limit = 5; // SET TO 5 FOR TESTING: Change to 10 or 20 later
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$search_param = "%$search%";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users WHERE user_type = 'teacher' AND (full_name LIKE ? OR username LIKE ?)";
$stmt_count = $conn->prepare($count_query);
$stmt_count->bind_param("ss", $search_param, $search_param);
$stmt_count->execute();
$total_results = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);

// --- STATUS TOGGLE ---
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $current = (int)$_GET['current'];
    $new_status = ($current === 1) ? 0 : 1;
    $update = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $update->bind_param("ii", $new_status, $id);
    $update->execute();
    header("Location: manage_teachers.php?page=$page&search=" . urlencode($search));
    exit;
}

// 2. Fetch Teachers
$sql = "SELECT user_id, username, full_name, status FROM users 
        WHERE user_type = 'teacher' AND (full_name LIKE ? OR username LIKE ?) 
        ORDER BY full_name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssii", $search_param, $search_param, $limit, $offset);
$stmt->execute();
$teachers = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Teachers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; padding-top: 20px; }
        .card { border-radius: 10px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold">Manage Teachers</h2>
        <div>
            <a href="add_teacher.php" class="btn btn-success">Add New</a>
            <a href="admin_dashboard.php" class="btn btn-secondary">Dashboard</a>
        </div>
    </div>

    <form class="mb-4" method="GET">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search name or username..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $teachers->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-3">
                            <strong><?php echo htmlspecialchars($row['full_name'] ?: 'No Name'); ?></strong><br>
                            <small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge <?php echo $row['status'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $row['status'] == 1 ? 'Active' : 'Suspended'; ?>
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <a href="manage_teachers.php?toggle_id=<?php echo $row['user_id']; ?>&current=<?php echo $row['status']; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-power-off"></i>
                            </a>
                            <a href="edit_teacher.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">« Previous</a>
            </li>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next »</a>
            </li>

        </ul>
    </nav>
    <?php endif; ?>
    
    <div class="text-center text-muted mt-2 small">
        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
    </div>
</div>
</body>
</html>