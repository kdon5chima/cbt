<?php
// ==========================================================
// start_quiz.php - FULLY SYNCHRONIZED
// ==========================================================
error_reporting(E_ALL); 
ini_set('display_errors', 1);
require_once "db_connect.php"; 

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'participant') {
    header("location: login.php"); exit;
}

// 1. Initialize core variables to prevent "Undefined Variable" warnings
$user_id = $_SESSION["user_id"];
$full_name = "Student";
$class_year = "N/A";
$attempt_id = 0;
$contestant_number = "N/A";
$total_questions = 0;
$current_index = 0;
$no_questions = false;
$js_time_remaining = 0;
$current_question = null;
$selected_option = "";

// 2. Fetch User Data
$stmt_user = $conn->prepare("SELECT full_name, class_year FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
if ($user_data) {
    $full_name = $user_data['full_name'];
    $class_year = $user_data['class_year'];
}

// 3. Determine Quiz ID
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : ($_SESSION['current_quiz']['quiz_id'] ?? null);
if (!$quiz_id) { header("location: participant_dashboard.php"); exit; }

// 4. Dynamic Question Check
$stmt_count = $conn->prepare("SELECT COUNT(*) as q_count FROM questions WHERE quiz_id = ?");
$stmt_count->bind_param("i", $quiz_id);
$stmt_count->execute();
$q_count_actual = $stmt_count->get_result()->fetch_assoc()['q_count'];

if ($q_count_actual == 0) {
    $no_questions = true;
} else {
    // 5. Setup or Resume Session
    if (!isset($_SESSION['current_quiz']) || $_SESSION['current_quiz']['quiz_id'] != $quiz_id) {
        // Check for existing unfinished attempt
        $stmt_check = $conn->prepare("SELECT attempt_id, contestant_number FROM attempts WHERE user_id = ? AND quiz_id = ? AND end_time IS NULL LIMIT 1");
        $stmt_check->bind_param("ii", $user_id, $quiz_id);
        $stmt_check->execute();
        $active_attempt = $stmt_check->get_result()->fetch_assoc();

        if ($active_attempt) {
            $attempt_id = $active_attempt['attempt_id'];
            $contestant_number = $active_attempt['contestant_number'];
        } else {
            $contestant_number = isset($_GET['contestant_number']) ? trim($_GET['contestant_number']) : 'N/A';
            $stmt_insert = $conn->prepare("INSERT INTO attempts (user_id, quiz_id, start_time, contestant_number) VALUES (?, ?, NOW(), ?)");
            $stmt_insert->bind_param("iis", $user_id, $quiz_id, $contestant_number);
            $stmt_insert->execute();
            $attempt_id = $conn->insert_id;
        }

        // Fetch ALL questions
        $questions = [];
        $quiz_title = "Quiz";
        $stmt_q = $conn->prepare("SELECT q.question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, z.title 
                                 FROM questions q JOIN quizzes z ON q.quiz_id = z.quiz_id 
                                 WHERE q.quiz_id = ? ORDER BY q.question_id ASC");
        $stmt_q->bind_param("i", $quiz_id);
        $stmt_q->execute();
        $res_q = $stmt_q->get_result();
        while ($row = $res_q->fetch_assoc()) { 
            $questions[] = $row; 
            $quiz_title = $row['title']; 
        }

        $_SESSION['current_quiz'] = [
            'quiz_id' => $quiz_id,
            'attempt_id' => $attempt_id,
            'title' => $quiz_title,
            'questions' => $questions,
            'user_answers' => array_fill_keys(array_column($questions, 'question_id'), ""), 
            'current_question_index' => 0,
            'total_questions' => count($questions),
            'contestant_number' => $contestant_number,
            'full_name' => $full_name,
            'class_year' => $class_year,
            'auto_move' => true 
        ];
    }

    // Assign reference variables from Session
    $current_quiz = &$_SESSION['current_quiz'];
    $current_index = &$current_quiz['current_question_index'];
    $total_questions = $current_quiz['total_questions'];
    $attempt_id = $current_quiz['attempt_id'];
    $contestant_number = $current_quiz['contestant_number'];

    // 6. Handle Navigation and Submissions
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['current_answer'])) {
            $current_q_id = $current_quiz['questions'][$current_index]['question_id'];
            $current_quiz['user_answers'][$current_q_id] = $_POST['current_answer'];
        }

        if (isset($_POST['update_toggle'])) {
            $current_quiz['auto_move'] = ($_POST['update_toggle'] === 'true');
            exit;
        }

        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            if ($action === 'quit_quiz') { header("location: participant_dashboard.php"); exit; }
            if ($action === 'next' && $current_index < $total_questions - 1) { $current_index++; } 
            elseif ($action === 'prev' && $current_index > 0) { $current_index--; } 
            elseif ($action === 'jump') { $current_index = (int)$_POST['jump_index']; } 
            elseif (in_array($action, ['finish', 'timeout'])) {
                $_SESSION['final_submission'] = [
                    'attempt_id' => $current_quiz['attempt_id'],
                    'quiz_id' => $current_quiz['quiz_id'],
                    'answers' => $current_quiz['user_answers'], 
                    'contestant_number' => $current_quiz['contestant_number']
                ];
                unset($_SESSION['current_quiz']);
                session_write_close();
                header("location: submit_quiz.php");
                exit;
            }
        }
        header("Location: start_quiz.php");
        exit;
    }

    // 7. Timer Logic
    $stmt_t = $conn->prepare("SELECT q.time_limit_minutes, UNIX_TIMESTAMP(a.start_time) AS start_time_unix 
                             FROM attempts a JOIN quizzes q ON a.quiz_id = q.quiz_id 
                             WHERE a.attempt_id = ?");
    $stmt_t->bind_param("i", $attempt_id);
    $stmt_t->execute();
    $row_t = $stmt_t->get_result()->fetch_assoc();
    $elapsed = time() - ($row_t['start_time_unix'] ?? time());
    $total_allowed = ($row_t['time_limit_minutes'] ?? 0) * 60;
    $js_time_remaining = max(0, $total_allowed - $elapsed);

    // 8. Prepare Current Question for HTML
    if (isset($current_quiz['questions'][$current_index])) {
        $current_question = $current_quiz['questions'][$current_index];
        $selected_option = $current_quiz['user_answers'][$current_question['question_id']] ?? "";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz: <?php echo htmlspecialchars($current_quiz['title'] ?? 'CBT Portal'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .sticky-nav { position: sticky; top: 0; background: white; z-index: 1000; border-bottom: 2px solid #e9ecef; padding: 12px 0; }
        .option-card { background: white; padding: 20px; border-radius: 12px; border: 2px solid #eee; display: flex; align-items: center; transition: 0.2s; cursor: pointer; }
        .option-wrapper input:checked + .option-card { border-color: #0d6efd; background-color: #f0f7ff; transform: scale(1.01); }
        .option-letter { width: 40px; height: 40px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin-right: 15px; font-weight: bold; }
        .option-wrapper input:checked + .option-card .option-letter { background: #0d6efd; color: white; }
        .option-wrapper input { display: none; }
        .nav-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(45px, 1fr)); gap: 8px; }
        .btn-nav { height: 45px; border-radius: 8px; font-weight: bold; border: 1px solid #ddd; background: white; }
        .btn-nav.active { background: #0d6efd; color: white; border-color: #0d6efd; border-width: 2px; }
        .btn-nav.done { background: #198754; color: white; border-color: #198754; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .animate-pulse { animation: pulse 1s infinite; }
        .user-info-badge { font-size: 0.8rem; background: #e9ecef; padding: 4px 12px; border-radius: 50px; color: #495057; display: inline-block; }
    </style>
</head>
<body>
<?php if ($no_questions): ?>
    <div class="modal fade" id="emptyModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-body text-center p-5">
                    <i class="fas fa-folder-open text-muted mb-4" style="font-size: 4rem; opacity: 0.5;"></i>
                    <h3 class="fw-bold">Subject Not Ready</h3>
                    <p class="text-muted fs-5">There are no questions in the bank for this subject.</p>
                    <div class="mt-4"><a href="participant_dashboard.php" class="btn btn-primary btn-lg rounded-pill px-5">Go Back</a></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>new bootstrap.Modal(document.getElementById('emptyModal')).show();</script>
<?php else: ?>
<div class="sticky-nav shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm me-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#quitModal">
                <i class="fas fa-arrow-left me-1"></i> Exit
            </button>
            <div>
                <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($current_quiz['title']); ?></h5>
                <div class="d-flex gap-2 align-items-center mt-1">
                    <span class="user-info-badge"><i class="fas fa-user me-1 text-primary"></i> <?php echo htmlspecialchars($full_name); ?></span>
                    <span class="user-info-badge"><i class="fas fa-id-badge me-1 text-primary"></i> No: <?php echo htmlspecialchars($contestant_number); ?></span>
                </div>
            </div>
        </div>
        <div id="timer-display" class="badge bg-primary fs-5 px-3 py-2 rounded-pill">00:00</div>
    </div>
</div>

<div class="container mt-4" style="max-width: 800px;">
    <div class="progress mb-4" style="height: 10px; border-radius: 20px;">
        <div class="progress-bar bg-success" style="width: <?php echo $total_questions > 0 ? (($current_index+1)/$total_questions)*100 : 0; ?>%"></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body d-flex justify-content-between align-items-center py-2">
            <span class="text-muted small fw-bold"><i class="fas fa-bolt me-1 text-warning"></i> Auto-Next Mode</span>
            <div class="form-switch">
                <input class="form-check-input" type="checkbox" id="autoMoveToggle" <?php echo ($current_quiz['auto_move'] ?? true) ? 'checked' : ''; ?>>
            </div>
        </div>
    </div>

    <form id="quizForm" method="POST">
        <input type="hidden" name="action" id="formAction" value="">
        <input type="hidden" name="jump_index" id="jumpIndex" value="">
        
        <?php if ($current_question): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h6 class="text-primary fw-bold mb-3">Question <?php echo $current_index + 1; ?> / <?php echo $total_questions; ?></h6>
            <h3 class="fw-bold mb-4"><?php echo htmlspecialchars($current_question['question_text']); ?></h3>
            <div class="options">
                <?php
                $opts = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
                foreach ($opts as $key => $col) {
                    if (empty($current_question[$col])) continue;
                    $checked = ($selected_option === $key) ? 'checked' : '';
                    echo "
                    <label class='option-wrapper d-block mb-3'>
                        <input type='radio' name='current_answer' value='$key' $checked onchange='handleAnswerClick()'>
                        <div class='option-card'>
                            <span class='option-letter'>$key</span>
                            <span class='option-text fs-5'>" . htmlspecialchars($current_question[$col]) . "</span>
                        </div>
                    </label>";
                }
                ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mb-5">
            <button type="button" onclick="navigate('prev')" class="btn btn-lg btn-light border px-4 shadow-sm" <?php echo ($current_index == 0) ? 'disabled' : ''; ?>>Back</button>
            <?php if ($current_index < $total_questions - 1): ?>
                <button type="button" onclick="navigate('next')" class="btn btn-lg btn-primary px-5 shadow">Next</button>
            <?php else: ?>
                <button type="button" class="btn btn-lg btn-success px-5 shadow" data-bs-toggle="modal" data-bs-target="#finishModal">Finish Quiz</button>
            <?php endif; ?>
        </div>
    </form>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-5">
        <h6 class="text-muted fw-bold mb-3 small">Question Navigator</h6>
        <div class="nav-grid">
            <?php for ($i = 0; $i < $total_questions; $i++): 
                $q_id = $current_quiz['questions'][$i]['question_id'];
                $status = ($i == $current_index) ? 'active' : (!empty($current_quiz['user_answers'][$q_id]) ? 'done' : '');
            ?>
                <button type="button" onclick="jumpTo(<?php echo $i; ?>)" class="btn-nav w-100 <?php echo $status; ?>"><?php echo $i + 1; ?></button>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="quitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-body text-center p-5">
                <i class="fas fa-door-open mb-3 text-warning" style="font-size: 3rem;"></i>
                <h3 class="fw-bold">Leave Subject?</h3>
                <p class="text-muted">Your progress is saved.</p>
                <div class="d-grid gap-2">
                    <form method="POST"><button type="submit" name="action" value="quit_quiz" class="btn btn-warning btn-lg w-100 fw-bold">Return to Dashboard</button></form>
                    <button type="button" class="btn btn-light btn-lg text-muted" data-bs-dismiss="modal">Keep Quiz Open</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="finishModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-body text-center p-5">
                <i class="fas fa-check-circle mb-3 text-success" style="font-size: 3rem;"></i>
                <h3 class="fw-bold">Submit Quiz?</h3>
                <div class="d-grid gap-2 mt-4">
                    <button type="button" class="btn btn-success btn-lg fw-bold" onclick="submitFinal()">Yes, Submit</button>
                    <button type="button" class="btn btn-light btn-lg text-muted" data-bs-dismiss="modal">Review Answers</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const form = document.getElementById('quizForm');
    const toggle = document.getElementById('autoMoveToggle');
    const actionInput = document.getElementById('formAction');
    const jumpInput = document.getElementById('jumpIndex');

    toggle.addEventListener('change', () => {
        const fd = new FormData();
        fd.append('update_toggle', toggle.checked);
        fetch(window.location.href, { method: 'POST', body: fd });
    });

    function navigate(action) { 
        actionInput.value = action; 
        form.submit(); 
    }

    function jumpTo(index) { 
        actionInput.value = 'jump'; 
        jumpInput.value = index; 
        form.submit(); 
    }

    function handleAnswerClick() { 
        if (toggle.checked) { 
            <?php if ($current_index < $total_questions - 1): ?>
            setTimeout(() => { navigate('next'); }, 400); 
            <?php endif; ?>
        } 
    }

    function submitFinal() { 
        actionInput.value = 'finish'; 
        form.submit(); 
    }

    let timeLeft = <?php echo (int)$js_time_remaining; ?>;
    const timerDisplay = document.getElementById('timer-display');
    function tick() {
        if (timeLeft <= 0) { 
            actionInput.value = 'timeout'; 
            form.submit(); 
            return; 
        }
        timeLeft--;
        const m = Math.floor(timeLeft / 60).toString().padStart(2, '0');
        const s = (timeLeft % 60).toString().padStart(2, '0');
        timerDisplay.textContent = `${m}:${s}`;
        if (timeLeft < 60) {
            timerDisplay.classList.remove('bg-primary');
            timerDisplay.classList.add('bg-danger', 'animate-pulse');
        }
    }
    tick();
    setInterval(tick, 1000);
</script>
<?php endif; ?>
</body>
</html>