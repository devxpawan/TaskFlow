<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../my_tasks.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid form submission.';
    header('Location: ../my_tasks.php');
    exit;
}

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = 'Please select a valid file.';
    header('Location: ../my_tasks.php');
    exit;
}

$filename = $_FILES['import_file']['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$content = file_get_contents($_FILES['import_file']['tmp_name']);

if ($ext === 'csv') {
    // Parse CSV
    $lines = explode("\n", $content);
    // Skip BOM if present
    if (isset($lines[0]) && str_starts_with($lines[0], "\xEF\xBB\xBF")) {
        $lines[0] = substr($lines[0], 3);
    }
    // Remove empty trailing lines
    $lines = array_filter($lines, fn($l) => trim($l) !== '');
    $lines = array_values($lines);

    if (count($lines) < 2) {
        $_SESSION['error'] = 'CSV file must have a header row and at least one data row.';
        header('Location: ../my_tasks.php');
        exit;
    }

    $header = str_getcsv($lines[0]);
    $expected = ['title', 'description', 'due_date', 'priority', 'status', 'recurrence', 'categories', 'subtasks'];
    // Allow minimal format: just title
    $has_full_header = count(array_intersect($header, $expected)) >= 4;

    $tasks = [];
    for ($i = 1; $i < count($lines); $i++) {
        $row = str_getcsv($lines[$i]);
        if ($has_full_header) {
            $row = array_pad($row, count($expected), '');
            $row = array_combine($expected, array_slice($row, 0, count($expected)));
        } else {
            // Treat first column as title only
            $row = ['title' => $row[0] ?? '', 'description' => '', 'due_date' => '', 'priority' => 'Medium', 'status' => 'Pending', 'recurrence' => 'none', 'categories' => '', 'subtasks' => ''];
        }

        $cats = [];
        if (!empty($row['categories'])) {
            foreach (explode(',', $row['categories']) as $cn) {
                $cn = trim($cn);
                if ($cn) $cats[] = ['name' => $cn, 'color' => '#6366f1'];
            }
        }

        $subs = [];
        if (!empty($row['subtasks'])) {
            foreach (explode('|', $row['subtasks']) as $s) {
                $s = trim($s);
                if (!$s) continue;
                $completed = str_starts_with($s, '[x]');
                $title = ltrim(substr($s, 3));
                if ($title) $subs[] = ['title' => $title, 'completed' => $completed];
            }
        }

        $tasks[] = [
            'title'       => $row['title'],
            'description' => $row['description'] ?? '',
            'due_date'    => $row['due_date'] ?? '',
            'priority'    => $row['priority'] ?? 'Medium',
            'status'      => $row['status'] ?? 'Pending',
            'recurrence'  => $row['recurrence'] ?? 'none',
            'categories'  => $cats,
            'subtasks'    => $subs,
        ];
    }
} else {
    // JSON
    $data = json_decode($content, true);
    if (!$data || !isset($data['tasks']) || !is_array($data['tasks'])) {
        $_SESSION['error'] = 'Invalid JSON format. Expected {"tasks": [...]}';
        header('Location: ../my_tasks.php');
        exit;
    }
    $tasks = $data['tasks'];
}

$imported = 0;
$errors = 0;

$cat_cache = [];
$conn->begin_transaction();
try {
    $cat_stmt = $conn->prepare("SELECT id, name FROM categories WHERE user_id = ?");
    $cat_stmt->bind_param("i", $user_id);
    $cat_stmt->execute();
    foreach ($cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $cat_cache[$row['name']] = $row['id'];
    }
    $cat_stmt->close();

    $ins_cat = $conn->prepare("INSERT IGNORE INTO categories (user_id, name, color) VALUES (?, ?, ?)");
    $sel_cat = $conn->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ?");
    $ins_task = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, status, recurrence) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins_tc   = $conn->prepare("INSERT INTO task_categories (task_id, category_id) VALUES (?, ?)");
    $ins_sub  = $conn->prepare("INSERT INTO subtasks (task_id, title, completed) VALUES (?, ?, ?)");

    foreach ($tasks as $task) {
        $title = trim($task['title'] ?? '');
        if (empty($title)) { $errors++; continue; }

        $description = trim($task['description'] ?? '');
        $due_date    = !empty($task['due_date']) ? $task['due_date'] : null;
        $priority    = in_array($task['priority'] ?? '', ['Low', 'Medium', 'High']) ? $task['priority'] : 'Medium';
        $status      = in_array($task['status'] ?? '', ['Pending', 'Completed']) ? $task['status'] : 'Pending';
        $recurrence  = in_array($task['recurrence'] ?? '', ['daily', 'weekly', 'monthly', 'yearly']) ? $task['recurrence'] : 'none';

        $ins_task->bind_param("issssss", $user_id, $title, $description, $due_date, $priority, $status, $recurrence);
        $ins_task->execute();
        $task_id = $ins_task->insert_id;

        // Categories
        if (!empty($task['categories'])) {
            foreach ($task['categories'] as $c) {
                $cname = trim(is_string($c) ? $c : ($c['name'] ?? ''));
                if (empty($cname)) continue;

                if (!isset($cat_cache[$cname])) {
                    $color = is_array($c) ? ($c['color'] ?? '#6366f1') : '#6366f1';
                    $ins_cat->bind_param("iss", $user_id, $cname, $color);
                    $ins_cat->execute();
                    if ($ins_cat->insert_id > 0) {
                        $cat_cache[$cname] = $ins_cat->insert_id;
                    } else {
                        $sel_cat->bind_param("is", $user_id, $cname);
                        $sel_cat->execute();
                        $cat_cache[$cname] = (int)$sel_cat->get_result()->fetch_row()[0];
                    }
                }
                if (isset($cat_cache[$cname])) {
                    $ins_tc->bind_param("ii", $task_id, $cat_cache[$cname]);
                    $ins_tc->execute();
                }
            }
        }

        // Subtasks
        if (!empty($task['subtasks'])) {
            foreach ($task['subtasks'] as $s) {
                $stitle = trim(is_string($s) ? $s : ($s['title'] ?? ''));
                if (empty($stitle)) continue;
                $completed = is_array($s) ? (!empty($s['completed']) ? 1 : 0) : 0;
                $ins_sub->bind_param("isi", $task_id, $stitle, $completed);
                $ins_sub->execute();
            }
        }

        $imported++;
    }

    $conn->commit();
    $_SESSION['success'] = "Imported $imported task(s)" . ($errors ? " ($errors skipped)" : "") . "!";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Import failed: ' . $e->getMessage();
}

header('Location: ../my_tasks.php');
exit;
