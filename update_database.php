<?php
require_once 'config/database.php';

echo "<h2>Database Update Script</h2>";
echo "<p>Running database updates...</p>";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "<p style='color: red;'>Failed to connect to database!</p>";
    exit();
}

$updates = [];

// Update notifications table to include 'warning' type
try {
    $query = "SHOW COLUMNS FROM notifications WHERE Field = 'type'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column && strpos($column['Type'], 'warning') === false) {
        $db->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM('friend_request', 'friend_accepted', 'message', 'warning') NOT NULL");
        $updates[] = "✓ Added 'warning' type to notifications table";
    } else {
        $updates[] = "✓ Notifications table already has 'warning' type";
    }
} catch (Exception $e) {
    $updates[] = "✗ Error updating notifications: " . $e->getMessage();
}

// Add index to messages for unread queries
try {
    $query = "SHOW INDEX FROM messages WHERE Key_name = 'idx_unread'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE messages ADD INDEX idx_unread (receiver_id, is_read)");
        $updates[] = "✓ Added index for unread messages";
    } else {
        $updates[] = "✓ Unread messages index already exists";
    }
} catch (Exception $e) {
    $updates[] = "✗ Error adding index: " . $e->getMessage();
}

// Display results
echo "<h3>Update Results:</h3>";
echo "<ul>";
foreach ($updates as $update) {
    $color = strpos($update, '✓') !== false ? 'green' : 'red';
    echo "<li style='color: $color;'>$update</li>";
}
echo "</ul>";

echo "<hr>";
echo "<p><a href='user/chat.php'>Go to Chat</a> | <a href='admin/dashboard.php'>Admin Dashboard</a></p>";
?>
