<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$priority_filter = isset($_GET['priority']) ? trim($_GET['priority']) : '';

$query = "SELECT * FROM tasks WHERE user_id = ?";
$types = "i";
$params = [$user_id];

if (!empty($search)) {
    $query .= " AND title LIKE ?";
    $types .= "s";
    $search_param = "%$search%";
    $params[] = $search_param;
}

if (!empty($status_filter) && in_array($status_filter, ['Pending', 'Completed'])) {
    $query .= " AND status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

if (!empty($priority_filter) && in_array($priority_filter, ['Low', 'Medium', 'High'])) {
    $query .= " AND priority = ?";
    $types .= "s";
    $params[] = $priority_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result();

$completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">TaskFlow</a>
            <div class="navbar-right">
                <span class="user-greeting">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="profile.php" class="btn btn-sm btn-secondary">Profile</a>
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

        <div class="card">
            <div class="card-header">
                <h2>Add New Task</h2>
            </div>
            <div class="card-body">
                <form action="tasks/add_task.php" method="POST" class="task-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <input type="text" name="title" placeholder="What needs to be done?" required>
                        </div>
                        <div class="form-group" style="flex: 3;">
                            <input type="text" name="description" placeholder="Description (optional)">
                        </div>
                        <div class="form-group">
                            <input type="date" name="due_date">
                        </div>
                        <div class="form-group">
                            <select name="priority">
                                <option value="Low">Low Priority</option>
                                <option value="Medium" selected>Medium Priority</option>
                                <option value="High">High Priority</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-full">Add Task</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>My Tasks</h2>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="search-filter">
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <input type="text" name="search" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="priority">
                                <option value="">All Priorities</option>
                                <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex-shrink: 0; min-width: auto; display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="dashboard.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </form>

                <?php if ($tasks->num_rows > 0): ?>
                    <div class="task-list">
                        <?php while ($task = $tasks->fetch_assoc()): ?>
                            <div class="task-item <?php echo $task['status'] === 'Completed' ? 'task-completed' : ''; ?>">
                                <div class="task-info">
                                    <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <?php if ($task['description']): ?>
                                        <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="task-meta">
                                        <span class="badge badge-<?php echo strtolower($task['priority']); ?>"><?php echo $task['priority']; ?></span>
                                        <span class="badge badge-<?php echo $task['status'] === 'Completed' ? 'completed' : 'pending'; ?>"><?php echo $task['status']; ?></span>
                                        <?php if ($task['due_date']): ?>
                                            <span class="due-date">Due: <?php echo htmlspecialchars($task['due_date']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="task-actions">
                                    <?php if ($task['status'] !== 'Completed'): ?>
                                        <a href="tasks/complete_task.php?id=<?php echo $task['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-sm btn-success" title="Mark complete">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        </a>
                                    <?php endif; ?>
                                    <a href="tasks/edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <a href="tasks/delete_task.php?id=<?php echo $task['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this task?')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No tasks found. <?php echo !empty($search) || !empty($status_filter) || !empty($priority_filter) ? 'Try adjusting your filters.' : 'Add your first task above!'; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
