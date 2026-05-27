<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">TaskFlow</a>
            <div class="navbar-right">
                <span class="user-greeting">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="dashboard.php" class="btn btn-sm btn-secondary">Dashboard</a>
                <a href="auth/logout.php" class="btn btn-sm btn-secondary">Logout</a>
            </div>
        </div>
    </nav>

    <div class="toast-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="toast toast-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <div class="toast-message"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="toast toast-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <div class="toast-message"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="container main-content">
        <div class="card">
            <div class="card-header">
                <h2>Profile Settings</h2>
            </div>
            <div class="card-body">
                <form action="update_profile.php" method="POST" style="margin-bottom: 32px; padding-bottom: 32px; border-bottom: 1px solid var(--border);">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="username">
                    <h3 style="margin-bottom: 16px; font-size: 1rem;">Change Username</h3>
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label for="username">Current Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="form-group" style="flex-shrink: 0; min-width: auto;">
                            <button type="submit" class="btn btn-primary">Update Username</button>
                        </div>
                    </div>
                </form>

                <form action="update_profile.php" method="POST" style="margin-bottom: 32px; padding-bottom: 32px; border-bottom: 1px solid var(--border);">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="email">
                    <h3 style="margin-bottom: 16px; font-size: 1rem;">Change Email</h3>
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label for="new_email">New Email</label>
                            <input type="email" id="new_email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="password_email">Current Password</label>
                            <input type="password" id="password_email" name="current_password" placeholder="Enter your password" required>
                        </div>
                        <div class="form-group" style="flex-shrink: 0; min-width: auto;">
                            <button type="submit" class="btn btn-primary">Update Email</button>
                        </div>
                    </div>
                </form>

                <form action="update_profile.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="password">
                    <h3 style="margin-bottom: 16px; font-size: 1rem;">Change Password</h3>
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Current password" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="At least 6 characters" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Verify new password" required>
                        </div>
                        <div class="form-group" style="flex-shrink: 0; min-width: auto;">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
