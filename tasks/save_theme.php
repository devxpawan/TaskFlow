<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$theme = $_POST['theme'] ?? '';
if (!in_array($theme, ['light', 'dark'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid theme']);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
$stmt->bind_param("si", $theme, $user_id);

if ($stmt->execute()) {
    $_SESSION['theme'] = $theme;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

$stmt->close();
