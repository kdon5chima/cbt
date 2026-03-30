<?php
// login.php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$username = $password = "";
$login_err = "";

// Redirect if already logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    if ($_SESSION["user_type"] === 'admin') {
        header("location: admin_dashboard.php");
    } elseif ($_SESSION["user_type"] === 'teacher') {
        header("location: teacher_dashboard.php");
    } else {
        header("location: participant_dashboard.php");
    }
    exit;
}

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // UPDATED: Added 'status' to check if user is active/suspended
    $sql = "SELECT user_id, username, full_name, class_year, password_hash, user_type, status FROM users WHERE username = ?";
    
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $username);
        
        if($stmt->execute()){
            $stmt->store_result();
            
            if($stmt->num_rows == 1){                    
                $stmt->bind_result($id, $username, $full_name, $class_year, $hashed_password, $user_type, $status);
                
                if($stmt->fetch()){
                    // Check if the account is suspended
                    if($status == 0){
                        $login_err = "Your account has been suspended. Please contact the administrator.";
                    } else {
                        if(password_verify($password, $hashed_password)){
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["user_type"] = $user_type;
                            $_SESSION["full_name"] = $full_name;
                            $_SESSION["class_year"] = $class_year;
                            
                            if ($user_type === 'admin') {
                                header("location: admin_dashboard.php");
                            } elseif ($user_type === 'teacher') {
                                header("location: teacher_dashboard.php");
                            } else {
                                header("location: participant_dashboard.php");
                            }
                            exit;
                        } else {
                            $login_err = "Invalid username or password.";
                        }
                    }
                }
            } else {
                $login_err = "Invalid username or password.";
            }
        } else {
            echo "Oops! Something went wrong. Please try again later.";
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
    <title>Login to CBT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .card { border-radius: 15px; border: none; }
        .login-title { font-weight: 700; color: #0d6efd; }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow-lg p-4 w-100" style="max-width: 400px;">
            <div class="card-body">
                <h2 class="card-title text-center login-title mb-4">Login to CBT</h2>
                
                <?php 
                    if(!empty($login_err)){
                        echo '<div class="alert alert-danger small py-2" role="alert">' . $login_err . '</div>';
                    }
                ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label small fw-bold">Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>    
                    <div class="mb-4">
                        <label for="password" class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 mb-3 shadow-sm">Login</button>
                </form>
                
                <div class="text-center mt-3">
                    <p class="mb-1 small">Don't have an account? <a href="register.php" class="text-primary text-decoration-none fw-bold">Register here</a>.</p>
                    <p class="mb-1 large fw-bold">For staff members only: <a href="teacher_login.php" class="text-success text-decoration-none fw-bold">Teacher Login</a>.</p>
                </div>

                <hr class="my-4">

                <div class="text-center">
                    <a href="index.php" class="text-muted text-decoration-none small">← Back to Home</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>