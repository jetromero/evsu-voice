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
        // Verify current password
        $database = new DatabaseNative();
        $conn = $database->getConnection();

        $query = "SELECT password FROM users WHERE id = $1";
        $result = $database->query($query, [$_SESSION['user_id']]);
        $user = $database->fetchAssoc($result);

        if ($user && $current_password === $user['password']) {
            // Update password (plain text since we removed hashing)
            $update_query = "UPDATE users SET password = $1 WHERE id = $2";
            $update_result = $database->query($update_query, [$new_password, $_SESSION['user_id']]);

            if ($update_result) {
                $message = 'Password changed successfully!';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } else {
            $error = 'Current password is incorrect.';
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
