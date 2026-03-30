<?php
// sample_questions.csv.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=quiz_template.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// 1. Output the column headings (Must match your fgetcsv logic)
fputcsv($output, array('Question Text', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer (A, B, C, or D)'));

// 2. Add some example rows to guide the teacher
fputcsv($output, array('What is the capital of Nigeria?', 'Lagos', 'Abuja', 'Kano', 'Ibadan', 'B'));
fputcsv($output, array('Which planet is known as the Red Planet?', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'B'));
fputcsv($output, array('Solve: 5 + 7', '10', '11', '12', '13', 'C'));

fclose($output);
exit;
?>