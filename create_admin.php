<?php
// create_admin.php
require_once "db_connect.php";

// Start session to access $_SESSION variables
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

$username = $password = $confirm_password = $email = $full_name = "";
$username_err = $password_err = $confirm_password_err = $message = "";
$message_type = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    // 1. Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter a username.";
    } else {
        $sql = "SELECT user_id FROM users WHERE username = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            if($stmt->execute()){
                $stmt->store_result();
                if($stmt->num_rows == 1){
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            }
            $stmt->close();
        }
    }

    // 2. Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // 3. Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // Capture extra fields from form
    $email = trim($_POST["email"]);
    $full_name = trim($_POST["full_name"]);

    // 4. Check input errors before inserting
    if(empty($username_err) && empty($password_err) && empty($confirm_password_err)){
        
        // Corrected SQL: Targets password_hash and includes all required fields
        $sql = "INSERT INTO users (username, email, full_name, password_hash, user_type, status) VALUES (?, ?, ?, ?, 'admin', 1)";
         
        if($stmt = $conn->prepare($sql)){
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param("ssss", $username, $email, $full_name, $hashed_password);
            
            if($stmt->execute()){
                $message = "✅ New Admin user <strong>" . htmlspecialchars($username) . "</strong> created successfully!";
                $message_type = "success";
                // Clear inputs
                $username = $password = $confirm_password = $email = $full_name = ""; 
            } else {
                $message = "Error creating admin: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { padding-top: 56px; background-color: #f8f9fa; }
        #sidebar { position: fixed; top: 56px; bottom: 0; left: 0; z-index: 1000; padding: 1rem; width: 250px; background-color: #343a40; }
        #main-content { margin-left: 250px; padding: 2rem; }
        @media (max-width: 768px) { #sidebar { position: static; width: 100%; } #main-content { margin-left: 0; } }
        .nav-link { color: rgba(255, 255, 255, 0.75); }
        .nav-link.active { color: #fff; background-color: #0d6efd; border-radius: 5px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php"><i class="fas fa-tools"></i> Admin Control Panel</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <nav id="sidebar" class="collapse d-md-block bg-dark">
        <div class="position-sticky">
            <h5 class="text-white mt-2 mb-3 border-bottom pb-2">Navigation</h5>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="manage_admins.php"><i class="fas fa-users-cog me-2"></i> Manage Admins</a></li>
                <li class="nav-item"><a class="nav-link active" href="create_admin.php"><i class="fas fa-user-plus me-2"></i> Create New Admin</a></li>
            </ul>
        </div>
    </nav>

    <main id="main-content">
        <div class="container-fluid">
            <h1 class="mb-4 text-primary"><i class="fas fa-user-shield"></i> New Admin Registration</h1>
            
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <?php if(!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Username *</label>
                                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>">
                                <div class="invalid-feedback"><?php echo $username_err; ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Password *</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="pass1" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass('pass1')"><i class="fas fa-eye"></i></button>
                                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">Confirm Password *</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="pass2" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass('pass2')"><i class="fas fa-eye"></i></button>
                                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Create Admin Account</button>
                            <a href="manage_admins.php" class="btn btn-outline-secondary">Back to Admin List</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function togglePass(inputId) {
        const input = document.getElementById(inputId);
        input.type = (input.type === "password") ? "text" : "password";
    }
    </script>
</body>
</html>