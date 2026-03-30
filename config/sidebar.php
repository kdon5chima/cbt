
<div class="sidebar bg-dark text-white p-3" style="min-height: 100vh; width: 250px; position: fixed;">
    <h4 class="text-center fw-bold mb-4">CBT<class="text-primary"> </span></h4>
    <hr>
    <ul class="nav flex-column">
            <div class="section-title">Core</div>
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>

            <div class="section-title">Academic & Quizzes</div>
            <li class="nav-item">
                <a class="nav-link" href="create_quiz.php">
                    <i class="fas fa-plus-circle me-2"></i> Create Quiz
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="add_questions.php">
                    <i class="fas fa-question-circle me-2"></i> Add Questions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_questions.php">
                    <i class="fas fa-tasks me-2"></i> Manage Question
                </a>
                <a class="nav-link" href="manage_students.php">
                    <i class="fas fa-tasks me-2"></i> Manage Pupils
                </a>
            </li>
<li class="nav-item">
    <a class="nav-link" href="student_list.php">
        <i class="fas fa-users me-2"></i> Student Directory
    </a>
</li>
            <div class="section-title">Reports</div>
            <li class="nav-item">
                <a class="nav-link" href="view_results.php">
                    <i class="fas fa-chart-line me-2"></i> Leaderboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_analytics.php">
                    <i class="fas fa-chart-pie me-2"></i> Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="view_all_attempts.php">
                    <i class="fas fa-history me-2"></i> Attempt Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_reset.php">
                    <i class="fas fa-undo me-2"></i> Reset Attempt
                </a>
            </li>

            <div class="section-title">Staff & Access</div>
            <li class="nav-item">
                <a class="nav-link" href="manage_teachers.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Teachers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_admins.php">
                    <i class="fas fa-user-shield me-2"></i> Admin List
                </a>
            </li>
        </ul>

        <div class="mt-4 p-3">
            <a href="system_settings.php" class="btn btn-outline-warning w-100 text-start btn-sm">
                <i class="fas fa-cogs me-2"></i> System Settings
            </a>
            <div class="section-title px-3 mt-3 text-warning">Maintenance</div>
<li class="nav-item">
    <a class="nav-link text-info" href="admin_backup_db.php">
        <i class="fas fa-database me-2"></i> Backup Database
    </a>
</li>
<li class="nav-item">
    <a class="nav-link text-info" href="admin_backup_files.php">
        <i class="fas fa-file-archive me-2"></i> Backup Code (.zip)
    </a>
</li>
            <a href="logout.php" class="btn btn-danger w-100 text-start btn-sm mt-2">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
    </ul>
</div>