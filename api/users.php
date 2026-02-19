<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$database = new Database();
$db = $database->getConnection();

if ($action === 'search') {
    $search = trim($_GET['search'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT id, username FROM users WHERE username LIKE :search AND id != :user_id LIMIT 20";
    $stmt = $db->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'get_friends') {
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT u.id, u.username, f.status FROM friends f 
              JOIN users u ON (f.friend_id = u.id) 
              WHERE f.user_id = :user_id AND f.status = 'accepted'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'friends' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'send_friend_request') {
    $user_id = $_SESSION['user_id'];
    $friend_id = $_POST['friend_id'] ?? 0;
    
    // Check if request already exists
    $check = "SELECT * FROM friends WHERE (user_id = :user_id AND friend_id = :friend_id) OR (user_id = :friend_id2 AND friend_id = :user_id2)";
    $stmt_check = $db->prepare($check);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->bindParam(':friend_id', $friend_id);
    $stmt_check->bindParam(':user_id2', $user_id);
    $stmt_check->bindParam(':friend_id2', $friend_id);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Friend request already exists']);
        exit();
    }
    
    $query = "INSERT INTO friends (user_id, friend_id, status) VALUES (:user_id, :friend_id, 'pending')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':friend_id', $friend_id);
    
    if ($stmt->execute()) {
        // Create notification
        $notif = "INSERT INTO notifications (user_id, type, from_user_id) VALUES (:user_id, 'friend_request', :from_user_id)";
        $stmt_notif = $db->prepare($notif);
        $stmt_notif->bindParam(':user_id', $friend_id);
        $stmt_notif->bindParam(':from_user_id', $user_id);
        $stmt_notif->execute();
        
        echo json_encode(['success' => true, 'message' => 'Friend request sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send request']);
    }
}

if ($action === 'accept_friend_request') {
    $user_id = $_SESSION['user_id'];
    $friend_id = $_POST['friend_id'] ?? 0;
    
    // Update request status
    $query = "UPDATE friends SET status = 'accepted' WHERE user_id = :friend_id AND friend_id = :user_id AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':friend_id', $friend_id);
    
    if ($stmt->execute() && $stmt->rowCount() > 0) {
        // Add reverse friendship
        $query2 = "INSERT INTO friends (user_id, friend_id, status) VALUES (:user_id, :friend_id, 'accepted')";
        $stmt2 = $db->prepare($query2);
        $stmt2->bindParam(':user_id', $user_id);
        $stmt2->bindParam(':friend_id', $friend_id);
        $stmt2->execute();
        
        // Create notification
        $notif = "INSERT INTO notifications (user_id, type, from_user_id) VALUES (:user_id, 'friend_accepted', :from_user_id)";
        $stmt_notif = $db->prepare($notif);
        $stmt_notif->bindParam(':user_id', $friend_id);
        $stmt_notif->bindParam(':from_user_id', $user_id);
        $stmt_notif->execute();
        
        echo json_encode(['success' => true, 'message' => 'Friend request accepted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }
}

if ($action === 'reject_friend_request') {
    $user_id = $_SESSION['user_id'];
    $friend_id = $_POST['friend_id'] ?? 0;
    
    $query = "DELETE FROM friends WHERE user_id = :friend_id AND friend_id = :user_id AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':friend_id', $friend_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend request rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject request']);
    }
}

if ($action === 'get_friend_requests') {
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT f.id, u.id as user_id, u.username, f.created_at 
              FROM friends f 
              JOIN users u ON f.user_id = u.id 
              WHERE f.friend_id = :user_id AND f.status = 'pending'
              ORDER BY f.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'get_chats') {
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT DISTINCT 
              CASE 
                WHEN m.sender_id = :user_id THEN m.receiver_id 
                ELSE m.sender_id 
              END as user_id,
              u.username,
              (SELECT message FROM messages 
               WHERE (sender_id = :user_id2 AND receiver_id = user_id) 
                  OR (sender_id = user_id AND receiver_id = :user_id3)
               ORDER BY created_at DESC LIMIT 1) as last_message
              FROM messages m
              JOIN users u ON u.id = CASE 
                WHEN m.sender_id = :user_id4 THEN m.receiver_id 
                ELSE m.sender_id 
              END
              WHERE m.sender_id = :user_id5 OR m.receiver_id = :user_id6
              ORDER BY m.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':user_id2', $user_id);
    $stmt->bindParam(':user_id3', $user_id);
    $stmt->bindParam(':user_id4', $user_id);
    $stmt->bindParam(':user_id5', $user_id);
    $stmt->bindParam(':user_id6', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'chats' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
?>
