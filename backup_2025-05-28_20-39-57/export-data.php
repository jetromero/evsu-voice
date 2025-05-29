<?php
$page_title = "Export Data";
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/GoogleSheetsService.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new Database();
$conn = $database->getConnection();

// Initialize Google Sheets service
$googleSheetsAvailable = false;
$googleSheetsError = '';
try {
    $googleSheetsService = new GoogleSheetsService();
    $googleSheetsAvailable = $googleSheetsService->isConfigured();
} catch (Exception $e) {
    $googleSheetsError = $e->getMessage();
}

// Handle export request
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

    // Build query
    $where_conditions = [];
    $params = [];

    if ($status_filter) {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
    }

    if ($category_filter) {
        $where_conditions[] = "s.category = ?";
        $params[] = $category_filter;
    }

    if ($date_from) {
        $where_conditions[] = "DATE(s.created_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $where_conditions[] = "DATE(s.created_at) <= ?";
        $params[] = $date_to;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $query = "SELECT 
                s.id,
                s.title,
                s.description,
                s.category,
                s.status,
                s.is_anonymous,
                s.upvotes_count,
                s.admin_response,
                s.created_at,
                s.updated_at,
                CASE WHEN s.is_anonymous = 1 THEN 'Anonymous' 
                     ELSE CONCAT(u.first_name, ' ', u.last_name) END as author_name,
                u.email as author_email,
                CONCAT(admin.first_name, ' ', admin.last_name) as admin_name
              FROM suggestions s 
              LEFT JOIN users u ON s.user_id = u.id 
              LEFT JOIN users admin ON s.admin_id = admin.id
              $where_clause
              ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($export_type === 'csv') {
        // Generate CSV
        $filename = 'evsu_voice_suggestions_' . date('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, [
            'ID',
            'Title',
            'Description',
            'Category',
            'Status',
            'Anonymous',
            'Author Name',
            'Author Email',
            'Upvotes',
            'EVSU Response',
            'Admin Name',
            'Created Date',
            'Updated Date'
        ]);

        // CSV Data
        foreach ($suggestions as $suggestion) {
            fputcsv($output, [
                $suggestion['id'],
                $suggestion['title'],
                $suggestion['description'],
                $suggestion['category'],
                ucfirst(str_replace('_', ' ', $suggestion['status'])),
                $suggestion['is_anonymous'] ? 'Yes' : 'No',
                $suggestion['author_name'],
                $suggestion['is_anonymous'] ? '' : $suggestion['author_email'],
                $suggestion['upvotes_count'],
                $suggestion['admin_response'],
                $suggestion['admin_name'],
                date('Y-m-d H:i:s', strtotime($suggestion['created_at'])),
                date('Y-m-d H:i:s', strtotime($suggestion['updated_at']))
            ]);
        }

        fclose($output);
        exit();
    } elseif ($export_type === 'sheets' && $googleSheetsAvailable) {
        // Generate Google Sheets
        try {
            $data = $googleSheetsService->prepareDataFromSuggestions($suggestions);

            // Debug: Check if data is properly formatted
            if (empty($data)) {
                throw new Exception("No data to export. Please check your filters or ensure there are suggestions in the database.");
            }

            // Create descriptive title
            $title_parts = ['EVSU Voice Suggestions'];
            if ($status_filter) $title_parts[] = ucfirst(str_replace('_', ' ', $status_filter));
            if ($category_filter) $title_parts[] = $category_filter;
            if ($date_from || $date_to) {
                $date_range = '';
                if ($date_from) $date_range .= $date_from;
                if ($date_from && $date_to) $date_range .= ' to ';
                if ($date_to) $date_range .= $date_to;
                if ($date_range) $title_parts[] = $date_range;
            }
            $title_parts[] = date('Y-m-d H-i-s');

            $title = implode(' - ', $title_parts);

            // Log attempt
            error_log("Attempting to create Google Sheet: " . $title . " with " . count($data) . " rows");

            $result = $googleSheetsService->createSpreadsheet($title, $data);

            if ($result['success']) {
                $sheets_success_url = $result['url'];
                $sheets_success_title = $result['title'];
                error_log("Successfully created Google Sheet: " . $sheets_success_url);
            } else {
                $sheets_error = $result['error'];
                error_log("Failed to create Google Sheet: " . $sheets_error);
            }
        } catch (Exception $e) {
            $sheets_error = $e->getMessage();
            error_log("Exception during Google Sheets export: " . $sheets_error);
        }
    }
}

// Get statistics for the page
$stats_query = "SELECT 
                COUNT(*) as total_suggestions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'implemented' THEN 1 ELSE 0 END) as implemented,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM suggestions";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get categories
$categories_query = "SELECT DISTINCT category FROM suggestions ORDER BY category";
$categories_stmt = $conn->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>

<main class="main">
    <section class="export-data section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title">Reports</h1>
                <p class="section__description">
                    Generate and download spreadsheet reports of suggestions data for analysis and reporting.
                </p>
            </div>

            <?php if (isset($sheets_success_url)): ?>
                <div class="alert alert-success">
                    <i class="ri-checkbox-circle-line"></i>
                    <div>
                        <strong>Google Sheet Created Successfully!</strong>
                        <p>Your spreadsheet "<?php echo htmlspecialchars($sheets_success_title); ?>" has been created.</p>
                        <a href="<?php echo htmlspecialchars($sheets_success_url); ?>" target="_blank" class="button button-small">
                            <i class="ri-external-link-line"></i> Open Google Sheet
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($sheets_error)): ?>
                <div class="alert alert-error">
                    <i class="ri-error-warning-line"></i>
                    <div>
                        <strong>Google Sheets Export Failed</strong>
                        <p><?php echo htmlspecialchars($sheets_error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$googleSheetsAvailable && !empty($googleSheetsError)): ?>
                <div class="alert alert-warning">
                    <i class="ri-information-line"></i>
                    <div>
                        <strong>Google Sheets Not Available</strong>
                        <p><?php echo htmlspecialchars($googleSheetsError); ?></p>
                        <p>Please configure Google Sheets API to enable direct export to Google Sheets.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Export Form -->
            <div class="export-form-container">
                <div class="export-card">
                    <div class="card-header">
                        <h3><i class="ri-download-line"></i> Generate Report</h3>
                        <p>Customize your export by selecting filters below</p>
                    </div>

                    <form method="GET" class="export-form" id="exportForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="status" class="form__label">Status Filter</label>
                                <select name="status" id="status" class="form__select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="new" <?php echo (isset($_GET['status']) && $_GET['status'] === 'new') ? 'selected' : ''; ?>>New</option>
                                    <option value="under_review" <?php echo (isset($_GET['status']) && $_GET['status'] === 'under_review') ? 'selected' : ''; ?>>Under Review</option>
                                    <option value="in_progress" <?php echo (isset($_GET['status']) && $_GET['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="implemented" <?php echo (isset($_GET['status']) && $_GET['status'] === 'implemented') ? 'selected' : ''; ?>>Implemented</option>
                                    <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="category" class="form__label">Category Filter</label>
                                <select name="category" id="category" class="form__select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo (isset($_GET['category']) && $_GET['category'] === $category) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="date_from" class="form__label">From Date</label>
                                <input type="date" name="date_from" id="date_from" class="form__input" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="date_to" class="form__label">To Date</label>
                                <input type="date" name="date_to" id="date_to" class="form__input" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                            </div>
                        </div>

                        <div class="export-actions">
                            <button type="button" onclick="exportData('csv')" class="button export-button">
                                <i class="ri-download-line"></i>
                                Download CSV Report
                            </button>

                            <?php if ($googleSheetsAvailable): ?>
                                <button type="button" onclick="exportData('sheets')" class="button export-button export-button-sheets">
                                    <i class="ri-google-line"></i>
                                    Create Google Sheet
                                </button>
                            <?php else: ?>
                                <button type="button" class="button export-button export-button-disabled" disabled title="Google Sheets not configured">
                                    <i class="ri-google-line"></i>
                                    Create Google Sheet
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Export Information -->
                <div class="export-info">
                    <div class="info-card">
                        <h4><i class="ri-information-line"></i> Export Information</h4>
                        <ul>
                            <li><strong>Formats:</strong> CSV (Download) & Google Sheets (Online)</li>
                            <li><strong>Encoding:</strong> UTF-8</li>
                            <li><strong>Includes:</strong> All suggestion data, author information, votes, and EVSU responses</li>
                            <li><strong>Privacy:</strong> Anonymous submissions show "Anonymous" for author details</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <h4><i class="ri-file-list-line"></i> Data Fields</h4>
                        <div class="fields-grid">
                            <span class="field-tag">ID</span>
                            <span class="field-tag">Title</span>
                            <span class="field-tag">Description</span>
                            <span class="field-tag">Category</span>
                            <span class="field-tag">Status</span>
                            <span class="field-tag">Author</span>
                            <span class="field-tag">Upvotes</span>
                            <span class="field-tag">Response</span>
                            <span class="field-tag">Dates</span>
                        </div>
                    </div>

                    <div class="info-card export">
                        <h4><i class="ri-shield-check-line"></i> Data Usage Guidelines</h4>
                        <ul>
                            <li>Exported data should be handled according to university privacy policies</li>
                            <li>Do not share personal information from non-anonymous suggestions</li>
                            <li>Use data for institutional improvement and reporting purposes only</li>
                            <li>Ensure secure storage and disposal of exported files</li>
                        </ul>
                    </div>

                    <?php if ($googleSheetsAvailable): ?>
                        <div class="info-card">
                            <h4><i class="ri-google-line"></i> Google Sheets Features</h4>
                            <ul>
                                <li>Automatically formatted headers with colors</li>
                                <li>Auto-resized columns for better readability</li>
                                <li>Shared with view access for collaboration</li>
                                <li>Data updates in real-time if re-exported</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Export Buttons -->
            <div class="quick-exports">
                <h3>Quick Exports</h3>
                <div class="quick-export-buttons">
                    <div class="quick-export-group">
                        <h4>CSV Downloads</h4>
                        <div class="export-buttons-row">
                            <a href="?export=csv&status=pending" class="quick-export-btn">
                                <i class="ri-time-line"></i>
                                <span>Pending Suggestions</span>
                                <small><?php echo $stats['pending']; ?> items</small>
                            </a>

                            <a href="?export=csv&status=implemented" class="quick-export-btn">
                                <i class="ri-checkbox-circle-line"></i>
                                <span>Implemented Suggestions</span>
                                <small><?php echo $stats['implemented']; ?> items</small>
                            </a>

                            <a href="?export=csv&date_from=<?php echo date('Y-m-01'); ?>" class="quick-export-btn">
                                <i class="ri-calendar-line"></i>
                                <span>This Month</span>
                                <small>Current month data</small>
                            </a>

                            <a href="?export=csv" class="quick-export-btn">
                                <i class="ri-download-line"></i>
                                <span>All Data</span>
                                <small><?php echo $stats['total_suggestions']; ?> items</small>
                            </a>
                        </div>
                    </div>

                    <?php if ($googleSheetsAvailable): ?>
                        <div class="quick-export-group">
                            <h4>Google Sheets</h4>
                            <div class="export-buttons-row">
                                <a href="?export=sheets&status=pending" class="quick-export-btn sheets-btn">
                                    <i class="ri-google-line"></i>
                                    <span>Pending Suggestions</span>
                                    <small><?php echo $stats['pending']; ?> items</small>
                                </a>

                                <a href="?export=sheets&status=implemented" class="quick-export-btn sheets-btn">
                                    <i class="ri-google-line"></i>
                                    <span>Implemented Suggestions</span>
                                    <small><?php echo $stats['implemented']; ?> items</small>
                                </a>

                                <a href="?export=sheets&date_from=<?php echo date('Y-m-01'); ?>" class="quick-export-btn sheets-btn">
                                    <i class="ri-google-line"></i>
                                    <span>This Month</span>
                                    <small>Current month data</small>
                                </a>

                                <a href="?export=sheets" class="quick-export-btn sheets-btn">
                                    <i class="ri-google-line"></i>
                                    <span>All Data</span>
                                    <small><?php echo $stats['total_suggestions']; ?> items</small>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    function exportData(type) {
        const form = document.getElementById('exportForm');
        const formData = new FormData(form);

        // Build URL with current form data
        const params = new URLSearchParams();
        params.append('export', type);

        for (let [key, value] of formData.entries()) {
            if (value) {
                params.append(key, value);
            }
        }

        if (type === 'sheets') {
            // For Google Sheets, stay on page to show result
            window.location.href = '?' + params.toString();
        } else {
            // For CSV, open in new tab/window for download
            window.open('?' + params.toString(), '_blank');
        }
    }

    // Add loading states for Google Sheets export
    document.addEventListener('DOMContentLoaded', function() {
        const sheetsButtons = document.querySelectorAll('.sheets-btn, .export-button-sheets');

        sheetsButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.classList.contains('export-button-sheets')) {
                    e.preventDefault();
                    exportData('sheets');
                    return;
                }

                // Add loading state for quick export buttons
                this.style.opacity = '0.7';
                this.style.pointerEvents = 'none';

                const originalContent = this.innerHTML;
                this.innerHTML = '<i class="ri-loader-line"></i><span>Creating Sheet...</span><small>Please wait</small>';

                // Reset after a delay if user navigates back
                setTimeout(() => {
                    this.innerHTML = originalContent;
                    this.style.opacity = '1';
                    this.style.pointerEvents = 'auto';
                }, 10000);
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>