<?php
// live_results.php - Professional Table Version with Search & Pagination
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['admin', 'teacher'])) {
    header("location: login.php"); exit;
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$quiz_info = null;

if ($quiz_id) {
    // Check if quizzes table has these columns. If it fails here, check your column names!
    $stmt = $conn->prepare("SELECT title, target_class, total_questions FROM quizzes WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quiz_info = $stmt->get_result()->fetch_assoc();
}

// Fetch all quizzes for the selector table
$all_quizzes = $conn->query("SELECT q.quiz_id, q.title, q.target_class, 
                            (SELECT COUNT(*) FROM attempts a WHERE a.quiz_id = q.quiz_id AND a.end_time IS NOT NULL) as sub_count 
                            FROM quizzes q ORDER BY q.quiz_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Monitor | CBT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .main-card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .thead-dark { background-color: #2d3436; color: white; }
        .blink { animation: blinker 1.2s linear infinite; color: #ff4757; }
        @keyframes blinker { 50% { opacity: 0; } }
        /* Professional pagination styling */
        .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; }
    </style>
</head>
<body>

<div class="container py-5">
    <?php if ($quiz_info): ?>
        <div class="card main-card mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <a href="live_results.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Change Quiz
                </a>
                <div class="text-center">
                    <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($quiz_info['title']); ?></h4>
                    <small class="text-danger fw-bold"><i class="fas fa-circle blink me-1"></i> LIVE MONITORING MODE</small>
                </div>
                <button onclick="window.location.reload()" class="btn btn-primary btn-sm">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <table id="monitorTable" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Progress</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res_sql = "SELECT a.*, u.full_name FROM attempts a 
                                   JOIN users u ON a.user_id = u.id 
                                   WHERE a.quiz_id = ? AND a.end_time IS NOT NULL 
                                   ORDER BY a.end_time DESC";
                        $res_stmt = $conn->prepare($res_sql);
                        if($res_stmt) {
                            $res_stmt->bind_param("i", $quiz_id);
                            $res_stmt->execute();
                            $results = $res_stmt->get_result();
                            while($row = $results->fetch_assoc()):
                                $perc = ($row['total_questions'] > 0) ? ($row['score'] / $row['total_questions']) * 100 : 0;
                                $color = ($perc >= 70) ? 'success' : (($perc >= 45) ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                            <td><?php echo $row['score']; ?> / <?php echo $row['total_questions']; ?></td>
                            <td class="fw-bold text-<?php echo $color; ?>"><?php echo number_format($perc, 1); ?>%</td>
                            <td>
                                <div class="progress" style="height: 10px; width: 120px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $perc; ?>%"></div>
                                </div>
                            </td>
                            <td><span class="badge bg-success">Completed</span></td>
                        </tr>
                        <?php endwhile; } ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <div class="text-center mb-4">
            <h2 class="fw-bold">Assessment Monitor Hub</h2>
            <p class="text-muted">Select an active quiz from the table below to monitor student progress.</p>
        </div>

        <div class="card main-card">
            <div class="card-body p-4">
                <table id="selectorTable" class="table table-hover align-middle" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Quiz Title</th>
                            <th>Target Class</th>
                            <th>Submissions</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($all_quizzes && $all_quizzes->num_rows > 0): ?>
                            <?php while($quiz = $all_quizzes->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($quiz['title']); ?></td>
                                <td><span class="badge bg-info-subtle text-info px-3"><?php echo htmlspecialchars($quiz['target_class']); ?></span></td>
                                <td>
                                    <span class="fw-bold text-primary"><?php echo $quiz['sub_count']; ?></span> students
                                </td>
                                <td class="text-center">
                                    <a href="live_results.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-dark btn-sm px-4 rounded-pill">
                                        Monitor <i class="fas fa-chart-line ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize the Selector Table (The one with the list of quizzes)
    $('#selectorTable').DataTable({
        "pageLength": 10,
        "ordering": true,
        "language": {
            "search": "_INPUT_",
            "searchPlaceholder": "Search quiz or class..."
        }
    });

    // Initialize the Monitor Table
    $('#monitorTable').DataTable({
        "paging": true,
        "pageLength": 25,
        "info": false
    });

    // If monitoring, auto-refresh the data every 20 seconds
    <?php if ($quiz_id): ?>
    setTimeout(function(){
       location.reload();
    }, 20000);
    <?php endif; ?>
});
</script>

</body>
</html>