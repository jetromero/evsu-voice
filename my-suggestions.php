<?php
$page_title = "My Suggestions";
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
$success = $_SESSION['my_suggestions_success'] ?? '';
$error = $_SESSION['my_suggestions_error'] ?? '';

// Clear messages from session
unset($_SESSION['my_suggestions_success'], $_SESSION['my_suggestions_error']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_suggestion'])) {
        $suggestion_id = (int)$_POST['suggestion_id'];

        // Get suggestion data and verify ownership
        $verify_query = "SELECT * FROM suggestions WHERE id = $1 AND user_id = $2";
        $verify_result = $database->query($verify_query, [$suggestion_id, $user['id']]);
        $suggestion_data = $database->fetchAssoc($verify_result);

        if ($suggestion_data) {
            try {
                pg_query($conn, "BEGIN");

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
                    'student'
                ]);

                // Delete from suggestions table
                $delete_query = "DELETE FROM suggestions WHERE id = $1 AND user_id = $2";
                $delete_result = $database->query($delete_query, [$suggestion_id, $user['id']]);

                pg_query($conn, "COMMIT");
                $_SESSION['my_suggestions_success'] = 'Suggestion moved to trash successfully!';
            } catch (Exception $e) {
                pg_query($conn, "ROLLBACK");
                $_SESSION['my_suggestions_error'] = 'Failed to delete suggestion: ' . $e->getMessage();
            }
        } else {
            $_SESSION['my_suggestions_error'] = 'Suggestion not found or you do not have permission to delete it.';
        }
    } elseif (isset($_POST['update_suggestion'])) {
        $suggestion_id = (int)$_POST['suggestion_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

        // Verify that the suggestion belongs to the current user and is pending
        $verify_query = "SELECT id FROM suggestions WHERE id = $1 AND user_id = $2 AND status = 'pending'";
        $verify_result = $database->query($verify_query, [$suggestion_id, $user['id']]);

        if ($database->fetchAssoc($verify_result)) {
            if (!empty($title) && !empty($description) && !empty($category)) {
                $update_query = "UPDATE suggestions SET title = $1, description = $2, category = $3, is_anonymous = $4, updated_at = CURRENT_TIMESTAMP WHERE id = $5 AND user_id = $6";
                $update_result = $database->query($update_query, [$title, $description, $category, $is_anonymous, $suggestion_id, $user['id']]);

                if ($update_result) {
                    $_SESSION['my_suggestions_success'] = 'Suggestion updated successfully!';
                } else {
                    $_SESSION['my_suggestions_error'] = 'Failed to update suggestion.';
                }
            } else {
                $_SESSION['my_suggestions_error'] = 'Please fill in all required fields.';
            }
        } else {
            $_SESSION['my_suggestions_error'] = 'Suggestion not found, you do not have permission to edit it, or it is no longer pending.';
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
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query with filters (include anonymous suggestions)
$where_conditions = ["user_id = $1"];
$params = [$user['id']];

if ($status_filter) {
    $where_conditions[] = "status = $" . (count($params) + 1);
    $params[] = $status_filter;
}

if ($category_filter) {
    $where_conditions[] = "category = $" . (count($params) + 1);
    $params[] = $category_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_clause = $sort_order === 'oldest' ? 'ORDER BY created_at ASC' : 'ORDER BY created_at DESC';

// Get user's suggestions with filters
$query = "SELECT * FROM suggestions WHERE $where_clause $order_clause";
$result = $database->query($query, $params);
$suggestions = $database->fetchAll($result);

// Get all categories for filter dropdown (from all suggestions, not just user's)
try {
    $categories_query = "SELECT name FROM categories ORDER BY id ASC";
    $categories_result = $database->query($categories_query);
    $categories = [];
    if ($categories_result) {
        while ($row = $database->fetchAssoc($categories_result)) {
            $categories[] = $row['name'];
        }
    }
} catch (Exception $e) {
    // If categories table doesn't exist, get from all suggestions
    $categories_query = "SELECT DISTINCT category FROM suggestions ORDER BY category";
    $categories_result = $database->query($categories_query);
    $categories = [];
    if ($categories_result) {
        while ($row = $database->fetchAssoc($categories_result)) {
            $categories[] = $row['category'];
        }
    }
}

// Get statistics
$stats_query = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'implemented' THEN 1 ELSE 0 END) as implemented,
                SUM(upvotes_count) as total_votes
                FROM suggestions WHERE user_id = $1";
$stats_result = $database->query($stats_query, [$user['id']]);
$stats = [];
if ($stats_result) {
    $stats = $database->fetchAssoc($stats_result);
}

// Initialize default stats if query failed
if (empty($stats)) {
    $stats = [
        'total' => 0,
        'new' => 0,
        'under_review' => 0,
        'in_progress' => 0,
        'implemented' => 0,
        'total_votes' => 0
    ];
}

include 'includes/header.php';
?>

<main class="main">
    <section class="my-suggestions-section section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title">My Suggestions</h1>
                <p class="section__description">
                    Track your submitted suggestions and see how they're progressing through the review process.
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
                        <button type="button" class="button button--danger" id="confirm-action-btn">Delete</button>
                    </div>
                </div>
            </div>

            <!-- Update Modal -->
            <div id="update-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Update Suggestion</h3>
                        <button type="button" class="modal-close" onclick="closeUpdateModal()">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="update-form">
                            <input type="hidden" name="suggestion_id" id="update-suggestion-id">

                            <div class="form-group">
                                <label for="update-title" class="form__label">Title *</label>
                                <input type="text" name="title" id="update-title" class="form__input" required>
                            </div>

                            <div class="form-group">
                                <label for="update-category" class="form__label">Category *</label>
                                <select name="category" id="update-category" class="form__select" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>">
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="update-description" class="form__label">Description *</label>
                                <textarea name="description" id="update-description" class="form__textarea" rows="4" required></textarea>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_anonymous" id="update-anonymous">
                                    <span class="checkmark"></span>
                                    Submit anonymously
                                </label>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button button--secondary" onclick="closeUpdateModal()">Cancel</button>
                        <button type="submit" form="update-form" name="update_suggestion" class="button">
                            <i class="ri-save-line"></i> Update Suggestion
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ri-file-list-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Suggestions</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ri-check-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['new'] + $stats['under_review'] + $stats['in_progress']; ?></h3>
                        <p>Published</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ri-tools-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['implemented']; ?></h3>
                        <p>Implemented</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ri-thumb-up-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_votes']; ?></h3>
                        <p>Total Votes</p>
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

                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>Published</option>
                            <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="implemented" <?php echo $status_filter === 'implemented' ? 'selected' : ''; ?>>Implemented</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>

                        <select name="sort" class="filter-select">
                            <option value="newest" <?php echo $sort_order === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_order === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>

                        <?php if (!empty($status_filter) || !empty($category_filter) || $sort_order !== 'newest'): ?>
                            <a href="my-suggestions.php" class="clear-filters">
                                <i class="ri-close-line"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="results-info">
                    <span class="results-count"><?php echo count($suggestions); ?> suggestion(s) found</span>
                    <div class="action-links">
                        <a href="submit-suggestion.php" class="button">
                            <i class="ri-add-line"></i> Submit New
                        </a>
                        <a href="my-trash.php" class="button-secondary">
                            <i class="ri-archive-line"></i> View Trash
                        </a>
                        <a href="browse-suggestions.php" class="button-secondary">
                            <i class="ri-search-line"></i> Browse All
                        </a>
                    </div>
                </div>
            </div>

            <!-- Suggestions List -->
            <div class="my-suggestions-list">
                <?php if (empty($suggestions)): ?>
                    <div class="no-suggestions">
                        <i class="ri-file-list-line"></i>
                        <h3>No suggestions yet</h3>
                        <p>You haven't submitted any suggestions yet. Share your ideas to help improve EVSU!</p>
                        <a href="submit-suggestion.php" class="button">Submit Your First Suggestion</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($suggestions as $suggestion): ?>
                        <div class="my-suggestion-card">
                            <div class="suggestion-header">
                                <div class="suggestion-meta">
                                    <span class="suggestion-category"><?php echo htmlspecialchars($suggestion['category']); ?></span>
                                    <span class="suggestion-status status-<?php echo $suggestion['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                    </span>
                                    <?php if ($suggestion['is_anonymous'] == 1 || $suggestion['is_anonymous'] === true || $suggestion['is_anonymous'] === 't'): ?>
                                        <span class="anonymous-badge">
                                            <i class="ri-eye-off-line"></i> Anonymous
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="suggestion-date">
                                    Submitted on <?php echo date('M j, Y \a\t g:i A', strtotime($suggestion['created_at'])); ?>
                                </div>
                            </div>

                            <h3 class="suggestion-title"><?php echo htmlspecialchars($suggestion['title']); ?></h3>
                            <div class="suggestion-description-container">
                                <p class="suggestion-description collapsed" data-full-text="<?php echo htmlspecialchars($suggestion['description']); ?>">
                                    <?php
                                    $description = htmlspecialchars($suggestion['description']);
                                    $line_count = substr_count($description, "\n") + 1;
                                    $should_truncate = strlen($description) > 80 || $line_count > 3;
                                    $truncated = $should_truncate ? substr($description, 0, 80) . '...' : $description;
                                    echo nl2br($truncated);
                                    ?>
                                </p>
                                <?php if (strlen($suggestion['description']) > 80 || (substr_count($suggestion['description'], "\n") + 1) > 3): ?>
                                    <button class="read-more-btn" onclick="toggleDescription(this)">
                                        <span class="read-more-text">Read more</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="suggestion-footer">
                                <div class="suggestion-stats">
                                    <span class="vote-count user">
                                        <i class="ri-thumb-up-line"></i>
                                        <?php echo $suggestion['upvotes_count']; ?> votes
                                    </span>

                                    <?php if ($suggestion['updated_at'] !== $suggestion['created_at']): ?>
                                        <span class="last-updated">
                                            <i class="ri-refresh-line"></i>
                                            Updated: <?php echo date('M j, Y \a\t g:i A', strtotime($suggestion['updated_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="suggestion-actions">
                                    <div class="action-buttons">
                                        <?php if ($suggestion['status'] === 'pending'): ?>
                                            <button type="button" class="action-btn update-btn"
                                                data-id="<?php echo $suggestion['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($suggestion['title']); ?>"
                                                data-category="<?php echo htmlspecialchars($suggestion['category']); ?>"
                                                data-description="<?php echo htmlspecialchars($suggestion['description']); ?>"
                                                data-anonymous="<?php echo $suggestion['is_anonymous']; ?>"
                                                title="Update Suggestion">
                                                <i class="ri-edit-line"></i>
                                                Update
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="action-btn delete-btn user"
                                            onclick="deleteSuggestion(<?php echo $suggestion['id']; ?>, '<?php echo htmlspecialchars($suggestion['title'], ENT_QUOTES); ?>')"
                                            title="Delete Suggestion">
                                            <i class="ri-delete-bin-line"></i>
                                            Delete
                                        </button>
                                    </div>

                                    <?php if ($suggestion['status'] === 'pending'): ?>
                                        <span class="status-info">
                                            <i class="ri-time-line"></i>
                                            Awaiting admin review
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($suggestion['admin_response']): ?>
                                <div class="admin-response">
                                    <div class="admin-response-header">
                                        <i class="ri-admin-line"></i>
                                        <strong>EVSU Response:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($suggestion['admin_response'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
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

        // Add event listeners for update buttons
        const updateButtons = document.querySelectorAll('.update-btn');
        updateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                const category = this.getAttribute('data-category');
                const description = this.getAttribute('data-description');
                const isAnonymous = this.getAttribute('data-anonymous');

                openUpdateModal(id, title, category, description, isAnonymous);
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

    // Toggle description function
    function toggleDescription(button) {
        const descriptionContainer = button.closest('.suggestion-description-container');
        const description = descriptionContainer.querySelector('.suggestion-description');
        const readMoreText = button.querySelector('.read-more-text');
        const icon = button.querySelector('i');
        const fullText = description.getAttribute('data-full-text');

        if (description.classList.contains('collapsed')) {
            // Expand
            description.innerHTML = fullText.replace(/\n/g, '<br>');
            description.classList.remove('collapsed');
            description.classList.add('expanded');
            readMoreText.textContent = 'Read less';
            icon.className = 'ri-arrow-up-s-line';
        } else {
            // Collapse
            const lineCount = (fullText.match(/\n/g) || []).length + 1;
            const shouldTruncate = fullText.length > 80 || lineCount > 3;
            const truncated = shouldTruncate ? fullText.substring(0, 80) + '...' : fullText;
            description.innerHTML = truncated.replace(/\n/g, '<br>');
            description.classList.remove('expanded');
            description.classList.add('collapsed');
            readMoreText.textContent = 'Read more';
            icon.className = 'ri-arrow-down-s-line';
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

    // Update modal functions
    function openUpdateModal(id, title, category, description, isAnonymous) {
        const modal = document.getElementById('update-modal');

        document.getElementById('update-suggestion-id').value = id;
        document.getElementById('update-title').value = title;
        document.getElementById('update-category').value = category;
        document.getElementById('update-description').value = description;

        // Handle different boolean formats from PostgreSQL
        document.getElementById('update-anonymous').checked = (isAnonymous == 1 || isAnonymous === true || isAnonymous === 't' || isAnonymous === 'true');

        modal.style.display = 'block';
    }

    function closeUpdateModal() {
        const modal = document.getElementById('update-modal');
        modal.style.display = 'none';
    }

    // Action functions
    function deleteSuggestion(id, title) {
        showConfirmationModal(
            'Delete Suggestion',
            `Are you sure you want to delete "${title}"? This action cannot be undone.`,
            function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="suggestion_id" value="${id}">
                    <input type="hidden" name="delete_suggestion" value="1">
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
        const updateModal = document.getElementById('update-modal');

        if (event.target === notificationModal) {
            closeNotificationModal();
        }

        if (event.target === confirmationModal) {
            closeConfirmationModal();
        }

        if (event.target === updateModal) {
            closeUpdateModal();
        }
    }
</script>

<style>
    span.vote-count:hover {
        color: var(--text-color);
    }

    .suggestion-description-container {
        position: relative;
    }

    .suggestion-description {
        margin-bottom: 0;
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .suggestion-description.collapsed {
        max-height: 4.5rem;
        /* About 3 lines */
        line-height: 1.5em;
        margin-bottom: 8px;
    }

    .suggestion-description.expanded {
        max-height: none;
    }

    .read-more-btn {
        background: none;
        border: none;
        color: var(--evsu-color, #dc3545);
        cursor: pointer;
        font-size: 14px;
        padding: 5px 0;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
        transition: color 0.3s ease;
    }

    .read-more-btn:hover {
        color: var(--evsu-hover-color, #b52d3c);
    }

    .read-more-btn i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .read-more-text {
        font-weight: 500;
    }

    /* Smooth animation for icon rotation */
    .read-more-btn i.ri-arrow-up-s-line {
        transform: rotate(0deg);
    }

    .read-more-btn i.ri-arrow-down-s-line {
        transform: rotate(0deg);
    }

    /* Action buttons styles */
    .action-buttons {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
    }

    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .action-btn i {
        font-size: 16px;
    }

    .update-btn {
        background-color: var(--evsu-color, #dc3545);
        color: white;
    }

    .update-btn:hover {
        background-color: var(--evsu-hover-color, #b52d3c);
        transform: translateY(-1px);
    }

    .delete-btn.user {
        background-color: transparent;
        color: var(--evsu-color);
        border: 1px solid var(--evsu-color);
    }

    .delete-btn.user:hover {
        background-color: var(--evsu-color);
        color: var(--white-color);
        transform: translateY(-1px);
    }

    /* Modal styles */
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(2px);
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #e9ecef;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-color);
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #6c757d;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background-color: #f8f9fa;
        color: var(--text-color);
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 24px;
        border-top: 1px solid #e9ecef;
        background-color: var(--container-color);
        border-radius: 0 0 8px 8px;
    }

    /* Icon styles */


    #modal-icon,
    #confirmation-icon {
        text-align: center;
        margin-bottom: 16px;
    }

    /* Form styles in modal */
    .form-group {
        margin-bottom: 20px;
    }

    .form__label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-color);
    }

    .form__input,
    .form__select,
    .form__textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .form__input:focus,
    .form__select:focus,
    .form__textarea:focus {
        outline: none;
        border-color: var(--evsu-color, #dc3545);
        box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: 14px;
    }

    .checkbox-label input[type="checkbox"] {
        width: auto;
        margin: 0;
    }

    /* Button styles */
    .button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        background-color: var(--evsu-color, #dc3545);
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .button:hover {
        background-color: var(--evsu-hover-color, #b52d3c);
        transform: translateY(-1px);
    }

    .button--secondary {
        background-color: #6c757d;
        color: white;
    }

    .button--secondary:hover {
        background-color: #5a6268;
    }

    .button--danger {
        background-color: var(--evsu-color);
        color: white;
    }

    .button--danger:hover {
        background-color: #c82333;
    }

    input#update-anonymous {
        width: 1rem;
        height: 1rem;
        border-radius: 0.25rem;
        cursor: pointer;
        accent-color: var(--evsu-color);
    }
</style>

<?php include 'includes/footer.php'; ?>