<?php
$page_title = "Browse Suggestions";
require_once 'includes/auth.php';
require_once 'config/database_native.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$database = new DatabaseNative();
$conn = $database->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$sort_by = $_GET['sort'] ?? 'date'; // date, votes, oldest

// Build query with filters
$where_conditions = ["s.status != 'pending'", "s.status != 'rejected'"];
$params = [];
$param_counter = 1;

if (!empty($search)) {
    $where_conditions[] = "(s.title LIKE $" . $param_counter . " OR s.description LIKE $" . ($param_counter + 1) . ")";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_counter += 2;
}

if (!empty($status_filter)) {
    $where_conditions[] = "s.status = $" . $param_counter;
    $params[] = $status_filter;
    $param_counter++;
}

if (!empty($category_filter)) {
    $where_conditions[] = "s.category = $" . $param_counter;
    $params[] = $category_filter;
    $param_counter++;
}

$where_clause = implode(' AND ', $where_conditions);

// Determine sort order
$order_by = "s.created_at DESC"; // default
switch ($sort_by) {
    case 'votes':
        $order_by = "s.upvotes_count DESC, s.created_at DESC";
        break;
    case 'oldest':
        $order_by = "s.created_at ASC";
        break;
    case 'date':
    default:
        $order_by = "s.created_at DESC";
        break;
}

// Get suggestions with user vote status
$query = "SELECT s.*, 
          CASE WHEN s.is_anonymous = true THEN 'Anonymous' 
               ELSE (u.first_name || ' ' || u.last_name) END as author_name,
          " . ($user ? "CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END as user_voted" : "0 as user_voted") . "
          FROM suggestions s 
          LEFT JOIN users u ON s.user_id = u.id ";

if ($user) {
    $query .= "LEFT JOIN votes v ON s.id = v.suggestion_id AND v.user_id = " . $user['id'] . " ";
}

$query .= "WHERE $where_clause ORDER BY $order_by";

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

// Get all possible statuses (excluding rejected since they're not displayed)
$statuses = [
    'new' => 'New',
    'under_review' => 'Under Review',
    'in_progress' => 'In Progress',
    'implemented' => 'Implemented'
];

include 'includes/header.php';
?>

<main class="main">
    <section class="suggestions-section section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title">Browse Suggestions</h1>
                <p class="section__description">
                    Explore ideas and feedback from the EVSU community. Vote for suggestions you support!
                </p>
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
                            <?php foreach ($statuses as $status_key => $status_label): ?>
                                <option value="<?php echo htmlspecialchars($status_key); ?>"
                                    <?php echo $status_filter === $status_key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="sort" class="filter-select">
                            <option value="date" <?php echo $sort_by === 'date' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="votes" <?php echo $sort_by === 'votes' ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>

                        <div class="search-box">
                            <input type="text" name="search" placeholder="Search suggestions..."
                                value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                            <button type="submit" class="search-button">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>

                        <?php if (!empty($search) || !empty($status_filter) || !empty($category_filter) || $sort_by !== 'date'): ?>
                            <a href="browse-suggestions.php" class="clear-filters">
                                <i class="ri-close-line"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="results-info">
                    <span class="results-count"><?php echo count($suggestions); ?> suggestion(s) found</span>
                </div>
            </div>

            <!-- Suggestions List -->
            <div class="suggestions-grid">
                <?php if (empty($suggestions)): ?>
                    <div class="no-suggestions">
                        <i class="ri-file-list-line"></i>
                        <h3>No suggestions found</h3>
                        <p>Share your ideas with the EVSU community!</p>
                        <?php if ($user): ?>
                            <a href="submit-suggestion.php" class="button">Submit Suggestion</a>
                        <?php else: ?>
                            <a href="login.php" class="button">Sign In to Submit</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($suggestions as $suggestion): ?>
                        <div class="suggestion-card">
                            <div class="suggestion-header">
                                <div class="suggestion-meta">
                                    <span class="suggestion-category"><?php echo htmlspecialchars($suggestion['category']); ?></span>
                                    <span class="suggestion-status status-<?php echo $suggestion['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                    </span>
                                </div>
                                <div class="suggestion-date">
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($suggestion['created_at'])); ?>
                                </div>
                            </div>

                            <h3 class="suggestion-title"><?php echo htmlspecialchars($suggestion['title']); ?></h3>
                            <p class="suggestion-description"><?php echo nl2br(htmlspecialchars($suggestion['description'])); ?></p>

                            <div class="suggestion-footer">
                                <div class="suggestion-author">
                                    <i class="ri-user-line"></i>
                                    <?php echo htmlspecialchars($suggestion['author_name']); ?>
                                </div>

                                <div class="suggestion-actions">
                                    <div class="vote-section">
                                        <?php if ($user && $user['role'] === 'student'): ?>
                                            <button type="button"
                                                class="vote-button <?php echo $suggestion['user_voted'] ? 'voted' : ''; ?>"
                                                data-suggestion-id="<?php echo $suggestion['id']; ?>"
                                                onclick="toggleVote(this)">
                                                <i class="ri-thumb-up-<?php echo $suggestion['user_voted'] ? 'fill' : 'line'; ?>"></i>
                                                <span class="vote-count"><?php echo $suggestion['upvotes_count']; ?></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="vote-count">
                                                <i class="ri-thumb-up-line"></i>
                                                <?php echo $suggestion['upvotes_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
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

            <?php if (!$user): ?>
                <div class="login-prompt">
                    <div class="login-prompt-content">
                        <i class="ri-user-line"></i>
                        <h3>Want to vote on suggestions?</h3>
                        <p>Sign in with your EVSU account to vote and submit your own suggestions.</p>
                        <a href="login.php" class="button">Sign In</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>



<script>
    function toggleVote(button) {
        const suggestionId = button.dataset.suggestionId;
        const isVoted = button.classList.contains('voted');
        const icon = button.querySelector('i');
        const countSpan = button.querySelector('.vote-count');
        const currentCount = parseInt(countSpan.textContent);

        // Optimistic update - immediately update UI
        if (isVoted) {
            // Removing vote
            button.classList.remove('voted');
            icon.className = 'ri-thumb-up-line';
            countSpan.textContent = currentCount - 1;
        } else {
            // Adding vote
            button.classList.add('voted');
            icon.className = 'ri-thumb-up-fill';
            countSpan.textContent = currentCount + 1;
        }

        // Disable button during request
        button.disabled = true;

        fetch('vote-handler-simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `suggestion_id=${suggestionId}&action=${isVoted ? 'remove' : 'add'}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Server confirmed - sync the actual count from server
                    countSpan.textContent = data.vote_count;

                    // Ensure UI state matches server response
                    if (data.voted) {
                        button.classList.add('voted');
                        icon.className = 'ri-thumb-up-fill';
                    } else {
                        button.classList.remove('voted');
                        icon.className = 'ri-thumb-up-line';
                    }
                } else {
                    // Server failed - revert the optimistic update
                    console.error('Vote error:', data.message);

                    if (isVoted) {
                        // Was trying to remove vote, revert back to voted state
                        button.classList.add('voted');
                        icon.className = 'ri-thumb-up-fill';
                        countSpan.textContent = currentCount;
                    } else {
                        // Was trying to add vote, revert back to unvoted state
                        button.classList.remove('voted');
                        icon.className = 'ri-thumb-up-line';
                        countSpan.textContent = currentCount;
                    }

                    alert(data.message || 'An error occurred while voting.');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);

                // Network error - revert the optimistic update
                if (isVoted) {
                    // Was trying to remove vote, revert back to voted state
                    button.classList.add('voted');
                    icon.className = 'ri-thumb-up-fill';
                    countSpan.textContent = currentCount;
                } else {
                    // Was trying to add vote, revert back to unvoted state
                    button.classList.remove('voted');
                    icon.className = 'ri-thumb-up-line';
                    countSpan.textContent = currentCount;
                }

                alert('An error occurred while voting: ' + error.message);
            })
            .finally(() => {
                button.disabled = false;
            });
    }

    // Auto-submit form when filters change
    document.addEventListener('DOMContentLoaded', function() {
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>