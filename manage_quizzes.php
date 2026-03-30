<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_role"], ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$user_role = $_SESSION["user_role"];

// Handle Deletion (ONLY for Admin)
if (isset($_GET['delete_id']) && $user_role === 'admin') {
    $del_id = (int)$_GET['delete_id'];
    
    // Delete quiz and its questions
    $stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
    $stmt->bind_param("i", $del_id);
    
    if ($stmt->execute()) {
        $message = "Quiz deleted successfully.";
        $message_type = "success";
    }
}

// Fetch Quizzes: Teachers only see their class quizzes, Admins see all
$sql = ($user_role === 'admin') 
    ? "SELECT * FROM quizzes ORDER BY quiz_id DESC" 
    : "SELECT * FROM quizzes WHERE class_id = (SELECT assigned_class_id FROM users WHERE user_id = $user_id) ORDER BY quiz_id DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Quizzes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; }
        .card { border: none; border-radius: 10px; }
        .badge-type { font-size: 0.75rem; vertical-align: middle; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">CBT MANAGER</a>
        <div class="ms-auto">
            <a href="<?php echo ($user_role === 'admin') ? '../admin/dashboard.php' : '../teacher/dashboard.php'; ?>" class="btn btn-outline-light btn-sm">Dashboard</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">Manage Exams</h2>
        <a href="create_quiz.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> New Quiz</a>
    </div>

    <?php if(isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Quiz Title</th>
                        <th>Class</th>
                        <th>Exam Type</th>
                        <th>Questions</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): 
                        $qid = $row['quiz_id'];
                        $q_count = $conn->query("SELECT COUNT(*) as c FROM questions WHERE quiz_id = $qid")->fetch_assoc()['c'];
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo "Class ID: ".$row['class_id']; ?></span></td>
                        <td><span class="badge bg-info"><?php echo $row['exam_type'] ?? 'Standard'; ?></span></td>
                        <td><?php echo $q_count; ?></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="add_questions.php?quiz_id=<?php echo $qid; ?>" class="btn btn-sm btn-info text-white" title="Add/Edit Questions">
                                    <i class="fas fa-edit"></i> Edit Questions
                                </a>
                                
                                <?php if($user_role === 'admin'): ?>
                                    <a href="manage_quizzes.php?delete_id=<?php echo $qid; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Confirm Delete? This cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="text-center py-4">No quizzes found for your assigned class.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>