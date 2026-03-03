<?php
session_start();

// Set a test user ID (change this to your actual user ID)
if (!isset($_SESSION['user_id'])) {
    echo "Please login first, then run this test.<br>";
    echo "Or manually set user_id: ";
    $_SESSION['user_id'] = 1; // Change this to your user ID
    echo "Set to user_id = 1<br><br>";
}

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

echo "<h2>Testing Friends API for User ID: $user_id</h2>";

// Test 1: Check friends table
echo "<h3>1. Friends in Database:</h3>";
$query = "SELECT * FROM friends WHERE user_id = :user_id OR friend_id = :user_id2";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':user_id2', $user_id);
$stmt->execute();
$all_friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($all_friends);
echo "</pre>";

// Test 2: Check accepted friends
echo "<h3>2. Accepted Friends Query:</h3>";
$query = "SELECT u.id, u.username, u.is_online, u.last_seen, f.status 
          FROM friends f 
          JOIN users u ON (f.friend_id = u.id) 
          WHERE f.user_id = :user_id AND f.status = 'accepted'
          ORDER BY u.is_online DESC, u.last_seen DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($friends);
echo "</pre>";

// Test 3: Check users table
echo "<h3>3. All Users:</h3>";
$query = "SELECT id, username, is_online FROM users";
$stmt = $db->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($users);
echo "</pre>";

// Test 4: Test search
echo "<h3>4. Search Test (searching for 'a'):</h3>";
$search = '%a%';
$query = "SELECT id, username FROM users WHERE username LIKE :search AND id != :user_id LIMIT 20";
$stmt = $db->prepare($query);
$stmt->bindParam(':search', $search);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($search_results);
echo "</pre>";

echo "<hr>";
echo "<p><strong>If you see data above, the database is working correctly.</strong></p>";
echo "<p>If friends list is empty in the app but shows data here, it's a JavaScript issue.</p>";
echo "<p>Check browser console (F12) for errors.</p>";
?>
