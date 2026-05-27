<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission. Please try again.';
        header('Location: ../dashboard.php');
        exit;
    }

    
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $priority = $_POST['priority'] ?? 'Medium';

    if (empty($title)) {
        $_SESSION['error'] = 'Task title is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $title, $description, $due_date, $priority);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Task added successfully!';
    }
}

header('Location: ../dashboard.php');
exit;
?>
