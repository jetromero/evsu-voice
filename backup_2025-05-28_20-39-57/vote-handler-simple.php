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
    require_once 'config/database.php';

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
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Start transaction
    $conn->beginTransaction();

    // Check if user already voted
    $check_vote = "SELECT id FROM votes WHERE user_id = ? AND suggestion_id = ?";
    $stmt = $conn->prepare($check_vote);
    $stmt->execute([$user_id, $suggestion_id]);
    $existing_vote = $stmt->fetch();

    if ($action === 'add') {
        if ($existing_vote) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Already voted']);
            exit();
        }

        // Add vote
        $add_vote = "INSERT INTO votes (user_id, suggestion_id) VALUES (?, ?)";
        $stmt = $conn->prepare($add_vote);
        $stmt->execute([$user_id, $suggestion_id]);

        // Update count
        $update_count = "UPDATE suggestions SET upvotes_count = upvotes_count + 1 WHERE id = ?";
        $stmt = $conn->prepare($update_count);
        $stmt->execute([$suggestion_id]);

        $voted = true;
    } else { // remove
        if (!$existing_vote) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Not voted yet']);
            exit();
        }

        // Remove vote
        $remove_vote = "DELETE FROM votes WHERE user_id = ? AND suggestion_id = ?";
        $stmt = $conn->prepare($remove_vote);
        $stmt->execute([$user_id, $suggestion_id]);

        // Update count
        $update_count = "UPDATE suggestions SET upvotes_count = upvotes_count - 1 WHERE id = ?";
        $stmt = $conn->prepare($update_count);
        $stmt->execute([$suggestion_id]);

        $voted = false;
    }

    // Get updated count
    $count_query = "SELECT upvotes_count FROM suggestions WHERE id = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->execute([$suggestion_id]);
    $vote_count = $stmt->fetchColumn();

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'voted' => $voted,
        'vote_count' => (int)$vote_count
    ]);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit();
