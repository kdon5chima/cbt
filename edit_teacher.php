<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;
$message = "";
$error = "";

// 1. FETCH EXISTING DATA
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();

    if (!$teacher) {
        die("Teacher not found.");
    }
} else {
    header("location: admin_dashboard.php");
    exit;
}

// 2. HANDLE UPDATE REQUEST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST["full_name"]);
    $username = trim($_POST["username"]);
    $new_password = $_POST["password"];

    // Basic Validation
    if (empty($full_name) || empty($username)) {
        $error = "Name and Username are required.";
    } else {
        // If password is provided, hash it and update; otherwise, keep old password
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET full_name = ?, username = ?, password = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssi", $full_name, $username, $hashed_password, $id);
        } else {
            $update_sql = "UPDATE users SET full_name = ?, username = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssi", $full_name, $username, $id);
        }

        if ($update_stmt->execute()) {
            $_SESSION['message'] = "Teacher details updated successfully!";
            header("location: admin_dashboard.php");
            exit;
        } else {
            $error = "Error updating record: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Teacher - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Teacher Details</h5>
                    <a href="manage_teachers.php" class="btn btn-sm btn-light text-primary">Back</a>
                </div>
                <div class="card-body p-4">
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="edit_teacher.php?id=<?php echo $id; ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($teacher['username']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Change Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                            <div class="form-text text-muted">Only fill this if you want to reset their password.</div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>