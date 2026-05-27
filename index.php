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
    <title>TaskFlow - Minimal & Productive To-Do List</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="hero">
        <nav class="navbar">
            <div class="container">
                <span class="navbar-brand">TaskFlow</span>
                <div>
                    <a href="auth/login.php" class="btn btn-secondary">Login</a>
                    <a href="auth/register.php" class="btn btn-primary">Get Started</a>
                </div>
            </div>
        </nav>

        <div class="hero-content">
            <div class="container">
                <div class="hero-text">
                    <h1>Organize your work and life, finally.</h1>
                    <p>TaskFlow is the clean, minimalist task manager designed to help you regain focus and boost daily productivity without the clutter.</p>
                    <div class="hero-buttons">
                        <a href="auth/register.php" class="btn btn-primary btn-lg">Create Free Account</a>
                        <a href="auth/login.php" class="btn btn-secondary btn-lg">Sign In</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="features">
            <div class="container">
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="9"></line><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="13" y2="17"></line></svg>
                        </div>
                        <h3>Organize Tasks</h3>
                        <p>Create, categorize and prioritize tasks with structured due dates and custom priorities.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                        </div>
                        <h3>Track Progress</h3>
                        <p>Monitor your performance with clear metrics and a live task completion progress tracker.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </div>
                        <h3>Search & Filter</h3>
                        <p>Instantly find high priority or overdue tasks with fast global search and filters.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> TaskFlow. Designed By Pawan Perera with ❤️</p>
        </div>
    </footer>
</body>
</html>
