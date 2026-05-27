<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$type = $_POST['type'] ?? ''; // 'tasks' or 'subtasks'
$ids = $_POST['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'error' => 'No IDs']);
    exit;
}

$ids = array_map('intval', $ids);

if ($type === 'tasks') {
    // Verify all tasks belong to user
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $types .= 'i';
    $params = $ids;
    $params[] = $user_id;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tasks WHERE id IN ($placeholders) AND user_id = ?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if ($count !== count($ids)) {
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE tasks SET position = ? WHERE id = ?");
    foreach ($ids as $pos => $id) {
        $stmt->bind_param("ii", $pos, $id);
        $stmt->execute();
    }
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($type === 'subtasks') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    // Verify task ownership
    $stmt = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE subtasks SET position = ? WHERE id = ? AND task_id = ?");
    foreach ($ids as $pos => $id) {
        $stmt->bind_param("iii", $pos, $id, $task_id);
        $stmt->execute();
    }
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid type']);
