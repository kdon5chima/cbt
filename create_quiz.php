<?php
// create_quiz.php - Final Reviewed Version with Read-Only Attempts
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

$message = "";
$message_type = ""; 

// --- 1. Fetch Dynamic Options into Arrays (Prevents pointer issues) ---
$classes_list = $conn->query("SELECT class_name FROM classes ORDER BY class_name ASC")->fetch_all(MYSQLI_ASSOC);
$subjects_list = $conn->query("SELECT subject_name FROM subjects ORDER BY subject_name ASC")->fetch_all(MYSQLI_ASSOC);
$sessions_list = $conn->query("SELECT * FROM academic_years ORDER BY session_name DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch Global "Active" Year
$active_year_query = $conn->query("SELECT session_name FROM academic_years WHERE is_current = 1 LIMIT 1");
$default_year = ($active_year_query->num_rows > 0) ? $active_year_query->fetch_assoc()['session_name'] : "";

// --- 2. Handle Form Submission ---
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $title = trim($_POST["title"]);
    $subject_name = trim($_POST["subject_name"]);
    $target_class = trim($_POST["target_class"]); 
    $academic_year = trim($_POST["academic_year"]);
    $term = trim($_POST["term"]);
    $exam_type = trim($_POST["exam_type"]);
    
    $total_questions = (int) $_POST["total_questions"]; 
    $time_limit = (int) $_POST["time_limit_minutes"];
    $max_attempts = (int) $_POST["max_attempts"]; // This will be 1 from the readonly input
    $created_by = $_SESSION["id"] ?? $_SESSION["user_id"]; 

    if (empty($title) || empty($target_class) || empty($subject_name) || empty($academic_year)) {
        $message = "Please fill in all required fields.";
        $message_type = "danger";
    } else {
        $sql = "INSERT INTO quizzes (title, subject_name, target_class, academic_year, term, exam_type, total_questions, time_limit_minutes, max_attempts, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("ssssssiiii", $title, $subject_name, $target_class, $academic_year, $term, $exam_type, $total_questions, $time_limit, $max_attempts, $created_by); 

            if($stmt->execute()){
                $new_id = $conn->insert_id;
                $message = "Success! Quiz structure defined.";
                $message_type = "success";
                $follow_up_link = "<p class='mt-3'><a href='add_questions.php?quiz_id=$new_id' class='btn btn-info'>Add Questions Now</a></p>";
            } else {
                $message = "Error: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
    }
}
$dashboard_link = ($_SESSION["user_type"] === 'admin') ? "admin_dashboard.php" : "teacher_dashboard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Quiz | CBT Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; overflow: hidden; }
        .form-label { font-size: 0.85rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 0.5rem; }
        .bg-gradient-primary { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .readonly-field { background-color: #e9ecef !important; cursor: not-allowed; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="btn btn-outline-light btn-sm" href="<?php echo $dashboard_link; ?>">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <span class="navbar-text text-white-50 small">Date: <?php echo date("d M Y"); ?></span>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="card-header bg-gradient-primary text-white p-4 text-center">
                        <h2 class="h4 mb-0">Create New Quiz Structure</h2>
                        <p class="mb-0 opacity-75 small">Assign this quiz to a specific class group.</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if(!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?> shadow-sm"><?php echo $message; ?></div>
                            <?php if(isset($follow_up_link)) echo $follow_up_link; ?>
                        <?php endif; ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="row g-4">
                                <div class="col-md-12">
                                    <label class="form-label">Full Quiz Title</label>
                                    <input type="text" name="title" class="form-control form-control-lg" placeholder="e.g. Year 4 English Mid-Term" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Subject</label>
                                    <select name="subject_name" class="form-select" required>
                                        <option value="">-- Choose Subject --</option>
                                        <?php foreach($subjects_list as $s): ?>
                                            <option value="<?php echo htmlspecialchars($s['subject_name']); ?>">
                                                <?php echo htmlspecialchars($s['subject_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-primary">Target Class</label>
                                    <select name="target_class" class="form-select border-primary" required>
                                        <option value="">-- Select Class --</option>
                                        <?php foreach($classes_list as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c['class_name']); ?>">
                                                <?php echo htmlspecialchars($c['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Academic Session</label>
                                    <select name="academic_year" class="form-select" required>
                                        <?php foreach($sessions_list as $y): ?>
                                            <option value="<?php echo $y['session_name']; ?>" <?php echo ($y['session_name'] == $default_year) ? 'selected' : ''; ?>>
                                                <?php echo $y['session_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Term</label>
                                    <select name="term" class="form-select" required>
                                        <option value="1st Term">1st Term</option>
                                        <option value="2nd Term">2nd Term</option>
                                        <option value="3rd Term">3rd Term</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Exam Type</label>
                                    <select name="exam_type" class="form-select" required>
                                        <option value="Mid-Term">Mid-Term</option>
                                        <option value="Term Examination">Term Examination</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Duration (Minutes)</label>
                                    <input type="number" name="time_limit_minutes" class="form-control" value="40" required min="1">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">No. of Questions</label>
                                    <input type="number" name="total_questions" class="form-control" placeholder="e.g. 50" required min="1">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label text-muted">Max Attempts</label>
                                    <input type="number" name="max_attempts" class="form-control readonly-field" value="1" readonly>
                                    <small class="text-muted">Set to 1 by default.</small>
                                </div>
                            </div>

                            <div class="mt-5">
                                <button type="submit" class="btn btn-success btn-lg w-100 shadow-sm py-3 fw-bold">
                                    Create Quiz & Continue <i class="fas fa-chevron-right ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>