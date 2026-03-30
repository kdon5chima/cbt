<?php
require_once "db_connect.php";

$exam_type = $_GET['exam_type'] ?? 'Mid-Term';
$term = $_GET['term'] ?? '1st Term';
$academic_year = $_GET['academic_year'] ?? '2025/2026';
$search = $_GET['search'] ?? '';

// Pagination Setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// SQL to get ONLY Completed Quizzes
// A quiz is complete if actual_count >= total_questions
$where_clause = "WHERE z.exam_type = ? AND z.term = ? AND z.academic_year = ? AND (z.title LIKE ? OR z.subject LIKE ?)";
$search_param = "%$search%";

// 1. Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM (
                SELECT z.quiz_id FROM quizzes z 
                LEFT JOIN questions q ON z.quiz_id = q.quiz_id 
                $where_clause 
                GROUP BY z.quiz_id 
                HAVING COUNT(q.question_id) >= z.total_questions
              ) as subquery";
$c_stmt = $conn->prepare($count_sql);
$c_stmt->bind_param("sssss", $exam_type, $term, $academic_year, $search_param, $search_param);
$c_stmt->execute();
$total_records = $c_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// 2. Get the actual data
$sql = "SELECT z.quiz_id, z.title, z.subject, z.total_questions, COUNT(q.question_id) as actual_count 
        FROM quizzes z 
        LEFT JOIN questions q ON z.quiz_id = q.quiz_id 
        $where_clause 
        GROUP BY z.quiz_id 
        HAVING actual_count >= z.total_questions 
        ORDER BY z.subject ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssii", $exam_type, $term, $academic_year, $search_param, $search_param, $limit, $offset);
$stmt->execute();
$results = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Completed Quizzes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="bg-light p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-success"><i class="fas fa-check-double me-2"></i>Completed Registry</h3>
        <div class="btn-group">
            <a href="incomplete_quizzes.php" class="btn btn-outline-danger">View Drafts</a>
            <a href="admin_dashboard.php" class="btn btn-secondary">Back</a>
        </div>
    </div>

    <form method="GET" class="card shadow-sm border-0 p-3 mb-4">
        <div class="row g-2">
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>"></div>
            <div class="col-md-2">
                <select name="exam_type" class="form-select">
                    <option value="Mid-Term" <?= $exam_type == 'Mid-Term' ? 'selected' : '' ?>>Mid-Term</option>
                    <option value="Term Examination" <?= $exam_type == 'Term Examination' ? 'selected' : '' ?>>Term Examination</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="term" class="form-select">
                    <option value="1st Term" <?= $term == '1st Term' ? 'selected' : '' ?>>1st Term</option>
                    <option value="2nd Term" <?= $term == '2nd Term' ? 'selected' : '' ?>>2nd Term</option>
                </select>
            </div>
            <div class="col-md-3"><input type="text" name="academic_year" class="form-control" value="<?= htmlspecialchars($academic_year) ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-success">
                <tr><th>Subject / Title</th><th>Progress</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php while($row = $results->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['subject']) ?></strong><br><small><?= htmlspecialchars($row['title']) ?></small></td>
                    <td><span class="badge bg-success"><?= $row['actual_count'] ?> / <?= $row['total_questions'] ?></span></td>
                    <td><a href="add_questions.php?quiz_id=<?= $row['quiz_id'] ?>" class="btn btn-sm btn-dark">Manage</a></td>
                </tr>
                <?php endwhile; if($results->num_rows == 0) echo "<tr><td colspan='3' class='text-center p-4'>No completed quizzes found.</td></tr>"; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_pages > 1): ?>
    <nav class="mt-4"><ul class="pagination justify-content-center">
        <?php for($i=1; $i<=$total_pages; $i++): ?>
            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&exam_type=<?= $exam_type ?>&term=<?= $term ?>&academic_year=<?= $academic_year ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
</body>
</html>