<?php
require_once 'config/database.php';

echo "<h2>Database Check</h2>";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "<p style='color: red;'>Failed to connect to database!</p>";
    exit();
}

echo "<p style='color: green;'>Database connected successfully!</p>";

// Check if profile_image column exists
try {
    $query = "SHOW COLUMNS FROM users LIKE 'profile_image'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ profile_image column exists in users table</p>";
    } else {
        echo "<p style='color: red;'>✗ profile_image column does NOT exist in users table</p>";
        echo "<p>Run this SQL to add it:</p>";
        echo "<pre>ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL;</pre>";
        
        // Try to add it automatically
        try {
            $alter = "ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL";
            $db->exec($alter);
            echo "<p style='color: green;'>✓ Column added successfully!</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>Failed to add column: " . $e->getMessage() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking column: " . $e->getMessage() . "</p>";
}

// Check if notifications table exists
try {
    $query = "SHOW TABLES LIKE 'notifications'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ notifications table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ notifications table does NOT exist</p>";
        echo "<p>Run the SQL from config/setup.sql to create it</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking table: " . $e->getMessage() . "</p>";
}

// Check friends table status column
try {
    $query = "SHOW COLUMNS FROM friends WHERE Field = 'status'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($column) {
        echo "<p style='color: green;'>✓ friends.status column exists</p>";
        echo "<p>Type: " . $column['Type'] . "</p>";
        
        if (strpos($column['Type'], 'rejected') !== false) {
            echo "<p style='color: green;'>✓ 'rejected' status is available</p>";
        } else {
            echo "<p style='color: orange;'>⚠ 'rejected' status not available. Run this SQL:</p>";
            echo "<pre>ALTER TABLE friends MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending';</pre>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking friends table: " . $e->getMessage() . "</p>";
}

// Check upload directory
$upload_dir = 'uploads/profiles/';
if (file_exists($upload_dir)) {
    echo "<p style='color: green;'>✓ Upload directory exists: $upload_dir</p>";
    if (is_writable($upload_dir)) {
        echo "<p style='color: green;'>✓ Upload directory is writable</p>";
    } else {
        echo "<p style='color: red;'>✗ Upload directory is NOT writable</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Upload directory does NOT exist: $upload_dir</p>";
    if (mkdir($upload_dir, 0777, true)) {
        echo "<p style='color: green;'>✓ Created upload directory</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create upload directory</p>";
    }
}

echo "<hr>";
echo "<p><a href='user/chat.php'>Go to Chat</a> | <a href='test_upload.php'>Test Upload</a></p>";
?>
