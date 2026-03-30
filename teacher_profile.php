<?php
// teacher_profile.php - Updated with Login Activity and Data Export
require_once "db_connect.php";
session_start();

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: teacher_login.php");
    exit;
}

$user_id = $_SESSION["id"];
$message = "";
$message_type = "";

// 1. Fetch current profile data including last_login
$stmt = $conn->prepare("SELECT username, email, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// 2. Handle Update Requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_email = trim($_POST["email"]);
    $new_pass = $_POST["new_password"];
    $confirm_pass = $_POST["confirm_password"];

    if (!empty($new_pass)) {
        if ($new_pass !== $confirm_pass) {
            $message = "Passwords do not match.";
            $message_type = "danger";
        } else {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $new_email, $hashed_pass, $user_id);
            if ($update_stmt->execute()) {
                $message = "Profile and password updated successfully!";
                $message_type = "success";
            }
        }
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_email, $user_id);
        if ($update_stmt->execute()) {
            $message = "Profile updated successfully!";
            $message_type = "success";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        #sidebar { width: 250px; height: 100vh; position: fixed; background: #2c3e50; color: white; padding: 20px; }
        #main-content { margin-left: 250px; padding: 40px; }
        .profile-card, .activity-card { border: none; border-radius: 15px; }
        .nav-link { color: #bdc3c7; margin: 10px 0; border-radius: 8px; }
        .nav-link:hover, .nav-link.active { background: #34495e; color: white; }
        .icon-box { width: 45px; height: 45px; background: #e0f2ff; color: #0d6efd; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .icon-box-success { background: #e7f5ea; color: #198754; }
    </style>
</head>
<body>

    <div id="sidebar">
        <h4 class="fw-bold mb-4"><i class="fas fa-chalkboard-teacher me-2"></i>Teacher</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="teacher_dashboard.php"><i class="fas fa-chart-line me-2"></i> Overview</a>
            <a class="nav-link" href="add_questions.php"><i class="fas fa-plus-circle me-2"></i> Manage Quizzes</a>
            <a class="nav-link active" href="teacher_profile.php"><i class="fas fa-user-circle me-2"></i> My Profile</a>
            <hr>
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div id="main-content">
        <div class="container" style="max-width: 800px;">
            <h3 class="fw-bold mb-4">Account Settings</h3>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> shadow-sm border-0"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="card profile-card shadow-sm mb-4">
                <div class="card-body p-4">
                    <form action="teacher_profile.php" method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Username</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>

                            <hr class="my-4">
                            <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-key me-2"></i>Security Update</h6>

                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-5 shadow">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card activity-card shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="icon-box me-3">
                            <i class="fas fa-shield-check fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold">Recent Account Activity</h6>
                            <p class="text-muted small mb-0">
                                Last successful login: 
                                <span class="text-dark fw-bold">
                                    <?php 
                                        echo $user_data['last_login'] 
                                        ? date("D, M j, Y @ g:i A", strtotime($user_data['last_login'])) 
                                        : "First time logging in!"; 
                                    ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card activity-card shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="icon-box icon-box-success me-3">
                                <i class="fas fa-file-export fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">Data Management</h6>
                                <p class="text-muted small mb-0">Download all your quiz records in CSV format.</p>
                            </div>
                        </div>
                        <a href="export_quizzes.php" class="btn btn-outline-success btn-sm fw-bold px-3">
                            <i class="fas fa-download me-2"></i> Export CSV
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-5 p-4 bg-white rounded shadow-sm border-start border-4 border-info">
                <h5><i class="fas fa-info-circle text-info me-2"></i>Security Reminder</h5>
                <p class="text-muted small mb-0">
                    Always ensure your password is unique and not used on other websites. 
                    Recording your login activity helps us keep your account safe from unauthorized access.
                </p>
            </div>
        </div>
    </div>

</body>
</html>