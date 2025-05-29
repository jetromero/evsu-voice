<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h2>Database Connection Test</h2>";

try {
    // Test connection
    echo "✅ Database connection successful<br>";

    // Check if votes table exists
    $result = $conn->query("SHOW TABLES LIKE 'votes'");
    if ($result->rowCount() > 0) {
        echo "✅ Votes table exists<br>";

        // Check votes table structure
        $result = $conn->query("DESCRIBE votes");
        echo "<h3>Votes table structure:</h3>";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
        }
    } else {
        echo "❌ Votes table does not exist<br>";
        echo "Creating votes table...<br>";

        $create_votes = "
        CREATE TABLE votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            suggestion_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_vote (user_id, suggestion_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE
        )";

        if ($conn->exec($create_votes)) {
            echo "✅ Votes table created successfully<br>";
        } else {
            echo "❌ Failed to create votes table<br>";
        }
    }

    // Check if suggestions table exists
    $result = $conn->query("SHOW TABLES LIKE 'suggestions'");
    if ($result->rowCount() > 0) {
        echo "✅ Suggestions table exists<br>";
    } else {
        echo "❌ Suggestions table does not exist<br>";
    }

    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() > 0) {
        echo "✅ Users table exists<br>";
    } else {
        echo "❌ Users table does not exist<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='browse-suggestions.php'>← Back to Browse Suggestions</a>";
