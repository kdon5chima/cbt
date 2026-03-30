<?php
// student_list.php - Full Pupil Directory with Counter, Search, and Custom Modal
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

// HANDLE DELETE via POST
if (isset($_POST['confirm_delete'])) {
    $id_to_delete = intval($_POST['user_id']);
    $conn->query("DELETE FROM quiz_results WHERE user_id = $id_to_delete");
    $delete_sql = "DELETE FROM users WHERE user_id = ? AND user_type = 'participant'";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        header("Location: student_list.php?msg=deleted");
        exit();
    }
}

$sql = "SELECT user_id, full_name, username, class_year FROM users WHERE user_type = 'participant' ORDER BY class_year ASC, full_name ASC";
$result = $conn->query($sql);
$total_students = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pupil Directory | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .thead-custom { background-color: #2c3e50; color: white; }
        .counter-card { background: #fff; border-left: 5px solid #e74c3c; border-radius: 8px; }
        .search-input { border-radius: 20px; padding-left: 40px; border: 1px solid #ddd; }
        .search-icon { position: absolute; left: 15px; top: 10px; color: #aaa; }
        .modal-header-danger { background-color: #dc3545; color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row align-items-center mb-4">
        <div class="col-lg-5">
            <h2 class="fw-bold text-dark mb-0">Pupil Directory</h2>
            <p class="text-muted small">Manage and track all registered students.</p>
        </div>
        
        <div class="col-lg-4 position-relative">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="filterInput" class="form-control search-input shadow-sm" placeholder="Search name or class...">
        </div>

        <div class="col-lg-3">
            <div class="counter-card p-2 shadow-sm text-center">
                <h6 class="text-uppercase x-small fw-bold text-muted mb-0">Total Pupils</h6>
                <h3 class="fw-bold mb-0 text-danger"><?php echo $total_students; ?></h3>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <a href="admin_dashboard.php" class="btn btn-sm btn-outline-dark rounded-pill px-4">Back to Dashboard</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show">
            Student removed successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="bg-white p-3 rounded shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="pupilTable">
                <thead class="thead-custom">
                    <tr>
                        <th width="50">S/N</th>
                        <th>Student Information</th>
                        <th>Class</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sn = 1;
                    if($total_students > 0):
                        while($row = $result->fetch_assoc()): 
                    ?>
                    <tr class="pupil-row">
                        <td class="text-muted small"><?php echo $sn++; ?>.</td>
                        <td>
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['full_name']); ?></span>
                            <div class="text-muted small font-monospace"><?php echo htmlspecialchars($row['username']); ?></div>
                        </td>
                        <td>
                            <?php if(empty($row['class_year']) || $row['class_year'] == 'Not Assigned'): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3">Unassigned</span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3"><?php echo htmlspecialchars($row['class_year']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="admin_edit_student.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-dark rounded-pill px-3 me-1">Edit</a>
                            
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" 
                                    onclick="openDeleteModal(<?php echo $row['user_id']; ?>, '<?php echo addslashes($row['full_name']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-danger">
                <h5 class="modal-title fw-bold">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                <p class="mb-1 text-muted">You are about to delete:</p>
                <h4 id="studentNameDisplay" class="text-danger fw-bold"></h4>
                <p class="text-muted small mt-2">Warning: This student's score history will also be permanently deleted from the database.</p>
            </div>
            <div class="modal-footer bg-light justify-content-center border-0">
                <form method="POST">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="confirm_delete" class="btn btn-danger px-4 rounded-pill shadow-sm">Confirm Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// --- LIVE SEARCH SCRIPT ---
document.getElementById('filterInput').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('.pupil-row');
    
    rows.forEach(row => {
        // Search across Name, Username, and Class
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(value) ? '' : 'none';
    });
});

// --- MODAL TRIGGER SCRIPT ---
function openDeleteModal(id, name) {
    document.getElementById('delete_user_id').value = id;
    document.getElementById('studentNameDisplay').innerText = name;
    var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    myModal.show();
}
</script>

</body>
</html>