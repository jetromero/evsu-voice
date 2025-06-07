<?php
$page_title = "Manage Users";
require_once '../includes/auth.php';
require_once '../config/database_native.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new DatabaseNative();
$conn = $database->getConnection();

$message = '';
$error = '';

// Handle password change requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_password') {
        $user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_password) || empty($confirm_password)) {
            $error = 'All fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            // Use the admin password change method
            $result = $auth->adminChangePassword($user_id, $new_password);

            if ($result['success']) {
                $message = 'Password changed successfully for user.';
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Handle URL parameters for notifications
if (isset($_GET['password_changed']) && $_GET['password_changed'] === 'success') {
    $message = 'Password changed successfully.';
}
if (isset($_GET['password_error'])) {
    $error = urldecode($_GET['password_error']);
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query with filters (exclude admin users)
$where_conditions = ["role != 'admin'"];
$params = [];
$param_count = 1;

if (!empty($search)) {
    $where_conditions[] = "(first_name ILIKE $" . $param_count . " OR last_name ILIKE $" . $param_count . " OR email ILIKE $" . $param_count . ")";
    $params[] = '%' . $search . '%';
    $param_count++;
}

if (!empty($role_filter) && $role_filter !== 'all') {
    $where_conditions[] = "role = $" . $param_count;
    $params[] = $role_filter;
    $param_count++;
}

if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(created_at) = CURRENT_DATE";
            break;
        case 'week':
            $where_conditions[] = "created_at >= CURRENT_DATE - INTERVAL '7 days'";
            break;
        case 'month':
            $where_conditions[] = "created_at >= CURRENT_DATE - INTERVAL '30 days'";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get filtered users (excluding admins)
$users_query = "SELECT id, email, first_name, last_name, role, created_at 
                FROM users 
                $where_clause
                ORDER BY created_at DESC";
$users_result = $database->query($users_query, $params);
$users = $database->fetchAll($users_result);

// Get user statistics (excluding admins for the main count)
$stats_query = "SELECT 
                COUNT(*) FILTER (WHERE role != 'admin') as total_users,
                COUNT(*) FILTER (WHERE role = 'admin') as admin_count,
                COUNT(*) FILTER (WHERE role = 'student') as student_count
                FROM users";
$stats_result = $database->query($stats_query);
$stats = $database->fetchAssoc($stats_result);

include '../includes/header.php';
?>

<main class="main">
    <section class="admin-users section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title">Manage Users</h1>
                <p class="section__description">
                    View and manage all registered users in the EVSU Voice platform.
                </p>
            </div>

            <!-- Filters -->
            <div class="dashboard-card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h3><i class="ri-filter-line"></i> Filter Users</h3>
                </div>
                <div class="card-content">
                    <form method="GET" action="" class="filter-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="search">Search Users</label>
                                <input type="text" id="search" name="search"
                                    placeholder="Search by name or email..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>

                            <div class="filter-group">
                                <label for="date">Registration Date</label>
                                <select id="date" name="date">
                                    <option value="" <?php echo $date_filter === '' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                </select>
                            </div>

                            <div class="filter-actions">
                                <button type="submit" class="btn-primary manage-users-filter">
                                    <i class="ri-search-line"></i> Filter
                                </button>
                                <a href="?" class="btn-secondary">
                                    <i class="ri-refresh-line"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notifications -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="ri-check-line"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="ri-error-warning-line"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="ri-user-settings-line"></i> All Users (Excluding Admins)</h3>
                </div>
                <div class="card-content manage-users-table">
                    <?php if (empty($users)): ?>
                        <p class="no-data">
                            <?php if (!empty($search) || !empty($role_filter) || !empty($date_filter)): ?>
                                No users found matching your filter criteria.
                            <?php else: ?>
                                No users found.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registration Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <span class="user-name">
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="user-role role-<?php echo $user['role']; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons manage-row">
                                                    <button class="btn-action btn-warning manage-warning"
                                                        onclick="openChangeUserPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                        <i class="ri-lock-password-line"></i>
                                                        Change Password
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Change User Password Modal -->
<div id="changeUserPasswordModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Change User Password</h3>
            <span class="close" onclick="closeChangeUserPasswordModal()">&times;</span>
        </div>

        <div class="modal-body">
            <p><strong id="selectedUserName"></strong></p>

            <form id="changeUserPasswordForm" method="POST" action="">
                <input type="hidden" id="selected_user_id" name="user_id" value="">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label for="new_user_password">New Password</label>
                    <input type="password" id="new_user_password" name="new_password" required minlength="6">
                    <small class="form-hint">Minimum 6 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm_user_password">Confirm New Password</label>
                    <input type="password" id="confirm_user_password" name="confirm_password" required minlength="6">
                </div>

                <div class="form-actions">
                    <button type="button" onclick="closeChangeUserPasswordModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openChangeUserPasswordModal(userId, userName) {
        document.getElementById('selected_user_id').value = userId;
        document.getElementById('selectedUserName').textContent = userName;
        document.getElementById('changeUserPasswordModal').style.display = 'block';
    }

    function closeChangeUserPasswordModal() {
        document.getElementById('changeUserPasswordModal').style.display = 'none';
        document.getElementById('changeUserPasswordForm').reset();
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('changeUserPasswordModal');
        if (event.target == modal) {
            closeChangeUserPasswordModal();
        }
    }

    // Password confirmation validation
    document.getElementById('confirm_user_password').addEventListener('input', function() {
        const password = document.getElementById('new_user_password').value;
        const confirm = this.value;

        if (confirm && password !== confirm) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
</script>

<style>
    /* User Management Styles */
    #new_user_password:focus,
    #confirm_user_password:focus {
        border: 1px solid var(--evsu-color);
    }

    .manage-row {
        margin-bottom: 0;
    }

    .manage-users-table {
        padding-top: 0;
    }

    .users-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }

    .users-table th {
        padding: 0 1rem 1rem 1rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .users-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .users-table th {
        background-color: var(--container-color);
        font-weight: 600;
        color: var(--title-color);
    }

    .user-info {
        display: flex;
        flex-direction: column;
        margin-bottom: 0;
    }

    .user-name {
        font-size: 16px;
        font-weight: 500;
        color: var(--title-color);
    }

    .user-role {
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
    }

    .role-admin {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .role-student {
        background-color: #f3e5f5;
        color: #7b1fa2;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-action {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 0.25rem;
        cursor: pointer;
        font-size: 0.75rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.3s ease;
    }

    .btn-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .btn-warning:hover {
        background-color: var(--evsu-color);
        color: var(--white-color);
    }

    .manage-warning {
        border: 1px solid var(--evsu-color);
        background-color: transparent;
        color: var(--evsu-color);
    }

    .table-responsive {
        overflow-x: auto;
    }

    /* Filter Styles */
    .filter-form {
        width: 100%;
    }

    .filter-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .filter-group label {
        font-weight: 500;
        color: var(--title-color);
        font-size: 0.875rem;
    }

    .filter-group input,
    .filter-group select {
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 0.25rem;
        background-color: var(--body-color);
        color: var(--text-color);
        font-size: 0.875rem;
    }

    .filter-group input:focus,
    .filter-group select:focus {
        outline: none;
        border-color: var(--evsu-color);
    }

    .filter-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-primary,
    .btn-secondary {
        padding: 0.75rem 1rem;
        border: none;
        border-radius: 0.25rem;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.3s ease;
        font-size: 0.875rem;
    }

    .btn-primary.manage-users-filter {
        background-color: var(--evsu-color);
        color: white;
    }

    .btn-primary.manage-users-filter:hover {
        background-color: var(--first-color);
    }

    .btn-secondary {
        background-color: var(--container-color);
        color: var(--text-color);
        border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
        background-color: var(--border-color);
    }

    /* Modal Styles */
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
        background-color: var(--body-color);
        margin: 10% auto;
        padding: 0;
        border-radius: 0.5rem;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        color: var(--title-color);
    }

    .close {
        color: var(--text-color);
        font-size: 1.5rem;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }

    .close:hover {
        color: var(--first-color);
    }

    .modal-body {
        padding: 1.5rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--title-color);
        font-weight: 500;
    }

    .form-group input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 0.25rem;
        background-color: var(--body-color);
        color: var(--text-color);
    }

    .form-group input:focus {
        outline: none;
        border-color: var(--first-color);
    }

    .form-hint {
        font-size: 0.75rem;
        color: var(--text-color-light);
        margin-top: 0.25rem;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }

    @media screen and (max-width: 768px) {
        .filter-row {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .filter-actions {
            justify-content: stretch;
        }

        .filter-actions .btn-primary.manage-users-filter,
        .filter-actions .btn-secondary {
            flex: 1;
            justify-content: center;
        }

        .users-table {
            font-size: 0.875rem;
        }

        .users-table th,
        .users-table td {
            padding: 0.5rem;
        }

        .action-buttons {
            flex-direction: column;
        }

        .modal-content {
            width: 95%;
            margin: 5% auto;
        }
    }

    /* Alert Styles */
    .alert.alert-success {
        background-color: #f0f9ff;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        border: 1px solid #e0f2fe;
    }

    .alert.alert-success i {
        flex-shrink: 0;
    }
</style>

<?php include '../includes/footer.php'; ?>