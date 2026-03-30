<?php
// system_settings.php - Enhanced with Session Management
require_once "db_connect.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

$message = "";

// --- 1. HANDLE UPDATES ---

// Add Class
if (isset($_POST['add_class'])) {
    $name = trim($_POST['class_name']);
    $stmt = $conn->prepare("INSERT INTO classes (class_name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $message = "Class added successfully.";
}

// Add Subject
if (isset($_POST['add_subject'])) {
    $name = trim($_POST['subject_name']);
    $stmt = $conn->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $message = "Subject added successfully.";
}

// NEW: Add Academic Session (History)
if (isset($_POST['add_session'])) {
    $session = trim($_POST['new_session_name']);
    $stmt = $conn->prepare("INSERT IGNORE INTO academic_years (session_name) VALUES (?)");
    $stmt->bind_param("s", $session);
    $stmt->execute();
    $message = "Session added to history.";
}

// NEW: Set Current Academic Session
if (isset($_POST['set_current_session'])) {
    $session_id = (int)$_POST['active_session_id'];
    
    // 1. Reset all to 0
    $conn->query("UPDATE academic_years SET is_current = 0");
    // 2. Set selected to 1
    $stmt = $conn->prepare("UPDATE academic_years SET is_current = 1 WHERE id = ?");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    
    // Also update your legacy academic_settings table for backward compatibility
    $res = $conn->query("SELECT session_name FROM academic_years WHERE id = $session_id");
    $name = $res->fetch_assoc()['session_name'];
    $conn->query("UPDATE academic_settings SET setting_value = '$name' WHERE setting_key = 'current_session'");
    
    $message = "Current session updated to $name.";
}

// DELETE LOGIC
if (isset($_GET['delete']) && isset($_GET['type']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['type'];
    
    if ($type === 'class') $table = 'classes';
    elseif ($type === 'subject') $table = 'subjects';
    elseif ($type === 'session') $table = 'academic_years';
    
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: system_settings.php?msg=Deleted");
    exit;
}

// --- 2. FETCH DATA ---
$classes = $conn->query("SELECT * FROM classes ORDER BY class_name ASC");
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name ASC");
$all_sessions = $conn->query("SELECT * FROM academic_years ORDER BY session_name DESC");

// Get the current active name for display
$current_session_res = $conn->query("SELECT session_name FROM academic_years WHERE is_current = 1 LIMIT 1");
$display_current = ($current_session_res->num_rows > 0) ? $current_session_res->fetch_assoc()['session_name'] : "Not Set";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings | CBT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .scroll-list { max-height: 250px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; }
        .card { border: none; border-radius: 12px; }
        .active-session-banner { background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cogs me-2"></i>System Configuration</h2>
        <a href="admin_dashboard.php" class="btn btn-outline-dark">Back to Dashboard</a>
    </div>

    <?php if(!empty($message) || isset($_GET['msg'])): ?>
        <div class="alert alert-success shadow-sm"><?php echo $message ?: $_GET['msg']; ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between">
                    <span>Academic Sessions (Yearly History)</span>
                    <span class="badge bg-info">Current: <?php echo $display_current; ?></span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 border-end">
                            <form method="POST" class="mb-4">
                                <label class="form-label fw-bold">Add New Session</label>
                                <div class="input-group">
                                    <input type="text" name="new_session_name" class="form-control" placeholder="e.g. 2026/2027" required>
                                    <button type="submit" name="add_session" class="btn btn-success">Add</button>
                                </div>
                            </form>

                            <form method="POST">
                                <label class="form-label fw-bold">Set Active Session</label>
                                <select name="active_session_id" class="form-select mb-2" required>
                                    <option value="">-- Choose Session --</option>
                                    <?php 
                                    $all_sessions->data_seek(0);
                                    while($row = $all_sessions->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo $row['is_current'] ? 'selected' : ''; ?>>
                                            <?php echo $row['session_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <button type="submit" name="set_current_session" class="btn btn-primary w-100">Update Current Session</button>
                            </form>
                        </div>
                        
                        <div class="col-md-8">
                            <label class="form-label fw-bold px-2">Session History</label>
                            <div class="scroll-list mx-2">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Session Name</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $all_sessions->data_seek(0);
                                        while($row = $all_sessions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['session_name']; ?></td>
                                            <td>
                                                <?php if($row['is_current']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Archive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="system_settings.php?delete=1&type=session&id=<?php echo $row['id']; ?>" 
                                                   class="text-danger" onclick="return confirm('Delete this session?')">
                                                   <i class="fas fa-times-circle"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white fw-bold">Manage Classes</div>
                <div class="card-body">
                    <form method="POST" class="d-flex mb-3">
                        <input type="text" name="class_name" class="form-control me-2" placeholder="e.g. JSS 1" required>
                        <button type="submit" name="add_class" class="btn btn-success">Add</button>
                    </form>
                    <div class="scroll-list">
                        <ul class="list-group list-group-flush">
                            <?php while($c = $classes->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($c['class_name']); ?>
                                    <a href="system_settings.php?delete=1&type=class&id=<?php echo $c['id']; ?>" class="text-danger"><i class="fas fa-trash"></i></a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white fw-bold">Manage Subjects</div>
                <div class="card-body">
                    <form method="POST" class="d-flex mb-3">
                        <input type="text" name="subject_name" class="form-control me-2" placeholder="e.g. English" required>
                        <button type="submit" name="add_subject" class="btn btn-success">Add</button>
                    </form>
                    <div class="scroll-list">
                        <ul class="list-group list-group-flush">
                            <?php while($s = $subjects->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($s['subject_name']); ?>
                                    <a href="system_settings.php?delete=1&type=subject&id=<?php echo $s['id']; ?>" class="text-danger"><i class="fas fa-trash"></i></a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>