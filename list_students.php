<?php
// list_students.php - Teacher's View of Students
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: teacher_login.php");
    exit;
}

// Fetch students who have records in the attempts table 
// (or join with a classes table if you have a specific assignment)
$sql = "SELECT DISTINCT u.user_id, u.username, u.full_name, a.class_at_time, u.last_login 
        FROM users u 
        INNER JOIN attempts a ON u.user_id = a.user_id 
        WHERE u.user_type = 'student' 
        ORDER BY a.class_at_time ASC, u.full_name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student List | Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        #sidebar { width: 260px; height: 100vh; position: fixed; background: #2c3e50; color: white; padding: 20px; }
        #main-content { margin-left: 260px; padding: 40px; }
        .nav-link { color: #bdc3c7; }
        .nav-link.active { background: #34495e; color: #fff; border-radius: 10px; }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="text-center mb-4"><i class="fas fa-chalkboard-teacher fa-2x"></i><h5 class="fw-bold">Teacher Portal</h5></div>
        <nav class="nav flex-column">
            <a class="nav-link" href="teacher_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link active" href="list_students.php"><i class="fas fa-users me-2"></i> Students</a>
            <a class="nav-link" href="view_results.php"><i class="fas fa-chart-bar me-2"></i> Results</a>
            <a class="nav-link text-danger mt-4" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div id="main-content">
        <h2 class="fw-bold mb-4">Registered Students</h2>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Full Name</th>
                            <th>Username</th>
                            <th>Class</th>
                            <th>Last Active</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo htmlspecialchars($row['full_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['class_at_time']); ?></span></td>
                                <td class="small text-muted"><?php echo $row['last_login'] ? date('M d, H:i', strtotime($row['last_login'])) : 'Never'; ?></td>
                                <td class="text-end pe-4">
                                    <a href="view_results.php?user_id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-outline-primary">View Performance</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4">No student records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>