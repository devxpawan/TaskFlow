<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$page_title = 'Tools';
$current_page = 'tools';
$csrf_token_js = generate_csrf_token();

require_once 'includes/app_header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Export Tasks</h2>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 16px; color: var(--text-muted); font-size: 0.9rem;">
            Download all your tasks as a CSV file. Includes categories, subtasks, and recurrence settings.
        </p>
        <a href="tasks/export.php" class="btn btn-primary">Export CSV</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Import Tasks</h2>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 16px; color: var(--text-muted); font-size: 0.9rem;">
            Upload a JSON or CSV file to bulk-create tasks. Download the template for the expected format.
        </p>
        <form method="POST" action="tasks/import.php" enctype="multipart/form-data" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_js; ?>">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                <label for="import_file" style="font-size:0.85rem;">Choose file</label>
                <input type="file" id="import_file" name="import_file" accept=".json,.csv" required>
            </div>
            <button type="submit" class="btn btn-primary">Import</button>
            <a href="tasks/template.php" class="btn btn-secondary">Download Template</a>
        </form>
    </div>
</div>

<?php require_once 'includes/app_footer.php'; ?>
