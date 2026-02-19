<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once 'content_filter.php';

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

    // Check for vulgar content
    $filter = new ContentFilter($db, $user_id);
    $check_result = $filter->checkMessage($message);

    if ($check_result['is_vulgar']) {
        // Issue warning
        $warning_result = $filter->issueWarning('Use of inappropriate language');

        // Log for debugging
        error_log("Vulgar message detected. User: $user_id, Warnings: {$warning_result['warnings']}, Banned: " . ($warning_result['banned'] ? 'yes' : 'no'));

        if ($warning_result['banned']) {
            // Destroy session to log out user
            session_destroy();

            echo json_encode([
                'success' => false,
                'message' => 'You have been banned for 7 days due to multiple violations.',
                'banned' => true,
                'warnings' => $warning_result['warnings']
            ]);
            exit();
        }

        echo json_encode([
            'success' => false,
            'message' => "Warning: Inappropriate language detected. You have {$warning_result['warnings']} warning(s). 3 warnings will result in a 7-day ban.",
            'warning' => true,
            'warnings' => $warning_result['warnings']
        ]);
        exit();
    }

    // Check if receiver is online
    $check_online = "SELECT is_online FROM users WHERE id = :receiver_id";
    $stmt_online = $db->prepare($check_online);
    $stmt_online->bindParam(':receiver_id', $receiver_id);
    $stmt_online->execute();
    $receiver = $stmt_online->fetch(PDO::FETCH_ASSOC);
    $is_delivered = ($receiver && $receiver['is_online']) ? 1 : 0;

    $query = "INSERT INTO messages (sender_id, receiver_id, message, is_delivered) VALUES (:sender_id, :receiver_id, :message, :is_delivered)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sender_id', $user_id);
    $stmt->bindParam(':receiver_id', $receiver_id);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':is_delivered', $is_delivered);

    if ($stmt->execute()) {
        $message_id = $db->lastInsertId();

        // Create notification for receiver
        $notif = "INSERT INTO notifications (user_id, type, from_user_id, message) VALUES (:user_id, 'message', :from_user_id, :message)";
        $stmt_notif = $db->prepare($notif);
        $stmt_notif->bindParam(':user_id', $receiver_id);
        $stmt_notif->bindParam(':from_user_id', $user_id);
        $stmt_notif->bindParam(':message', $message);
        $stmt_notif->execute();

        echo json_encode(['success' => true, 'message_id' => $message_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

if ($action === 'get') {
    $other_user_id = $_GET['user_id'] ?? 0;

    // Mark messages as delivered first
    $mark_delivered = "UPDATE messages SET is_delivered = TRUE WHERE sender_id = :sender_id AND receiver_id = :receiver_id AND is_delivered = FALSE";
    $stmt_delivered = $db->prepare($mark_delivered);
    $stmt_delivered->bindParam(':sender_id', $other_user_id);
    $stmt_delivered->bindParam(':receiver_id', $user_id);
    $stmt_delivered->execute();

    // Mark messages as read
    $mark_read = "UPDATE messages SET is_read = TRUE WHERE sender_id = :sender_id AND receiver_id = :receiver_id AND is_read = FALSE";
    $stmt_read = $db->prepare($mark_read);
    $stmt_read->bindParam(':sender_id', $other_user_id);
    $stmt_read->bindParam(':receiver_id', $user_id);
    $stmt_read->execute();

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

if ($action === 'mark_delivered') {
    $message_ids = $_POST['message_ids'] ?? '';

    if (empty($message_ids)) {
        echo json_encode(['success' => false, 'message' => 'No message IDs provided']);
        exit();
    }

    $ids = explode(',', $message_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $query = "UPDATE messages SET is_delivered = TRUE WHERE id IN ($placeholders) AND receiver_id = :user_id";
    $stmt = $db->prepare($query);

    foreach ($ids as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

if ($action === 'get_status') {
    $message_ids = $_GET['message_ids'] ?? '';

    if (empty($message_ids)) {
        echo json_encode(['success' => false, 'message' => 'No message IDs provided']);
        exit();
    }

    $ids = explode(',', $message_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $query = "SELECT id, is_delivered, is_read FROM messages WHERE id IN ($placeholders) AND sender_id = :user_id";
    $stmt = $db->prepare($query);

    foreach ($ids as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    echo json_encode(['success' => true, 'statuses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'get_media') {
    $other_user_id = $_GET['user_id'] ?? 0;

    $query = "SELECT * FROM messages 
              WHERE ((sender_id = :user_id AND receiver_id = :other_user_id) 
                 OR (sender_id = :other_user_id2 AND receiver_id = :user_id2))
              AND message_type IN ('image', 'video', 'document')
              ORDER BY created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':user_id2', $user_id);
    $stmt->bindParam(':other_user_id', $other_user_id);
    $stmt->bindParam(':other_user_id2', $other_user_id);
    $stmt->execute();

    echo json_encode(['success' => true, 'media' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
?>