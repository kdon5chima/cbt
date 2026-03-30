<?php
// impersonate.php
require_once "db_connect.php";
session_start();

// Security: Only a logged-in Admin can trigger this
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    die("Unauthorized access.");
}

if (isset($_GET['id'])) {
    $teacher_id = (int)$_GET['id'];

    // Fetch teacher details to ensure they exist
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ? AND user_type = 'teacher'");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // 1. Store the Admin's identity so they can switch back
        $_SESSION["admin_id"] = $_SESSION["id"];
        $_SESSION["admin_user"] = $_SESSION["username"];

        // 2. Overwrite session with Teacher details
        $_SESSION["id"] = $row['id'];
        $_SESSION["username"] = $row['username'];
        $_SESSION["user_type"] = 'teacher';
        $_SESSION["is_impersonating"] = true;

        header("Location: teacher_dashboard.php");
        exit;
    }
}
header("Location: manage_teachers.php");