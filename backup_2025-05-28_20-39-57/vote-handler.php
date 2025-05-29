<?php
// Prevent any output before JSON
ob_start();
session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';

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

if (!$suggestion_id || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    $conn->beginTransaction();

    // Check if user already voted
    $check_vote = "SELECT id FROM votes WHERE user_id = ? AND suggestion_id = ?";
    $stmt = $conn->prepare($check_vote);
    $stmt->execute([$user['id'], $suggestion_id]);
    $existing_vote = $stmt->fetch();

    if ($action === 'add') {
        if ($existing_vote) {
            echo json_encode(['success' => false, 'message' => 'You have already voted for this suggestion']);
            exit();
        }

        // Add vote
        $add_vote = "INSERT INTO votes (user_id, suggestion_id) VALUES (?, ?)";
        $stmt = $conn->prepare($add_vote);
        $stmt->execute([$user['id'], $suggestion_id]);

        // Update upvotes count
        $update_count = "UPDATE suggestions SET upvotes_count = upvotes_count + 1 WHERE id = ?";
        $stmt = $conn->prepare($update_count);
        $stmt->execute([$suggestion_id]);

        $voted = true;
    } else { // remove
        if (!$existing_vote) {
            echo json_encode(['success' => false, 'message' => 'You have not voted for this suggestion']);
            exit();
        }

        // Remove vote
        $remove_vote = "DELETE FROM votes WHERE user_id = ? AND suggestion_id = ?";
        $stmt = $conn->prepare($remove_vote);
        $stmt->execute([$user['id'], $suggestion_id]);

        // Update upvotes count
        $update_count = "UPDATE suggestions SET upvotes_count = upvotes_count - 1 WHERE id = ?";
        $stmt = $conn->prepare($update_count);
        $stmt->execute([$suggestion_id]);

        $voted = false;
    }

    // Get updated vote count
    $count_query = "SELECT upvotes_count FROM suggestions WHERE id = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->execute([$suggestion_id]);
    $vote_count = $stmt->fetchColumn();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'voted' => $voted,
        'vote_count' => (int)$vote_count
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// Ensure no extra output
exit();
