<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/category_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($task_id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $task_id, $user_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    echo json_encode(['error' => 'Task not found']);
    exit;
}

$task_cats = get_task_categories($conn, $task_id);
$task_cat_ids = array_column($task_cats, 'id');

echo json_encode([
    'id'          => $task['id'],
    'title'       => $task['title'],
    'description' => $task['description'],
    'due_date'    => $task['due_date'],
    'priority'    => $task['priority'],
    'status'      => $task['status'],
    'recurrence'  => $task['recurrence'] ?? 'none',
    'category_ids'=> $task_cat_ids,
]);
