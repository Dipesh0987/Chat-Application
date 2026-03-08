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

    // Check if blocked (either way)
    $check_block = "SELECT id FROM blocked_users 
                    WHERE (user_id = :user_id AND blocked_user_id = :receiver_id) 
                       OR (user_id = :receiver_id2 AND blocked_user_id = :user_id2)";
    $stmt_block = $db->prepare($check_block);
    $stmt_block->bindParam(':user_id', $user_id);
    $stmt_block->bindParam(':receiver_id', $receiver_id);
    $stmt_block->bindParam(':user_id2', $user_id);
    $stmt_block->bindParam(':receiver_id2', $receiver_id);
    $stmt_block->execute();

    if ($stmt_block->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be sent. You or the recipient has blocked the other.']);
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
            // Set user offline in DB before destroying session
            $set_offline = "UPDATE users SET is_online = FALSE, last_seen = NOW() WHERE id = :user_id";
            $stmt_offline = $db->prepare($set_offline);
            $stmt_offline->bindParam(':user_id', $user_id);
            $stmt_offline->execute();

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

    // Check if receiver is online (robust check: flag or active within last 2 mins)
    $check_online = "SELECT is_online, last_seen FROM users WHERE id = :receiver_id";
    $stmt_online = $db->prepare($check_online);
    $stmt_online->bindParam(':receiver_id', $receiver_id);
    $stmt_online->execute();
    $receiver = $stmt_online->fetch(PDO::FETCH_ASSOC);

    $is_delivered = 0;
    if ($receiver) {
        $last_seen = strtotime($receiver['last_seen']);
        $diff = time() - $last_seen;
        if ($receiver['is_online'] || $diff < 120) {
            $is_delivered = 1;
        }
    }

    $reply_to = $_POST['reply_to'] ?? null;
    if ($reply_to === '') {
        $reply_to = null;
    }

    $query = "INSERT INTO messages (sender_id, receiver_id, message, is_delivered, reply_to) VALUES (:sender_id, :receiver_id, :message, :is_delivered, :reply_to)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sender_id', $user_id);
    $stmt->bindParam(':receiver_id', $receiver_id);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':is_delivered', $is_delivered);
    $stmt->bindParam(':reply_to', $reply_to);

    if ($stmt->execute()) {
        $message_id = $db->lastInsertId();

        // Check if sender and receiver are friends
        $check_friends = "SELECT * FROM friends 
                         WHERE ((user_id = :user_id AND friend_id = :receiver_id) 
                            OR (user_id = :receiver_id2 AND friend_id = :user_id2))
                         AND status = 'accepted'";
        $stmt_friends = $db->prepare($check_friends);
        $stmt_friends->bindParam(':user_id', $user_id);
        $stmt_friends->bindParam(':receiver_id', $receiver_id);
        $stmt_friends->bindParam(':user_id2', $user_id);
        $stmt_friends->bindParam(':receiver_id2', $receiver_id);
        $stmt_friends->execute();

        // Only create notification if they are NOT friends (message request)
        if ($stmt_friends->rowCount() === 0) {
            // Check if this is the first message (no notification sent yet)
            $check_notif = "SELECT * FROM notifications 
                           WHERE user_id = :receiver_id 
                           AND from_user_id = :user_id 
                           AND type = 'message_request'";
            $stmt_check_notif = $db->prepare($check_notif);
            $stmt_check_notif->bindParam(':receiver_id', $receiver_id);
            $stmt_check_notif->bindParam(':user_id', $user_id);
            $stmt_check_notif->execute();

            // Only create notification if no message_request notification exists
            if ($stmt_check_notif->rowCount() === 0) {
                $notif = "INSERT INTO notifications (user_id, type, from_user_id, message) 
                         VALUES (:user_id, 'message_request', :from_user_id, :message)";
                $stmt_notif = $db->prepare($notif);
                $stmt_notif->bindParam(':user_id', $receiver_id);
                $stmt_notif->bindParam(':from_user_id', $user_id);
                $stmt_notif->bindParam(':message', $message);
                $stmt_notif->execute();
            }
        }

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

    $query = "SELECT m.*, u.username as sender_name,
              p.message as reply_message, p.message_type as reply_message_type, pu.username as reply_to_username, p.is_unsent as reply_is_unsent,
              (SELECT GROUP_CONCAT(CONCAT(mr.user_id, ':', mr.emoji) SEPARATOR '|') 
               FROM message_reactions mr WHERE mr.message_id = m.id) as reactions
              FROM messages m 
              JOIN users u ON m.sender_id = u.id
              LEFT JOIN messages p ON m.reply_to = p.id
              LEFT JOIN users pu ON p.sender_id = pu.id
              WHERE (m.sender_id = :user_id AND m.receiver_id = :other_user_id) 
                 OR (m.sender_id = :other_user_id2 AND m.receiver_id = :user_id2)
              ORDER BY m.created_at ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':user_id2', $user_id);
    $stmt->bindParam(':other_user_id', $other_user_id);
    $stmt->bindParam(':other_user_id2', $other_user_id);
    $stmt->execute();

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process messages to handle unsent state and format reactions
    foreach ($messages as &$msg) {
        if ($msg['is_unsent']) {
            $msg['message'] = 'This message is not available';
            $msg['message_type'] = 'text';
            $msg['file_path'] = null;
            $msg['file_name'] = null;
        }

        $msg['reactions_list'] = [];
        if (isset($msg['reactions']) && $msg['reactions']) {
            $parts = explode('|', $msg['reactions']);
            foreach ($parts as $part) {
                if (strpos($part, ':') !== false) {
                    list($uid, $emoji) = explode(':', $part);
                    $msg['reactions_list'][] = ['user_id' => $uid, 'emoji' => $emoji];
                }
            }
        }
        unset($msg['reactions']);
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
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



if ($action === 'delete_chat') {
    $other_user_id = $_POST['user_id'] ?? 0;

    $query = "DELETE FROM messages 
              WHERE (sender_id = :user_id AND receiver_id = :other_user_id) 
                 OR (sender_id = :other_user_id2 AND receiver_id = :user_id2)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':user_id2', $user_id);
    $stmt->bindParam(':other_user_id', $other_user_id);
    $stmt->bindParam(':other_user_id2', $other_user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete chat']);
    }
}

if ($action === 'unsend') {
    $message_id = $_POST['message_id'] ?? 0;

    $query = "UPDATE messages SET is_unsent = TRUE WHERE id = :message_id AND sender_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':message_id', $message_id);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute() && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unsend message or unauthorized']);
    }
}

if ($action === 'react') {
    $message_id = $_POST['message_id'] ?? 0;
    $emoji = $_POST['emoji'] ?? '';

    if (empty($emoji)) {
        echo json_encode(['success' => false, 'message' => 'Emoji required']);
        exit();
    }

    // Check if reaction already exists
    $check = "SELECT id FROM message_reactions WHERE message_id = :message_id AND user_id = :user_id AND emoji = :emoji";
    $stmt_check = $db->prepare($check);
    $stmt_check->bindParam(':message_id', $message_id);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->bindParam(':emoji', $emoji);
    $stmt_check->execute();

    if ($stmt_check->rowCount() > 0) {
        // Remove reaction
        $query = "DELETE FROM message_reactions WHERE message_id = :message_id AND user_id = :user_id AND emoji = :emoji";
    } else {
        // Add reaction
        // First remove any existing reaction by this user for this message (if one reaction per user)
        // Actually, user can have multiple reactions? Plan says "5 default emojis". 
        // Usually, one user can react with multiple emojis, or one emoji per message?
        // Let's allow toggling specific emojis.
        $query = "INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (:message_id, :user_id, :emoji)";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':message_id', $message_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':emoji', $emoji);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update reaction']);
    }
}

if ($action === 'forward') {
    $message_id = $_POST['message_id'] ?? 0;
    $receiver_ids = $_POST['receiver_ids'] ?? ''; // Comma separated

    if (empty($receiver_ids)) {
        echo json_encode(['success' => false, 'message' => 'No recipients selected']);
        exit();
    }

    // Get original message
    $query_orig = "SELECT * FROM messages WHERE id = :message_id";
    $stmt_orig = $db->prepare($query_orig);
    $stmt_orig->bindParam(':message_id', $message_id);
    $stmt_orig->execute();
    $orig = $stmt_orig->fetch(PDO::FETCH_ASSOC);

    if (!$orig || $orig['is_unsent']) {
        echo json_encode(['success' => false, 'message' => 'Original message not found or unsent']);
        exit();
    }

    $receivers = explode(',', $receiver_ids);
    $success_count = 0;

    foreach ($receivers as $rid) {
        $rid = (int) $rid;
        if ($rid === 0)
            continue;

        $query = "INSERT INTO messages (sender_id, receiver_id, message, message_type, file_path, file_name, file_size) 
                  VALUES (:sender_id, :receiver_id, :message, :message_type, :file_path, :file_name, :file_size)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':sender_id', $user_id);
        $stmt->bindParam(':receiver_id', $rid);
        $stmt->bindParam(':message', $orig['message']);
        $stmt->bindParam(':message_type', $orig['message_type']);
        $stmt->bindParam(':file_path', $orig['file_path']);
        $stmt->bindParam(':file_name', $orig['file_name']);
        $stmt->bindParam(':file_size', $orig['file_size']);

        if ($stmt->execute()) {
            $success_count++;
        }
    }

    echo json_encode(['success' => true, 'forwarded_count' => $success_count]);
}
