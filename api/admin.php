<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$database = new Database();
$db = $database->getConnection();

if ($action === 'get_users') {
    $query = "SELECT id, username, email, profile_image, warnings, is_banned, ban_until, created_at 
              FROM users 
              ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'users' => $users]);
}

if ($action === 'warn_user') {
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    
    // Get current warnings
    $query = "SELECT warnings, username FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $new_warnings = $user['warnings'] + 1;
    
    // Update warnings
    $update = "UPDATE users SET warnings = :warnings WHERE id = :user_id";
    $stmt_update = $db->prepare($update);
    $stmt_update->bindParam(':warnings', $new_warnings);
    $stmt_update->bindParam(':user_id', $user_id);
    $stmt_update->execute();
    
    // Create notification for user
    $admin_id = $_SESSION['user_id'];
    $notif_message = "You have received a warning from admin. Total warnings: {$new_warnings}/3";
    
    $notif = "INSERT INTO notifications (user_id, type, from_user_id, message) 
              VALUES (:user_id, 'warning', :from_user_id, :message)";
    $stmt_notif = $db->prepare($notif);
    $stmt_notif->bindParam(':user_id', $user_id);
    $stmt_notif->bindParam(':from_user_id', $admin_id);
    $stmt_notif->bindParam(':message', $notif_message);
    $stmt_notif->execute();
    
    // Check if user should be auto-banned (3 warnings)
    if ($new_warnings >= 3) {
        $ban_until = date('Y-m-d H:i:s', strtotime('+7 days'));
        $ban_query = "UPDATE users SET is_banned = TRUE, ban_until = :ban_until WHERE id = :user_id";
        $stmt_ban = $db->prepare($ban_query);
        $stmt_ban->bindParam(':ban_until', $ban_until);
        $stmt_ban->bindParam(':user_id', $user_id);
        $stmt_ban->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => "User warned. Total warnings: {$new_warnings}. User has been auto-banned for 7 days."
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => "User warned successfully. Total warnings: {$new_warnings}/3"
        ]);
    }
}

if ($action === 'ban_user') {
    $user_id = $_POST['user_id'] ?? 0;
    $days = $_POST['days'] ?? 3;
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    
    $ban_until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    
    $query = "UPDATE users SET is_banned = TRUE, ban_until = :ban_until WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':ban_until', $ban_until);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        // Create notification
        $admin_id = $_SESSION['user_id'];
        $notif_message = "Your account has been banned until {$ban_until}";
        
        $notif = "INSERT INTO notifications (user_id, type, from_user_id, message) 
                  VALUES (:user_id, 'warning', :from_user_id, :message)";
        $stmt_notif = $db->prepare($notif);
        $stmt_notif->bindParam(':user_id', $user_id);
        $stmt_notif->bindParam(':from_user_id', $admin_id);
        $stmt_notif->bindParam(':message', $notif_message);
        $stmt_notif->execute();
        
        echo json_encode(['success' => true, 'message' => "User banned until {$ban_until}"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to ban user']);
    }
}

if ($action === 'unban_user') {
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    
    $query = "UPDATE users SET is_banned = FALSE, ban_until = NULL WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User unbanned successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unban user']);
    }
}

if ($action === 'delete_user') {
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    
    // Delete user (cascade will handle related records)
    $query = "DELETE FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
}

if ($action === 'get_stats') {
    // Get statistics
    $stats = [];
    
    // Total users
    $query = "SELECT COUNT(*) as total FROM users";
    $stmt = $db->query($query);
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Banned users
    $query = "SELECT COUNT(*) as total FROM users WHERE is_banned = TRUE";
    $stmt = $db->query($query);
    $stats['banned_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total messages
    $query = "SELECT COUNT(*) as total FROM messages";
    $stmt = $db->query($query);
    $stats['total_messages'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Online users
    $query = "SELECT COUNT(*) as total FROM users WHERE is_online = TRUE";
    $stmt = $db->query($query);
    $stats['online_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}
?>
