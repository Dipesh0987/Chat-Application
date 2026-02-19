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
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </button>
                    <button id="settingsBtn" class="icon-btn" title="Settings">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6m0 6v6m9-9h-6m-6 0H3"></path>
                            <path d="M20.49 8.5l-4.24 4.24m-4.24 0L7.51 8.5m8.98 8.98l-4.24-4.24m-4.24 4.24L3.51 15.5"></path>
                        </svg>
                    </button>
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
