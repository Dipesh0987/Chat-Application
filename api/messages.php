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
$user_id = $_SESSION['user_id'];

if ($action === 'send') {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit();
    }
    
    $query = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (:sender_id, :receiver_id, :message)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sender_id', $user_id);
    $stmt->bindParam(':receiver_id', $receiver_id);
    $stmt->bindParam(':message', $message);
    
    if ($stmt->execute()) {
        // Create notification for receiver
        $notif = "INSERT INTO notifications (user_id, type, from_user_id, message) VALUES (:user_id, 'message', :from_user_id, :message)";
        $stmt_notif = $db->prepare($notif);
        $stmt_notif->bindParam(':user_id', $receiver_id);
        $stmt_notif->bindParam(':from_user_id', $user_id);
        $stmt_notif->bindParam(':message', $message);
        $stmt_notif->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

if ($action === 'get') {
    $other_user_id = $_GET['user_id'] ?? 0;
    
    $query = "SELECT m.*, u.username as sender_name 
              FROM messages m 
              JOIN users u ON m.sender_id = u.id
              WHERE (m.sender_id = :user_id AND m.receiver_id = :other_user_id) 
                 OR (m.sender_id = :other_user_id2 AND m.receiver_id = :user_id2)
              ORDER BY m.created_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':user_id2', $user_id);
    $stmt->bindParam(':other_user_id', $other_user_id);
    $stmt->bindParam(':other_user_id2', $other_user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
?>
