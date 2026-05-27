<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/category_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Edit Task';
$current_page = 'dashboard';

$task_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($task_id <= 0) {
    header('Location: ../my_tasks.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $task_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();
$stmt->close();

if (!$task) {
    header('Location: ../my_tasks.php');
    exit;
}

$all_categories = get_user_categories($conn, $user_id);
$task_categories = get_task_categories($conn, $task_id);
$task_cat_ids = array_column($task_categories, 'id');

$error = '';
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$has_recurrence = column_exists($conn, 'tasks', 'recurrence');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $priority = $_POST['priority'] ?? 'Medium';
        $status = $_POST['status'] ?? 'Pending';
        $category_ids = $_POST['category_ids'] ?? [];

        if ($has_recurrence) {
            $recurrence = in_array($_POST['recurrence'] ?? '', ['daily', 'weekly', 'monthly', 'yearly']) ? $_POST['recurrence'] : 'none';
        }

        if (empty($title)) {
            $error = 'Task title is required.';
        } else {
            $conn->begin_transaction();
            try {
                if ($has_recurrence) {
                    $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, status = ?, recurrence = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ssssssii", $title, $description, $due_date, $priority, $status, $recurrence, $task_id, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, status = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("sssssii", $title, $description, $due_date, $priority, $status, $task_id, $user_id);
                }
                $stmt->execute();
                $stmt->close();

                $conn->query("DELETE FROM task_categories WHERE task_id = $task_id");

                $category_ids = array_filter($category_ids, fn($v) => $v !== '');
                if (!empty($category_ids)) {
                    $cat_stmt = $conn->prepare("INSERT INTO task_categories (task_id, category_id) VALUES (?, ?)");
                    foreach ($category_ids as $cid) {
                        $cid = (int)$cid;
                        $cat_stmt->bind_param("ii", $task_id, $cid);
                        $cat_stmt->execute();
                    }
                    $cat_stmt->close();
                }

                $conn->commit();
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit;
                }
                $_SESSION['success'] = 'Task updated successfully!';
                header('Location: ../my_tasks.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to update task.';
            }
        }
    }
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
}

require_once '../includes/app_header.php';
?>
<div class="auth-container" style="min-height: auto; padding: 0; background: none;">
    <div class="auth-card">
        <h1>TaskFlow</h1>
        <h2>Edit your task details.</h2>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($task['description']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="due_date">Due Date</label>
                <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($task['due_date']); ?>">
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <option value="Low" <?php echo $task['priority'] === 'Low' ? 'selected' : ''; ?>>Low Priority</option>
                    <option value="Medium" <?php echo $task['priority'] === 'Medium' ? 'selected' : ''; ?>>Medium Priority</option>
                    <option value="High" <?php echo $task['priority'] === 'High' ? 'selected' : ''; ?>>High Priority</option>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <?php if ($has_recurrence): ?>
            <div class="form-group">
                <label for="recurrence">Repeat</label>
                <select id="recurrence" name="recurrence">
                    <option value="none" <?php echo ($task['recurrence'] ?? 'none') === 'none' ? 'selected' : ''; ?>>No repeat</option>
                    <option value="daily" <?php echo ($task['recurrence'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                    <option value="weekly" <?php echo ($task['recurrence'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?php echo ($task['recurrence'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="yearly" <?php echo ($task['recurrence'] ?? '') === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                </select>
            </div>
            <?php endif; ?>
            <?php if (count($all_categories) > 0): ?>
                <div class="form-group">
                    <label>Categories</label>
                    <div class="category-checkboxes">
                        <?php foreach ($all_categories as $cat): ?>
                            <label class="category-checkbox-label">
                                <input type="checkbox" name="category_ids[]" value="<?php echo $cat['id']; ?>" <?php echo in_array($cat['id'], $task_cat_ids) ? 'checked' : ''; ?>>
                                <span class="category-checkbox-dot" style="--cat-color: <?php echo htmlspecialchars($cat['color']); ?>;"></span>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Task</button>
                <a href="../my_tasks.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once '../includes/app_footer.php'; ?>
