<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - To-Do List App</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="hero">
        <nav class="navbar">
            <div class="container">
                <span class="navbar-brand">TaskFlow</span>
                <div>
                    <a href="auth/login.php" class="btn btn-primary">Login</a>
                    <a href="auth/register.php" class="btn btn-secondary">Register</a>
                </div>
            </div>
        </nav>

        <div class="hero-content">
            <div class="container">
                <div class="hero-text">
                    <h1>Manage Your Tasks with TaskFlow</h1>
                    <p>A simple, intuitive to-do list application to help you stay organized and productive.</p>
                    <div class="hero-buttons">
                        <a href="auth/register.php" class="btn btn-primary btn-lg">Get Started</a>
                        <a href="auth/login.php" class="btn btn-secondary btn-lg">Login</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="features">
            <div class="container">
                <div class="features-grid">
                    <div class="feature-card">
                        <h3>Organize Tasks</h3>
                        <p>Create, edit, and organize tasks with due dates and priority levels.</p>
                    </div>
                    <div class="feature-card">
                        <h3>Track Progress</h3>
                        <p>Monitor completed and pending tasks with an intuitive dashboard.</p>
                    </div>
                    <div class="feature-card">
                        <h3>Search & Filter</h3>
                        <p>Quickly find tasks by title, status, or priority level.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> TaskFlow. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
