<?php
// Start output buffering to prevent any accidental output
ob_start();

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(0);

session_start();

// Clear any output that might have been generated
ob_clean();

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Include files
    require_once 'config/database_native.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }

    // Check if user is a student
    if ($_SESSION['user_role'] !== 'student') {
        echo json_encode(['success' => false, 'message' => 'Only students can vote']);
        exit();
    }

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    }

    // Get parameters
    $suggestion_id = (int)($_POST['suggestion_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (!$suggestion_id || !in_array($action, ['add', 'remove'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }

    // Database connection
    $database = new DatabaseNative();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    if ($action === 'add') {
        // Try to insert vote, ignore if already exists
        $add_vote = "INSERT INTO votes (user_id, suggestion_id) VALUES ($1, $2) ON CONFLICT (user_id, suggestion_id) DO NOTHING";
        $result = $database->query($add_vote, [$user_id, $suggestion_id]);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Failed to add vote']);
            exit();
        }

        // Check if the vote was actually inserted (not a conflict)
        $affected_rows = pg_affected_rows($result);
        if ($affected_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Already voted']);
            exit();
        }

        $voted = true;
    } else { // remove
        // Try to delete vote
        $remove_vote = "DELETE FROM votes WHERE user_id = $1 AND suggestion_id = $2";
        $result = $database->query($remove_vote, [$user_id, $suggestion_id]);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Failed to remove vote']);
            exit();
        }

        // Check if any row was actually deleted
        $affected_rows = pg_affected_rows($result);
        if ($affected_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Not voted yet']);
            exit();
        }

        $voted = false;
    }

    // Get updated count
    $count_query = "SELECT upvotes_count FROM suggestions WHERE id = $1";
    $result = $database->query($count_query, [$suggestion_id]);
    $suggestion_data = $database->fetchAssoc($result);
    $vote_count = $suggestion_data['upvotes_count'];

    // Return success response
    echo json_encode([
        'success' => true,
        'voted' => $voted,
        'vote_count' => (int)$vote_count
    ]);
} catch (Exception $e) {
    if (isset($conn)) {
        pg_query($conn, "ROLLBACK");
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit();
