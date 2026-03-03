<?php
// Simple test file to check API responses
require_once 'config/session.php';
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Test get_chats
echo "<h2>Testing get_chats API:</h2>";
$query = "SELECT 
          CASE 
            WHEN m.sender_id = :user_id THEN m.receiver_id 
            ELSE m.sender_id 
          END as user_id,
          u.username,
          u.is_online,
          u.last_seen,
          MAX(m.created_at) as last_message_time
          FROM messages m
          JOIN users u ON u.id = CASE 
            WHEN m.sender_id = :user_id2 THEN m.receiver_id 
            ELSE m.sender_id 
          END
          WHERE m.sender_id = :user_id3 OR m.receiver_id = :user_id4
          GROUP BY user_id, u.username, u.is_online, u.last_seen
          ORDER BY last_message_time DESC
          LIMIT 5";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':user_id2', $user_id);
$stmt->bindParam(':user_id3', $user_id);
$stmt->bindParam(':user_id4', $user_id);
$stmt->execute();

$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($chats);
echo "</pre>";

echo "<h2>Current User ID: $user_id</h2>";
echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>
