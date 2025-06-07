<?php
$page_title = "Admin Dashboard";
require_once '../includes/auth.php';
require_once '../config/database_native.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new DatabaseNative();
$conn = $database->getConnection();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_suggestions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'implemented' THEN 1 ELSE 0 END) as implemented,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(upvotes_count) as total_votes
                FROM suggestions";
$stats_result = $database->query($stats_query);
$stats = $database->fetchAssoc($stats_result);

// Get user statistics
$user_stats_query = "SELECT COUNT(*) as total_users FROM users WHERE role = 'student'";
$user_stats_result = $database->query($user_stats_query);
$user_stats = $database->fetchAssoc($user_stats_result);

// Get recent suggestions
$recent_query = "SELECT s.*, 
                 CASE WHEN s.is_anonymous = true THEN 'Anonymous' 
                      ELSE (u.first_name || ' ' || u.last_name) END as author_name
                 FROM suggestions s 
                 LEFT JOIN users u ON s.user_id = u.id 
                 ORDER BY s.created_at DESC 
                 LIMIT 5";
$recent_result = $database->query($recent_query);
$recent_suggestions = $database->fetchAll($recent_result);

// Get category statistics
$category_stats_query = "SELECT category, COUNT(*) as count 
                         FROM suggestions 
                         GROUP BY category 
                         ORDER BY count DESC";
$category_stats_result = $database->query($category_stats_query);
$category_stats = $database->fetchAll($category_stats_result);

include '../includes/header.php';
?>

<main class="main">
    <section class="admin-dashboard section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title admin-dashboard-title">Admin Dashboard</h1>
                <p class="section__description">
                    Manage suggestions, monitor community engagement, and oversee the EVSU Voice platform.
                </p>
            </div>

            <!-- Statistics Grid -->
            <div class="admin-stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="ri-file-list-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_suggestions']; ?></h3>
                        <p>Total Suggestions</p>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="ri-time-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="ri-lightbulb-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['new']; ?></h3>
                        <p>New</p>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="ri-search-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['under_review']; ?></h3>
                        <p>Under Review</p>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="ri-refresh-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['in_progress']; ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="ri-tools-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['implemented']; ?></h3>
                        <p>Implemented</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content Grid -->
            <div class="dashboard-content-grid">
                <!-- Recent Suggestions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="ri-file-list-line"></i> Recent Suggestions</h3>
                        <a href="manage-suggestions.php" class="view-all">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recent_suggestions)): ?>
                            <p class="no-data">No suggestions yet.</p>
                        <?php else: ?>
                            <div class="suggestions-list">
                                <?php foreach ($recent_suggestions as $suggestion): ?>
                                    <div class="suggestion-item">
                                        <div class="suggestion-info">
                                            <h4><?php echo htmlspecialchars($suggestion['title']); ?></h4>
                                        </div>
                                        <span class="suggestion-status status-<?php echo $suggestion['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="ri-pie-chart-line"></i> Status Distribution</h3>
                    </div>
                    <div class="card-content">
                        <div class="status-chart">
                            <div class="status-item">
                                <div class="status-bar">
                                    <div class="status-fill status-pending"
                                        style="width: <?php echo $stats['total_suggestions'] > 0 ? ($stats['pending'] / $stats['total_suggestions']) * 100 : 0; ?>%"></div>
                                </div>
                                <span class="status-label">Pending (<?php echo $stats['pending']; ?>)</span>
                            </div>
                            <div class="status-item">
                                <div class="status-bar">
                                    <div class="status-fill status-new"
                                        style="width: <?php echo $stats['total_suggestions'] > 0 ? ($stats['new'] / $stats['total_suggestions']) * 100 : 0; ?>%"></div>
                                </div>
                                <span class="status-label">New (<?php echo $stats['new']; ?>)</span>
                            </div>
                            <div class="status-item">
                                <div class="status-bar">
                                    <div class="status-fill status-under-review"
                                        style="width: <?php echo $stats['total_suggestions'] > 0 ? ($stats['under_review'] / $stats['total_suggestions']) * 100 : 0; ?>%"></div>
                                </div>
                                <span class="status-label">Under Review (<?php echo $stats['under_review']; ?>)</span>
                            </div>
                            <div class="status-item">
                                <div class="status-bar">
                                    <div class="status-fill status-in_progress"
                                        style="width: <?php echo $stats['total_suggestions'] > 0 ? ($stats['in_progress'] / $stats['total_suggestions']) * 100 : 0; ?>%"></div>
                                </div>
                                <span class="status-label">In Progress (<?php echo $stats['in_progress']; ?>)</span>
                            </div>
                            <div class="status-item">
                                <div class="status-bar">
                                    <div class="status-fill status-implemented"
                                        style="width: <?php echo $stats['total_suggestions'] > 0 ? ($stats['implemented'] / $stats['total_suggestions']) * 100 : 0; ?>%"></div>
                                </div>
                                <span class="status-label">Implemented (<?php echo $stats['implemented']; ?>)</span>
                            </div>
                            <div class="status-item">
                                <div class="status-bar">
                                    <div class="status-fill status-rejected"
                                        style="width: <?php echo $stats['total_suggestions'] > 0 ? ($stats['rejected'] / $stats['total_suggestions']) * 100 : 0; ?>%"></div>
                                </div>
                                <span class="status-label">Rejected (<?php echo $stats['rejected']; ?>)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Statistics -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="ri-bar-chart-line"></i> Categories</h3>
                    </div>
                    <div class="card-content">
                        <?php if (empty($category_stats)): ?>
                            <p class="no-data">No categories yet.</p>
                        <?php else: ?>
                            <div class="category-list">
                                <?php foreach ($category_stats as $category): ?>
                                    <div class="category-item">
                                        <span class="category-name"><?php echo htmlspecialchars($category['category']); ?></span>
                                        <span class="category-count"><?php echo $category['count']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include '../includes/footer.php'; ?>