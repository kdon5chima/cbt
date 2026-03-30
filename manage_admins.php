<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') { 
    header("location: login.php"); 
    exit; 
}

$current_admin_id = $_SESSION["user_id"];

// --- Status Toggle Logic ---
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $current_status = (int)$_GET['current'];
    
    if ($id === $current_admin_id) {
        header("location: manage_admins.php?error=self_suspend");
        exit;
    }

    $new_status = ($current_status === 1) ? 0 : 1;
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ? AND user_type = 'admin'");
    $stmt->bind_param("ii", $new_status, $id);
    $stmt->execute();
    header("location: manage_admins.php?msg=updated");
    exit;
}

// --- Delete Admin Logic (NEW) ---
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];

    if ($id === $current_admin_id) {
        header("location: manage_admins.php?error=self_delete");
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND user_type = 'admin'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("location: manage_admins.php?msg=deleted");
    exit;
}

// 2. Fetch Admins
$admins = $conn->query("SELECT user_id, username, email, status FROM users WHERE user_type = 'admin'");

if (!$admins) {
    die("Database Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Administrators</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .card { border-radius: 12px; }
        .avatar-admin { background-color: #e0e7ff; color: #4338ca; }
    </style>
</head>
<body class="p-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-primary fw-bold">Manage Admins</h2>
                <p class="text-muted small">System level access control</p>
            </div>
            <div>
                <a href="create_admin.php" class="btn btn-primary"><i class="fas fa-user-shield me-2"></i>Add New Admin</a>
                <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    if($_GET['msg'] == 'updated') echo "Admin status updated successfully!";
                    if($_GET['msg'] == 'deleted') echo "Admin removed from system.";
                    if($_GET['msg'] == 'created') echo "New administrator account created.";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Action Denied:</strong> 
                <?php 
                    if($_GET['error'] == 'self_suspend') echo "You cannot suspend your own account.";
                    if($_GET['error'] == 'self_delete') echo "You cannot delete your own account.";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Admin Information</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($admins->num_rows > 0): ?>
                            <?php while($row = $admins->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm avatar-admin rounded-circle p-2 me-3 text-center" style="width: 40px;">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($row['username']); ?>
                                                <?php if($row['user_id'] == $current_admin_id): ?>
                                                    <span class="badge bg-info ms-1" style="font-size: 0.7rem;">You</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($row['status'] == 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <?php if($row['user_id'] != $current_admin_id): ?>
                                            <a href="manage_admins.php?toggle_id=<?php echo $row['user_id']; ?>&current=<?php echo $row['status']; ?>" 
                                               class="btn btn-sm <?php echo $row['status'] == 1 ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                               title="<?php echo $row['status'] == 1 ? 'Suspend' : 'Activate'; ?>">
                                                <i class="fas <?php echo $row['status'] == 1 ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </a>
                                            
                                            <a href="javascript:void(0);" 
                                               onclick="confirmDelete(<?php echo $row['user_id']; ?>, '<?php echo addslashes($row['username']); ?>')" 
                                               class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="edit_admin.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">No other administrators found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmDelete(id, name) {
        if (confirm("Permanently delete admin '" + name + "'? This cannot be undone.")) {
            window.location.href = "manage_admins.php?delete_id=" + id;
        }
    }
    </script>
</body>
</html>