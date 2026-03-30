<?php
require_once "db_connect.php";
$quiz_id = 97; // Using the Quiz ID from your last attempt

echo "<h2>Quiz Debugger</h2>";

$res = $conn->query("SELECT question_id, correct_option, question_text FROM questions WHERE quiz_id = $quiz_id LIMIT 5");

if ($res->num_rows == 0) {
    echo "<p style='color:red;'>ERROR: No questions found for Quiz ID $quiz_id!</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Question</th><th>Correct Letter in DB</th></tr>";
    while($row = $res->fetch_assoc()) {
        $val = $row['correct_option'];
        $display_val = ($val == "") ? "[EMPTY]" : "'$val'";
        echo "<tr>
                <td>{$row['question_id']}</td>
                <td>{$row['question_text']}</td>
                <td style='background:".($val == "" ? "#fee":"#efe")."'>$display_val</td>
              </tr>";
    }
    echo "</table>";
}
?>