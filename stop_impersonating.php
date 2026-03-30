<?php
// stop_impersonating.php
session_start();

if (isset($_SESSION["is_impersonating"])) {
    // Restore the Admin ID and Name
    $_SESSION["id"] = $_SESSION["admin_id"];
    $_SESSION["username"] = $_SESSION["admin_user"];
    $_SESSION["user_type"] = 'admin';

    // Remove the temp data
    unset($_SESSION["admin_id"]);
    unset($_SESSION["admin_user"]);
    unset($_SESSION["is_impersonating"]);

    header("Location: manage_teachers.php");
    exit;
}

header("Location: index.php");