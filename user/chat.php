<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat App</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="chat-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="user-info">
                    <img id="userProfileImg" src="../assets/images/default-avatar.svg" alt="Profile" class="profile-img-small">
                    <h3><?php echo htmlspecialchars($_SESSION['username']); ?></h3>
                </div>
                <div class="header-actions">
                    <button id="notificationBtn" class="icon-btn" title="Notifications">
                        <span class="notification-badge hidden" id="notificationBadge">0</span>
                        🔔
                    </button>
                    <button id="settingsBtn" class="icon-btn" title="Settings">⚙️</button>
                </div>
            </div>
            <div class="tabs">
                <button class="tab-btn active" data-tab="chats">Chats</button>
                <button class="tab-btn" data-tab="friends">Friends</button>
                <button class="tab-btn" data-tab="requests">Requests</button>
            </div>
            <div class="tab-content" id="chats-tab">
                <button class="add-btn" id="newChatBtn">+ New Chat</button>
                <div id="chatList"></div>
            </div>
            <div class="tab-content hidden" id="friends-tab">
                <button class="add-btn" id="addFriendBtn">+ Add Friend</button>
                <div id="friendList"></div>
            </div>
            <div class="tab-content hidden" id="requests-tab">
                <div id="requestList"></div>
            </div>
        </div>
        <div class="chat-area">
            <div class="chat-header" id="chatHeader">
                <span>Select a chat to start messaging</span>
            </div>
            <div class="messages" id="messagesContainer"></div>
            <div class="message-input hidden" id="messageInputArea">
                <input type="text" id="messageInput" placeholder="Type a message...">
                <button id="sendBtn">Send</button>
            </div>
        </div>
    </div>
    
    <div id="modal" class="modal hidden">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 id="modalTitle"></h3>
            <div id="modalBody"></div>
        </div>
    </div>
    
    <script src="../assets/js/chat.js"></script>
</body>
</html>
