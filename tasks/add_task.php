<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/category_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission. Please try again.';
        header('Location: ../my_tasks.php');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $priority = $_POST['priority'] ?? 'Medium';
    $category_ids = array_filter($_POST['category_ids'] ?? [], fn($v) => $v !== '');

    $has_recurrence = column_exists($conn, 'tasks', 'recurrence');
    $recurrence = $has_recurrence ? (in_array($_POST['recurrence'] ?? '', ['daily', 'weekly', 'monthly', 'yearly']) ? $_POST['recurrence'] : 'none') : null;

    $has_position = column_exists($conn, 'tasks', 'position');

    if (empty($title)) {
        $_SESSION['error'] = 'Task title is required.';
    } else {
        if ($has_position) {
            $pos_stmt = $conn->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM tasks WHERE user_id = ?");
            $pos_stmt->bind_param("i", $user_id);
            $pos_stmt->execute();
            $position = (int)$pos_stmt->get_result()->fetch_row()[0];
            $pos_stmt->close();
        } else {
            $position = 0;
        }

        $conn->begin_transaction();
        try {
            if ($has_recurrence) {
                $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, recurrence, position) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssi", $user_id, $title, $description, $due_date, $priority, $recurrence, $position);
            } elseif ($has_position) {
                $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, position) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssi", $user_id, $title, $description, $due_date, $priority, $position);
            } else {
                $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $title, $description, $due_date, $priority);
            }
            $stmt->execute();
            $task_id = $stmt->insert_id;
            $stmt->close();

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
            $_SESSION['success'] = 'Task added successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Failed to add task: ' . $e->getMessage();
        }
    }
}

header('Location: ../my_tasks.php');
exit;
