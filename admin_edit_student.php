<?php
// admin_edit_student.php - Professional Admin Edit Interface
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security: Only Admin can edit profiles
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

$message = "";
$message_type = "";

// 1. Get Student ID from URL
$student_id = $_GET['id'] ?? null;
if (!$student_id) {
    die("No student selected.");
}

// 2. Fetch Master Class List for the dropdown
$classes_res = $conn->query("SELECT class_name FROM classes ORDER BY class_name ASC");
$classes = $classes_res->fetch_all(MYSQLI_ASSOC);

// 3. Handle Update Request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_name = trim($_POST['full_name']);
    $new_class = trim($_POST['class_year']); // This fixes the "Not Assigned" issue

    $update_sql = "UPDATE users SET full_name = ?, class_year = ? WHERE user_id = ? AND user_type = 'participant'";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssi", $new_name, $new_class, $student_id);

    if ($stmt->execute()) {
        $message = "Student profile updated successfully! Exams for $new_class will now appear.";
        $message_type = "success";
    } else {
        $message = "Error updating profile: " . $conn->error;
        $message_type = "danger";
    }
    $stmt->close();
}

// 4. Fetch Current Student Data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND user_type = 'participant'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { die("Student not found."); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow border-0">
                    <div class="card-header bg-dark text-white p-3">
                        <h5 class="mb-0">Edit Student: <?php echo htmlspecialchars($student['full_name']); ?></h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-uppercase">Assign Class</label>
                                <select name="class_year" class="form-select border-primary" required>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach($classes as $c): ?>
                                        <option value="<?php echo $c['class_name']; ?>" <?php echo ($student['class_year'] == $c['class_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-danger">Changing this will instantly update which exams the student sees.</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="manage_students.php" class="btn btn-outline-secondary">Back to List</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>