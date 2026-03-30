<?php
// teacher_login.php
require_once "db_connect.php";

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// REMOVED: The auto-redirect block that was "locking" you out of this page.
// You can now access this page anytime to log in as a different teacher.

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Query for teacher account
    $sql = "SELECT user_id, username, password_hash, status FROM users WHERE username = ? AND user_type = 'teacher'";
    
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($row = $result->fetch_assoc()){
            // Verify password
            if(password_verify($password, $row['password_hash'])){
                
                // Check if account is active (1 = Active, 0 = Suspended)
                if($row['status'] == 0){
                    $error = "<strong>Access Denied:</strong> Your account is suspended.";
                } else {
                    // Regenerate session ID for security
                    session_regenerate_id(true);

                    // SET SESSION DATA
                    $_SESSION["loggedin"] = true;
                    $_SESSION["user_id"] = (int)$row['user_id']; 
                    $_SESSION["username"] = $row['username'];
                    $_SESSION["user_type"] = 'teacher';
                    
                    // Save and redirect
                    session_write_close();
                    header("location: teacher_dashboard.php");
                    exit;
                }
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "No teacher account found with that username.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login | Quiz System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px;
        }
        .login-card { width: 100%; max-width: 420px; border-radius: 20px; background: #ffffff; box-shadow: 0 15px 35px rgba(0,0,0,0.2); overflow: hidden; }
        .login-header { background: #f8f9fa; padding: 30px; text-align: center; border-bottom: 1px solid #eee; }
        .btn-teacher { background: #764ba2; color: white; border: none; padding: 12px; border-radius: 10px; font-weight: bold; width: 100%; transition: all 0.3s; }
        .btn-teacher:hover { background: #5a3a7e; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="card login-card border-0 shadow-lg">
        <div class="login-header">
            <div class="mb-3"><i class="fas fa-chalkboard-teacher fa-3x" style="color: #764ba2;"></i></div>
            <h4 class="fw-bold mb-1">Teacher Portal</h4>
            <p class="text-muted small">Enter your teacher credentials</p>
        </div>
        <div class="card-body p-4 p-md-5">
            <?php if($error): ?>
                <div class="alert alert-danger py-2 small shadow-sm mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="teacher_login.php" method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">USERNAME</label>
                    <input type="text" name="username" class="form-control bg-light" placeholder="Username" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">PASSWORD</label>
                    <input type="password" name="password" class="form-control bg-light" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-teacher py-2">Login to Dashboard</button>
                <div class="text-center mt-3">
                    <a href="index.php" class="text-muted small text-decoration-none">← Back to Homepage</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>