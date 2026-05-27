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
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function verify_task_ownership($conn, $task_id, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$exists;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    if ($task_id <= 0 || empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        exit;
    }

    if (!verify_task_ownership($conn, $task_id, $user_id)) {
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    $pos_stmt = $conn->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM subtasks WHERE task_id = ?");
    $pos_stmt->bind_param("i", $task_id);
    $pos_stmt->execute();
    $pos = (int)$pos_stmt->get_result()->fetch_row()[0];
    $pos_stmt->close();

    $stmt = $conn->prepare("INSERT INTO subtasks (task_id, title, position) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $task_id, $title, $pos);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'id' => $id, 'title' => $title, 'position' => $pos]);
    exit;
}

if (($action === 'complete' || $action === 'uncomplete') && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $completed = $action === 'complete' ? 1 : 0;

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    // Verify ownership through task
    $stmt = $conn->prepare("SELECT t.id FROM tasks t JOIN subtasks s ON s.task_id = t.id WHERE s.id = ? AND t.user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE subtasks SET completed = ? WHERE id = ?");
    $stmt->bind_param("ii", $completed, $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    $stmt = $conn->prepare("SELECT t.id FROM tasks t JOIN subtasks s ON s.task_id = t.id WHERE s.id = ? AND t.user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM subtasks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
