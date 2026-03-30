<?php
// register.php
require_once "db_connect.php";

$username = $full_name = $class_year = $password = "";
$message = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $username = trim($_POST["username"]);
    $full_name = trim($_POST["full_name"]);
    $class_year = trim($_POST["class_year"]);
    $password = $_POST["password"];

    // Updated SQL to include full_name and class_year
    $sql = "INSERT INTO users (username, full_name, class_year, password_hash) VALUES (?, ?, ?, ?)";
    
    if($stmt = $conn->prepare($sql)){
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // "ssss" for four strings
        $stmt->bind_param("ssss", $username, $full_name, $class_year, $hashed_password); 

        if($stmt->execute()){
            header("location: login.php?registration=success");
            exit;
        } else{
            if ($conn->errno == 1062) {
                $message = "Error: Username already taken. Please choose another.";
            } else {
                $message = "Oops! Something went wrong. Please try again later.";
            }
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
    <title>Register for Quiz Competition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow-lg p-4 w-100" style="max-width: 450px;">
            <div class="card-body">
                <h2 class="card-title text-center text-success mb-4">Create Your Account</h2>
                
                <?php 
                    if(!empty($message)){
                        $alert_class = ($conn->errno == 1062) ? 'alert-danger' : 'alert-success';
                        echo '<div class="alert ' . $alert_class . '" role="alert">' . $message . '</div>';
                    }
                ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label class="form-label">Username (Login ID)</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required placeholder="John Doe">
                    </div>
                    <div class="mb-3">
    <label for="class_year" class="form-label">Class Level</label>
    <select name="class_year" id="class_year" class="form-control" required>
        <option value="" disabled <?php echo ($class_year == "") ? 'selected' : ''; ?>>Select your year</option>
        <?php
        $years = ["Year 1", "Year 2", "Year 3", "Year 4", "Year 5", "Year 6"];
        foreach($years as $y){
            $selected = ($class_year == $y) ? 'selected' : '';
            echo "<option value=\"$y\" $selected>$y</option>";
        }
        ?>
    </select>
</div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <input type="submit" value="Register" class="btn btn-success btn-lg">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
                
                <p class="text-center mt-3">
                    Already have an account? <a href="login.php" class="text-primary fw-bold">Login here</a>.
                </p>
            </div>
        </div>
    </div>
</body>
</html>