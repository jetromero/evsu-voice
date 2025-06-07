<?php
$page_title = "Manage Suggestions";
require_once '../includes/auth.php';
require_once '../config/database_native.php';
require_once '../config/postgresql_helpers.php';

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
$success = $_SESSION['manage_success'] ?? '';
$error = $_SESSION['manage_error'] ?? '';

// Clear messages from session
unset($_SESSION['manage_success'], $_SESSION['manage_error']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $suggestion_id = (int)$_POST['suggestion_id'];
        $new_status = $_POST['status'];
        $admin_response = trim($_POST['admin_response']);

        $query = "UPDATE suggestions SET status = $1, admin_response = $2, admin_id = $3, updated_at = CURRENT_TIMESTAMP WHERE id = $4";

        $result = $database->query($query, [$new_status, $admin_response, $user['id'], $suggestion_id]);

        if ($result) {
            $_SESSION['manage_success'] = 'Suggestion updated successfully!';
        } else {
            $error = pg_last_error($database->conn);
            error_log("Failed to update suggestion $suggestion_id: " . $error);
            $_SESSION['manage_error'] = 'Failed to update suggestion.';
        }
    } elseif (isset($_POST['delete_multiple'])) {
        if (isset($_POST['selected_suggestions']) && is_array($_POST['selected_suggestions'])) {
            $selected_ids = array_map('intval', $_POST['selected_suggestions']);
            $archived_count = 0;
            $failed_count = 0;

            foreach ($selected_ids as $suggestion_id) {
                try {
                    pg_query($conn, "BEGIN");

                    // Get suggestion data
                    $get_query = "SELECT * FROM suggestions WHERE id = $1";
                    $get_result = $database->query($get_query, [$suggestion_id]);
                    $suggestion_data = $database->fetchAssoc($get_result);

                    if ($suggestion_data) {
                        // Move to archive
                        $archive_query = "INSERT INTO archived_suggestions (original_id, user_id, title, description, category, status, is_anonymous, upvotes_count, admin_response, admin_id, original_created_at, original_updated_at, deleted_by, deleted_by_role)
                                         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14)";
                        $archive_result = $database->query($archive_query, [
                            $suggestion_data['id'],
                            $suggestion_data['user_id'],
                            $suggestion_data['title'],
                            $suggestion_data['description'],
                            $suggestion_data['category'],
                            $suggestion_data['status'],
                            $suggestion_data['is_anonymous'],
                            $suggestion_data['upvotes_count'],
                            $suggestion_data['admin_response'],
                            $suggestion_data['admin_id'],
                            $suggestion_data['created_at'],
                            $suggestion_data['updated_at'],
                            $user['id'],
                            'admin'
                        ]);

                        // Delete from suggestions table
                        $delete_query = "DELETE FROM suggestions WHERE id = $1";
                        $delete_result = $database->query($delete_query, [$suggestion_id]);

                        pg_query($conn, "COMMIT");
                        $archived_count++;
                    } else {
                        $failed_count++;
                    }
                } catch (Exception $e) {
                    pg_query($conn, "ROLLBACK");
                    $failed_count++;
                }
            }

            if ($archived_count > 0) {
                $_SESSION['manage_success'] = "$archived_count suggestion(s) moved to archive successfully!";
                if ($failed_count > 0) {
                    $_SESSION['manage_success'] .= " ($failed_count failed)";
                }
            } else {
                $_SESSION['manage_error'] = 'Failed to archive suggestions.';
            }
        } else {
            $_SESSION['manage_error'] = 'No suggestions selected for deletion.';
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
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build query
$where_conditions = [];
$params = [];
$param_counter = 1;

if ($status_filter) {
    $where_conditions[] = "s.status = $" . $param_counter;
    $params[] = $status_filter;
    $param_counter++;
}

if ($category_filter) {
    $where_conditions[] = "s.category = $" . $param_counter;
    $params[] = $category_filter;
    $param_counter++;
}

if ($search) {
    $where_conditions[] = "(s.title LIKE $" . $param_counter . " OR s.description LIKE $" . ($param_counter + 1) . ")";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_counter += 2;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = PostgreSQLHelpers::todayCondition('s.created_at');
            break;
        case 'week':
            $where_conditions[] = PostgreSQLHelpers::thisWeekCondition('s.created_at');
            break;
        case 'month':
            $where_conditions[] = PostgreSQLHelpers::thisMonthCondition('s.created_at');
            break;
        case 'year':
            $where_conditions[] = PostgreSQLHelpers::thisYearCondition('s.created_at');
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Build ORDER BY clause
$order_clause = "ORDER BY CASE WHEN s.status = 'pending' THEN 1 ELSE 2 END, ";
switch ($sort_order) {
    case 'oldest':
        $order_clause .= "s.created_at ASC";
        break;
    case 'newest':
    default:
        $order_clause .= "s.created_at DESC";
        break;
}

// Get suggestions
$query = "SELECT s.*,
          CASE WHEN s.is_anonymous = true THEN 'Anonymous'
               ELSE (u.first_name || ' ' || u.last_name) END as author_name,
          admin.first_name as admin_first_name,
          admin.last_name as admin_last_name
          FROM suggestions s
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN users admin ON s.admin_id = admin.id
          $where_clause
          $order_clause";

$stmt = $query;
$result = $database->query($stmt, $params);
$suggestions = $database->fetchAll($result);

// Get all categories from the categories table if it exists, otherwise from suggestions
$categories = [];
try {
    $categories_query = "SELECT name FROM categories ORDER BY id ASC";
    $categories_result = $database->query($categories_query);
    $categories_data = $database->fetchAll($categories_result);
    $categories = array_column($categories_data, 'name');
} catch (Exception $e) {
    // If categories table doesn't exist, get from suggestions
    $categories_query = "SELECT DISTINCT category FROM suggestions ORDER BY category";
    $categories_result = $database->query($categories_query);
    $categories_data = $database->fetchAll($categories_result);
    $categories = array_column($categories_data, 'category');
}

include '../includes/header.php';
?>

<main class="main">
    <section class="manage-suggestions section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title">Manage Suggestions</h1>
                <p class="section__description">
                    Review, respond to, and manage the status of community suggestions.
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
                        <button type="button" class="button button--secondary cancel" onclick="closeConfirmationModal()">Cancel</button>
                        <button type="button" class="button button--danger" id="confirm-action-btn">Delete</button>
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

                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="implemented" <?php echo $status_filter === 'implemented' ? 'selected' : ''; ?>>Implemented</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                            <input type="text" name="search" placeholder="Search suggestions..."
                                value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                            <button type="submit" class="search-button">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>

                        <?php if (!empty($search) || !empty($status_filter) || !empty($category_filter) || $sort_order !== 'newest' || !empty($date_filter)): ?>
                            <a href="manage-suggestions.php" class="clear-filters">
                                <i class="ri-close-line"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="results-info admin-results-info">
                    <span class="results-count"><?php echo count($suggestions); ?> suggestion(s) found</span>
                    <div class="admin-actions">
                        <div class="view-toggle">
                            <button type="button" class="view-btn" id="tiles-view-btn" onclick="switchView('tiles')">
                                <i class="ri-grid-line"></i>
                                Tiles
                            </button>
                            <button type="button" class="view-btn" id="list-view-btn" onclick="switchView('list')">
                                <i class="ri-list-check"></i>
                                List
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suggestions List (Tiles View) -->
            <div class="admin-suggestions-list" id="tiles-view">
                <?php if (empty($suggestions)): ?>
                    <div class="no-suggestions">
                        <i class="ri-file-list-line"></i>
                        <h3>No suggestions found</h3>
                        <p>No suggestions match your current filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($suggestions as $suggestion): ?>
                        <div class="admin-suggestion-card" data-suggestion-id="<?php echo $suggestion['id']; ?>">
                            <div class="admin-suggestion-header">
                                <div class="suggestion-meta">
                                    <span class="suggestion-id">#<?php echo $suggestion['id']; ?></span>
                                    <span class="suggestion-category"><?php echo htmlspecialchars($suggestion['category']); ?></span>
                                    <span class="suggestion-status status-<?php echo $suggestion['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                    </span>
                                    <?php if ($suggestion['is_anonymous'] === true || $suggestion['is_anonymous'] === 't' || $suggestion['is_anonymous'] === 'true' || $suggestion['is_anonymous'] === '1' || $suggestion['is_anonymous'] === 1): ?>
                                        <span class="anonymous-badge">
                                            <i class="ri-eye-off-line"></i> Anonymous
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="suggestion-date">
                                    Submitted on <?php echo date('M j, Y \a\t g:i A', strtotime($suggestion['created_at'])); ?>
                                </div>
                            </div>

                            <div class="suggestion-content">
                                <div class="suggestion-content-toggle admin" onclick="toggleSuggestionContent(<?php echo $suggestion['id']; ?>)">
                                    <div class="content-header">
                                        <i class="ri-arrow-down-s-line content-toggle-icon" id="content-icon-<?php echo $suggestion['id']; ?>"></i>
                                        <h3 class="suggestion-title"><?php echo htmlspecialchars($suggestion['title']); ?></h3>
                                    </div>
                                    <div class="suggestion-info admin-suggestion-info">
                                        <span class="author">
                                            <i class="ri-user-line"></i>
                                            <?php echo htmlspecialchars($suggestion['author_name']); ?>
                                        </span>
                                        <span class="votes">
                                            <i class="ri-thumb-up-line"></i>
                                            <?php echo $suggestion['upvotes_count']; ?> votes
                                        </span>
                                    </div>
                                </div>

                                <div class="suggestion-content-details" id="content-details-<?php echo $suggestion['id']; ?>" style="display: none;">
                                    <p class="suggestion-description"><?php echo nl2br(htmlspecialchars($suggestion['description'])); ?></p>
                                </div>
                            </div>

                            <!-- Admin Response Form -->
                            <div class="admin-response-section">
                                <div class="admin-response-toggle" onclick="toggleAdminResponse(<?php echo $suggestion['id']; ?>)">
                                    <i class="ri-arrow-down-s-line toggle-icon" id="toggle-icon-<?php echo $suggestion['id']; ?>"></i>
                                    <span>Admin Actions</span>
                                    <span class="current-status">
                                        Current: <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                    </span>
                                </div>

                                <div class="admin-response-content" id="admin-content-<?php echo $suggestion['id']; ?>" style="display: none;">
                                    <form method="POST" class="admin-response-form">
                                        <input type="hidden" name="suggestion_id" value="<?php echo $suggestion['id']; ?>">

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="status_<?php echo $suggestion['id']; ?>" class="form__label">Status</label>
                                                <select name="status" id="status_<?php echo $suggestion['id']; ?>" class="form__select" required>
                                                    <option value="pending" <?php echo $suggestion['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="new" <?php echo $suggestion['status'] === 'new' ? 'selected' : ''; ?>>New (Publish)</option>
                                                    <option value="under_review" <?php echo $suggestion['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                                    <option value="in_progress" <?php echo $suggestion['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="implemented" <?php echo $suggestion['status'] === 'implemented' ? 'selected' : ''; ?>>Implemented</option>
                                                    <option value="rejected" <?php echo $suggestion['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="response_<?php echo $suggestion['id']; ?>" class="form__label">EVSU Response</label>
                                            <textarea name="admin_response" id="response_<?php echo $suggestion['id']; ?>"
                                                class="form__textarea" rows="3"
                                                placeholder="Provide feedback or explanation for your decision..."><?php echo htmlspecialchars($suggestion['admin_response']); ?></textarea>
                                        </div>

                                        <div class="form-actions">
                                            <button type="submit" name="update_status" class="button">
                                                <i class="ri-save-line"></i>
                                                Update Suggestion
                                            </button>
                                        </div>
                                    </form>

                                    <?php if ($suggestion['admin_response'] && $suggestion['admin_first_name']): ?>
                                        <div class="previous-response">
                                            <div class="response-header">
                                                <i class="ri-admin-line"></i>
                                                <strong>Previous Response by <?php echo htmlspecialchars($suggestion['admin_first_name'] . ' ' . $suggestion['admin_last_name']); ?>:</strong>
                                                <span class="response-date"><?php echo date('M j, Y', strtotime($suggestion['updated_at'])); ?></span>
                                            </div>
                                            <p><?php echo nl2br(htmlspecialchars($suggestion['admin_response'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Suggestions Table (List View) -->
            <div class="admin-suggestions-table" id="list-view" style="display: none;">
                <?php if (empty($suggestions)): ?>
                    <div class="no-suggestions">
                        <i class="ri-file-list-line"></i>
                        <h3>No suggestions found</h3>
                        <p>No suggestions match your current filters.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" id="bulk-delete-form">
                        <div class="table-actions">
                            <button type="button" class="button button--danger" onclick="deleteSelected()" id="delete-selected-btn" disabled>
                                <i class="ri-delete-bin-line"></i>
                                Delete Selected
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
                                        <th>Votes</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suggestions as $suggestion): ?>
                                        <tr class="admin-suggestion-row">
                                            <td>
                                                <input type="checkbox" name="selected_suggestions[]"
                                                    value="<?php echo $suggestion['id']; ?>"
                                                    class="suggestion-checkbox"
                                                    onchange="updateDeleteButton()">
                                            </td>
                                            <td>#<?php echo $suggestion['id']; ?></td>
                                            <td>
                                                <div class="table-title" title="<?php echo htmlspecialchars($suggestion['title']); ?>">
                                                    <?php echo htmlspecialchars($suggestion['title']); ?>
                                                    <?php if ($suggestion['is_anonymous'] === true || $suggestion['is_anonymous'] === 't' || $suggestion['is_anonymous'] === 'true' || $suggestion['is_anonymous'] === '1' || $suggestion['is_anonymous'] === 1): ?>
                                                        <i class="ri-eye-off-line" title="Anonymous"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($suggestion['category']); ?></td>
                                            <td><?php echo htmlspecialchars($suggestion['author_name']); ?></td>
                                            <td>
                                                <span class="suggestion-status status-<?php echo $suggestion['status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $suggestion['upvotes_count']; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($suggestion['created_at'])); ?></td>
                                            <td>
                                                <button type="button" class="table-action-btn"
                                                    onclick="viewSuggestionDetails(<?php echo $suggestion['id']; ?>)"
                                                    title="View Details">
                                                    <i class="ri-eye-line"></i>
                                                </button>
                                                <button type="button" class="table-action-btn"
                                                    onclick="editSuggestionStatus(<?php echo $suggestion['id']; ?>)"
                                                    title="Edit Status">
                                                    <i class="ri-edit-line"></i>
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

        // Restore saved view preference
        restoreViewPreference();

        // Show notification modal if there are success/error messages
        <?php if ($success): ?>
            showNotificationModal('success', <?php echo json_encode($success); ?>);
        <?php endif; ?>

        <?php if ($error): ?>
            showNotificationModal('error', <?php echo json_encode($error); ?>);
        <?php endif; ?>
    });

    // View preference functions
    function saveViewPreference(viewType) {
        localStorage.setItem('manage-suggestions-view', viewType);
    }

    function restoreViewPreference() {
        const savedView = localStorage.getItem('manage-suggestions-view');
        if (savedView && (savedView === 'tiles' || savedView === 'list')) {
            switchView(savedView);
        } else {
            // Default to tiles view if no preference is saved
            switchView('tiles');
        }
    }

    // View switching functionality
    function switchView(viewType) {
        const tilesView = document.getElementById('tiles-view');
        const listView = document.getElementById('list-view');
        const tilesBtn = document.getElementById('tiles-view-btn');
        const listBtn = document.getElementById('list-view-btn');

        if (viewType === 'tiles') {
            tilesView.style.display = ''; // Reset to CSS default (grid)
            listView.style.display = 'none';
            tilesBtn.classList.add('active');
            listBtn.classList.remove('active');
        } else {
            tilesView.style.display = 'none';
            listView.style.display = 'block';
            tilesBtn.classList.remove('active');
            listBtn.classList.add('active');
        }

        // Save the view preference
        saveViewPreference(viewType);
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

    // Bulk selection functions
    function selectAll() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox');
        const selectAllCheckbox = document.getElementById('select-all-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        selectAllCheckbox.checked = true;
        updateDeleteButton();
    }

    function deselectAll() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox');
        const selectAllCheckbox = document.getElementById('select-all-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAllCheckbox.checked = false;
        updateDeleteButton();
    }

    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        const checkboxes = document.querySelectorAll('.suggestion-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateDeleteButton();
    }

    function updateDeleteButton() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox:checked');
        const deleteBtn = document.getElementById('delete-selected-btn');
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        const allCheckboxes = document.querySelectorAll('.suggestion-checkbox');

        deleteBtn.disabled = checkboxes.length === 0;

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

    function deleteSelected() {
        const checkboxes = document.querySelectorAll('.suggestion-checkbox:checked');
        if (checkboxes.length === 0) {
            showNotificationModal('error', 'Please select at least one suggestion to delete.');
            return;
        }

        showConfirmationModal(
            'Delete Suggestions',
            `Are you sure you want to delete ${checkboxes.length} suggestion(s)?`,
            function() {
                const form = document.getElementById('bulk-delete-form');
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_multiple';
                deleteInput.value = '1';
                form.appendChild(deleteInput);
                form.submit();
            }
        );
    }

    // Table action functions
    function viewSuggestionDetails(suggestionId) {
        // Switch to tiles view and expand the suggestion
        switchView('tiles');
        setTimeout(() => {
            const suggestionCard = document.querySelector(`[data-suggestion-id="${suggestionId}"]`);
            if (suggestionCard) {
                suggestionCard.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                toggleSuggestionContent(suggestionId);
            }
        }, 100);
    }

    function editSuggestionStatus(suggestionId) {
        // Switch to tiles view and expand the admin response section
        switchView('tiles');
        setTimeout(() => {
            const suggestionCard = document.querySelector(`[data-suggestion-id="${suggestionId}"]`);
            if (suggestionCard) {
                suggestionCard.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                toggleAdminResponse(suggestionId);
            }
        }, 100);
    }

    // Toggle admin response section
    function toggleAdminResponse(suggestionId) {
        const content = document.getElementById('admin-content-' + suggestionId);
        const icon = document.getElementById('toggle-icon-' + suggestionId);

        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.classList.remove('ri-arrow-down-s-line');
            icon.classList.add('ri-arrow-up-s-line');
        } else {
            content.style.display = 'none';
            icon.classList.remove('ri-arrow-up-s-line');
            icon.classList.add('ri-arrow-down-s-line');
        }
    }

    // Toggle suggestion content section
    function toggleSuggestionContent(suggestionId) {
        const content = document.getElementById('content-details-' + suggestionId);
        const icon = document.getElementById('content-icon-' + suggestionId);

        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.classList.remove('ri-arrow-down-s-line');
            icon.classList.add('ri-arrow-up-s-line');
        } else {
            content.style.display = 'none';
            icon.classList.remove('ri-arrow-up-s-line');
            icon.classList.add('ri-arrow-down-s-line');
        }
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
    .button--secondary.cancel {
        background-color: transparent;
        color: var(--text-color);
        border: 1px solid var(--border-color);
    }

    .button--secondary.cancel:hover {
        background-color: var(--evsu-color);
        color: var(--white-color);
        box-shadow: none;
    }

    .admin-suggestion-row td {
        font-size: var(--small-font-size);
    }

    .table-action-btn {
        margin: 0;
    }

    /* Admin actions layout */
    .admin-actions {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .admin-actions .button {
        margin: 0;
    }
</style>

<?php include '../includes/footer.php'; ?>