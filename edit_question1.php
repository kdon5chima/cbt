<?php
// edit_question.php - Multi-Role Supported with Warning Fix
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Robust Security Check & Key Mapping
// This prevents the "Undefined array key 'id'" warning
$user_id = $_SESSION["id"] ?? $_SESSION["user_id"] ?? null;
$user_type = $_SESSION["user_type"] ?? null;

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($user_type, ['admin', 'teacher'])) {
    header("location: login.php");
    exit;
}

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message = "";
$message_type = "";
$question = null;
$target_dir = "images/questions/";

if (!$question_id) {
    header("location: add_questions1.php"); 
    exit;
}

// 2. Fetch question data with Ownership Check for Teachers
$sql_fetch = "SELECT q.*, qz.created_by FROM questions q 
              JOIN quizzes qz ON q.quiz_id = qz.quiz_id 
              WHERE q.question_id = ?";

if ($stmt_fetch = $conn->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("i", $question_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    
    if ($result_fetch->num_rows == 1) {
        $question = $result_fetch->fetch_assoc();
        
        // If user is a teacher, verify they own this quiz
        if ($user_type === 'teacher' && $question['created_by'] != $user_id) {
            header("location: add_questions1.php?status=danger&msg=Unauthorized+access");
            exit;
        }
    } else {
        $message = "Question not found.";
        $message_type = "danger";
    }
    $stmt_fetch->close();
}

// 3. Process Update
if($_SERVER["REQUEST_METHOD"] == "POST" && $question && $question_id){
    $question_text = trim($_POST["question_text"]);
    $option_a = trim($_POST["option_a"]);
    $option_b = trim($_POST["option_b"]);
    $option_c = trim($_POST["option_c"]);
    $option_d = trim($_POST["option_d"]);
    $correct_answer = $_POST["correct_answer"];
    
    $new_image_path = $question['image_path']; 
    $file_upload_error = false;

    // Handle Image Removal
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == 'yes') {
        if (!empty($question['image_path']) && file_exists($question['image_path'])) {
            unlink($question['image_path']);
        }
        $new_image_path = null;
    } 
    
    // Handle New Image Upload
    if (isset($_FILES["question_image"]) && $_FILES["question_image"]["error"] == 0) {
        $ext = strtolower(pathinfo($_FILES["question_image"]["name"], PATHINFO_EXTENSION));
        $unique_name = uniqid('img_', true) . '.' . $ext;
        $target_path = $target_dir . $unique_name;

        if (move_uploaded_file($_FILES["question_image"]["tmp_name"], $target_path)) {
            // Delete old image if it exists
            if (!empty($question['image_path']) && file_exists($question['image_path'])) {
                unlink($question['image_path']);
            }
            $new_image_path = $target_path;
        } else {
            $message = "Error uploading file."; $message_type = "danger"; $file_upload_error = true;
        }
    }

    if (!$file_upload_error) { 
        $sql_update = "UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, image_path=? WHERE question_id=?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("sssssssi", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $new_image_path, $question_id);
            if($stmt_update->execute()){
                header("Location: add_questions1.php?quiz_id=" . $question['quiz_id'] . "&status=success&msg=Question+Updated");
                exit;
            } else {
                $message = "Update failed: " . $conn->error; $message_type = "danger";
            }
        }
    }
}

$dashboard_link = ($user_type === 'admin') ? "admin_dashboard.php" : "teacher_dashboard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Question</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { padding-top: 70px; background-color: #f4f7f6; }
        .card { border-radius: 15px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark fixed-top shadow">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?php echo $dashboard_link; ?>">QUIZ SYSTEM</a>
            <a href="add_questions1.php?quiz_id=<?php echo $question['quiz_id'] ?? ''; ?>" class="btn btn-outline-light btn-sm">Exit Editor</a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Question #<?php echo $question_id; ?></h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <?php if($question): ?>
                        <form action="edit_question1.php?id=<?php echo $question_id; ?>" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Question Text</label>
                                <textarea name="question_text" class="form-control" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Options</label>
                                    <input type="text" name="option_a" class="form-control mb-2" value="<?php echo htmlspecialchars($question['option_a']); ?>" required placeholder="Option A">
                                    <input type="text" name="option_b" class="form-control mb-2" value="<?php echo htmlspecialchars($question['option_b']); ?>" required placeholder="Option B">
                                    <input type="text" name="option_c" class="form-control mb-2" value="<?php echo htmlspecialchars($question['option_c']); ?>" placeholder="Option C">
                                    <input type="text" name="option_d" class="form-control mb-2" value="<?php echo htmlspecialchars($question['option_d']); ?>" placeholder="Option D">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Correct Answer</label>
                                    <select name="correct_answer" class="form-select mb-4" required>
                                        <?php foreach(['A','B','C','D'] as $opt): ?>
                                            <option value="<?php echo $opt; ?>" <?php echo ($question['correct_answer'] == $opt) ? 'selected' : ''; ?>>Option <?php echo $opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label class="form-label fw-bold">Image Attachment</label>
                                    <?php if($question['image_path']): ?>
                                        <div class="mb-2 small border p-2 bg-light">
                                            <img src="<?php echo $question['image_path']; ?>" style="max-height: 80px;" class="d-block mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="remove_image" value="yes" id="rmv">
                                                <label class="form-check-label text-danger" for="rmv">Remove current image</label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="question_image" class="form-control form-control-sm">
                                </div>
                            </div>

                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="add_questions1.php?quiz_id=<?php echo $question['quiz_id']; ?>" class="btn btn-light">Cancel</a>
                                <button type="submit" class="btn btn-success px-5 fw-bold">Update Question</button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>