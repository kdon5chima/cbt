<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') { 
    header("location: login.php"); 
    exit; 
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = "";

// 2. Handle Update Logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($email)) {
        $error = "Username and Email are required.";
    } else {
        // NOTE: Updated column name to 'password_hash' based on your table structure
        if (!empty($password)) {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username = ?, email = ?, password_hash = ? WHERE user_id = ? AND user_type = 'admin'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $username, $email, $new_hash, $id);
        } else {
            $sql = "UPDATE users SET username = ?, email = ? WHERE user_id = ? AND user_type = 'admin'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $username, $email, $id);
        }

        if ($stmt && $stmt->execute()) {
            header("location: manage_admins.php?msg=updated");
            exit;
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }
}

// 3. Fetch Current Admin Data
$stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ? AND user_type = 'admin'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    header("location: manage_admins.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .edit-card { max-width: 500px; margin: 50px auto; border-radius: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card edit-card shadow-sm border-0">
            <div class="card-header bg-primary text-white p-4 rounded-top">
                <h4 class="mb-0"><i class="fas fa-user-shield me-2"></i>Edit Admin Profile</h4>
            </div>
            <div class="card-body p-4">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="edit_admin.php?id=<?php echo $id; ?>" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Update Password</label>
                        <small class="text-muted d-block mb-2 italic">Leave blank to keep current password</small>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="New password">
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Update Admin Account</button>
                        <a href="manage_admins.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>