<?php
// edit_quiz.php - Final Reviewed Version
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user_id = $_SESSION["id"] ?? $_SESSION["user_id"] ?? null;
$user_type = $_SESSION["user_type"] ?? null;

// Security Check
if (!isset($_SESSION["loggedin"]) || !in_array($user_type, ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

$quiz_id = $title = $time_limit = $max_attempts = $target_class = $subject_name = $academic_year = $term = $exam_type = "";
$error_message = $success_message = "";
$dashboard_link = ($user_type === 'admin') ? "admin_dashboard.php" : "teacher_dashboard.php";

// 1. Fetch Dynamic Data into Arrays
$classes_list = $conn->query("SELECT class_name FROM classes ORDER BY class_name ASC")->fetch_all(MYSQLI_ASSOC);
$subjects_list = $conn->query("SELECT subject_name FROM subjects ORDER BY subject_name ASC")->fetch_all(MYSQLI_ASSOC);
$sessions_list = $conn->query("SELECT session_name FROM academic_years ORDER BY session_name DESC")->fetch_all(MYSQLI_ASSOC);

// 2. Handle POST Request (Update Quiz)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["quiz_id"])) {
    $quiz_id = (int)$_POST["quiz_id"];
    $new_title = trim($_POST["title"]);
    $new_subject = trim($_POST["subject_name"]);
    $new_class = trim($_POST["target_class"]);
    $new_session = trim($_POST["academic_year"]);
    $new_term = trim($_POST["term"]);
    $new_type = trim($_POST["exam_type"]);
    $new_time = (int)$_POST["time_limit_minutes"];

    if (empty($new_title) || empty($new_class) || empty($new_session)) {
        $error_message = "All primary fields (Title, Class, Session) are required.";
    } else {
        $sql = "UPDATE quizzes SET title = ?, subject_name = ?, target_class = ?, academic_year = ?, term = ?, exam_type = ?, time_limit_minutes = ? WHERE quiz_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssssii", $new_title, $new_subject, $new_class, $new_session, $new_term, $new_type, $new_time, $quiz_id);
            if ($stmt->execute()) {
                $success_message = "Quiz updated successfully! Redirecting...";
                header("Refresh: 2; url=$dashboard_link");
            } else {
                $error_message = "Database Error: " . $conn->error;
            }
        }
    }
}

// 3. Handle GET Request (Fetch data)
if (isset($_GET["id"]) && empty($success_message)) {
    $quiz_id = (int)$_GET["id"];
    $sql = ($user_type === 'admin') ? 
        "SELECT * FROM quizzes WHERE quiz_id = ?" : 
        "SELECT * FROM quizzes WHERE quiz_id = ? AND created_by = ?";
    
    $stmt = $conn->prepare($sql);
    if ($user_type === 'admin') {
        $stmt->bind_param("i", $quiz_id);
    } else {
        $stmt->bind_param("ii", $quiz_id, $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($quiz = $result->fetch_assoc()) {
        $title = $quiz['title'];
        $time_limit = $quiz['time_limit_minutes'];
        $max_attempts = $quiz['max_attempts'];
        $target_class = $quiz['target_class'];
        $subject_name = $quiz['subject_name'];
        $academic_year = $quiz['academic_year'];
        $term = $quiz['term'];
        $exam_type = $quiz['exam_type'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Quiz Settings | CBT Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .header-box { background: #0f172a; color: white; border-radius: 16px 16px 0 0; }
        .form-label { font-weight: 700; color: #334155; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.5rem; }
        .readonly-field { background-color: #e9ecef !important; cursor: not-allowed; opacity: 0.8; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="header-box p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 h5"><i class="fas fa-edit me-2 text-info"></i> Edit Quiz Settings</h3>
                    </div>
                    <a href="<?php echo $dashboard_link; ?>" class="btn btn-sm btn-outline-light">Back</a>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <form action="edit_quiz.php" method="post">
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                        
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label">Quiz Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <select name="subject_name" class="form-select" required>
                                    <option value="">-- Select Subject --</option>
                                    <?php foreach($subjects_list as $s): ?>
                                        <option value="<?php echo $s['subject_name']; ?>" <?php echo ($subject_name == $s['subject_name']) ? 'selected' : ''; ?>>
                                            <?php echo $s['subject_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-primary">Target Class</label>
                                <select name="target_class" class="form-select border-primary" required>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach($classes_list as $c): ?>
                                        <option value="<?php echo $c['class_name']; ?>" <?php echo ($target_class == $c['class_name']) ? 'selected' : ''; ?>>
                                            <?php echo $c['class_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Academic Session</label>
                                <select name="academic_year" class="form-select" required>
                                    <?php foreach($sessions_list as $y): ?>
                                        <option value="<?php echo $y['session_name']; ?>" <?php echo ($academic_year == $y['session_name']) ? 'selected' : ''; ?>>
                                            <?php echo $y['session_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Term</label>
                                <select name="term" class="form-select">
                                    <option value="1st Term" <?php echo ($term == '1st Term') ? 'selected' : ''; ?>>1st Term</option>
                                    <option value="2nd Term" <?php echo ($term == '2nd Term') ? 'selected' : ''; ?>>2nd Term</option>
                                    <option value="3rd Term" <?php echo ($term == '3rd Term') ? 'selected' : ''; ?>>3rd Term</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Exam Type</label>
                                <select name="exam_type" class="form-select">
                                    <option value="Mid-Term" <?php echo ($exam_type == 'Mid-Term') ? 'selected' : ''; ?>>Mid-Term</option>
                                    <option value="Term Examination" <?php echo ($exam_type == 'Term Examination') ? 'selected' : ''; ?>>Term Examination</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Time Limit (Mins)</label>
                                <input type="number" name="time_limit_minutes" class="form-control" value="<?php echo $time_limit; ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted">Max Attempts (Read-Only)</label>
                                <input type="number" name="max_attempts" class="form-control readonly-field" value="<?php echo $max_attempts; ?>" readonly>
                            </div>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-dark btn-lg py-3 fw-bold">Update Quiz Configuration</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>