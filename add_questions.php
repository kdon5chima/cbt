<?php
// add_questions.php - Refined Version with Guided Bulk Upload
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. SECURITY & SESSION CHECK
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

// 2. FETCH CURRENT QUIZ METADATA
if ($quiz_id) {
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quiz_meta = $stmt->get_result()->fetch_assoc();
}

// 3. FETCH AVAILABLE QUIZZES FOR SELECTOR
$quizzes = [];
$sql_select = ($user_type === 'admin') ? 
    "SELECT quiz_id, title, target_class FROM quizzes ORDER BY quiz_id DESC" : 
    "SELECT quiz_id, title, target_class FROM quizzes WHERE created_by = ? ORDER BY quiz_id DESC";
$stmt_q = $conn->prepare($sql_select);
if ($user_type === 'teacher') { $stmt_q->bind_param("i", $user_id); }
$stmt_q->execute();
$quizzes = $stmt_q->get_result()->fetch_all(MYSQLI_ASSOC);

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
        header("Location: add_questions.php?quiz_id=$quiz_id&status=success&msg=Imported+$import_count+questions");
        exit;
    }
}

// 5. UPDATED CSV IMPORT LOGIC WITH SUMMARY
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_csv']) && $quiz_id){
    if (isset($_FILES["csv_file"]) && $_FILES["csv_file"]["error"] == 0) {
        $handle = fopen($_FILES["csv_file"]["tmp_name"], "r");
        fgetcsv($handle); // Skip header row
        
        $success_count = 0;
        $error_count = 0;
        $row_number = 1; // Tracking for error reporting

        $sql = "INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_number++;
            // Check if we have at least the question and 2 options + answer
            if (!empty($data[0]) && !empty($data[1]) && !empty($data[2]) && !empty($data[5])) {
                $stmt->bind_param("issssss", 
                    $quiz_id, 
                    $data[0], // Question
                    $data[1], // A
                    $data[2], // B
                    $data[3], // C
                    $data[4], // D
                    strtoupper(trim($data[5])) // Clean the Answer (A, B, etc)
                );
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                $error_count++; // Row was empty or missing required fields
            }
        }
        fclose($handle);
        
        $status = ($success_count > 0) ? "success" : "danger";
        $msg = "Upload Complete: $success_count questions added.";
        if($error_count > 0) { $msg .= " ($error_count rows were skipped due to formatting)."; }
        
        header("Location: add_questions.php?quiz_id=$quiz_id&status=$status&msg=" . urlencode($msg));
        exit;
    }
}

// 6. SINGLE QUESTION ADDITION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_question'])) {
    $target_quiz = (int)$_POST["quiz_id"];
    $sql = "INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssss", $target_quiz, $_POST['question_text'], $_POST['option_a'], $_POST['option_b'], $_POST['option_c'], $_POST['option_d'], $_POST['correct_answer']);
    if ($stmt->execute()) {
        header("Location: add_questions.php?quiz_id=$target_quiz&status=success&msg=Question+Added");
        exit;
    }
}

// 7. FETCH QUESTIONS CURRENTLY IN THE SELECTED QUIZ
$existing_questions = [];
if ($quiz_id) {
    $stmt_list = $conn->prepare("SELECT question_id, question_text FROM questions WHERE quiz_id = ? ORDER BY question_id DESC");
    $stmt_list->bind_param("i", $quiz_id);
    $stmt_list->execute();
    $existing_questions = $stmt_list->get_result()->fetch_all(MYSQLI_ASSOC);
}

if (isset($_GET['status'])) {
    $message = urldecode($_GET['msg']);
    $message_type = $_GET['status'];
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
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .sidebar { height: 100vh; background: #0f172a; color: white; position: fixed; width: 260px; z-index: 1000; }
        .main-content { margin-left: 260px; padding: 40px; }
        .nav-link { color: #94a3b8; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { color: #fff; background: #1e293b; }
        .quiz-card { border-left: 5px solid #3b82f6; }
        .question-list-scroll { max-height: 520px; overflow-y: auto; }
        .bank-row:hover { background-color: #f1f5f9; cursor: pointer; }
        
        /* Tab Styling */
        .nav-tabs .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 25px; }
        .nav-tabs .nav-link.active { color: #3b82f6; border-bottom: 3px solid #3b82f6; background: transparent; }
        
        @media (max-width: 992px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <div class="sidebar d-flex flex-column p-4 shadow">
        <div class="mb-4">
            <h5 class="fw-bold text-info"><i class="fas fa-book-open me-2"></i>CBT ADMIN</h5>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="<?php echo $dashboard_link; ?>" class="nav-link"><i class="fas fa-columns me-2"></i> Dashboard</a></li>
            <li class="nav-item"><a href="create_quiz.php" class="nav-link"><i class="fas fa-plus-circle me-2"></i> New Quiz</a></li>
            <li class="nav-item"><a href="add_questions.php" class="nav-link active"><i class="fas fa-tasks me-2"></i> Questions</a></li>
        </ul>
        <hr>
        <div class="mt-auto">
            <a href="logout.php" class="text-danger text-decoration-none small"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        
        <?php if($quiz_meta): ?>
        <div class="card quiz-card shadow-sm mb-4 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($quiz_meta['title']); ?></h4>
                    <p class="text-muted small mb-0">
                        Class: <b><?php echo $quiz_meta['target_class']; ?></b> | 
                        Subject: <b><?php echo $quiz_meta['subject_name']; ?></b>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning fw-bold" data-bs-toggle="modal" data-bs-target="#bankModal">
                        <i class="fas fa-university me-2"></i> Question Bank
                    </button>
                    <a href="preview_quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-dark"><i class="fas fa-eye me-1"></i> Preview</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <?php if($message): ?>
    <div class="alert alert-<?php echo ($message_type == 'success' ? 'success' : 'danger'); ?> d-flex align-items-center shadow-sm alert-dismissible fade show" role="alert">
        <?php if($message_type == 'success'): ?>
            <i class="fas fa-check-circle fa-lg me-3"></i>
        <?php else: ?>
            <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
        <?php endif; ?>
        <div>
            <strong>Process Update:</strong> <?php echo htmlspecialchars($message); ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

                <form action="add_questions.php" method="get" class="mb-4">
                    <label class="form-label fw-bold small text-muted">SELECT A QUIZ TO MANAGE</label>
                    <select name="quiz_id" class="form-select form-select-lg shadow-sm" onchange="this.form.submit()">
                        <option value="">-- Select Quiz --</option>
                        <?php foreach ($quizzes as $q): ?>
                            <option value="<?php echo $q['quiz_id']; ?>" <?php echo ($quiz_id == $q['quiz_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($q['title']); ?> (<?php echo $q['target_class']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($quiz_id): ?>
                    <hr class="my-4">

                    <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-pane" type="button">
                                <i class="fas fa-keyboard me-2"></i>Add Single Question
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk-pane" type="button">
                                <i class="fas fa-file-csv me-2"></i>Bulk CSV Upload
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="managementTabsContent">
                        
                        <div class="tab-pane fade show active" id="manual-pane">
                            <div class="row g-4">
                                
                                <div class="col-lg-5">
                                    <h6 class="fw-bold mb-3 text-secondary">ACTIVE QUESTIONS (<?php echo count($existing_questions); ?>)</h6>
                                    <?php if(!empty($existing_questions)): ?>
        <a href="export_questions.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-sm btn-outline-success">
            <i class="fas fa-file-export me-1"></i> Export to CSV
        </a>
    <?php endif; ?>
                     <div class="question-list-scroll border bg-white rounded shadow-sm">
                    <div class="list-group list-group-flush">
                     <?php if(empty($existing_questions)): ?>
                    <div class="p-4 text-center text-muted">No questions added yet.</div>
                    <?php endif; ?>
                    <?php foreach ($existing_questions as $eq): ?>
                     <div class="list-group-item p-3">
                     <p class="mb-2 small fw-bold text-dark"><?php echo htmlspecialchars($eq['question_text']); ?></p>
                    <div class="d-flex gap-3">
                     <a href="edit_question.php?id=<?php echo $eq['question_id']; ?>&quiz_id=<?php echo $quiz_id; ?>" class="text-primary small text-decoration-none"><i class="fas fa-edit"></i> Edit</a>
                    <a href="#" 
   class="text-danger small text-decoration-none btn-delete-trigger" 
   data-bs-toggle="modal" 
   data-bs-target="#deleteConfirmModal" 
   data-qid="<?php echo $eq['question_id']; ?>" 
   data-quizid="<?php echo $quiz_id; ?>">
   <i class="fas fa-trash"></i> Delete
</a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-7">
                                    <div class="p-4 bg-white border rounded shadow-sm">
                                        <h6 class="fw-bold mb-3 text-primary">New Question Form</h6>
                                        <form action="add_questions.php?quiz_id=<?php echo $quiz_id; ?>" method="post">
                                            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                                            <div class="mb-3">
                                                <textarea name="question_text" class="form-control" rows="4" placeholder="Type your question here..." required></textarea>
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <label class="small fw-bold">Option A</label>
                                                    <input type="text" name="option_a" class="form-control" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="small fw-bold">Option B</label>
                                                    <input type="text" name="option_b" class="form-control" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="small fw-bold">Option C</label>
                                                    <input type="text" name="option_c" class="form-control">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="small fw-bold">Option D</label>
                                                    <input type="text" name="option_d" class="form-control">
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label class="small fw-bold text-success">Correct Answer</label>
                                                <select name="correct_answer" class="form-select border-success" required>
                                                    <option value="">-- Select Correct Option --</option>
                                                    <option value="A">A</option>
                                                    <option value="B">B</option>
                                                    <option value="C">C</option>
                                                    <option value="D">D</option>
                                                </select>
                                            </div>
                                            <button type="submit" name="submit_question" class="btn btn-primary w-100 fw-bold py-2">Save Question</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="bulk-pane">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="fw-bold"><i class="fas fa-lightbulb text-warning me-2"></i>Bulk Upload Instructions</h5>
                                    <p class="text-muted small">Importing many questions at once is easy if you follow the correct format.</p>
                                    
                                    <div class="alert alert-info py-2">
                                        <i class="fas fa-download me-2"></i> 
                                        <a href="sample_questions.csv.php" class="alert-link small">
    <i class="fas fa-file-excel me-1"></i>Download Excel/CSV Template
</a>
                                    </div>

                                    <h6 class="fw-bold small mt-4 text-dark uppercase">Expected CSV Columns:</h6>
                                    
                                    <div class="table-responsive mt-2">
                                        <table class="table table-bordered table-sm small text-center bg-light">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Question</th>
                                                    <th>Option A</th>
                                                    <th>Option B</th>
                                                    <th>Option C</th>
                                                    <th>Option D</th>
                                                    <th>Answer (A-D)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Capital of France?</td>
                                                    <td>Berlin</td>
                                                    <td>Paris</td>
                                                    <td>Madrid</td>
                                                    <td>Rome</td>
                                                    <td class="fw-bold text-success">B</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-danger small mt-2"><b>Important:</b> Ensure there are no commas inside your question text as this can break the CSV structure. Use semicolons (;) if punctuation is needed.</p>
                                </div>

                                <div class="col-md-6 border-start ps-lg-5">
                                    <div class="bg-light p-5 rounded text-center border mt-4 mt-md-0">
                                        <i class="fas fa-upload fa-4x text-primary mb-3"></i>
                                        <h5 class="fw-bold">Upload Your File</h5>
                                        <p class="text-muted small mb-4">Select your prepared .CSV file to begin the import.</p>
                                        
                                        <form action="add_questions.php?quiz_id=<?php echo $quiz_id; ?>" method="post" enctype="multipart/form-data">
                                            <input type="file" name="csv_file" class="form-control mb-3" accept=".csv" required>
                                            <button type="submit" name="submit_csv" class="btn btn-dark w-100 py-2 fw-bold">
                                                <i class="fas fa-file-import me-2"></i>Start Bulk Import
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-arrow-up fa-3x mb-3 opacity-25"></i>
                        <h5>Please select a quiz to start adding questions.</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0">
                <form action="add_questions.php?quiz_id=<?php echo $quiz_id; ?>" method="post">
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
        const deleteUrl = `delete_question.php?id=${qid}&quiz_id=${quizId}`;
        
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