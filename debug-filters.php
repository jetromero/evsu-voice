<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h2>Debug: Categories and Statuses</h2>";

try {
    // Check categories table
    echo "<h3>Categories Table:</h3>";
    try {
        $categories_query = "SELECT name FROM categories ORDER BY name";
        $categories_stmt = $conn->prepare($categories_query);
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($categories)) {
            echo "❌ Categories table is empty<br>";
        } else {
            echo "✅ Categories found:<br>";
            foreach ($categories as $category) {
                echo "- " . htmlspecialchars($category) . "<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Categories table doesn't exist or error: " . $e->getMessage() . "<br>";

        // Try getting from suggestions
        echo "<h4>Getting categories from suggestions:</h4>";
        $categories_query = "SELECT DISTINCT category FROM suggestions ORDER BY category";
        $categories_stmt = $conn->prepare($categories_query);
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($categories)) {
            echo "❌ No categories found in suggestions<br>";
        } else {
            echo "✅ Categories from suggestions:<br>";
            foreach ($categories as $category) {
                echo "- " . htmlspecialchars($category) . "<br>";
            }
        }
    }

    // Check statuses in suggestions (excluding pending and rejected)
    echo "<h3>Statuses in Suggestions (displayed on Browse page):</h3>";
    $statuses_query = "SELECT DISTINCT status FROM suggestions WHERE status != 'pending' AND status != 'rejected' ORDER BY status";
    $statuses_stmt = $conn->prepare($statuses_query);
    $statuses_stmt->execute();
    $statuses = $statuses_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($statuses)) {
        echo "❌ No statuses found in suggestions<br>";
    } else {
        echo "✅ Statuses found:<br>";
        $status_labels = [
            'new' => 'New',
            'under_review' => 'Under Review',
            'in_progress' => 'In Progress',
            'implemented' => 'Implemented'
        ];
        foreach ($statuses as $status) {
            $display_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst(str_replace('_', ' ', $status));
            echo "- " . htmlspecialchars($status) . " (displayed as: " . htmlspecialchars($display_label) . ")<br>";
        }
    }

    // Check all statuses including rejected
    echo "<h3>All Statuses in Database (including hidden ones):</h3>";
    $all_statuses_query = "SELECT DISTINCT status FROM suggestions ORDER BY status";
    $all_statuses_stmt = $conn->prepare($all_statuses_query);
    $all_statuses_stmt->execute();
    $all_statuses = $all_statuses_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($all_statuses)) {
        echo "❌ No statuses found<br>";
    } else {
        echo "✅ All statuses in database:<br>";
        foreach ($all_statuses as $status) {
            $display_note = ($status === 'pending' || $status === 'rejected') ? " (hidden from Browse page)" : "";
            echo "- " . htmlspecialchars($status) . $display_note . "<br>";
        }
    }

    // Check all suggestions
    echo "<h3>All Suggestions:</h3>";
    $all_suggestions_query = "SELECT id, title, category, status FROM suggestions ORDER BY id";
    $all_suggestions_stmt = $conn->prepare($all_suggestions_query);
    $all_suggestions_stmt->execute();
    $all_suggestions = $all_suggestions_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($all_suggestions)) {
        echo "❌ No suggestions found<br>";
    } else {
        echo "✅ Suggestions found (" . count($all_suggestions) . " total):<br>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Category</th><th>Status</th></tr>";
        foreach ($all_suggestions as $suggestion) {
            echo "<tr>";
            echo "<td>" . $suggestion['id'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($suggestion['title'], 0, 30)) . "...</td>";
            echo "<td>" . htmlspecialchars($suggestion['category']) . "</td>";
            echo "<td>" . htmlspecialchars($suggestion['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='browse-suggestions.php'>← Back to Browse Suggestions</a>";
