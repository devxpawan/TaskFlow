<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/recurrence_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = $_GET['csrf_token'] ?? '';

if ($task_id > 0 && verify_csrf_token($token)) {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($task) {
        $has_recurrence = column_exists($conn, 'tasks', 'recurrence');

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE tasks SET status = 'Completed' WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $task_id, $user_id);
            $stmt->execute();
            $stmt->close();

            $msg = 'Task marked as completed!';
            if ($has_recurrence && ($task['recurrence'] ?? 'none') !== 'none') {
                $new_id = duplicate_recurring_task($conn, $task, $user_id);
                if ($new_id) {
                    $msg = 'Task completed! Next occurrence created.';
                }
            }

            $conn->commit();
            $_SESSION['success'] = $msg;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Failed to complete task.';
        }
    } else {
        $_SESSION['error'] = 'Task not found.';
    }
} else {
    $_SESSION['error'] = 'Invalid request. Please try again.';
}

header('Location: ../my_tasks.php');
exit;
