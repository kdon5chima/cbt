<?php
// edit_user.php - Updated with Class Year
require_once "db_connect.php";

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$allowed_user_types = ['admin', 'participant'];
// Define your school's classes here so the dropdown is always consistent
$available_classes = ["Year 1", "Year 2", "Year 3", "Year 4", "Year 5", "Year 6"];

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

$user_id = $username = $user_type = $full_name = $class_year = "";
$error_message = $success_message = "";

// --- 1. Handle GET Request (Display Form) ---
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["id"])) {
    $user_id = filter_var($_GET["id"], FILTER_SANITIZE_NUMBER_INT);

    // Added class_year to the SELECT query
    $sql = "SELECT user_id, username, user_type, full_name, class_year FROM users WHERE user_id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $username = htmlspecialchars($user['username']);
                $user_type = htmlspecialchars($user['user_type']);
                $full_name = htmlspecialchars($user['full_name']); 
                $class_year = htmlspecialchars($user['class_year'] ?? ""); 
            } else {
                $error_message = "User not found.";
            }
        } else {
            $error_message = "Database query failed.";
        }
        $stmt->close();
    }
} 

// --- 2. Handle POST Request (Update User) ---
elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    
    $user_id = filter_var($_POST["user_id"], FILTER_SANITIZE_NUMBER_INT);
    $new_username = trim($_POST["username"]);
    $new_user_type = trim($_POST["user_type"]);
    $new_full_name = trim($_POST["full_name"]);
    $new_class_year = trim($_POST["class_year"]); // Capture selected class

    if (empty($new_username) || strlen($new_username) < 3) {
        $error_message = "Username must be at least 3 characters long.";
    } elseif (empty($new_full_name)) {
        $error_message = "Full name is required.";
    } elseif (!in_array($new_user_type, $allowed_user_types)) {
        $error_message = "Invalid user type specified.";
    } else {
        // Updated UPDATE query to include class_year
        $sql = "UPDATE users SET username = ?, user_type = ?, full_name = ?, class_year = ? WHERE user_id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Updated bind_param: "ssssi" (4 strings, 1 integer)
            $stmt->bind_param("ssssi", $new_username, $new_user_type, $new_full_name, $new_class_year, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "User updated successfully! Redirecting...";
                header("Refresh: 2; url=admin_dashboard.php");
            } else {
                $error_message = "Error updating user: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    // Maintain form values if there is an error
    $username = $new_username;
    $user_type = $new_user_type;
    $full_name = $new_full_name;
    $class_year = $new_class_year;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg">
                <div class="card-header bg-info text-white">
                    <h3 class="mb-0">Edit User: <?php echo $username; ?></h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-bold">Full Name</label>
                            <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo $full_name; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">Username</label>
                            <input type="text" name="username" id="username" class="form-control" value="<?php echo $username; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="class_year" class="form-label fw-bold">Assigned Class</label>
                            <select name="class_year" id="class_year" class="form-select" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach($available_classes as $class): ?>
                                    <option value="<?php echo $class; ?>" <?php echo ($class_year == $class) ? 'selected' : ''; ?>>
                                        <?php echo $class; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="user_type" class="form-label fw-bold">User Type</label>
                            <select name="user_type" id="user_type" class="form-select" required>
                                <option value="participant" <?php echo ($user_type == 'participant') ? 'selected' : ''; ?>>Participant</option>
                                <option value="admin" <?php echo ($user_type == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-between pt-3 border-top">
                            <a href="admin_dashboard.php" class="btn btn-secondary">Back</a>
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