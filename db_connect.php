<?php
// ==========================================================
// db_connect.php - DATABASE CONNECTION ONLY
// ==========================================================

// Use conditional defines to prevent "Constant already defined" notices
if (!defined('DB_SERVER')) define('DB_SERVER', '127.0.0.1');
if (!defined('DB_USERNAME')) define('DB_USERNAME', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
if (!defined('DB_NAME')) define('DB_NAME', 'quiz_competition');

/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn->connect_error){
    die("ERROR: Could not connect. " . $conn->connect_error);
}

// Set charset to utf8mb4 (crucial for supporting symbols/emojis in questions)
$conn->set_charset("utf8mb4");

// Set the timezone to Nigeria (Lagos) so your quiz start/end times are accurate
date_default_timezone_set('Africa/Lagos');

// Note: Removed session_start() from here to prevent conflicts with 
// the logic already present in your start_quiz.php and dashboard files.
?>