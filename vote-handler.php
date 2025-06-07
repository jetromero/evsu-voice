<?php
// Prevent any output before JSON
ob_start();
session_start();
require_once 'includes/auth.php';
require_once 'config/database_native.php';

// Clear any output that might have been generated
ob_clean();
header('Content-Type: application/json');

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(0);

try {
    $auth = new Auth();
    $user = $auth->getCurrentUser();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication error']);
    exit();
}

// Check if user is logged in and is a student
if (!$user || $user['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$suggestion_id = (int)($_POST['suggestion_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$suggestion_id || !in_array($action, ['upvote', 'remove_vote'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $database = new DatabaseNative();
    $conn = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    pg_query($conn, "BEGIN");

    if ($action === 'upvote') {
        // Check if user already voted
        $check_vote = "SELECT id FROM votes WHERE user_id = $1 AND suggestion_id = $2";
        $vote_result = $database->query($check_vote, [$user['id'], $suggestion_id]);

        if ($database->numRows($vote_result) > 0) {
            pg_query($conn, "ROLLBACK");
            echo json_encode(['success' => false, 'message' => 'You have already voted on this suggestion']);
            exit();
        }

        // Add vote
        $add_vote = "INSERT INTO votes (user_id, suggestion_id) VALUES ($1, $2)";
        $database->query($add_vote, [$user['id'], $suggestion_id]);

        // Update suggestion vote count
        $update_count = "UPDATE suggestions SET upvotes_count = upvotes_count + 1 WHERE id = $1";
        $database->query($update_count, [$suggestion_id]);

        $message = 'Vote added successfully';
    } else { // remove_vote
        // Check if user has voted
        $check_vote = "SELECT id FROM votes WHERE user_id = $1 AND suggestion_id = $2";
        $vote_result = $database->query($check_vote, [$user['id'], $suggestion_id]);

        if ($database->numRows($vote_result) === 0) {
            pg_query($conn, "ROLLBACK");
            echo json_encode(['success' => false, 'message' => 'You have not voted on this suggestion']);
            exit();
        }

        // Remove vote
        $remove_vote = "DELETE FROM votes WHERE user_id = $1 AND suggestion_id = $2";
        $database->query($remove_vote, [$user['id'], $suggestion_id]);

        // Update suggestion vote count
        $update_count = "UPDATE suggestions SET upvotes_count = upvotes_count - 1 WHERE id = $1";
        $database->query($update_count, [$suggestion_id]);

        $message = 'Vote removed successfully';
    }

    // Get updated vote count
    $count_query = "SELECT upvotes_count FROM suggestions WHERE id = $1";
    $count_result = $database->query($count_query, [$suggestion_id]);
    $suggestion = $database->fetchAssoc($count_result);
    $new_count = $suggestion ? $suggestion['upvotes_count'] : 0;

    pg_query($conn, "COMMIT");

    echo json_encode([
        'success' => true,
        'message' => $message,
        'new_count' => $new_count,
        'action' => $action
    ]);
} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    error_log("Vote handler error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your vote']);
}

// Ensure no extra output
exit();
