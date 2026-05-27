<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Dashboard';
$current_page = 'dashboard';

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_tasks = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE user_id = ? AND status = 'Completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_tasks = $stmt->get_result()->fetch_assoc()['total'];

$pending_tasks = $total_tasks - $completed_tasks;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE user_id = ? AND status = 'Pending' AND due_date IS NOT NULL AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_deadlines = $stmt->get_result()->fetch_assoc()['total'];

$completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

require_once 'includes/app_header.php';
?>

        <div class="stats-grid">
            <div class="stat-card">
                <svg class="stat-card-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="9"></line><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="13" y2="17"></line></svg>
                <h3><?php echo $total_tasks; ?></h3>
                <p>Total Tasks</p>
            </div>
            <div class="stat-card stat-completed">
                <svg class="stat-card-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <h3><?php echo $completed_tasks; ?></h3>
                <p>Completed</p>
            </div>
            <div class="stat-card stat-pending">
                <svg class="stat-card-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <h3><?php echo $pending_tasks; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card stat-deadlines">
                <svg class="stat-card-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <h3><?php echo $upcoming_deadlines; ?></h3>
                <p>Upcoming Deadlines</p>
            </div>
        </div>

        <?php if ($total_tasks > 0): ?>
            <div class="progress-container">
                <div class="progress-header">
                    <span class="progress-title">Your Progress</span>
                    <span class="progress-value"><?php echo $completion_rate; ?>% Completed</span>
                </div>
                <div class="progress-track">
                    <div class="progress-bar" style="width: <?php echo $completion_rate; ?>%"></div>
                </div>
            </div>
        <?php endif; ?>

<?php require_once 'includes/app_footer.php'; ?>
