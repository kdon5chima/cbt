<?php
// pupil_transcript.php - Full Academic History
require_once "db_connect.php";
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check: Must be logged in
if (!isset($_SESSION["loggedin"])) {
    header("location: login.php");
    exit;
}

/** * 2. Determine User ID
 * If admin is viewing a specific pupil: uses ?user_id=X
 * If pupil is viewing their own: defaults to $_SESSION['user_id']
 */
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$_SESSION['user_id'];

// Prevent pupils from guessing IDs to see other students' results
if ($_SESSION["user_type"] !== 'admin' && $user_id !== (int)$_SESSION['user_id']) {
    die("Access Denied: You can only view your own transcript.");
}

// 3. Fetch Pupil General Info
$sql_user = "SELECT full_name, username, user_id FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_info = $stmt_user->get_result()->fetch_assoc();

if (!$user_info) { 
    die("Error: Pupil record not found."); 
}

// 4. Fetch All Quiz Attempts for this Pupil
$sql_history = "SELECT 
                    q.title as subject, 
                    a.score, 
                    q.total_questions, 
                    a.end_time,
                    (a.score / q.total_questions * 100) as percentage
                FROM attempts a
                JOIN quizzes q ON a.quiz_id = q.quiz_id
                WHERE a.user_id = ?
                ORDER BY a.end_time DESC";
$stmt_hist = $conn->prepare($sql_history);
$stmt_hist->bind_param("i", $user_id);
$stmt_hist->execute();
$history = $stmt_hist->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transcript: <?php echo htmlspecialchars($user_info['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .transcript-header { border-bottom: 3px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .school-logo { font-size: 28px; font-weight: bold; color: #1a73e8; letter-spacing: 1px; }
        .table thead { background-color: #f8f9fa; }
        
        @media print { 
            .no-print { display: none !important; }
            body { padding: 0; }
            .container { max-width: 100%; width: 100%; }
            .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="no-print mb-4 d-flex justify-content-between">
        <a href="javascript:history.back()" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button onclick="window.print()" class="btn btn-danger">
            <i class="fas fa-file-pdf"></i> Download PDF Report
        </button>
    </div>

    <div class="transcript-header text-center">
        <div class="school-logo">ACADEMIC PERFORMANCE TRANSCRIPT</div>
        <p class="text-muted mb-4">Official Academic Record • Generated on <?php echo date('d M Y, h:i A'); ?></p>
        
        <div class="row mt-4 text-start">
            <div class="col-6">
                <p class="mb-1 text-muted small uppercase">Student Information</p>
                <h5 class="mb-0"><?php echo htmlspecialchars($user_info['full_name']); ?></h5>
                <p class="text-secondary">ID: <?php echo htmlspecialchars($user_info['username']); ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1 text-muted small uppercase">Academic Summary</p>
                <p class="mb-0"><strong>Status:</strong> Active</p>
                <p class="mb-0"><strong>Subjects Completed:</strong> <?php echo $history->num_rows; ?></p>
            </div>
        </div>
    </div>

    <table class="table table-bordered align-middle">
        <thead>
            <tr class="text-uppercase small">
                <th style="width: 40%;">Subject / Subject Description</th>
                <th>Date Attempted</th>
                <th class="text-center">Score</th>
                <th class="text-center">Percentage</th>
                <th class="text-center">Grade/Result</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($history->num_rows > 0): 
                while($row = $history->fetch_assoc()): 
                    $pct = round($row['percentage']);
                    $is_pass = ($pct >= 50); 
            ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['subject']); ?></strong></td>
                    <td><?php echo date('d M Y', strtotime($row['end_time'])); ?></td>
                    <td class="text-center"><?php echo $row['score']; ?> / <?php echo $row['total_questions']; ?></td>
                    <td class="text-center fw-bold"><?php echo $pct; ?>%</td>
                    <td class="text-center">
                        <span class="badge <?php echo $is_pass ? 'bg-success' : 'bg-danger'; ?>" style="font-size: 0.85rem; padding: 6px 12px;">
                            <?php echo $is_pass ? 'PASS' : 'FAIL'; ?>
                        </span>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                        <i class="fas fa-folder-open fa-3x mb-3 d-block text-light"></i>
                        No academic history found for this pupil.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-5 pt-5 d-none d-print-block">
        <div class="row text-center">
            <div class="col-4">
                <div style="border-top: 2px solid #333; padding-top: 10px;">
                    <strong>Registrar's Signature</strong>
                </div>
            </div>
            <div class="col-4"></div>
            <div class="col-4">
                <div style="border-top: 2px solid #333; padding-top: 10px;">
                    <strong>Date of Issue</strong>
                </div>
            </div>
        </div>
        <div class="mt-4 text-center small text-muted">
            <em>This is a computer-generated document. No alterations are permitted.</em>
        </div>
    </div>
</div>

</body>
</html>