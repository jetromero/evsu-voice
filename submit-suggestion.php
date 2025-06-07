<?php
$page_title = "Submit Suggestion";
require_once 'includes/auth.php';
require_once 'config/database_native.php';

// Start session to handle messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$user = $auth->getCurrentUser();

// Require user to be logged in
if (!$user) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$database = new DatabaseNative();
$conn = $database->getConnection();

// Check for messages from previous redirect
$success = $_SESSION['suggestion_success'] ?? '';
$error = $_SESSION['suggestion_error'] ?? '';
$form_data = $_SESSION['suggestion_form_data'] ?? [];

// Clear messages and form data from session
unset($_SESSION['suggestion_success'], $_SESSION['suggestion_error'], $_SESSION['suggestion_form_data']);

// Get categories
$categories_query = "SELECT name FROM categories ORDER BY id";
$categories_result = $database->query($categories_query);
$categories = [];
if ($categories_result) {
    while ($row = $database->fetchAssoc($categories_result)) {
        $categories[] = $row['name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    // Store form data in case of validation errors
    $form_data = compact('title', 'description', 'category', 'is_anonymous');

    if (empty($title) || empty($description) || empty($category)) {
        $_SESSION['suggestion_error'] = 'Please fill in all required fields.';
        $_SESSION['suggestion_form_data'] = $form_data;
    } elseif (strlen($title) < 5) {
        $_SESSION['suggestion_error'] = 'Title must be at least 5 characters long.';
        $_SESSION['suggestion_form_data'] = $form_data;
    } elseif (strlen($description) < 20) {
        $_SESSION['suggestion_error'] = 'Description must be at least 20 characters long.';
        $_SESSION['suggestion_form_data'] = $form_data;
    } else {
        // Insert suggestion
        $user_id = $user['id']; // Always store user_id, even for anonymous suggestions
        $status = 'pending'; // All suggestions start as pending for admin review

        $query = "INSERT INTO suggestions (user_id, title, description, category, status, is_anonymous) 
                  VALUES ($1, $2, $3, $4, $5, $6)";

        $stmt_result = $database->query($query, [$user_id, $title, $description, $category, $status, $is_anonymous]);

        if ($stmt_result) {
            $_SESSION['suggestion_success'] = 'Your suggestion has been submitted successfully! It will be reviewed before being published.';
            // Don't store form data on success - let form be cleared
        } else {
            $_SESSION['suggestion_error'] = 'Failed to submit suggestion. Please try again.';
            $_SESSION['suggestion_form_data'] = $form_data;
        }
    }

    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

include 'includes/header.php';
?>

<main class="main">
    <section class="submit-section section">
        <div class="container">
            <div class="section-header">
                <h1 class="section__title">Submit Your Suggestion</h1>
                <p class="section__description">
                    Share your ideas to help improve EVSU. Your voice matters in shaping our university's future.
                </p>
            </div>

            <div class="submit-content">
                <div class="submit-form-container">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="ri-check-line"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error submit-suggestion-error">
                            <i class="ri-error-warning-line"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="suggestion-form">
                        <div class="form__group">
                            <label for="title" class="form__label">Suggestion Title *</label>
                            <input type="text" name="title" id="title" class="form__input my"
                                placeholder="Enter a clear, descriptive title for your suggestion"
                                value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>" required>
                            <small class="form__help">Minimum 5 characters</small>
                        </div>

                        <div class="form__group">
                            <label for="category" class="form__label">Category *</label>
                            <select name="category" id="category" class="form__select my" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"
                                        <?php echo (($form_data['category'] ?? '') === $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form__group">
                            <label for="description" class="form__label">Description *</label>
                            <textarea name="description" id="description" class="form__textarea my" rows="6"
                                placeholder="Provide a detailed description of your suggestion. Include why it's important and how it could benefit the EVSU community."
                                required><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                            <small class="form__help">Minimum 20 characters. Be specific and constructive.</small>
                        </div>

                        <div class="form__group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_anonymous" id="is_anonymous" class="form__checkbox"
                                    <?php echo ($form_data['is_anonymous'] ?? false) ? 'checked' : ''; ?>>
                                <label for="is_anonymous" class="checkbox-label">
                                    <i class="ri-eye-off-line"></i>
                                    Submit anonymously
                                </label>
                            </div>
                            <small class="form__help">
                                Your suggestion will be attributed to "Anonymous" instead of your name (<?php echo htmlspecialchars($user['name']); ?>).
                            </small>
                        </div>

                        <div class="form__actions">
                            <button type="submit" class="form__button button">
                                <i class="ri-send-plane-line"></i>
                                Submit Suggestion
                            </button>

                            <button type="reset" class="form__button-secondary">
                                <i class="ri-refresh-line"></i>
                                Clear Form
                            </button>
                        </div>
                    </form>
                </div>

                <div class="submit-info">
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="ri-lightbulb-line"></i>
                            <h3>Tips for Great Suggestions</h3>
                        </div>
                        <ul>
                            <li>Clearly state the problem or opportunity</li>
                            <li>Explain how your idea helps the EVSU community</li>
                            <li>Consider the feasibility and resources required</li>
                            <li>Provide examples or references if applicable</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="ri-shield-check-line"></i>
                            <h3>Review Process</h3>
                        </div>
                        <ul>
                            <li><strong>Pending:</strong> Your suggestion is awaiting admin review</li>
                            <li><strong>New:</strong> Approved and visible to the community</li>
                            <li><strong>Under Review:</strong> Being considered by administration</li>
                            <li><strong>In Progress:</strong> Accepted for implementation</li>
                            <li><strong>Implemented:</strong> Successfully put into action</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="ri-question-line"></i>
                            <h3>Need Help?</h3>
                        </div>
                        <p>If you have questions about submitting suggestions or need assistance, please contact us at:</p>
                        <p><strong>jetvenson.romero@evsu.edu.ph</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
    .form__button.button {
        margin: 0;
    }

    #category.form__select.my,
    #title.form__input.my,
    #description.form__textarea.my {
        background-color: var(--white-color);
        color: var(--text-color);
    }

    .dark-theme #category.form__select.my,
    .dark-theme #title.form__input.my,
    .dark-theme #description.form__textarea.my {
        background-color: var(--container-color);
        color: var(--text-color);
        ;
    }

    .alert.alert-success {
        padding: 0.5rem 1rem;
    }

    .alert.alert-success {
        display: flex;
        align-items: center;
        justify-content: left;
        text-align: center;
        gap: 0.5rem;
    }

    .alert.alert-success i {
        flex-shrink: 0;
    }

    /* Error Alert Styles */
    .alert.alert-error.submit-suggestion-error {
        display: flex;
        align-items: center;
        justify-content: left;
        gap: 0.5rem;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .alert.alert-error.submit-suggestion-error i {
        flex-shrink: 0;
    }

    /* Secondary Button Styles */
    .form__button-secondary {
        padding: 0.75rem 1.5rem;
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        background-color: var(--white-color);
        color: var(--text-color);
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        font-size: 1rem;
        margin-left: 1rem;
    }

    .form__button-secondary:hover {
        background-color: var(--white-color);
        color: var(--evsu-color);
        transform: translateY(-2px);
        border: 1px solid var(--evsu-color);
    }

    .form__button-secondary i {
        font-size: 1.2rem;
    }
</style>

<?php include 'includes/footer.php'; ?>