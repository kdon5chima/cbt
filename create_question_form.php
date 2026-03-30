<?php
// create_question_form.php - Separate Full Page Form
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quiz_id = (int)$_POST["quiz_id"];
    $q_text = trim($_POST["question_text"]);
    $a = trim($_POST["option_a"]); $b = trim($_POST["option_b"]);
    $c = trim($_POST["option_c"]); $d = trim($_POST["option_d"]);
    $correct = $_POST["correct_answer"];
    
    // Image Upload Logic...
    $image_path = null;
    if (isset($_FILES["q_image"]) && $_FILES["q_image"]["error"] == 0) {
        $path = "images/questions/" . time() . "_" . $_FILES["q_image"]["name"];
        move_uploaded_file($_FILES["q_image"]["tmp_name"], $path);
        $image_path = $path;
    }

    $sql = "INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssss", $quiz_id, $q_text, $a, $b, $c, $d, $correct, $image_path);
    
    if($stmt->execute()) {
        header("Location: add_questions.php?quiz_id=$quiz_id&status=success&msg=Question+Added");
    } else {
        $error = "Database Error.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Question | Quiz Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .form-container { max-width: 800px; margin: 50px auto; }
        .option-card { border-left: 4px solid #0d6efd; background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
    </style>
</head>
<body>

    <div class="container form-container">
        <a href="add_questions.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-link text-decoration-none mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Management</a>
        
        <div class="card shadow border-0">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Question</h4>
            </div>
            <div class="card-body p-4">
                <form action="create_question_form.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Question Text</label>
                        <textarea name="question_text" class="form-control form-control-lg" rows="3" placeholder="Enter the question here..." required></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="option-card shadow-sm">
                                <label class="small fw-bold text-primary">Option A</label>
                                <input type="text" name="option_a" class="form-control border-0 px-0" placeholder="Type answer A..." required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="option-card shadow-sm border-primary">
                                <label class="small fw-bold text-primary">Option B</label>
                                <input type="text" name="option_b" class="form-control border-0 px-0" placeholder="Type answer B..." required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="option-card shadow-sm border-primary">
                                <label class="small fw-bold text-primary">Option C</label>
                                <input type="text" name="option_c" class="form-control border-0 px-0" placeholder="Type answer C (Optional)...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="option-card shadow-sm border-primary">
                                <label class="small fw-bold text-primary">Option D</label>
                                <input type="text" name="option_d" class="form-control border-0 px-0" placeholder="Type answer D (Optional)...">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row align-items-center">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Correct Answer</label>
                            <select name="correct_answer" class="form-select border-primary" required>
                                <option value="">Select the correct option</option>
                                <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Question Image (Optional)</label>
                            <input type="file" name="q_image" class="form-control">
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="reset" class="btn btn-light px-4">Clear Form</button>
                        <button type="submit" class="btn btn-primary px-5 shadow">Save Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>