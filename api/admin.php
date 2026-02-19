<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$database = new Database();
$db = $database->getConnection();

if ($action === 'get_users') {
    $query = "SELECT id, username, email, profile_image, is_banned, ban_until, warnings, created_at FROM users ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'delete_user') {
    $user_id = $_POST['user_id'] ?? 0;
    
    $query = "DELETE FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
}

if ($action === 'warn_user') {
    $user_id = $_POST['user_id'] ?? 0;
    $admin_id = $_SESSION['user_id'];
    
    // Get current warning count
    $check_query = "SELECT warnings, username FROM users WHERE id = :user_id";
    $stmt_check = $db->prepare($check_query);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->execute();
    $user_data = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $new_warnings = $user_data['warnings'] + 1;
    
    // Update warnings
    $query = "UPDATE users SET warnings = warnings + 1 WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        // Create notification for the warned user
        try {
            $warning_message = "You have received a warning from the administrator. Total warnings: $new_warnings";
            $notif = "INSERT INTO notifications (user_id, type, from_user_id, message) VALUES (:user_id, 'warning', :from_user_id, :message)";
            $stmt_notif = $db->prepare($notif);
            $stmt_notif->bindParam(':user_id', $user_id);
            $stmt_notif->bindParam(':from_user_id', $admin_id);
            $stmt_notif->bindParam(':message', $warning_message);
            $stmt_notif->execute();
            
            error_log("Admin warning issued to user $user_id. New warning count: $new_warnings");
            
            echo json_encode([
                'success' => true, 
                'message' => "Warning issued to {$user_data['username']}. Total warnings: $new_warnings"
            ]);
        } catch (Exception $e) {
            error_log("Failed to create warning notification: " . $e->getMessage());
            echo json_encode([
                'success' => true, 
                'message' => 'Warning issued but notification failed: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to warn user']);
    }
}

if ($action === 'ban_user') {
    $user_id = $_POST['user_id'] ?? 0;
    $ban_until = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    $query = "UPDATE users SET is_banned = TRUE, ban_until = :ban_until WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':ban_until', $ban_until);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User banned for 3 days']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to ban user']);
    }
}
?>
