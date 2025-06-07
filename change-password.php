<?php
session_start();
require_once 'includes/auth.php';
require_once 'config/database_native.php';

$auth = new Auth();
$auth->requireLogin();

// Only allow admin users to change password through this interface
if (!$auth->isAdmin()) {
    header('Location: index.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        // Use the secure changePassword method from Auth class
        $result = $auth->changePassword($_SESSION['user_id'], $current_password, $new_password);

        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Redirect back to the referring page with message
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'admin/dashboard.php';

// Simple approach for local development - just add query parameters
if ($message) {
    $separator = strpos($redirect_url, '?') !== false ? '&' : '?';
    $redirect_url .= $separator . 'password_changed=success';
} elseif ($error) {
    $separator = strpos($redirect_url, '?') !== false ? '&' : '?';
    $redirect_url .= $separator . 'password_error=' . urlencode($error);
}

header('Location: ' . $redirect_url);
exit();
