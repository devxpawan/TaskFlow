<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid form submission. Please try again.';
    header('Location: profile.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'username') {
    $username = trim($_POST['username'] ?? '');

    if (empty($username)) {
        $_SESSION['error'] = 'Username cannot be empty.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->bind_param("si", $username, $user_id);
        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            $_SESSION['success'] = 'Username updated successfully!';
        } else {
            $_SESSION['error'] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }

} elseif ($action === 'email') {
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format.';
    } elseif (empty($current_password)) {
        $_SESSION['error'] = 'Please enter your current password.';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!password_verify($current_password, $user['password'])) {
            $_SESSION['error'] = 'Current password is incorrect.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $_SESSION['error'] = 'Email is already in use by another account.';
            } else {
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $email, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Email updated successfully!';
                } else {
                    $_SESSION['error'] = 'Something went wrong. Please try again.';
                }
            }
            $stmt->close();
        }
    }

} elseif ($action === 'password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = 'All password fields are required.';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error'] = 'New password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!password_verify($current_password, $user['password'])) {
            $_SESSION['error'] = 'Current password is incorrect.';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Password updated successfully!';
            } else {
                $_SESSION['error'] = 'Something went wrong. Please try again.';
            }
            $stmt->close();
        }
    }

} else {
    $_SESSION['error'] = 'Invalid action.';
}

header('Location: profile.php');
exit;
