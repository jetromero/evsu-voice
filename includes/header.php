<?php
require_once __DIR__ . '/auth.php';
$auth = new Auth();
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!--=============== FAVICON ===============-->
    <link rel="shortcut icon" href="<?php echo $_SERVER['REQUEST_URI'] === '/' || strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/img/evsu-logo.png" type="image/x-icon">

    <!--=============== REMIXICONS ===============-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/3.5.0/remixicon.css">

    <!--=============== SWIPER CSS ===============-->
    <link rel="stylesheet" href="<?php echo $_SERVER['REQUEST_URI'] === '/' || strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/css/swiper-bundle.min.css?v=<?php echo time(); ?>">

    <!--=============== CSS ===============-->
    <link rel="stylesheet" href="<?php echo $_SERVER['REQUEST_URI'] === '/' || strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/css/styles.css?v=<?php echo time(); ?>">

    <!--=============== GOOGLE SHEETS ADDON CSS ===============-->
    <link rel="stylesheet" href="<?php echo $_SERVER['REQUEST_URI'] === '/' || strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/css/google-sheets-addon.css?v=<?php echo time(); ?>">

    <title><?php echo isset($page_title) ? $page_title . ' - EVSU VOICE' : 'EVSU VOICE'; ?></title>
</head>

<body>
    <!--==================== HEADER ====================-->
    <header class="header" id="header">
        <nav class="nav container">
            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>index.php" class="nav__logo">
                <img src="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/img/evsu-logo.png">EVSU VOICE
            </a>

            <div class="nav__menu">
                <ul class="nav__list">
                    <?php if ($user && $user['role'] === 'admin'): ?>
                        <!-- Admin Navigation -->
                        <li class="nav__item">
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '' : 'admin/'; ?>dashboard.php" class="nav__link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active-link' : ''; ?>">
                                <span>Dashboard</span>
                            </a>
                        </li>

                        <li class="nav__item">
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>browse-suggestions.php" class="nav__link <?php echo basename($_SERVER['PHP_SELF']) == 'browse-suggestions.php' ? 'active-link' : ''; ?>">
                                <span>Browse Suggestions</span>
                            </a>
                        </li>

                        <li class="nav__item">
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '' : 'admin/'; ?>manage-suggestions.php" class="nav__link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-suggestions.php' ? 'active-link' : ''; ?>">
                                <span>Manage Suggestions</span>
                            </a>
                        </li>

                        <li class="nav__item">
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '' : 'admin/'; ?>manage-users.php" class="nav__link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active-link' : ''; ?>">
                                <span>Manage Users</span>
                            </a>
                        </li>

                        <li class="nav__item">
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '' : 'admin/'; ?>export-data.php" class="nav__link <?php echo basename($_SERVER['PHP_SELF']) == 'export-data.php' ? 'active-link' : ''; ?>">
                                <span>Reports</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Regular User Navigation -->
                        <li class="nav__item">
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>index.php" class="nav__link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active-link' : ''; ?>">
                                <span>Home</span>
                            </a>
                        </li>

                        <li class="nav__item">
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>browse-suggestions.php" class="nav__link <?php echo basename($_SERVER['PHP_SELF']) == 'browse-suggestions.php' ? 'active-link' : ''; ?>">
                                <span>Browse Suggestions</span>
                            </a>
                        </li>

                        <?php if ($user): ?>
                            <li class="nav__item">
                                <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>submit-suggestion.php" class="nav__link <?php echo basename($_SERVER['PHP_SELF']) == 'submit-suggestion.php' ? 'active-link' : ''; ?>">
                                    <span>Submit Suggestion</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="nav__actions">
                <?php if ($user): ?>
                    <!-- User dropdown -->
                    <div class="user-dropdown">
                        <i class="ri-user-line user-button" id="user-button"></i>
                        <div class="user-dropdown-content" id="user-dropdown">
                            <div class="user-info">
                                <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                                <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <hr>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="#" class="dropdown-item" onclick="openChangePasswordModal()">
                                    <i class="ri-lock-password-line"></i> Change Password
                                </a>
                                <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '' : 'admin/'; ?>archive.php" class="dropdown-item">
                                    <i class="ri-archive-line"></i> Archive
                                </a>
                            <?php else: ?>
                                <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>my-suggestions.php" class="dropdown-item">
                                    <i class="ri-file-list-line"></i> My Suggestions
                                </a>
                                <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>my-trash.php" class="dropdown-item">
                                    <i class="ri-delete-bin-line"></i> My Trash
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>logout.php" class="dropdown-item">
                                <i class="ri-logout-box-line"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login button -->
                    <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>login.php" class="login-button">
                        <i class="ri-user-line"></i>
                    </a>
                <?php endif; ?>

                <!-- Theme button -->
                <i class="ri-sun-line change-theme" id="theme-button"></i>
            </div>
        </nav>
    </header>

    <!-- Change Password Modal (for admin users) -->
    <?php if ($user && $user['role'] === 'admin'): ?>
        <div id="changePasswordModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Change Password</h3>
                    <span class="close" onclick="closeChangePasswordModal()">&times;</span>
                </div>
                <!-- Notification area -->
                <div id="passwordNotification" class="password-notification" style="display: none;">
                    <span id="notificationMessage"></span>
                </div>
                <form id="changePasswordForm" method="POST" action="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>change-password.php">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    <div class="form-actions">
                        <button type="button" onclick="closeChangePasswordModal()" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openChangePasswordModal() {
                document.getElementById('changePasswordModal').style.display = 'block';
            }

            function closeChangePasswordModal() {
                document.getElementById('changePasswordModal').style.display = 'none';
                document.getElementById('changePasswordForm').reset();
                hidePasswordNotification();
            }

            let notificationTimeout;

            function showPasswordNotification(message, type) {
                const notification = document.getElementById('passwordNotification');
                const messageSpan = document.getElementById('notificationMessage');

                // Clear any existing timeout
                if (notificationTimeout) {
                    clearTimeout(notificationTimeout);
                }

                messageSpan.textContent = message;
                notification.className = 'password-notification ' + type;
                notification.style.display = 'block';

                // Auto-hide after 4 seconds
                notificationTimeout = setTimeout(() => {
                    hidePasswordNotification();
                }, 3000);
            }

            function hidePasswordNotification() {
                // Clear the timeout when manually hiding
                if (notificationTimeout) {
                    clearTimeout(notificationTimeout);
                    notificationTimeout = null;
                }
                document.getElementById('passwordNotification').style.display = 'none';
            }

            // Check for notifications on page load
            document.addEventListener('DOMContentLoaded', function() {
                checkForPasswordNotifications();
            });

            function checkForPasswordNotifications() {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('password_changed') === 'success') {
                    openChangePasswordModal();
                    showPasswordNotification('Password changed successfully!', 'success');
                    // Remove the parameter from URL
                    urlParams.delete('password_changed');
                    window.history.replaceState({}, '', window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : ''));
                } else if (urlParams.get('password_error')) {
                    const errorMsg = urlParams.get('password_error');
                    openChangePasswordModal();
                    showPasswordNotification(decodeURIComponent(errorMsg), 'error');
                    // Remove the parameter from URL
                    urlParams.delete('password_error');
                    window.history.replaceState({}, '', window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : ''));
                }
            }

            // Close modal when clicking outside of it
            window.onclick = function(event) {
                const modal = document.getElementById('changePasswordModal');
                if (event.target == modal) {
                    closeChangePasswordModal();
                }
            }

            // Validate password confirmation
            document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showPasswordNotification('New password and confirmation do not match!', 'error');
                    return;
                }

                // Hide any existing notifications before submitting
                hidePasswordNotification();
            });
        </script>

        <style>
            .modal {
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
            }

            .modal-content {
                background-color: #fefefe;
                margin: 10% auto;
                padding: 0;
                border-radius: 8px;
                width: 90%;
                max-width: 500px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            .modal-header {
                padding: 20px;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .modal-header h3 {
                margin: 0;
                color: #333;
            }

            .close {
                color: #aaa;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .close:hover {
                color: #000;
            }

            .modal form {
                padding: 20px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
                color: #333;
            }

            .form-group input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }

            .form-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 20px;
            }

            .btn-primary,
            .btn-secondary {
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }

            .btn-primary {
                background-color: var(--evsu-color);
                color: white;
            }

            .btn-primary:hover {
                background-color: #c82333;
            }

            .btn-secondary {
                background-color: #6c757d;
                color: white;
            }

            .btn-secondary:hover {
                background-color: #5a6268;
            }

            .password-notification {
                margin: 0 20px 20px 20px;
                padding: 12px 15px;
                border-radius: 4px;
                position: relative;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 14px;
                animation: slideDown 0.3s ease-out;
            }

            .password-notification.success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .password-notification.error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .password-notification.info {
                background-color: #d1ecf1;
                color: #0c5460;
                border: 1px solid #bee5eb;
            }

            .notification-close {
                cursor: pointer;
                font-size: 18px;
                font-weight: bold;
                margin-left: 10px;
                opacity: 0.7;
            }

            .notification-close:hover {
                opacity: 1;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>
    <?php endif; ?>
</body>

</html>