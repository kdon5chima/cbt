<?php
require_once "db_connect.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Check
if (!isset($_SESSION["loggedin"])) {
    header("location: login.php");
    exit;
}

// 2. Capture ID from the URL
$attempt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if ($attempt_id === 0) {
    echo "<div style='padding:40px; text-align:center; font-family:sans-serif;'>";
    echo "<h2 style='color:red;'>Submission Error</h2>";
    echo "<p>No result ID was found. Please check your dashboard history.</p>";
    echo "</div>";
    exit;
}

// 3. SQL Query
// Note: Removed the strict user_id check if the person viewing is an admin or teacher
$sql = "SELECT 
            a.score, 
            a.total_questions, 
            q.title, 
            a.academic_year, 
            a.class_at_time,
            u.full_name 
        FROM attempts a 
        JOIN quizzes q ON a.quiz_id = q.quiz_id 
        JOIN users u ON a.user_id = u.user_id 
        WHERE a.attempt_id = ?";

// If it's a student, they can only see THEIR OWN result. 
// Admins and Teachers can see anyone's result.
if ($user_type === 'participant') {
    $sql .= " AND a.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $attempt_id, $user_id);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $attempt_id);
}

$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Error: Result details not found or access denied.");
}

$score = $data['score'];
$total = $data['total_questions'] > 0 ? $data['total_questions'] : 1;
$percentage = round(($score / $total) * 100);

// Determine Color Theme based on performance
$themeColor = "emerald";
$statusText = "Passed";

if ($percentage < 50) {
    $themeColor = "red";
    $statusText = "Needs Improvement";
} elseif ($percentage < 75) {
    $themeColor = "blue";
    $statusText = "Good Job";
} else {
    $themeColor = "emerald";
    $statusText = "Excellent!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result: <?php echo htmlspecialchars($data['full_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; p: 0; }
            .shadow-2xl { shadow: none; border: 1px solid #eee; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden border border-gray-100">
        <div class="bg-<?php echo $themeColor; ?>-600 p-6 text-center text-white transition-colors duration-500">
            <div class="inline-block bg-white/20 p-3 rounded-full mb-2">
                <i class="fas <?php echo $percentage >= 50 ? 'fa-award' : 'fa-circle-exclamation'; ?> text-3xl"></i>
            </div>
            <h1 class="text-xl font-black italic uppercase tracking-widest">Performance Report</h1>
        </div>

        <div class="p-8 text-center">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($data['full_name']); ?></h2>
                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-bold uppercase">
                    Class: <?php echo htmlspecialchars($data['class_at_time']); ?>
                </span>
            </div>

            <div class="inline-flex flex-col items-center justify-center w-40 h-40 border-8 border-<?php echo $themeColor; ?>-50 rounded-full mb-6 bg-<?php echo $themeColor; ?>-50/30">
                <span class="text-4xl font-black text-<?php echo $themeColor; ?>-600"><?php echo $score; ?>/<?php echo $total; ?></span>
                <span class="text-gray-400 font-bold text-xs uppercase"><?php echo $statusText; ?></span>
            </div>

            <div class="bg-slate-50 rounded-2xl p-4 mb-6 grid grid-cols-2 gap-4">
                <div class="text-left border-r border-gray-200">
                    <p class="text-[10px] text-gray-400 font-bold uppercase">Subject</p>
                    <p class="text-sm font-bold text-gray-700 truncate"><?php echo htmlspecialchars($data['title']); ?></p>
                </div>
                <div class="text-left pl-2">
                    <p class="text-[10px] text-gray-400 font-bold uppercase">Session</p>
                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($data['academic_year']); ?></p>
                </div>
            </div>

            <div class="mb-8">
                <div class="flex justify-between text-xs font-bold mb-1 uppercase text-gray-500">
                    <span>Final Score Percentage</span>
                    <span><?php echo $percentage; ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-<?php echo $themeColor; ?>-500 h-3 rounded-full transition-all duration-1000" style="width: <?php echo $percentage; ?>%"></div>
                </div>
            </div>

            <div class="space-y-3 no-print">
                <a href="participant_dashboard.php" class="block w-full bg-slate-800 text-white font-bold py-3 rounded-xl hover:bg-slate-900 shadow-lg transition">
                    Back to Dashboard
                </a>
                <button onclick="window.print()" class="text-gray-500 text-sm font-bold hover:text-<?php echo $themeColor; ?>-600 transition">
                    <i class="fas fa-print mr-1"></i> Print Official Result
                </button>
            </div>
        </div>
    </div>

</body>
</html>