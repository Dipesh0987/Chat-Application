<?php
// Simple test script to check upload functionality
session_start();

// Set a test user ID (replace with actual user ID for testing)
$_SESSION['user_id'] = 1; // Change this to your actual user ID

echo "<h2>Upload Test Page</h2>";
echo "<p>Session User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";

// Check if uploads directory exists
$upload_dir = 'uploads/profiles/';
if (!file_exists($upload_dir)) {
    echo "<p style='color: orange;'>Upload directory does not exist. Creating...</p>";
    if (mkdir($upload_dir, 0777, true)) {
        echo "<p style='color: green;'>Directory created successfully!</p>";
    } else {
        echo "<p style='color: red;'>Failed to create directory. Check permissions.</p>";
    }
} else {
    echo "<p style='color: green;'>Upload directory exists.</p>";
    echo "<p>Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "</p>";
}

// Check if directory is writable
if (is_writable($upload_dir)) {
    echo "<p style='color: green;'>Upload directory is writable.</p>";
} else {
    echo "<p style='color: red;'>Upload directory is NOT writable. Fix permissions!</p>";
}

// Check PHP upload settings
echo "<h3>PHP Upload Settings:</h3>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";

?>

<h3>Test Upload Form:</h3>
<form action="api/settings.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="upload_profile_image">
    <input type="file" name="profile_image" accept="image/*" required>
    <button type="submit">Upload Test</button>
</form>

<hr>
<p><a href="user/chat.php">Back to Chat</a></p>
