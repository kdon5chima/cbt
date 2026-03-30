<?php
require_once "db_connect.php";

// --- 1. DELETE LOGIC ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM questions WHERE quiz_id = $delete_id");
    $stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted");
        exit();
    }
}

$exam_type = $_GET['exam_type'] ?? 'Mid-Term';
$term = $_GET['term'] ?? '1st Term';
$academic_year = $_GET['academic_year'] ?? '2025/2026';

// 2. Updated Fetch Query to include updated_at
function getFilteredQuizzes($conn, $exam_type, $term, $academic_year) {
    $query = "SELECT z.quiz_id, z.title as quiz_name, z.subject_name as subject_name, 
              z.total_questions, z.updated_at, COUNT(q.question_id) as actual_count
              FROM quizzes z
              LEFT JOIN questions q ON z.quiz_id = q.quiz_id
              WHERE z.exam_type = ? AND z.term = ? AND z.academic_year = ?
              GROUP BY z.quiz_id
              ORDER BY z.updated_at DESC"; // Sort by most recent work
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $exam_type, $term, $academic_year);
    $stmt->execute();
    return $stmt->get_result();
}

$results = getFilteredQuizzes($conn, $exam_type, $term, $academic_year);

// Calculate Counts
$ready_count = 0; $draft_count = 0;
while ($row = $results->fetch_assoc()) {
    if ($row['actual_count'] >= $row['total_questions']) { $ready_count++; } 
    else { $draft_count++; }
}
$results->data_seek(0);

function renderQuizTable($result, $filterStatus) {
    $result->data_seek(0);
    echo '<table class="table table-hover align-middle mb-0">';
    echo '<thead class="table-light"><tr><th>Subject / Quiz</th><th>Progress</th><th>Last Updated</th><th>Action</th></tr></thead><tbody>';
    
    $found = false;
    while ($row = $result->fetch_assoc()) {
        $isCompleted = ($row['actual_count'] >= $row['total_questions']);
        if (($filterStatus == 'complete' && $isCompleted) || ($filterStatus == 'incomplete' && !$isCompleted)) {
            $found = true;
            $date = date("M d, Y", strtotime($row['updated_at']));
            $time = date("h:i A", strtotime($row['updated_at']));
            
            echo "<tr>
                    <td><strong>" . htmlspecialchars($row['subject_name']) . "</strong><br><small class='text-muted'>" . htmlspecialchars($row['quiz_name']) . "</small></td>
                    <td>" . $row['actual_count'] . " / " . $row['total_questions'] . " Questions</td>
                    <td><span class='text-dark fw-semibold'>$date</span><br><small class='text-muted'>$time</small></td>
                    <td class='text-end'>
                        <div class='btn-group shadow-sm'>
                            <a href='add_questions1.php?quiz_id=" . $row['quiz_id'] . "' class='btn btn-sm btn-outline-primary'><i class='fas fa-edit me-1'></i> Manage</a>
                            <button onclick='confirmDelete(" . $row['quiz_id'] . ", \"" . addslashes($row['quiz_name']) . "\")' class='btn btn-sm btn-outline-danger'><i class='fas fa-trash'></i></button>
                        </div>
                    </td>
                  </tr>";
        }
    }
    if (!$found) echo "<tr><td colspan='4' class='text-center text-muted py-5'>No items found.</td></tr>";
    echo '</tbody></table>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Questions | Registry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .stats-card { border-radius: 12px; }
        .nav-tabs .nav-link { color: #6c757d; border: none; border-bottom: 3px solid transparent; font-weight: 600; padding: 12px 20px; }
        .nav-tabs .nav-link.active { color: #0d6efd !important; border-bottom: 3px solid #0d6efd; background: transparent; }
        .table thead th { font-size: 0.85rem; text-uppercase: true; color: #6c757d; letter-spacing: 0.5px; }
    </style>
</head>
<body class="bg-light p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>Question Registry</h3>
        <a href="admin_dashboard.php" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card stats-card border-0 shadow-sm bg-white p-2">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-check-circle text-success fa-xl"></i></div>
                    <div><h4 class="fw-bold mb-0"><?= $ready_count ?></h4><small class="text-muted text-uppercase fw-bold small">Completed</small></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stats-card border-0 shadow-sm bg-white p-2">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3"><i class="fas fa-hourglass-half text-danger fa-xl"></i></div>
                    <div><h4 class="fw-bold mb-0"><?= $draft_count ?></h4><small class="text-muted text-uppercase fw-bold small">Incomplete</small></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="input-group mb-3 border rounded shadow-sm">
                <span class="input-group-text bg-white border-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="quizSearch" class="form-control border-0 ps-0" placeholder="Search subject or title..." onkeyup="liveSearch()">
            </div>
            <form method="GET" class="row g-2">
                <div class="col-md-3"><select name="exam_type" class="form-select border-0 bg-light"><option value="Mid-Term" <?= $exam_type == 'Mid-Term' ? 'selected' : '' ?>>Mid-Term</option><option value="Term Examination" <?= $exam_type == 'Term Examination' ? 'selected' : '' ?>>Term Examination</option></select></div>
                <div class="col-md-3"><select name="term" class="form-select border-0 bg-light"><option value="1st Term" <?= $term == '1st Term' ? 'selected' : '' ?>>1st Term</option><option value="2nd Term" <?= $term == '2nd Term' ? 'selected' : '' ?>>2nd Term</option></select></div>
                <div class="col-md-3"><input type="text" name="academic_year" class="form-control border-0 bg-light" value="<?= htmlspecialchars($academic_year) ?>"></div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100 fw-bold">Update View</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <ul class="nav nav-tabs px-3 pt-2" id="qTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#comp">Completed</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#inc">Incomplete</button></li>
            </ul>
            <div class="tab-content pt-1">
                <div class="tab-pane fade show active" id="comp"><?php renderQuizTable($results, 'complete'); ?></div>
                <div class="tab-pane fade" id="inc"><?php renderQuizTable($results, 'incomplete'); ?></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function liveSearch() {
    let input = document.getElementById("quizSearch").value.toLowerCase();
    let rows = document.querySelectorAll(".table tbody tr");
    rows.forEach(row => {
        let text = row.cells[0]?.textContent.toLowerCase() || "";
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Delete Quiz?',
        text: "Are you sure you want to delete '" + name + "'?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Delete Forever'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "?delete_id=" + id;
        }
    })
}
</script>
</body>
</html>