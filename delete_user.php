<?php
// delete_user.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "db_connect.php";

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

if (isset($_POST["user_id"]) && !empty(trim($_POST["user_id"]))) {
    $user_id_to_delete = (int)trim($_POST["user_id"]);
    
    // Prevent self-deletion
    if ($user_id_to_delete == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own active admin account.";
        header("location: admin_dashboard.php");
        exit;
    }

    $conn->begin_transaction();
    try {
        // STEP 0: Delete answers linked to this user's attempts
        // We do this first to satisfy foreign key constraints
        $sql_answers = "DELETE FROM user_answers WHERE attempt_id IN (SELECT attempt_id FROM attempts WHERE user_id = ?)";
        $stmt_ans = $conn->prepare($sql_answers);
        $stmt_ans->bind_param("i", $user_id_to_delete);
        $stmt_ans->execute();
        $stmt_ans->close();

        // STEP 1: Delete all associated records from 'attempts'
        $sql_attempts = "DELETE FROM attempts WHERE user_id = ?";
        $stmt_att = $conn->prepare($sql_attempts);
        $stmt_att->bind_param("i", $user_id_to_delete);
        $stmt_att->execute();
        $stmt_att->close();

        // STEP 2: Delete the user record from 'users'
        $sql_user = "DELETE FROM users WHERE user_id = ?";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("i", $user_id_to_delete);
        $stmt_user->execute();
        $stmt_user->close();

        $conn->commit();
        $_SESSION['message'] = "User and all associated data deleted successfully.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }

} else {
    $_SESSION['error'] = "Invalid request: User ID is missing.";
}

header("location: admin_dashboard.php");
exit;