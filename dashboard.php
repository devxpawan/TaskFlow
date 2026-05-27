<?php
session_start();
require_once 'includes/db.php';

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
                <a href="auth/logout.php" class="btn btn-sm btn-secondary">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_tasks; ?></h3>
                <p>Total Tasks</p>
            </div>
            <div class="stat-card stat-completed">
                <h3><?php echo $completed_tasks; ?></h3>
                <p>Completed</p>
            </div>
            <div class="stat-card stat-pending">
                <h3><?php echo $pending_tasks; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card stat-deadlines">
                <h3><?php echo $upcoming_deadlines; ?></h3>
                <p>Upcoming Deadlines</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Add New Task</h2>
            </div>
            <div class="card-body">
                <form action="tasks/add_task.php" method="POST" class="task-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="title" placeholder="Task title" required>
                        </div>
                        <div class="form-group">
                            <textarea name="description" placeholder="Description (optional)" rows="1"></textarea>
                        </div>
                        <div class="form-group">
                            <input type="date" name="due_date">
                        </div>
                        <div class="form-group">
                            <select name="priority">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
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
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="priority">
                                <option value="">All Priority</option>
                                <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                        <div class="form-group">
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
                                        <a href="tasks/complete_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-success" title="Mark complete">&#10003;</a>
                                    <?php endif; ?>
                                    <a href="tasks/edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary" title="Edit">&#9998;</a>
                                    <a href="tasks/delete_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this task?')">&#10005;</a>
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
