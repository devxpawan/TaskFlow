<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/category_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Categories';
$current_page = 'categories';

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission.';
    } elseif ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6366f1');
        if (empty($name)) {
            $_SESSION['error'] = 'Category name is required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $name, $color);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Category created!';
            } else {
                $_SESSION['error'] = 'Category name already exists.';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6366f1');
        if (empty($name)) {
            $_SESSION['error'] = 'Category name is required.';
        } else {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, color = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssii", $name, $color, $id, $user_id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $_SESSION['success'] = 'Category updated!';
            } else {
                $_SESSION['error'] = 'Category not found or name already taken.';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Category deleted.';
    }
    header('Location: categories.php');
    exit;
}

$categories = get_user_categories($conn, $user_id);

require_once 'includes/app_header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Categories</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="" class="task-form" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border);">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="add">
            <h3 style="margin-bottom: 16px; font-size: 1rem;">Create New Category</h3>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <input type="text" name="name" placeholder="Category name" required>
                </div>
                <div class="form-group" style="flex: 0 0 auto; min-width: auto;">
                    <input type="color" name="color" value="#6366f1" style="width: 50px; height: 42px; padding: 4px; cursor: pointer;">
                </div>
                <div class="form-group" style="flex-shrink: 0; min-width: auto;">
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </div>
        </form>

        <?php if (count($categories) > 0): ?>
            <div class="category-list">
                <?php foreach ($categories as $cat): ?>
                    <div class="category-item">
                        <span class="category-badge" style="--cat-color: <?php echo htmlspecialchars($cat['color']); ?>;">
                            <span class="category-dot"></span>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </span>
                        <div class="category-actions">
                            <button class="btn btn-sm btn-secondary" onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cat['color']); ?>')">Edit</button>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete category &quot;<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>&quot;?')">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No categories yet. Create one above to start organizing your tasks.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal" style="display:none;">
    <div class="modal">
        <h3 style="margin-bottom: 16px; font-size: 1rem;">Edit Category</h3>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label for="edit_name">Name</label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label for="edit_color">Color</label>
                <input type="color" name="color" id="edit_color" value="#6366f1" style="width: 60px; height: 42px; padding: 4px; cursor: pointer;">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(id, name, color) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_color').value = color;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php require_once 'includes/app_footer.php'; ?>
