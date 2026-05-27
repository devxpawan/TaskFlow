<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($task_id > 0) {
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = 'Task deleted successfully!';
}

header('Location: ../dashboard.php');
exit;
?>
