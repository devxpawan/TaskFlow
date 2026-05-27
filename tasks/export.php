<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/category_helpers.php';
require_once '../includes/subtask_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY position ASC, created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$task_ids = array_column($tasks, 'id');
$cat_map = !empty($task_ids) ? get_tasks_categories_map($conn, $task_ids) : [];
$sub_map = !empty($task_ids) ? get_tasks_subtasks_map($conn, $task_ids) : [];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="taskflow-export-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, ['title', 'description', 'due_date', 'priority', 'status', 'recurrence', 'categories', 'subtasks']);

foreach ($tasks as $task) {
    $cats = $cat_map[$task['id']] ?? [];
    $cat_str = implode(', ', array_map(fn($c) => $c['name'], $cats));

    $subs = $sub_map[$task['id']] ?? [];
    $sub_str = implode('|', array_map(fn($s) => ($s['completed'] ? '[x] ' : '[ ] ') . $s['title'], $subs));

    fputcsv($out, [
        $task['title'],
        $task['description'],
        $task['due_date'],
        $task['priority'],
        $task['status'],
        $task['recurrence'] ?? 'none',
        $cat_str,
        $sub_str,
    ]);
}
fclose($out);
