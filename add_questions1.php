<?php
// add_questions.php - Mobile-Responsive Version
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user_id = $_SESSION["user_id"] ?? $_SESSION["id"] ?? null;
$user_type = $_SESSION["user_type"] ?? null;

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($user_type, ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$message = ""; $message_type = ""; 
$quiz_meta = null; 
$dashboard_link = ($user_type === 'admin') ? "admin_dashboard.php" : "teacher_dashboard.php";

if ($quiz_id) {
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quiz_meta = $stmt->get_result()->fetch_assoc();
}

$quizzes = [];
$sql_select = ($user_type === 'admin') ? 
    "SELECT quiz_id, title, target_class FROM quizzes ORDER BY quiz_id DESC" : 
    "SELECT quiz_id, title, target_class FROM quizzes WHERE created_by = ? ORDER BY quiz_id DESC";
$stmt_q = $conn->prepare($sql_select);
if ($user_type === 'teacher') { $stmt_q->bind_param("i", $user_id); }
$stmt_q->execute();
$quizzes = $stmt_q->get_result()->fetch_all(MYSQLI_ASSOC);

// ... (Keep your existing POST logic for Import/CSV/Single Add here) ...

if (isset($_GET['status'])) {
    $message = urldecode($_GET['msg']);
    $message_type = $_GET['status'];
}

$existing_questions = [];
if ($quiz_id) {
    $stmt_list = $conn->prepare("SELECT question_id, question_text FROM questions WHERE quiz_id = ? ORDER BY question_id DESC");
    $stmt_list->bind_param("i", $quiz_id);
    $stmt_list->execute();
    $existing_questions = $stmt_list->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 4. QUESTION BANK IMPORT LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_from_bank']) && $quiz_id) {
    $selected_questions = $_POST['bank_q_ids'] ?? [];
    if (!empty($selected_questions)) {
        $import_count = 0;
        foreach ($selected_questions as $old_q_id) {
            $copy_sql = "INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer)
                         SELECT ?, question_text, option_a, option_b, option_c, option_d, correct_answer 
                         FROM questions WHERE question_id = ?";
            $copy_stmt = $conn->prepare($copy_sql);
            $copy_stmt->bind_param("ii", $quiz_id, $old_q_id);
            if($copy_stmt->execute()) $import_count++;
        }
        header("Location: add_questions1.php?quiz_id=$quiz_id&status=success&msg=Imported+$import_count+questions");
        exit;
    }
}
// 5. SINGLE QUESTION INSERTION LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_question'])) {
    $q_quiz_id = (int)$_POST['quiz_id'];
    $q_text = trim($_POST['question_text']);
    $opt_a = trim($_POST['option_a']);
    $opt_b = trim($_POST['option_b']);
    $opt_c = trim($_POST['option_c']);
    $opt_d = trim($_POST['option_d']);
    $correct = $_POST['correct_answer'];

    if (!empty($q_text) && !empty($opt_a) && !empty($opt_b) && !empty($correct)) {
        $ins_stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins_stmt->bind_param("issssss", $q_quiz_id, $q_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct);
        
        if ($ins_stmt->execute()) {
            header("Location: add_questions1.php?quiz_id=$q_quiz_id&status=success&msg=Question+added+successfully");
            exit;
        } else {
            $message = "Error: Could not save question.";
            $message_type = "danger";
        }
    } else {
        $message = "Please fill in all required fields (Question, A, B, and Correct Answer).";
        $message_type = "danger";
    }
}

// 6. CSV BULK IMPORT LOGIC (Optional but recommended to add now)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_csv']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $count = 0;
    
    // Skip header row if your CSV has one
    fgetcsv($handle); 

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) >= 6) { // Ensure row has enough columns
            $ins_stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins_stmt->bind_param("issssss", $quiz_id, $data[0], $data[1], $data[2], $data[3], $data[4], $data[5]);
            if($ins_stmt->execute()) $count++;
        }
    }
    fclose($handle);
    header("Location: add_questions1.php?quiz_id=$quiz_id&status=success&msg=$count+questions+imported+via+CSV");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions | CBT Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        /* Sidebar Styling */
        .sidebar { height: 100vh; background: #0f172a; color: white; position: fixed; width: 260px; z-index: 1040; transition: all 0.3s; }
        .main-content { margin-left: 260px; padding: 20px; transition: all 0.3s; }
        .nav-link { color: #94a3b8; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { color: #fff; background: #1e293b; }
        
        /* Mobile Navigation Overlay */
        @media (max-width: 992px) {
            .sidebar { margin-left: -260px; }
            .sidebar.active { margin-left: 0; }
            .main-content { margin-left: 0; padding-top: 70px; }
            .mobile-header { display: flex !important; }
        }

        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            width: 100%;
            background: #0f172a;
            color: white;
            padding: 15px 20px;
            z-index: 1050;
            justify-content: space-between;
            align-items: center;
        }

        .quiz-card { border-left: 5px solid #3b82f6; }
        .question-list-scroll { max-height: 400px; overflow-y: auto; }
        
        /* Buttons and Inputs adjustments for touch */
        .btn, .form-control, .form-select { min-height: 45px; }
        .nav-tabs .nav-link { font-size: 14px; padding: 10px; }
    </style>
</head>
<body>

    <div class="mobile-header shadow">
        <h6 class="mb-0 fw-bold text-info"><i class="fas fa-book-open me-2"></i>CBT ADMIN</h6>
        <button class="btn btn-outline-info btn-sm" id="sidebarCollapse">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar d-flex flex-column p-4 shadow" id="sidebar">
        <div class="mb-4 d-none d-lg-block">
            <h5 class="fw-bold text-info"><i class="fas fa-book-open me-2"></i>CBT ADMIN</h5>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="<?php echo $dashboard_link; ?>" class="nav-link"><i class="fas fa-columns me-2"></i> Dashboard</a></li>
            <li class="nav-item"><a href="create_quiz.php" class="nav-link"><i class="fas fa-plus-circle me-2"></i> New Quiz</a></li>
            <li class="nav-item"><a href="add_questions1.php" class="nav-link active"><i class="fas fa-tasks me-2"></i> Questions</a></li>
        </ul>
        <hr>
        <div class="mt-auto">
            <a href="logout.php" class="text-danger text-decoration-none small"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <?php if($quiz_meta): ?>
        <div class="card quiz-card shadow-sm mb-4 border-0">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($quiz_meta['title']); ?></h4>
                        <p class="text-muted small mb-0">Class: <b><?= $quiz_meta['target_class'] ?></b> | Sub: <b><?= $quiz_meta['subject_name'] ?></b></p>
                    </div>
                    <div class="d-grid d-md-flex gap-2">
                        <button type="button" class="btn btn-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#bankModal">
                            <i class="fas fa-university me-2"></i> Bank
                        </button>
                        <a href="preview_quiz.php?quiz_id=<?= $quiz_id ?>" class="btn btn-dark btn-sm"><i class="fas fa-eye me-1"></i> Preview</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <?php if($message): ?>
                <div class="alert alert-<?= ($message_type == 'success' ? 'success' : 'danger') ?> alert-dismissible fade show small" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form action="add_questions1.php" method="get" class="mb-4">
                    <label class="form-label fw-bold small text-muted">SELECT A QUIZ</label>
                    <select name="quiz_id" class="form-select shadow-sm" onchange="this.form.submit()">
                        <option value="">-- Select Quiz --</option>
                        <?php foreach ($quizzes as $q): ?>
                            <option value="<?= $q['quiz_id'] ?>" <?= ($quiz_id == $q['quiz_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($q['title']) ?> (<?= $q['target_class'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($quiz_id): ?>
                    <ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto" id="managementTabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#manual-pane" type="button">Single</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bulk-pane" type="button">Bulk CSV</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="manual-pane">
                            <div class="row g-4">
                                <div class="col-lg-7 order-1 order-lg-2">
                                    <div class="p-3 bg-white border rounded shadow-sm">
                                        <h6 class="fw-bold mb-3 text-primary">New Question Form</h6>
                                        <form action="add_questions1.php?quiz_id=<?= $quiz_id ?>" method="post">
                                            <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
                                            <textarea name="question_text" class="form-control mb-3" rows="3" placeholder="Question..." required></textarea>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6"><input type="text" name="option_a" class="form-control" placeholder="A" required></div>
                                                <div class="col-6"><input type="text" name="option_b" class="form-control" placeholder="B" required></div>
                                                <div class="col-6"><input type="text" name="option_c" class="form-control" placeholder="C"></div>
                                                <div class="col-6"><input type="text" name="option_d" class="form-control" placeholder="D"></div>
                                            </div>
                                            <select name="correct_answer" class="form-select mb-3 border-success" required>
                                                <option value="">Correct Option</option>
                                                <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                                            </select>
                                            <button type="submit" name="submit_question" class="btn btn-primary w-100 fw-bold">Save Question</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="col-lg-5 order-2 order-lg-1">
                                    <h6 class="fw-bold mb-3 text-secondary d-flex justify-content-between">
                                        QUESTIONS (<?= count($existing_questions) ?>)
                                        <?php if(!empty($existing_questions)): ?>
                                            <a href="export_questions.php?quiz_id=<?= $quiz_id ?>" class="text-success small"><i class="fas fa-file-export"></i> CSV</a>
                                        <?php endif; ?>
                                    </h6>
                                    <div class="question-list-scroll border bg-white rounded shadow-sm">
                                        <div class="list-group list-group-flush">
                            <?php foreach ($existing_questions as $eq): ?>
                         <div class="list-group-item p-2">
                                 <p class="mb-1 small fw-bold"><?= htmlspecialchars($eq['question_text']) ?></p>
                                <div class="d-flex gap-3">
                                <a href="edit_question1.php?id=<?= $eq['question_id'] ?>&quiz_id=<?= $quiz_id ?>" class="text-primary x-small"><i class="fas fa-edit"></i> Edit</a>
                                <a href="#" class="text-danger x-small btn-delete-trigger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-qid="<?= $eq['question_id'] ?>" data-quizid="<?= $quiz_id ?>"><i class="fas fa-trash"></i> Del</a>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade x-small" id="bulk-pane">
                            <div class="bg-light p-3 rounded border text-center">
                                <i class="fas fa-upload fa-2x text-primary mb-2"></i>
                                <h6>CSV Bulk Import</h6>
                                <form action="add_questions1.php?quiz_id=<?= $quiz_id ?>" method="post" enctype="multipart/form-data">
                                    <input type="file" name="csv_file" class="form-control mb-3" accept=".csv" required>
                                    <button type="submit" name="submit_csv" class="btn btn-dark w-100">Start Import</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking links on mobile
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if(window.innerWidth < 992) document.getElementById('sidebar').classList.remove('active');
            });
        });
    </script>
    <div class="modal fade" id="bankModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0">
                <form action="add_questions1.php?quiz_id=<?php echo $quiz_id; ?>" method="post">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold">Question Bank</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <div class="row g-2 mb-3 bg-white p-3 rounded shadow-sm mx-1">
                            <div class="col-md-4">
                                <label class="small fw-bold">Search</label>
                                <input type="text" id="bankSearch" class="form-control" placeholder="Search keywords...">
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold">Subject</label>
                                <select id="subjectFilter" class="form-select">
                                    <option value="">All Subjects</option>
                                    <?php 
                                        $sub_res = $conn->query("SELECT DISTINCT subject_name FROM quizzes WHERE subject_name != ''");
                                        while($s = $sub_res->fetch_assoc()) echo "<option value='{$s['subject_name']}'>{$s['subject_name']}</option>";
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold">Academic Year</label>
                                <select id="yearFilter" class="form-select">
                                    <option value="">All Years</option>
                                    <?php 
                                        $year_res = $conn->query("SELECT DISTINCT academic_year FROM quizzes ORDER BY academic_year DESC");
                                        while($y = $year_res->fetch_assoc()) echo "<option value='{$y['academic_year']}'>{$y['academic_year']}</option>";
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive bg-white rounded shadow-sm" style="max-height: 400px;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th width="40" class="ps-3"><input type="checkbox" id="selectAllBank" class="form-check-input"></th>
                                        <th>Question</th>
                                        <th>Subject</th>
                                        <th>Year</th>
                                    </tr>
                                </thead>
                                <tbody id="bankTableBody">
                                    <?php
                                    if($quiz_id):
                                        $bank_stmt = $conn->prepare("SELECT q.*, z.academic_year, z.subject_name 
                                                                    FROM questions q JOIN quizzes z ON q.quiz_id = z.quiz_id 
                                                                    WHERE z.quiz_id != ? ORDER BY z.academic_year DESC");
                                        $bank_stmt->bind_param("i", $quiz_id);
                                        $bank_stmt->execute();
                                        $bank_res = $bank_stmt->get_result();
                                        while($row = $bank_res->fetch_assoc()): ?>
                                            <tr class="bank-row">
                                                <td class="ps-3"><input type="checkbox" name="bank_q_ids[]" value="<?php echo $row['question_id']; ?>" class="form-check-input q-check"></td>
                                                <td class="q-text small fw-bold"><?php echo htmlspecialchars($row['question_text']); ?></td>
                                                <td class="q-subject small"><?php echo $row['subject_name']; ?></td>
                                                <td class="q-year small"><?php echo $row['academic_year']; ?></td>
                                            </tr>
                                        <?php endwhile; 
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="submit" name="import_from_bank" class="btn btn-success fw-bold px-4">Import Selected</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple Real-time Filter for Bank
        const searchInput = document.getElementById('bankSearch');
        const subjectFilter = document.getElementById('subjectFilter');
        const yearFilter = document.getElementById('yearFilter');

        function applyFilters() {
            const search = searchInput.value.toLowerCase();
            const subject = subjectFilter.value;
            const year = yearFilter.value;

            document.querySelectorAll('.bank-row').forEach(row => {
                const text = row.querySelector('.q-text').innerText.toLowerCase();
                const rSub = row.querySelector('.q-subject').innerText;
                const rYear = row.querySelector('.q-year').innerText;

                const match = text.includes(search) && (subject === "" || rSub === subject) && (year === "" || rYear === year);
                row.style.display = match ? "" : "none";
            });
        }

        [searchInput, subjectFilter, yearFilter].forEach(el => el?.addEventListener('input', applyFilters));

        document.getElementById('selectAllBank')?.addEventListener('change', function() {
            document.querySelectorAll('.q-check').forEach(cb => {
                if(cb.closest('tr').style.display !== 'none') cb.checked = this.checked;
            });
        });
    </script>
    <script> 
    // Handle Delete Modal URL dynamic population
document.addEventListener('click', function (e) {
    if (e.target.closest('.btn-delete-trigger')) {
        const trigger = e.target.closest('.btn-delete-trigger');
        const qid = trigger.getAttribute('data-qid');
        const quizId = trigger.getAttribute('data-quizid');
        
        // Construct the actual delete URL
        const deleteUrl = `delete_question1.php?id=${qid}&quiz_id=${quizId}`;
        
        // Update the "Delete Permanently" button in the modal
        document.getElementById('finalDeleteBtn').setAttribute('href', deleteUrl);
    }
});
    </script>
   <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center p-4">
                <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                <h5 class="fw-bold">Delete Question?</h5>
                <p class="text-muted small">This action cannot be undone. Are you sure?</p>
                <div class="d-grid gap-2">
                    <a id="finalDeleteBtn" href="#" class="btn btn-danger fw-bold">Delete Permanently</a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div> 
</body>
</html>