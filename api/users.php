<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$database = new Database();
$db = $database->getConnection();

// --- AUTO-OFFLINE CLEANUP ---
// Set users offline if they haven't been seen for more than 2 minutes.
// This runs on every API call to ensure stale online statuses are cleaned up
$cleanup_query = "UPDATE users SET is_online = FALSE WHERE last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE) AND is_online = TRUE";
$db->exec($cleanup_query);

function formatLastSeen($lastSeen)
{
    if (!$lastSeen)
        return ['text' => 'Offline', 'is_online' => false, 'ago_text' => 'Offline'];

    $timestamp = strtotime($lastSeen);
    $diff = time() - $timestamp;

    // User is "Online" if active within the last 2 minutes
    if ($diff < 120)
        return ['text' => 'Online', 'is_online' => true, 'ago_text' => 'Online'];

    // If more than 3 hours, just return 'Offline'
    if ($diff > 3 * 3600)
        return ['text' => 'Offline', 'is_online' => false, 'ago_text' => 'Offline'];

    // Otherwise, show relative time (up to 3 hours)
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return ['text' => "{$mins}min", 'is_online' => false, 'ago_text' => "Online {$mins}min ago"];
    } else {
        $hrs = floor($diff / 3600);
        return ['text' => "{$hrs}hr", 'is_online' => false, 'ago_text' => "Online {$hrs}hr ago"];
    }
}

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

    $query = "SELECT u.id, u.username, u.is_online, u.last_seen, f.status 
              FROM friends f 
              JOIN users u ON (f.friend_id = u.id) 
              WHERE f.user_id = :user_id AND f.status = 'accepted'
              ORDER BY u.is_online DESC, u.last_seen DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($friends as &$friend) {
        $status_info = formatLastSeen($friend['last_seen']);
        $friend['status_text'] = $status_info['text'];
        $friend['ago_text'] = $status_info['ago_text'] ?? $status_info['text'];
        $friend['is_online'] = $status_info['is_online'] ? 1 : 0;
    }

    echo json_encode(['success' => true, 'friends' => $friends]);
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

    $query = "SELECT 
              CASE 
                WHEN m.sender_id = :user_id THEN m.receiver_id 
                ELSE m.sender_id 
              END as user_id,
              u.username,
              u.is_online,
              u.last_seen,
              (SELECT message FROM messages 
               WHERE (sender_id = :user_id2 AND receiver_id = user_id) 
                  OR (sender_id = user_id AND receiver_id = :user_id3)
               ORDER BY created_at DESC LIMIT 1) as last_message,
              (SELECT COUNT(*) FROM messages 
               WHERE sender_id = user_id AND receiver_id = :user_id4 AND is_read = FALSE) as unread_count,
              MAX(m.created_at) as last_message_time
              FROM messages m
              JOIN users u ON u.id = CASE 
                WHEN m.sender_id = :user_id5 THEN m.receiver_id 
                ELSE m.sender_id 
              END
              WHERE m.sender_id = :user_id6 OR m.receiver_id = :user_id7
              GROUP BY user_id, u.username, u.is_online, u.last_seen
              ORDER BY last_message_time DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':user_id2', $user_id);
    $stmt->bindParam(':user_id3', $user_id);
    $stmt->bindParam(':user_id4', $user_id);
    $stmt->bindParam(':user_id5', $user_id);
    $stmt->bindParam(':user_id6', $user_id);
    $stmt->bindParam(':user_id7', $user_id);
    $stmt->execute();

    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chats as &$chat) {
        $status_info = formatLastSeen($chat['last_seen']);
        $chat['status_text'] = $status_info['text'];
        $chat['ago_text'] = $status_info['ago_text'] ?? $status_info['text'];
        $chat['is_online'] = $status_info['is_online'] ? 1 : 0;
    }

    echo json_encode(['success' => true, 'chats' => $chats]);
}

if ($action === 'get_user_info') {
    $other_user_id = $_GET['user_id'] ?? 0;

    $query = "SELECT id, username, profile_image, is_online, last_seen FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $other_user_id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $status_info = formatLastSeen($user['last_seen']);
        $user['status_text'] = $status_info['text'];
        $user['ago_text'] = $status_info['ago_text'] ?? $status_info['text'];
        $user['is_online'] = $status_info['is_online'] ? 1 : 0;
    }
    echo json_encode(['success' => true, 'user' => $user]);
}

if ($action === 'get_message_requests') {
    $user_id = $_SESSION['user_id'];
    
    // Get messages from users who are not friends - GROUP BY to show only ONE request per user
    $query = "SELECT 
              m.sender_id as user_id,
              u.username,
              u.is_online,
              u.last_seen,
              MAX(m.message) as last_message,
              MAX(m.created_at) as created_at
              FROM messages m
              JOIN users u ON m.sender_id = u.id
              WHERE m.receiver_id = :user_id
              AND m.sender_id NOT IN (
                  SELECT friend_id FROM friends 
                  WHERE user_id = :user_id2 AND status = 'accepted'
              )
              AND m.sender_id NOT IN (
                  SELECT user_id FROM friends 
                  WHERE friend_id = :user_id3 AND status = 'accepted'
              )
              AND m.sender_id NOT IN (
                  SELECT friend_id FROM friends 
                  WHERE user_id = :user_id4 AND status = 'pending'
              )
              AND m.sender_id NOT IN (
                  SELECT user_id FROM friends 
                  WHERE friend_id = :user_id5 AND status = 'pending'
              )
              GROUP BY m.sender_id, u.username, u.is_online, u.last_seen
              ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':user_id2', $user_id);
    $stmt->bindParam(':user_id3', $user_id);
    $stmt->bindParam(':user_id4', $user_id);
    $stmt->bindParam(':user_id5', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'delete_message_request') {
    $user_id = $_SESSION['user_id'];
    $sender_id = $_POST['user_id'] ?? 0;
    
    // Delete all messages from this sender
    $query = "DELETE FROM messages WHERE sender_id = :sender_id AND receiver_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sender_id', $sender_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Message request deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete']);
    }
}


if ($action === 'block_user') {
    $user_id = $_SESSION['user_id'];
    $blocked_user_id = $_POST['user_id'] ?? 0;
    
    // Check if already blocked
    $check = "SELECT * FROM blocked_users WHERE user_id = :user_id AND blocked_user_id = :blocked_user_id";
    $stmt_check = $db->prepare($check);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->bindParam(':blocked_user_id', $blocked_user_id);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'User already blocked']);
        exit();
    }
    
    $query = "INSERT INTO blocked_users (user_id, blocked_user_id) VALUES (:user_id, :blocked_user_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':blocked_user_id', $blocked_user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to block user']);
    }
}

if ($action === 'report_user') {
    $user_id = $_SESSION['user_id'];
    $reported_user_id = $_POST['user_id'] ?? 0;
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a reason']);
        exit();
    }
    
    $query = "INSERT INTO user_reports (reporter_id, reported_user_id, reason) VALUES (:reporter_id, :reported_user_id, :reason)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':reporter_id', $user_id);
    $stmt->bindParam(':reported_user_id', $reported_user_id);
    $stmt->bindParam(':reason', $reason);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to report user']);
    }
}
