<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security: Only admins allowed
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    die("Unauthorized access.");
}

// We change GET to POST for better security with SweetAlert forms
if (isset($_POST['attempt_id'])) {
    $attempt_id = (int)$_POST['attempt_id'];

    $conn->begin_transaction();

    try {
        // 1. Delete associated answers (Ensuring data integrity)
        // Note: Check if your table is 'user_answers' or 'student_answers'
        $stmt1 = $conn->prepare("DELETE FROM user_answers WHERE attempt_id = ?");
        $stmt1->bind_param("i", $attempt_id);
        $stmt1->execute();

        // 2. Delete the main attempt record
        $stmt2 = $conn->prepare("DELETE FROM attempts WHERE attempt_id = ?");
        $stmt2->bind_param("i", $attempt_id);
        $stmt2->execute();

        $conn->commit();
        $_SESSION['message'] = "Attempt record removed successfully.";
        
        // Redirect back to dashboard and scroll to the attempts section
        header("Location: admin_dashboard.php#attempt-history");
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Could not delete: " . $e->getMessage();
        header("Location: admin_dashboard.php#attempt-history");
    }
} else {
    header("Location: admin_dashboard.php");
}
exit;