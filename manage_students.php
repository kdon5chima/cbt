<?php
// manage_students.php - Admin Student Directory
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security: Only Admin/Teacher
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

// 1. Fetch all students
$sql = "SELECT user_id, full_name, class_year, username FROM users 
        WHERE user_type = 'participant' 
        ORDER BY class_year ASC, full_name ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students | CBT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .table-card { border: none; border-radius: 15px; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .thead-dark { background-color: #1e293b; color: white; }
        .badge-unassigned { background-color: #fee2e2; color: #dc2626; font-weight: 700; }
        .badge-assigned { background-color: #dcfce7; color: #166534; font-weight: 700; }
        .search-bar { border-radius: 50px; padding-left: 20px; }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold"><i class="fas fa-users me-2 text-primary"></i>Student Directory</h2>
            <p class="text-muted">Manage pupil profiles and class assignments.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">Dashboard</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="studentSearch" class="form-control border-start-0 rounded-end-pill search-bar" placeholder="Search by name or class...">
            </div>
        </div>
    </div>

    <div class="table-card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="studentTable">
                <thead class="thead-dark">
                    <tr>
                        <th class="ps-3">Full Name</th>
                        <th>Username</th>
                        <th>Assigned Class</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $is_assigned = !empty($row['class_year']) && $row['class_year'] !== 'Not Assigned';
                        ?>
                        <tr class="student-row">
                            <td class="fw-bold ps-3"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>
                                <span class="badge rounded-pill px-3 py-2 <?php echo $is_assigned ? 'badge-assigned' : 'badge-unassigned'; ?>">
                                    <?php echo $is_assigned ? htmlspecialchars($row['class_year']) : 'Not Assigned'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="admin_edit_student.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-primary px-3 rounded-pill">
                                    <i class="fas fa-user-edit me-1"></i> Edit Profile
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4">No students found in the database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Simple Search Logic
document.getElementById('studentSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('.student-row');
    
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>