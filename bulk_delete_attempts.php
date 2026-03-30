<?php
// bulk_delete_attempts.php
require_once "db_connect.php";
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') { die("Unauthorized"); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['delete_ids'])) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $id_list = implode(',', $ids);

    $conn->begin_transaction();
    try {
        // Step 1: Remove individual answers
        $conn->query("DELETE FROM user_answers WHERE attempt_id IN ($id_list)");
        // Step 2: Remove the attempt record
        $conn->query("DELETE FROM attempts WHERE attempt_id IN ($id_list)");
        
        $conn->commit();
        header("location: view_all_attempts.php?msg=success");
    } catch (Exception $e) {
        $conn->rollback();
        header("location: view_all_attempts.php?msg=error");
    }
} else {
    header("location: view_all_attempts.php");
}