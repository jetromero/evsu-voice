<?php
$page_title = "Archive";
require_once '../includes/auth.php';
require_once '../config/database_native.php';

// Start session to handle messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$auth->requireAdmin();

$database = new DatabaseNative();
$conn = $database->getConnection();
$user = $auth->getCurrentUser();

// Check for messages from previous redirect
$success = $_SESSION['archive_success'] ?? '';
$error = $_SESSION['archive_error'] ?? '';

// Clear messages from session
unset($_SESSION['archive_success'], $_SESSION['archive_error']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['restore_suggestion'])) {
        $archive_id = (int)$_POST['archive_id'];

        // Get archived suggestion data
        $archive_query = "SELECT * FROM archived_suggestions WHERE id = ?";
        $archive_result = $database->query($archive_query, [$archive_id]);
        $archived = $database->fetchAssoc($archive_result);

        if ($archived) {
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

                // Remove from archive
                $delete_archive_query = "DELETE FROM archived_suggestions WHERE id = $1";
                $delete_archive_result = $database->query($delete_archive_query, [$archive_id]);

                pg_query($conn, "COMMIT");
                $_SESSION['archive_success'] = 'Suggestion restored successfully!';
            } catch (Exception $e) {
                pg_query($conn, "ROLLBACK");
                $_SESSION['archive_error'] = 'Failed to restore suggestion: ' . $e->getMessage();
            }
        } else {
            $_SESSION['archive_error'] = 'Archived suggestion not found.';
        }
    } elseif (isset($_POST['permanent_delete'])) {
        $archive_id = (int)$_POST['archive_id'];

        $delete_query = "DELETE FROM archived_suggestions WHERE id = $1";
        $delete_result = $database->query($delete_query, [$archive_id]);

        if ($delete_result) {
            $_SESSION['archive_success'] = 'Suggestion permanently deleted!';
        } else {
            $_SESSION['archive_error'] = 'Failed to permanently delete suggestion.';
        }
    } elseif (isset($_POST['bulk_restore'])) {
        if (isset($_POST['selected_archives']) && is_array($_POST['selected_archives'])) {
            $selected_ids = array_map('intval', $_POST['selected_archives']);
            $restored_count = 0;
            $failed_count = 0;

            foreach ($selected_ids as $archive_id) {
                try {
                    pg_query($conn, "BEGIN");

                    // Get archived suggestion data
                    $archive_query = "SELECT * FROM archived_suggestions WHERE id = $1";
                    $archive_result = $database->query($archive_query, [$archive_id]);
                    $archived = $database->fetchAssoc($archive_result);

                    if ($archived) {
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

                        // Remove from archive
                        $delete_archive_query = "DELETE FROM archived_suggestions WHERE id = $1";
                        $delete_archive_result = $database->query($delete_archive_query, [$archive_id]);

                        pg_query($conn, "COMMIT");
                        $restored_count++;
                    } else {
                        $failed_count++;
                    }
                } catch (Exception $e) {
                    pg_query($conn, "ROLLBACK");
                    $failed_count++;
                }
            }

            if ($restored_count > 0) {
                $_SESSION['archive_success'] = "$restored_count suggestion(s) restored successfully!";
                if ($failed_count > 0) {
                    $_SESSION['archive_success'] .= " ($failed_count failed)";
                }
            } else {
                $_SESSION['archive_error'] = 'Failed to restore suggestions.';
            }
        } else {
            $_SESSION['archive_error'] = 'No suggestions selected for restoration.';
        }
    } elseif (isset($_POST['bulk_permanent_delete'])) {
        if (isset($_POST['selected_archives']) && is_array($_POST['selected_archives'])) {
            $selected_ids = array_map('intval', $_POST['selected_archives']);
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $placeholders = str_replace('?', '$' . implode(',$', range(1, count($selected_ids))), $placeholders);

            $query = "DELETE FROM archived_suggestions WHERE id IN ($placeholders)";
            $result = $database->query($query, $selected_ids);

            if ($result) {
                $count = count($selected_ids);
                $_SESSION['archive_success'] = "$count suggestion(s) permanently deleted!";
            } else {
                $_SESSION['archive_error'] = 'Failed to permanently delete suggestions.';
            }
        } else {
            $_SESSION['archive_error'] = 'No suggestions selected for permanent deletion.';
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
$deleted_by_filter = isset($_GET['deleted_by']) ? $_GET['deleted_by'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build query
$where_conditions = [];
$params = [];

if ($deleted_by_filter) {
    if ($deleted_by_filter === 'admin') {
        $where_conditions[] = "a.deleted_by_role = 'admin'";
    } elseif ($deleted_by_filter === 'user') {
        $where_conditions[] = "a.deleted_by_role = 'student'";
    }
}

if ($category_filter) {
    $where_conditions[] = "a.category = ?";
    $params[] = $category_filter;
}

if ($search) {
    $where_conditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(a.deleted_at) = CURRENT_DATE";
            break;
        case 'week':
            $where_conditions[] = "a.deleted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 WEEK)";
            break;
        case 'month':
            $where_conditions[] = "a.deleted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 MONTH)";
            break;
        case 'year':
            $where_conditions[] = "a.deleted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 YEAR)";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Build ORDER BY clause
$order_clause = "ORDER BY ";
switch ($sort_order) {
    case 'oldest':
        $order_clause .= "a.deleted_at ASC";
        break;
    case 'newest':
    default:
        $order_clause .= "a.deleted_at DESC";
        break;
}

// Get archived suggestions
$query = "SELECT a.*,
          CASE WHEN a.is_anonymous = true THEN 'Anonymous'
               ELSE (u.first_name || ' ' || u.last_name) END as author_name,
          (deleter.first_name || ' ' || deleter.last_name) as deleted_by_name,
          admin.first_name as admin_first_name,
          admin.last_name as admin_last_name
          FROM archived_suggestions a
          LEFT JOIN users u ON a.user_id = u.id
          LEFT JOIN users deleter ON a.deleted_by = deleter.id
          LEFT JOIN users admin ON a.admin_id = admin.id
          $where_clause
          $order_clause";

$stmt = $query;
$result = $database->query($stmt, $params);
$archived_suggestions = $database->fetchAll($result);

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
    $categories_query = "SELECT DISTINCT category FROM archived_suggestions WHERE category IS NOT NULL AND category != '' ORDER BY category";
    $categories_result = $database->query($categories_query);
    if ($categories_result) {
        while ($row = $database->fetchAssoc($categories_result)) {
            $categories[] = $row['category'];
        }
    }
}

// If still no categories found, provide some default ones
if (empty($categories)) {
    $categories = ['Academic Affairs', 'Student Services', 'Campus Facilities', 'Technology', 'Student Life', 'Administration', 'Other'];
}

include '../includes/header.php';
?>

<main class="main">
    <section class="archive section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title">Archive</h1>
                <p class="section__description">
                    View and manage deleted suggestions. You can restore suggestions or permanently delete them.
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

            <!-- Filters and Search -->
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

                        <select name="deleted_by" class="filter-select">
                            <option value="">All Deletions</option>
                            <option value="admin" <?php echo $deleted_by_filter === 'admin' ? 'selected' : ''; ?>>Deleted by Admin</option>
                            <option value="user" <?php echo $deleted_by_filter === 'user' ? 'selected' : ''; ?>>Deleted by User</option>
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

                        <?php if (!empty($search) || !empty($deleted_by_filter) || !empty($category_filter) || $sort_order !== 'newest' || !empty($date_filter)): ?>
                            <a href="archive.php" class="clear-filters">
                                <i class="ri-close-line"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="results-info admin-results-info">
                    <span class="results-count"><?php echo count($archived_suggestions); ?> archived suggestion(s) found</span>
                </div>
            </div>

            <!-- Archived Suggestions Table (List View) -->
            <div class="admin-suggestions-table">
                <?php if (empty($archived_suggestions)): ?>
                    <div class="no-suggestions">
                        <i class="ri-archive-line"></i>
                        <h3>No archived suggestions found</h3>
                        <p>No suggestions match your current filters.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" id="bulk-action-form">
                        <div class="table-actions">
                            <button type="button" class="button button--success" onclick="bulkRestore()" id="restore-selected-btn" disabled>
                                <i class="ri-refresh-line"></i>
                                Restore Selected
                            </button>
                            <button type="button" class="button button--danger" onclick="bulkPermanentDelete()" id="delete-selected-btn" disabled>
                                <i class="ri-delete-bin-line"></i>
                                Permanent Delete Selected
                            </button>
                            <button type="button" class="button button--secondary admin" onclick="selectAll()">
                                <i class="ri-checkbox-multiple-line"></i>
                                Select All
                            </button>
                            <button type="button" class="button button--secondary admin" onclick="deselectAll()">
                                <i class="ri-checkbox-blank-line"></i>
                                Deselect All
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="suggestions-table">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll()">
                                        </th>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Author</th>
                                        <th>Status</th>
                                        <th>Deleted By</th>
                                        <th>Deleted Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archived_suggestions as $suggestion): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_archives[]"
                                                    value="<?php echo $suggestion['id']; ?>"
                                                    class="suggestion-checkbox"
                                                    onchange="updateActionButtons()">
                                            </td>
                                            <td>#<?php echo $suggestion['original_id']; ?></td>
                                            <td>
                                                <div class="table-title" title="<?php echo htmlspecialchars($suggestion['title']); ?>">
                                                    <?php echo htmlspecialchars($suggestion['title']); ?>
                                                    <?php if ($suggestion['is_anonymous']): ?>
                                                        <i class="ri-eye-off-line" title="Anonymous"></i>
                                                    <?php endif; ?>
                                                    <span class="archived-indicator">
                                                        <i class="ri-archive-line" title="Archived"></i>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($suggestion['category']); ?></td>
                                            <td><?php echo htmlspecialchars($suggestion['author_name']); ?></td>
                                            <td>
                                                <span class="suggestion-status status-<?php echo $suggestion['status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($suggestion['deleted_by_name']); ?>
                                                <small>(<?php echo ucfirst($suggestion['deleted_by_role']); ?>)</small>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($suggestion['deleted_at'])); ?></td>
                                            <td>
                                                <button type="button" class="table-action-btn restore-btn"
                                                    onclick="restoreSuggestion(<?php echo $suggestion['id']; ?>, '<?php echo htmlspecialchars($suggestion['title'], ENT_QUOTES); ?>')"
                                                    title="Restore">
                                                    <i class="ri-refresh-line"></i>
                                                </button>
                                                <button type="button" class="table-action-btn delete-btn"
                                                    onclick="permanentDeleteSuggestion(<?php echo $suggestion['id']; ?>, '<?php echo htmlspecialchars($suggestion['title'], ENT_QUOTES); ?>')"
                                                    title="Permanent Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
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

        // Show notification modal if there are success/error messages
        <?php if ($success): ?>
            showNotificationModal('success', <?php echo json_encode($success); ?>);
        <?php endif; ?>

        <?php if ($error): ?>
            showNotificationModal('error', <?php echo json_encode($error); ?>);
        <?php endif; ?>
    });

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

    // Archive action functions
    function restoreSuggestion(archiveId, title) {
        showConfirmationModal(
            'Restore Suggestion',
            `Are you sure you want to restore "${title}"? This will move it back to the active suggestions.`,
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

    // Bulk selection functions
    function selectAll() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox');
        const selectAllCheckbox = document.getElementById('select-all-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        selectAllCheckbox.checked = true;
        updateActionButtons();
    }

    function deselectAll() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox');
        const selectAllCheckbox = document.getElementById('select-all-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAllCheckbox.checked = false;
        updateActionButtons();
    }

    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        const checkboxes = document.querySelectorAll('.suggestion-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateActionButtons();
    }

    function updateActionButtons() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox:checked');
        const restoreBtn = document.getElementById('restore-selected-btn');
        const deleteBtn = document.getElementById('delete-selected-btn');
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        const allCheckboxes = document.querySelectorAll('.suggestion-checkbox');

        if (restoreBtn) restoreBtn.disabled = checkboxes.length === 0;
        if (deleteBtn) deleteBtn.disabled = checkboxes.length === 0;

        // Update select all checkbox state
        if (checkboxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkboxes.length === allCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }

    function viewArchivedDetails(suggestionId) {
        // Find the suggestion card and toggle its archive actions
        toggleArchiveActions(suggestionId);
    }

    // Bulk action functions
    function bulkRestore() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox:checked');
        if (checkboxes.length === 0) {
            showNotificationModal('error', 'Please select suggestions to restore.');
            return;
        }

        const count = checkboxes.length;
        showConfirmationModal(
            'Bulk Restore',
            `Are you sure you want to restore ${count} suggestion(s)? They will be moved back to active suggestions.`,
            function() {
                const form = document.getElementById('bulk-action-form');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulk_restore';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        );
    }

    function bulkPermanentDelete() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox:checked');
        if (checkboxes.length === 0) {
            showNotificationModal('error', 'Please select suggestions to delete.');
            return;
        }

        const count = checkboxes.length;
        showConfirmationModal(
            'Bulk Permanent Delete',
            `Are you sure you want to permanently delete ${count} suggestion(s)? This action cannot be undone.`,
            function() {
                const form = document.getElementById('bulk-action-form');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulk_permanent_delete';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        );
    }
</script>

<style>
    .modal-footer .button--secondary {
        background-color: transparent;
        color: var(--evsu-color);
        border: 1px solid var(--evsu-color);
    }

    .modal-footer .button--secondary:hover {
        background-color: var(--evsu-color);
        color: var(--white-color);
        box-shadow: none;
    }
</style>

<?php include '../includes/footer.php'; ?>