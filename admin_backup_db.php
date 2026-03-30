<?php
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Security check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die("Unauthorized access.");
}

// 2. Get all tables
$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$return = "-- CBT Database Backup \n-- Generated: " . date("Y-m-d H:i:s") . "\n\n";

// 3. Loop through tables to get structure and data
foreach ($tables as $table) {
    // Table structure
    $result = $conn->query("SHOW CREATE TABLE $table");
    $row = $result->fetch_row();
    $return .= "\n\n" . $row[1] . ";\n\n";

    // Table data
    $result = $conn->query("SELECT * FROM $table");
    $num_fields = $result->field_count;

    for ($i = 0; $i < $num_fields; $i++) {
        while ($row = $result->fetch_row()) {
            $return .= "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                if (isset($row[$j])) {
                    $return .= '"' . $row[$j] . '"';
                } else {
                    $return .= '""';
                }
                if ($j < ($num_fields - 1)) { $return .= ','; }
            }
            $return .= ");\n";
        }
    }
}

// 4. Force Download
$filename = "db_backup_" . date("Y-m-d") . ".sql";
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $filename . "\"");
echo $return;
exit;