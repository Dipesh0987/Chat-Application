<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? '';
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($action === 'upload_file') {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $caption = trim($_POST['caption'] ?? '');
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit();
    }
    
    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload error occurred']);
        exit();
    }
    
    // Determine file type
    $mime_type = mime_content_type($file['tmp_name']);
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $message_type = 'document';
    $allowed_types = [];
    $max_size = 0;
    $upload_dir = '';
    
    if (strpos($mime_type, 'image/') === 0) {
        $message_type = 'image';
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 10 * 1024 * 1024; // 10MB
        $upload_dir = '../uploads/images/';
    } elseif (strpos($mime_type, 'video/') === 0) {
        $message_type = 'video';
        $allowed_types = ['mp4', 'webm', 'ogg', 'mov'];
        $max_size = 50 * 1024 * 1024; // 50MB
        $upload_dir = '../uploads/videos/';
    } else {
        $message_type = 'document';
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'];
        $max_size = 20 * 1024 * 1024; // 20MB
        $upload_dir = '../uploads/documents/';
    }
    
    // Validate file extension
    if (!in_array($file_ext, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        exit();
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        $max_mb = $max_size / (1024 * 1024);
        echo json_encode(['success' => false, 'message' => "File too large. Max size: {$max_mb}MB"]);
        exit();
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $new_filename = $user_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Save to database
        $file_path = str_replace('../', '', $upload_path);
        $message_text = $caption ?: "[{$message_type}]";
        
        $query = "INSERT INTO messages (sender_id, receiver_id, message, message_type, file_path, file_name, file_size, is_delivered) 
                  VALUES (:sender_id, :receiver_id, :message, :message_type, :file_path, :file_name, :file_size, FALSE)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':sender_id', $user_id);
        $stmt->bindParam(':receiver_id', $receiver_id);
        $stmt->bindParam(':message', $message_text);
        $stmt->bindParam(':message_type', $message_type);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':file_name', $file['name']);
        $stmt->bindParam(':file_size', $file['size']);
        
        if ($stmt->execute()) {
            $message_id = $db->lastInsertId();
            
            // Create notification
            $notif_message = "Sent you a {$message_type}";
            $notif = "INSERT INTO notifications (user_id, type, from_user_id, message) 
                      VALUES (:user_id, 'message', :from_user_id, :message)";
            $stmt_notif = $db->prepare($notif);
            $stmt_notif->bindParam(':user_id', $receiver_id);
            $stmt_notif->bindParam(':from_user_id', $user_id);
            $stmt_notif->bindParam(':message', $notif_message);
            $stmt_notif->execute();
            
            echo json_encode([
                'success' => true,
                'message_id' => $message_id,
                'file_path' => $file_path,
                'message_type' => $message_type
            ]);
        } else {
            // Delete uploaded file if database insert fails
            unlink($upload_path);
            echo json_encode(['success' => false, 'message' => 'Failed to save message']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
}
?>
