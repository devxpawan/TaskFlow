<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/category_helpers.php';
require_once 'includes/subtask_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'My Tasks';
$current_page = 'my_tasks';

$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$priority_filter = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$all_categories = get_user_categories($conn, $user_id);
$has_recurrence = column_exists($conn, 'tasks', 'recurrence');
$has_position = column_exists($conn, 'tasks', 'position');

$where = "WHERE user_id = ?";
$types = "i";
$params = [$user_id];

if (!empty($search)) {
    $where .= " AND title LIKE ?";
    $types .= "s";
    $search_param = "%$search%";
    $params[] = $search_param;
}

if (!empty($status_filter) && in_array($status_filter, ['Pending', 'Completed'])) {
    $where .= " AND status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

if (!empty($priority_filter) && in_array($priority_filter, ['Low', 'Medium', 'High'])) {
    $where .= " AND priority = ?";
    $types .= "s";
    $params[] = $priority_filter;
}

if (!empty($category_filter)) {
    $where .= " AND id IN (SELECT task_id FROM task_categories WHERE category_id = ?)";
    $types .= "i";
    $params[] = $category_filter;
}

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks $where");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_tasks = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = max(1, ceil($total_tasks / $limit));

$sort_newest = $has_position ? 'position ASC, created_at DESC' : 'created_at DESC';
$sort_oldest = $has_position ? 'position DESC, created_at ASC' : 'created_at ASC';
$sort_map = [
    'newest'     => $sort_newest,
    'oldest'     => $sort_oldest,
    'due_asc'    => 'due_date ASC, created_at DESC',
    'due_desc'   => 'due_date DESC, created_at DESC',
    'priority_high' => "FIELD(priority,'High','Medium','Low'), created_at DESC",
    'priority_low'  => "FIELD(priority,'Low','Medium','High'), created_at DESC",
    'title_asc'  => 'title ASC',
    'title_desc' => 'title DESC',
];
$order = $sort_map[$sort] ?? 'created_at DESC';
$query = "SELECT * FROM tasks $where ORDER BY $order LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result();

$task_ids = [];
$tasks_data = [];
while ($row = $tasks->fetch_assoc()) {
    $task_ids[] = $row['id'];
    $tasks_data[] = $row;
}
$tasks_cat_map = !empty($task_ids) ? get_tasks_categories_map($conn, $task_ids) : [];
$tasks_sub_map = !empty($task_ids) ? get_tasks_subtasks_map($conn, $task_ids) : [];

$csrf_token_js = generate_csrf_token();

require_once 'includes/app_header.php';
?>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <button class="btn btn-primary" onclick="openAddModal()">+ New Task</button>
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
                        <?php if (count($all_categories) > 0): ?>
                            <div class="form-group">
                                <select name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($all_categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter === $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <select name="sort">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                                <option value="due_asc" <?php echo $sort === 'due_asc' ? 'selected' : ''; ?>>Due Date (soonest)</option>
                                <option value="due_desc" <?php echo $sort === 'due_desc' ? 'selected' : ''; ?>>Due Date (latest)</option>
                                <option value="priority_high" <?php echo $sort === 'priority_high' ? 'selected' : ''; ?>>Priority (High first)</option>
                                <option value="priority_low" <?php echo $sort === 'priority_low' ? 'selected' : ''; ?>>Priority (Low first)</option>
                                <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title A-Z</option>
                                <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title Z-A</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex-shrink: 0; min-width: auto; display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="my_tasks.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </form>

                <?php if (count($tasks_data) > 0): ?>
                    <div class="task-list">
                        <?php foreach ($tasks_data as $task):
                            $task_cats = $tasks_cat_map[$task['id']] ?? [];
                            $task_subs = $tasks_sub_map[$task['id']] ?? [];
                            $sub_completed = array_filter($task_subs, fn($s) => $s['completed']);
                            $sub_total = count($task_subs);
                        ?>
                            <div class="task-item <?php echo $task['status'] === 'Completed' ? 'task-completed' : ''; ?>" data-task-id="<?php echo $task['id']; ?>">
                                <div class="task-drag-handle" title="Drag to reorder">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="16" y2="6"></line><line x1="8" y1="12" x2="16" y2="12"></line><line x1="8" y1="18" x2="16" y2="18"></line></svg>
                                </div>
                                <div class="task-info">
                                    <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <?php if ($task['description']): ?>
                                        <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="task-meta">
                                        <?php foreach ($task_cats as $tc): ?>
                                            <span class="category-badge" style="--cat-color: <?php echo htmlspecialchars($tc['color']); ?>;">
                                                <span class="category-dot"></span>
                                                <?php echo htmlspecialchars($tc['name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if ($has_recurrence && ($task['recurrence'] ?? 'none') !== 'none'): ?>
                                            <span class="badge badge-recurrence">↻ <?php echo ucfirst($task['recurrence']); ?></span>
                                        <?php endif; ?>
                                        <span class="badge badge-<?php echo strtolower($task['priority']); ?>"><?php echo $task['priority']; ?></span>
                                        <span class="badge badge-<?php echo $task['status'] === 'Completed' ? 'completed' : 'pending'; ?>"><?php echo $task['status']; ?></span>
                                        <?php if ($task['due_date']): ?>
                                            <span class="due-date">Due: <?php echo htmlspecialchars($task['due_date']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($sub_total > 0): ?>
                                            <span class="subtask-count"><?php echo count($sub_completed); ?>/<?php echo $sub_total; ?> subtasks</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($task['status'] !== 'Completed'): ?>
                                        <div class="subtask-section">
                                            <div class="subtask-list" data-task-id="<?php echo $task['id']; ?>">
                                                <?php foreach ($task_subs as $sub): ?>
                                                    <div class="subtask-item" data-sub-id="<?php echo $sub['id']; ?>">
                                                        <button class="subtask-toggle <?php echo $sub['completed'] ? 'checked' : ''; ?>" data-id="<?php echo $sub['id']; ?>" title="<?php echo $sub['completed'] ? 'Unmark' : 'Mark complete'; ?>">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        </button>
                                                        <span class="subtask-title <?php echo $sub['completed'] ? 'completed' : ''; ?>"><?php echo htmlspecialchars($sub['title']); ?></span>
                                                        <button class="subtask-delete" data-id="<?php echo $sub['id']; ?>" title="Delete subtask">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <form class="subtask-add-form" data-task-id="<?php echo $task['id']; ?>">
                                                <input type="text" class="subtask-input" placeholder="Add subtask..." maxlength="255" required>
                                                <button type="submit" class="btn btn-sm btn-primary">Add</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="task-actions">
                                    <?php if ($task['status'] !== 'Completed'): ?>
                                        <a href="tasks/complete_task.php?id=<?php echo $task['id']; ?>&csrf_token=<?php echo $csrf_token_js; ?>" class="btn btn-sm btn-success" title="Mark complete">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-primary" title="Edit" onclick="openEditModal(<?php echo $task['id']; ?>)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </button>
                                    <a href="tasks/delete_task.php?id=<?php echo $task['id']; ?>&csrf_token=<?php echo $csrf_token_js; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this task?')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <?php
                        $from = $offset + 1;
                        $to = min($offset + $limit, $total_tasks);
                        $query_params = [];
                        if (!empty($search)) $query_params['search'] = $search;
                        if (!empty($status_filter)) $query_params['status'] = $status_filter;
                        if (!empty($priority_filter)) $query_params['priority'] = $priority_filter;
                        if (!empty($category_filter)) $query_params['category'] = $category_filter;
                        if ($sort !== 'newest') $query_params['sort'] = $sort;
                        $base_url = 'my_tasks.php?' . http_build_query($query_params);
                        $base_url .= (empty($query_params) ? '?' : '&');
                        ?>
                        <div class="pagination-info">Showing <?php echo $from; ?>–<?php echo $to; ?> of <?php echo $total_tasks; ?></div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" class="btn btn-sm btn-secondary">Prev</a>
                            <?php else: ?>
                                <span class="btn btn-sm btn-secondary" style="opacity:0.4;cursor:default;">Prev</span>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            if ($start > 1): ?>
                                <a href="<?php echo $base_url; ?>page=1" class="btn btn-sm btn-secondary">1</a>
                                <?php if ($start > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <a href="<?php echo $base_url; ?>page=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
                                <a href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>" class="btn btn-sm btn-secondary"><?php echo $total_pages; ?></a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" class="btn btn-sm btn-secondary">Next</a>
                            <?php else: ?>
                                <span class="btn btn-sm btn-secondary" style="opacity:0.4;cursor:default;">Next</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <p>No tasks found. <?php echo !empty($search) || !empty($status_filter) || !empty($priority_filter) ? 'Try adjusting your filters.' : 'Add your first task above!'; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

<div class="modal-overlay" id="editTaskModal" style="display:none;">
    <div class="modal" style="max-width: 540px;">
        <h3 style="margin-bottom: 20px; font-size: 1.1rem;">Edit Task</h3>
        <form id="editTaskForm" method="POST" action="tasks/edit_task.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_js; ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_title">Title</label>
                <input type="text" id="edit_title" name="title" required>
            </div>
            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="description" rows="3"></textarea>
            </div>
            <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                <div class="form-group" style="flex:1; min-width:140px;">
                    <label for="edit_due_date">Due Date</label>
                    <input type="date" id="edit_due_date" name="due_date">
                </div>
                <div class="form-group" style="flex:1; min-width:120px;">
                    <label for="edit_priority">Priority</label>
                    <select id="edit_priority" name="priority">
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1; min-width:120px;">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status">
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <?php if ($has_recurrence): ?>
                    <div class="form-group" style="flex:1; min-width:120px;">
                        <label for="edit_recurrence">Repeat</label>
                        <select id="edit_recurrence" name="recurrence">
                            <option value="none">No repeat</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if (count($all_categories) > 0): ?>
                    <div class="form-group" style="flex:1; min-width:140px;">
                        <label for="edit_categories">Categories</label>
                        <select id="edit_categories" name="category_ids[]">
                            <option value="">No category</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-actions" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="addTaskModal" style="display:none;">
    <div class="modal" style="max-width: 540px;">
        <h3 style="margin-bottom: 20px; font-size: 1.1rem;">New Task</h3>
        <form action="tasks/add_task.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="form-group">
                <label for="modal_title">Title</label>
                <input type="text" id="modal_title" name="title" placeholder="What needs to be done?" required autofocus>
            </div>
            <div class="form-group">
                <label for="modal_description">Description</label>
                <textarea id="modal_description" name="description" rows="3" placeholder="Optional details..."></textarea>
            </div>
            <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                <div class="form-group" style="flex:1; min-width:140px;">
                    <label for="modal_due_date">Due Date</label>
                    <input type="date" id="modal_due_date" name="due_date">
                </div>
                <div class="form-group" style="flex:1; min-width:120px;">
                    <label for="modal_priority">Priority</label>
                    <select id="modal_priority" name="priority">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <?php if ($has_recurrence): ?>
                    <div class="form-group" style="flex:1; min-width:120px;">
                        <label for="modal_recurrence">Repeat</label>
                        <select id="modal_recurrence" name="recurrence">
                            <option value="none">No repeat</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if (count($all_categories) > 0): ?>
                    <div class="form-group" style="flex:1; min-width:140px;">
                        <label for="modal_categories">Categories</label>
                        <select id="modal_categories" name="category_ids[]">
                            <option value="">No category</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-actions" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Create Task</button>
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addTaskModal').style.display = 'flex';
    document.getElementById('modal_title').focus();
}
function closeAddModal() {
    document.getElementById('addTaskModal').style.display = 'none';
}
document.getElementById('addTaskModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});

function openEditModal(taskId) {
    fetch('tasks/get_task.php?id=' + taskId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { alert(data.error); return; }
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_title').value = data.title;
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_due_date').value = data.due_date || '';
            document.getElementById('edit_priority').value = data.priority;
            document.getElementById('edit_status').value = data.status;
            var el = document.getElementById('edit_recurrence');
            if (el) el.value = data.recurrence || 'none';
            var el2 = document.getElementById('edit_categories');
            if (el2) el2.value = (data.category_ids && data.category_ids.length > 0) ? data.category_ids[0] : '';
            document.getElementById('editTaskModal').style.display = 'flex';
        })
        .catch(function() { alert('Failed to load task data.'); });
}
function closeEditModal() {
    document.getElementById('editTaskModal').style.display = 'none';
}
document.getElementById('editTaskModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
document.getElementById('editTaskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    var fd = new FormData(this);
    fetch(this.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            closeEditModal();
            location.reload();
        } else {
            alert(data.error || 'Failed to update task.');
        }
    })
    .catch(function() { alert('Failed to save. Please try again.'); })
    .finally(function() { btn.disabled = false; });
});
</script>

<?php require_once 'includes/app_footer.php'; ?>
