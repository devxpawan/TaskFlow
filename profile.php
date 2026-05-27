<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Profile';
$current_page = 'profile';

$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

require_once 'includes/app_header.php';
?>
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

<?php require_once 'includes/app_footer.php'; ?>
