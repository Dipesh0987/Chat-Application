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
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
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
            <div class="file-upload-area hidden" id="fileUploadArea">
                <div class="file-preview" id="filePreview"></div>
                <button class="remove-file-btn" onclick="removeFile()">Remove</button>
            </div>
            <div class="message-input hidden" id="messageInputArea">
                <div class="input-actions">
                    <button class="attachment-btn" id="attachmentBtn" title="Attach file">📎</button>
                    <button class="emoji-btn" id="emojiBtn" title="Emoji">😊</button>
                </div>
                <input type="file" id="fileInput" style="display: none;" accept="image/*,video/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.zip,.rar">
                <input type="text" id="messageInput" placeholder="Type a message...">
                <button id="sendBtn">Send</button>
            </div>
            <div class="emoji-picker hidden" id="emojiPicker">
                <div class="emoji-grid" id="emojiGrid"></div>
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
    
    <script src="../assets/js/dialog.js"></script>
    <script src="../assets/js/chat.js"></script>
</body>
</html>
