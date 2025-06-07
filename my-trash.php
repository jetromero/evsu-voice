<?php
$page_title = "My Trash";
require_once 'includes/auth.php';
require_once 'config/database_native.php';

// Start session to handle messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Redirect admin to dashboard
if ($user['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit();
}

$database = new DatabaseNative();
$conn = $database->getConnection();

// Check for messages from previous redirect
$success = $_SESSION['my_trash_success'] ?? '';
$error = $_SESSION['my_trash_error'] ?? '';

// Clear messages from session
unset($_SESSION['my_trash_success'], $_SESSION['my_trash_error']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['restore_suggestion'])) {
        $archive_id = (int)$_POST['archive_id'];

        // Get archived suggestion data and verify ownership (SECURITY: Critical check)
        $archive_query = "SELECT * FROM archived_suggestions WHERE id = $1 AND user_id = $2";
        $archive_result = $database->query($archive_query, [$archive_id, $user['id']]);
        $archived = $database->fetchAssoc($archive_result);

        if ($archived && (int)$archived['user_id'] === (int)$user['id']) {  // Double-check ownership
            try {
                pg_query($conn, "BEGIN");

                // Restore to suggestions table
                $restore_query = "INSERT INTO suggestions (user_id, title, description, category, status, is_anonymous, upvotes_count, admin_response, admin_id, created_at, updated_at)
                                 VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, CURRENT_TIMESTAMP)";
                $restore_result = $database->query($restore_query, [
                    $archived['user_id'],
                    $archived['title'],
                    $archived['description'],
                    $archived['category'],
                    $archived['status'],
                    $archived['is_anonymous'],
                    $archived['upvotes_count'],
                    $archived['admin_response'],
                    $archived['admin_id'],
                    $archived['original_created_at']
                ]);

                // Remove from archive (with additional security check)
                $delete_archive_query = "DELETE FROM archived_suggestions WHERE id = $1 AND user_id = $2";
                $delete_archive_result = $database->query($delete_archive_query, [$archive_id, $user['id']]);

                pg_query($conn, "COMMIT");
                $_SESSION['my_trash_success'] = 'Suggestion restored successfully!';
            } catch (Exception $e) {
                pg_query($conn, "ROLLBACK");
                $_SESSION['my_trash_error'] = 'Failed to restore suggestion: ' . $e->getMessage();
            }
        } else {
            $_SESSION['my_trash_error'] = 'Archived suggestion not found or you do not have permission to restore it.';
        }
    } elseif (isset($_POST['permanent_delete'])) {
        $archive_id = (int)$_POST['archive_id'];

        // Verify ownership before permanent deletion (SECURITY: Critical check)
        $delete_query = "DELETE FROM archived_suggestions WHERE id = $1 AND user_id = $2";
        $delete_result = $database->query($delete_query, [$archive_id, $user['id']]);

        if ($delete_result) {
            $_SESSION['my_trash_success'] = 'Suggestion permanently deleted!';
        } else {
            $_SESSION['my_trash_error'] = 'Failed to permanently delete suggestion.';
        }
    }

    // Redirect to prevent form resubmission
    $redirect_url = $_SERVER['PHP_SELF'];
    $query_params = $_GET;
    if (!empty($query_params)) {
        $redirect_url .= '?' . http_build_query($query_params);
    }
    header('Location: ' . $redirect_url);
    exit;
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build query for user's archived suggestions ONLY (security: user can only see their own)
$where_conditions = ["a.user_id = $1"];  // CRITICAL: Only show current user's suggestions
$params = [$user['id']];

if ($category_filter) {
    $where_conditions[] = "a.category = $" . (count($params) + 1);
    $params[] = $category_filter;
}

if ($search) {
    $where_conditions[] = "(a.title ILIKE $" . (count($params) + 1) . " OR a.description ILIKE $" . (count($params) + 2) . ")";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(a.deleted_at) = CURRENT_DATE";
            break;
        case 'week':
            $where_conditions[] = "a.deleted_at >= CURRENT_TIMESTAMP - INTERVAL '1 WEEK'";
            break;
        case 'month':
            $where_conditions[] = "a.deleted_at >= CURRENT_TIMESTAMP - INTERVAL '1 MONTH'";
            break;
        case 'year':
            $where_conditions[] = "a.deleted_at >= CURRENT_TIMESTAMP - INTERVAL '1 YEAR'";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_clause = $sort_order === 'oldest' ? 'ORDER BY a.deleted_at ASC' : 'ORDER BY a.deleted_at DESC';

// Get user's archived suggestions ONLY - Security enforced at query level
$query = "SELECT a.*,
          (deleter.first_name || ' ' || deleter.last_name) as deleted_by_name
          FROM archived_suggestions a
          LEFT JOIN users deleter ON a.deleted_by = deleter.id
          WHERE $where_clause
          $order_clause";

$result = $database->query($query, $params);
$archived_suggestions = $database->fetchAll($result);

// Double-check security: Filter out any suggestions that don't belong to current user
$archived_suggestions = array_filter($archived_suggestions, function ($suggestion) use ($user) {
    return (int)$suggestion['user_id'] === (int)$user['id'];
});

// Get all categories for filter dropdown
$categories = [];
try {
    $categories_query = "SELECT name FROM categories ORDER BY id ASC";
    $categories_result = $database->query($categories_query);
    if ($categories_result) {
        while ($row = $database->fetchAssoc($categories_result)) {
            $categories[] = $row['name'];
        }
    }
} catch (Exception $e) {
    // If categories table doesn't exist, get from archived suggestions
    $categories_query = "SELECT DISTINCT category FROM archived_suggestions WHERE user_id = $1 ORDER BY category";
    $categories_result = $database->query($categories_query, [$user['id']]);
    if ($categories_result) {
        while ($row = $database->fetchAssoc($categories_result)) {
            $categories[] = $row['category'];
        }
    }
}

// Get statistics
$stats_query = "SELECT
                COUNT(*) as total_archived,
                SUM(CASE WHEN deleted_by_role = 'admin' THEN 1 ELSE 0 END) as deleted_by_admin,
                SUM(CASE WHEN deleted_by_role = 'student' THEN 1 ELSE 0 END) as deleted_by_user
                FROM archived_suggestions WHERE user_id = $1";
$stats_result = $database->query($stats_query, [$user['id']]);
$stats = [];
if ($stats_result) {
    $stats = $database->fetchAssoc($stats_result);
}

// Initialize default stats if query failed
if (empty($stats)) {
    $stats = [
        'total_archived' => 0,
        'deleted_by_admin' => 0,
        'deleted_by_user' => 0
    ];
}

include 'includes/header.php';
?>

<main class="main">
    <section class="my-trash-section section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title">My Trash</h1>
                <p class="section__description">
                    View and manage your own deleted suggestions. You can restore your suggestions or permanently delete them.
                </p>
            </div>

            <!-- Notification Modal -->
            <div id="notification-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Notification</h3>
                        <button type="button" class="modal-close" onclick="closeNotificationModal()">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="modal-icon"></div>
                        <p id="modal-message"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button" onclick="closeNotificationModal()">OK</button>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal -->
            <div id="confirmation-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="confirmation-title">Confirm Action</h3>
                        <button type="button" class="modal-close" onclick="closeConfirmationModal()">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="confirmation-icon"></div>
                        <p id="confirmation-message"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button button--secondary" onclick="closeConfirmationModal()">Cancel</button>
                        <button type="button" class="button button--danger" id="confirm-action-btn">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-controls">
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"
                                    <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="sort" class="filter-select">
                            <option value="newest" <?php echo $sort_order === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_order === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>

                        <select name="date_filter" class="filter-select">
                            <option value="">All Time</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>This Year</option>
                        </select>

                        <div class="search-box">
                            <input type="text" name="search" placeholder="Search archived suggestions..."
                                value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                            <button type="submit" class="search-button">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>

                        <?php if (!empty($search) || !empty($category_filter) || $sort_order !== 'newest' || !empty($date_filter)): ?>
                            <a href="my-trash.php" class="clear-filters">
                                <i class="ri-close-line"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="results-info">
                    <span class="results-count"><?php echo count($archived_suggestions); ?> archived suggestion(s) found</span>
                    <div class="action-links">
                        <a href="my-suggestions.php" class="button">
                            <i class="ri-arrow-left-line"></i> Back to My Suggestions
                        </a>
                        <a href="submit-suggestion.php" class="button-secondary">
                            <i class="ri-add-line"></i> Submit New
                        </a>
                    </div>
                </div>
            </div>

            <!-- Archived Suggestions List -->
            <div class="archived-suggestions-table">
                <?php if (empty($archived_suggestions)): ?>
                    <div class="no-suggestions">
                        <i class="ri-archive-line"></i>
                        <h3>No archived suggestions</h3>
                        <p>You don't have any deleted suggestions. Your deleted suggestions will appear here.</p>
                        <a href="my-suggestions.php" class="button trash-btn">View My Suggestions</a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="trash-table">
                            <thead>
                                <tr>
                                    <th>Title & Description</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Votes</th>
                                    <th>Deleted By</th>
                                    <th>Deleted Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archived_suggestions as $suggestion): ?>
                                    <tr class="archived-suggestion-row">
                                        <td class="title-cell">
                                            <div class="title-content">
                                                <h4 class="suggestion-title-compact">
                                                    <?php echo htmlspecialchars($suggestion['title']); ?>
                                                    <?php if ($suggestion['is_anonymous']): ?>
                                                        <i class="ri-eye-off-line anonymous-icon" title="Anonymous"></i>
                                                    <?php endif; ?>
                                                </h4>
                                                <p class="suggestion-description-compact" title="<?php echo htmlspecialchars($suggestion['description']); ?>">
                                                    <?php
                                                    $description = htmlspecialchars($suggestion['description']);
                                                    echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                                                    ?>
                                                </p>
                                                <div class="submission-info">
                                                    <small class="text-muted">
                                                        <i class="ri-calendar-line"></i>
                                                        Originally submitted: <?php echo date('M j, Y', strtotime($suggestion['original_created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="category-badge"><?php echo htmlspecialchars($suggestion['category']); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $suggestion['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="votes-cell">
                                            <span class="vote-count">
                                                <i class="ri-thumb-up-line"></i>
                                                <?php echo $suggestion['upvotes_count']; ?>
                                            </span>
                                        </td>
                                        <td class="deleted-by-cell">
                                            <div class="deletion-info-compact">
                                                <span class="deleted-by-name">
                                                    <?php echo htmlspecialchars($suggestion['deleted_by_name']); ?>
                                                </span>
                                                <small class="deleted-by-role">
                                                    <?php if ($suggestion['deleted_by'] == $user['id']): ?>
                                                        (You)
                                                    <?php else: ?>
                                                        (Admin)
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td class="date-cell">
                                            <span class="deleted-date"><?php echo date('M j, Y', strtotime($suggestion['deleted_at'])); ?></span>
                                            <small class="deleted-time text-muted"><?php echo date('g:i A', strtotime($suggestion['deleted_at'])); ?></small>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons-compact">
                                                <?php
                                                $deletedByAdmin = ($suggestion['deleted_by_role'] === 'admin' && $suggestion['deleted_by'] != $user['id']);
                                                ?>

                                                <button type="button" class="action-btn-compact restore-btn <?php echo $deletedByAdmin ? 'disabled' : ''; ?>"
                                                    data-archive-id="<?php echo $suggestion['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($suggestion['title']); ?>"
                                                    title="<?php echo $deletedByAdmin ? 'Cannot restore - deleted by admin' : 'Restore Suggestion'; ?>"
                                                    <?php echo $deletedByAdmin ? 'disabled' : ''; ?>>
                                                    <i class="ri-refresh-line"></i>
                                                </button>

                                                <button type="button" class="action-btn-compact delete-btn"
                                                    data-archive-id="<?php echo $suggestion['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($suggestion['title']); ?>"
                                                    title="Permanent Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>

                                                <?php if ($suggestion['deletion_reason'] || $suggestion['admin_response']): ?>
                                                    <button type="button" class="action-btn-compact info-btn"
                                                        onclick="toggleRowDetails(<?php echo $suggestion['id']; ?>)"
                                                        title="View Details">
                                                        <i class="ri-information-line"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Expandable details row -->
                                    <?php if ($suggestion['deletion_reason'] || $suggestion['admin_response']): ?>
                                        <tr class="details-row" id="details-row-<?php echo $suggestion['id']; ?>" style="display: none;">
                                            <td colspan="7" class="details-cell">
                                                <div class="details-content">
                                                    <?php if ($suggestion['deletion_reason']): ?>
                                                        <div class="deletion-reason-compact">
                                                            <strong><i class="ri-information-line"></i> Deletion Reason:</strong>
                                                            <p><?php echo htmlspecialchars($suggestion['deletion_reason']); ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($suggestion['admin_response']): ?>
                                                        <div class="admin-response-compact">
                                                            <strong><i class="ri-admin-line"></i> EVSU Response:</strong>
                                                            <p><?php echo nl2br(htmlspecialchars($suggestion['admin_response'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script>
    // Auto-submit form when filters change
    document.addEventListener('DOMContentLoaded', function() {
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Add event listeners for action buttons
        const restoreButtons = document.querySelectorAll('.restore-btn');
        restoreButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Check if button is disabled
                if (this.disabled || this.classList.contains('disabled')) {
                    return;
                }

                const archiveId = this.getAttribute('data-archive-id');
                const title = this.getAttribute('data-title');
                restoreSuggestion(archiveId, title);
            });
        });

        const deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const archiveId = this.getAttribute('data-archive-id');
                const title = this.getAttribute('data-title');
                permanentDeleteSuggestion(archiveId, title);
            });
        });

        // Show notification modal if there are success/error messages
        <?php if ($success): ?>
            showNotificationModal('success', <?php echo json_encode($success); ?>);
        <?php endif; ?>

        <?php if ($error): ?>
            showNotificationModal('error', <?php echo json_encode($error); ?>);
        <?php endif; ?>
    });

    // Toggle row details function
    function toggleRowDetails(suggestionId) {
        const detailsRow = document.getElementById('details-row-' + suggestionId);
        const infoBtn = document.querySelector(`[onclick="toggleRowDetails(${suggestionId})"] i`);

        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = 'table-row';
            infoBtn.className = 'ri-arrow-up-s-line';
        } else {
            detailsRow.style.display = 'none';
            infoBtn.className = 'ri-information-line';
        }
    }

    // Notification modal functions
    function showNotificationModal(type, message) {
        const modal = document.getElementById('notification-modal');
        const icon = document.getElementById('modal-icon');
        const messageEl = document.getElementById('modal-message');

        if (type === 'success') {
            icon.innerHTML = '<i class="ri-check-line success-icon"></i>';
        } else {
            icon.innerHTML = '<i class="ri-error-warning-line error-icon"></i>';
        }

        messageEl.textContent = message;
        modal.style.display = 'block';
    }

    function closeNotificationModal() {
        const modal = document.getElementById('notification-modal');
        modal.style.display = 'none';
    }

    // Confirmation modal functions
    function showConfirmationModal(title, message, onConfirm) {
        const modal = document.getElementById('confirmation-modal');
        const titleEl = document.getElementById('confirmation-title');
        const messageEl = document.getElementById('confirmation-message');
        const iconEl = document.getElementById('confirmation-icon');
        const confirmBtn = document.getElementById('confirm-action-btn');

        titleEl.textContent = title;
        messageEl.textContent = message;
        iconEl.innerHTML = '<i class="ri-error-warning-line error-icon"></i>';

        // Remove any existing event listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        // Add new event listener
        newConfirmBtn.addEventListener('click', function() {
            closeConfirmationModal();
            onConfirm();
        });

        modal.style.display = 'block';
    }

    function closeConfirmationModal() {
        const modal = document.getElementById('confirmation-modal');
        modal.style.display = 'none';
    }

    // Action functions
    function restoreSuggestion(archiveId, title) {
        showConfirmationModal(
            'Restore Suggestion',
            `Are you sure you want to restore "${title}"? This will move it back to your active suggestions.`,
            function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="archive_id" value="${archiveId}">
                    <input type="hidden" name="restore_suggestion" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        );
    }

    function permanentDeleteSuggestion(archiveId, title) {
        showConfirmationModal(
            'Permanent Delete',
            `Are you sure you want to permanently delete "${title}"? This action cannot be undone.`,
            function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="archive_id" value="${archiveId}">
                    <input type="hidden" name="permanent_delete" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        );
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const notificationModal = document.getElementById('notification-modal');
        const confirmationModal = document.getElementById('confirmation-modal');

        if (event.target === notificationModal) {
            closeNotificationModal();
        }

        if (event.target === confirmationModal) {
            closeConfirmationModal();
        }
    }
</script>

<style>
    .trash-btn {
        border-radius: 0.5rem;
    }

    /* Compact table styles for archived suggestions */
    .archived-suggestions-table {
        margin-top: 24px;
    }

    .table-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .trash-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .trash-table thead {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    .dark-theme .trash-table thead {
        background-color: var(--container-color);
        border-bottom: 2px solid var(--border-color);
    }

    .trash-table th {
        padding: 12px 8px;
        text-align: center;
        font-weight: 600;
        color: #495057;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dark-theme .trash-table th {
        color: var(--white-color);
    }

    .trash-table td {
        padding: 12px 8px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: top;
    }

    .archived-suggestion-row:hover {
        background-color: var(--body-color);
    }

    /* Title cell styles */
    .title-cell {
        max-width: 300px;
        min-width: 250px;
    }

    .suggestion-title-compact {
        font-family: 'Montserrat', sans-serif;
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 4px 0;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .anonymous-icon {
        color: #6c757d;
        font-size: 12px;
    }

    .suggestion-description-compact {
        color: #6c757d;
        font-size: 13px;
        line-height: 1.4;
        margin: 0 0 6px 0;
    }

    .submission-info {
        margin-top: 6px;
    }

    .text-muted {
        color: #6c757d !important;
        font-size: 12px;
    }

    /* Badge styles */
    .category-badge {
        background-color: #e9ecef;
        color: #495057;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    td span.category-badge {
        justify-content: center;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        text-transform: capitalize;
    }

    .status-badge.status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-badge.status-new {
        background-color: #d4edda;
        color: #155724;
    }

    .status-badge.status-under_review {
        background-color: #cce5ff;
        color: #004085;
    }

    .status-badge.status-in_progress {
        background-color: #e2e3e5;
        color: #383d41;
    }

    .status-badge.status-implemented {
        background-color: #d1ecf1;
        color: #0c5460;
    }

    .status-badge.status-rejected {
        background-color: #f8d7da;
        color: #721c24;
    }

    /* Center badges in their cells */
    .trash-table td:nth-child(2),
    .trash-table td:nth-child(3) {
        text-align: center;
    }

    /* Votes cell */
    .votes-cell {
        text-align: center;
        width: 80px;
    }

    .vote-count {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        color: #495057;
        font-weight: 500;
    }

    /* Deleted by cell */
    .deleted-by-cell {
        width: 120px;
    }

    .deletion-info-compact {
        text-align: center;
    }

    .deleted-by-name {
        display: block;
        font-weight: 500;
        color: #495057;
        font-size: 13px;
    }

    .deleted-by-role {
        display: block;
        color: #6c757d;
        font-size: 11px;
        margin-top: 2px;
    }

    /* Date cell */
    .date-cell {
        width: 100px;
        text-align: center;
    }

    .deleted-date {
        display: block;
        font-weight: 500;
        color: #495057;
        font-size: 13px;
    }

    .deleted-time {
        display: block;
        margin-top: 2px;
    }

    /* Actions cell */
    .actions-cell {
        width: 120px;
        text-align: center;
    }

    .action-buttons-compact {
        display: flex;
        gap: 4px;
        justify-content: center;
    }

    .action-btn-compact {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .action-btn-compact.restore-btn {
        background-color: transparent;
        border: 1px solid var(--evsu-color);
        color: var(--evsu-color);
    }

    .action-btn-compact.restore-btn:hover {
        background-color: var(--evsu-color);
        transform: translateY(-1px);
        color: var(--white-color);
    }

    .action-btn-compact.restore-btn.disabled,
    .action-btn-compact.restore-btn:disabled {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .action-btn-compact.restore-btn.disabled:hover,
    .action-btn-compact.restore-btn:disabled:hover {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
        transform: none;
        cursor: not-allowed;
    }

    .action-btn-compact.delete-btn {
        background-color: var(--evsu-color);
        color: white;
    }

    .action-btn-compact.delete-btn:hover {
        background-color: #c82333;
        transform: translateY(-1px);
    }

    .action-btn-compact.info-btn {
        background-color: #17a2b8;
        color: white;
    }

    .action-btn-compact.info-btn:hover {
        background-color: #138496;
        transform: translateY(-1px);
    }

    /* Details row styles */
    .details-row {
        background-color: #f8f9fa;
    }

    .details-cell {
        padding: 16px !important;
        border-bottom: 2px solid #dee2e6 !important;
    }

    .details-content {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
    }

    .deletion-reason-compact,
    .admin-response-compact {
        flex: 1;
        min-width: 300px;
        padding: 12px;
        border-radius: 6px;
    }

    .deletion-reason-compact {
        background-color: #e7f3ff;
        border-left: 3px solid #007bff;
    }

    .admin-response-compact {
        background-color: #e8f5e8;
        border-left: 3px solid #28a745;
    }

    .deletion-reason-compact strong,
    .admin-response-compact strong {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
        color: #495057;
        font-size: 14px;
    }

    .deletion-reason-compact p,
    .admin-response-compact p {
        margin: 0;
        color: #495057;
        font-size: 13px;
        line-height: 1.4;
    }

    .dark-theme .suggestion-title-compact {
        color: var(--white-color);
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .trash-table {
            font-size: 12px;
        }

        .trash-table th,
        .trash-table td {
            padding: 8px 4px;
        }

        .suggestion-title-compact {
            font-size: 14px;
        }

        .suggestion-description-compact {
            font-size: 12px;
        }

        .action-btn-compact {
            width: 28px;
            height: 28px;
            font-size: 12px;
        }

        .details-content {
            flex-direction: column;
            gap: 16px;
        }

        .deletion-reason-compact,
        .admin-response-compact {
            min-width: auto;
        }

        /* Hide some columns on mobile */
        .trash-table th:nth-child(3),
        .trash-table td:nth-child(3),
        .trash-table th:nth-child(4),
        .trash-table td:nth-child(4) {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .title-cell {
            min-width: 200px;
        }

        .deleted-by-cell,
        .date-cell {
            width: 80px;
        }

        .actions-cell {
            width: 80px;
        }

        .action-buttons-compact {
            flex-direction: column;
            gap: 2px;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>