<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') { 
    header("location: login.php");
    exit; 
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $full_name = trim($_POST["full_name"]); // New: Fetching full name from form
    $password = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT);

    // 2. Check for existing username
    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    if ($check) {
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Username already taken.";
        } else {
            // 3. Updated INSERT query to include full_name
            $stmt = $conn->prepare("INSERT INTO users (username, full_name, password_hash, user_type, status) VALUES (?, ?, ?, 'teacher', 1)");
            $stmt->bind_param("sss", $username, $full_name, $password);
            
            if($stmt->execute()) {
                header("location: manage_teachers.php?status=success&msg=Teacher+Added");
                exit;
            } else {
                $error = "Error saving to database: " . $conn->error;
            }
        }
    } else {
        die("SQL Error: " . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Teacher | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 15px; margin-top: 50px; border: none; }
        .form-control:focus { box-shadow: none; border-color: #0d6efd; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 500px;">
        <div class="card shadow p-4">
            <div class="text-center mb-4">
                <div class="bg-primary bg-opacity-10 text-primary d-inline-block p-3 rounded-circle mb-3">
                    <i class="fas fa-user-plus fa-2x"></i>
                </div>
                <h4 class="fw-bold text-primary">Create Teacher Account</h4>
                <p class="text-muted small">Set up credentials for a new staff member</p>
            </div>
            
            <?php if($error): ?> 
                <div class="alert alert-danger small py-2"><?php echo $error; ?></div> 
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Teacher's Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Dr. Olumide Adebayo" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Login Username</label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. oadebayo" required>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">Initial Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary fw-bold py-2">Create Account</button>
                    <a href="manage_teachers.php" class="btn btn-light border py-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>