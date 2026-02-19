let currentChatUser = null;
let messageInterval = null;
let notificationInterval = null;
let chatListInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    loadChats();
    loadFriends();
    loadFriendRequests();
    loadNotifications();
    loadUserProfile();

    document.getElementById('settingsBtn').addEventListener('click', showSettingsModal);
    document.getElementById('newChatBtn').addEventListener('click', showNewChatModal);
    document.getElementById('addFriendBtn').addEventListener('click', showAddFriendModal);
    document.getElementById('sendBtn').addEventListener('click', sendMessage);
    
    document.getElementById('messageInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            this.classList.add('active');
            document.getElementById(tab + '-tab').classList.remove('hidden');
            
            if (tab === 'requests') {
                loadFriendRequests();
            }
        });
    });

    document.querySelector('.close').addEventListener('click', closeModal);
    
    // Poll for notifications every 10 seconds
    notificationInterval = setInterval(loadNotifications, 10000);
    
    // Poll for chat list updates every 5 seconds (real-time updates)
    chatListInterval = setInterval(loadChats, 5000);
});

async function loadChats() {
    const response = await fetch('../api/users.php?action=get_chats');
    const data = await response.json();
    
    const chatList = document.getElementById('chatList');
    chatList.innerHTML = '';
    
    if (data.success && data.chats.length > 0) {
        data.chats.forEach(chat => {
            const chatItem = document.createElement('div');
            chatItem.className = 'chat-item';
            chatItem.style.position = 'relative';
            
            // Highlight if there are unread messages
            if (chat.unread_count > 0) {
                chatItem.classList.add('has-unread');
            }
            
            const unreadBadge = chat.unread_count > 0 
                ? `<span class="unread-badge">${chat.unread_count}</span>` 
                : '';
            
            const onlineStatus = chat.is_online ? 
                '<span class="online-status online" title="Online"></span>' : 
                '<span class="online-status offline" title="Offline"></span>';
            
            // Truncate long messages intelligently
            let displayMessage = chat.last_message || 'No messages yet';
            if (displayMessage !== 'No messages yet') {
                const words = displayMessage.split(' ');
                
                // Check if any single word is too long
                const hasLongWord = words.some(word => word.length > 30);
                
                if (hasLongWord) {
                    // If there's a very long word, truncate at character level (50 chars)
                    if (displayMessage.length > 50) {
                        displayMessage = displayMessage.substring(0, 50) + '...';
                    }
                } else if (words.length > 5) {
                    // If more than 5 words, show first 5 words
                    displayMessage = words.slice(0, 5).join(' ') + '...';
                } else if (displayMessage.length > 60) {
                    // If less than 5 words but still too long, truncate at 60 chars
                    displayMessage = displayMessage.substring(0, 60) + '...';
                }
            }
            
            chatItem.innerHTML = `
                <strong>${chat.username} ${onlineStatus}</strong>
                <p>${displayMessage}</p>
                ${unreadBadge}
            `;
            chatItem.addEventListener('click', () => openChat(chat.user_id, chat.username));
            chatList.appendChild(chatItem);
        });
    } else {
        chatList.innerHTML = '<p class="empty-state">No chats yet</p>';
    }
}

async function loadFriends() {
    const response = await fetch('../api/users.php?action=get_friends');
    const data = await response.json();
    
    const friendList = document.getElementById('friendList');
    friendList.innerHTML = '';
    
    if (data.success && data.friends.length > 0) {
        data.friends.forEach(friend => {
            const friendItem = document.createElement('div');
            friendItem.className = 'friend-item';
            
            const onlineStatus = friend.is_online ? 
                '<span class="online-status online" title="Online"></span>' : 
                '<span class="online-status offline" title="Offline"></span>';
            
            friendItem.innerHTML = `
                <span>${friend.username} ${onlineStatus}</span>
                <button class="message-btn" onclick="openChat(${friend.id}, '${friend.username}')">Message</button>
            `;
            friendList.appendChild(friendItem);
        });
    } else {
        friendList.innerHTML = '<p class="empty-state">No friends yet</p>';
    }
}

function openChat(userId, username) {
    currentChatUser = userId;
    document.getElementById('chatHeader').innerHTML = `<strong>${username}</strong>`;
    document.getElementById('messageInputArea').classList.remove('hidden');
    
    if (messageInterval) clearInterval(messageInterval);
    loadMessages();
    messageInterval = setInterval(loadMessages, 2000);
}

async function loadMessages() {
    if (!currentChatUser) return;
    
    const response = await fetch(`../api/messages.php?action=get&user_id=${currentChatUser}`);
    const data = await response.json();
    
    const container = document.getElementById('messagesContainer');
    container.innerHTML = '';
    
    if (data.success && data.messages.length > 0) {
        data.messages.forEach(msg => {
            const msgDiv = document.createElement('div');
            const isSent = msg.sender_id != currentChatUser;
            msgDiv.className = isSent ? 'message sent' : 'message received';
            
            let content = '';
            
            // Handle different message types
            if (msg.message_type === 'image') {
                content = `
                    <div class="message-file">
                        <img src="../${msg.file_path}" alt="${msg.file_name}" onclick="window.open('../${msg.file_path}', '_blank')" style="max-width: 250px; max-height: 250px; cursor: pointer; border-radius: 8px;">
                        ${msg.message !== '[image]' ? `<p>${msg.message}</p>` : ''}
                    </div>
                `;
            } else if (msg.message_type === 'video') {
                content = `
                    <div class="message-file">
                        <video controls style="max-width: 250px; border-radius: 8px;">
                            <source src="../${msg.file_path}" type="video/mp4">
                        </video>
                        ${msg.message !== '[video]' ? `<p>${msg.message}</p>` : ''}
                    </div>
                `;
            } else if (msg.message_type === 'document') {
                content = `
                    <div class="message-file">
                        <div style="font-size: 40px;">📄</div>
                        <p><strong>${msg.file_name}</strong></p>
                        <button class="download-btn" onclick="window.open('../${msg.file_path}', '_blank')" style="padding: 5px 10px; background: #0084ff; color: white; border: none; border-radius: 4px; cursor: pointer;">Download</button>
                        ${msg.message !== '[document]' ? `<p>${msg.message}</p>` : ''}
                    </div>
                `;
            } else {
                content = `<p>${msg.message}</p>`;
            }
            
            // Add status ticks for sent messages
            let statusIcon = '';
            if (isSent) {
                if (msg.is_read) {
                    statusIcon = '<span class="status-tick read">✓✓</span>';
                } else if (msg.is_delivered) {
                    statusIcon = '<span class="status-tick delivered">✓✓</span>';
                } else {
                    statusIcon = '<span class="status-tick sent">✓</span>';
                }
            }
            
            msgDiv.innerHTML = `
                ${content}
                <span class="time">${new Date(msg.created_at).toLocaleTimeString()} ${statusIcon}</span>
            `;
            container.appendChild(msgDiv);
        });
        container.scrollTop = container.scrollHeight;
    }
}

async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!currentChatUser) return;
    
    // If file is selected, upload it
    if (selectedFile) {
        const formData = new FormData();
        formData.append('action', 'upload_file');
        formData.append('receiver_id', currentChatUser);
        formData.append('file', selectedFile);
        formData.append('caption', message);
        
        try {
            const response = await fetch('../api/upload.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // dialog.success('File sent successfully!');
                input.value = '';
                removeFile();
                loadMessages();
                loadChats();
            } else {
                dialog.error(data.message || 'Failed to send file');
            }
        } catch (error) {
            console.error('Upload error:', error);
            dialog.error('Failed to upload file. Please try again.');
        }
        return;
    }
    
    // Otherwise send text message
    if (!message) return;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', currentChatUser);
    formData.append('message', message);
    
    const response = await fetch('../api/messages.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
        input.value = '';
        loadMessages();
        loadChats();
    } else {
        // Handle warnings and bans with dialog boxes
        if (data.banned) {
            dialog.error(data.message, 'Account Banned');
            setTimeout(() => logout(), 2000);
        } else if (data.warning) {
            dialog.warning(data.message, 'Warning Issued');
            loadNotifications();
        } else {
            dialog.error(data.message || 'Failed to send message');
        }
    }
}

function showNewChatModal() {
    document.getElementById('modalTitle').textContent = 'Start New Chat';
    document.getElementById('modalBody').innerHTML = `
        <input type="text" id="searchUser" placeholder="Search users...">
        <div id="searchResults"></div>
    `;
    document.getElementById('modal').classList.remove('hidden');
    
    document.getElementById('searchUser').addEventListener('input', async function(e) {
        const search = e.target.value;
        if (search.length < 2) return;
        
        const response = await fetch(`../api/users.php?action=search&search=${search}`);
        const data = await response.json();
        
        const results = document.getElementById('searchResults');
        results.innerHTML = '';
        
        if (data.success && data.users.length > 0) {
            data.users.forEach(user => {
                const userDiv = document.createElement('div');
                userDiv.className = 'user-result';
                userDiv.innerHTML = `<span>${user.username}</span>`;
                userDiv.addEventListener('click', () => {
                    openChat(user.id, user.username);
                    closeModal();
                });
                results.appendChild(userDiv);
            });
        }
    });
}

function showAddFriendModal() {
    document.getElementById('modalTitle').textContent = 'Add Friend';
    document.getElementById('modalBody').innerHTML = `
        <input type="text" id="searchFriend" placeholder="Search users...">
        <div id="friendSearchResults"></div>
    `;
    document.getElementById('modal').classList.remove('hidden');
    
    document.getElementById('searchFriend').addEventListener('input', async function(e) {
        const search = e.target.value;
        if (search.length < 2) return;
        
        const response = await fetch(`../api/users.php?action=search&search=${search}`);
        const data = await response.json();
        
        const results = document.getElementById('friendSearchResults');
        results.innerHTML = '';
        
        if (data.success && data.users.length > 0) {
            data.users.forEach(user => {
                const userDiv = document.createElement('div');
                userDiv.className = 'user-result';
                userDiv.innerHTML = `
                    <span>${user.username}</span>
                    <button onclick="addFriend(${user.id})">Add</button>
                `;
                results.appendChild(userDiv);
            });
        }
    });
}

async function addFriend(friendId) {
    const formData = new FormData();
    formData.append('action', 'send_friend_request');
    formData.append('friend_id', friendId);
    
    const response = await fetch('../api/users.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    alert(data.message);
    if (data.success) {
        closeModal();
    }
}

async function loadFriendRequests() {
    const response = await fetch('../api/users.php?action=get_friend_requests');
    const data = await response.json();
    
    const requestList = document.getElementById('requestList');
    requestList.innerHTML = '';
    
    if (data.success && data.requests.length > 0) {
        data.requests.forEach(req => {
            const reqItem = document.createElement('div');
            reqItem.className = 'request-item';
            reqItem.innerHTML = `
                <span>${req.username}</span>
                <div class="request-actions">
                    <button class="accept-btn" onclick="acceptFriendRequest(${req.user_id})">Accept</button>
                    <button class="reject-btn" onclick="rejectFriendRequest(${req.user_id})">Reject</button>
                </div>
            `;
            requestList.appendChild(reqItem);
        });
    } else {
        requestList.innerHTML = '<p class="empty-state">No friend requests</p>';
    }
}

async function acceptFriendRequest(friendId) {
    const formData = new FormData();
    formData.append('action', 'accept_friend_request');
    formData.append('friend_id', friendId);
    
    const response = await fetch('../api/users.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    if (data.success) {
        loadFriendRequests();
        loadFriends();
    }
}

async function rejectFriendRequest(friendId) {
    const formData = new FormData();
    formData.append('action', 'reject_friend_request');
    formData.append('friend_id', friendId);
    
    const response = await fetch('../api/users.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    if (data.success) {
        loadFriendRequests();
    }
}

async function loadNotifications() {
    const response = await fetch('../api/notifications.php?action=get_unread_count');
    const data = await response.json();
    
    if (data.success && data.count > 0) {
        document.getElementById('notificationBadge').textContent = data.count;
        document.getElementById('notificationBadge').classList.remove('hidden');
    } else {
        document.getElementById('notificationBadge').classList.add('hidden');
    }
}

async function showNotificationsModal() {
    const response = await fetch('../api/notifications.php?action=get');
    const data = await response.json();
    
    document.getElementById('modalTitle').textContent = 'Notifications';
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '';
    
    if (data.success && data.notifications.length > 0) {
        const notifContainer = document.createElement('div');
        notifContainer.className = 'notification-list';
        
        data.notifications.forEach(notif => {
            const notifDiv = document.createElement('div');
            notifDiv.className = 'notification-item' + (notif.is_read ? ' read' : '');
            
            let message = '';
            if (notif.type === 'friend_request') {
                message = `${notif.from_username} sent you a friend request`;
            } else if (notif.type === 'friend_accepted') {
                message = `${notif.from_username} accepted your friend request`;
            } else if (notif.type === 'message') {
                message = `New message from ${notif.from_username}`;
            } else if (notif.type === 'warning') {
                message = notif.message || `You received a warning from ${notif.from_username}`;
            }
            
            notifDiv.innerHTML = `
                <p>${message}</p>
                <span class="time">${new Date(notif.created_at).toLocaleString()}</span>
            `;
            notifContainer.appendChild(notifDiv);
        });
        
        const markAllBtn = document.createElement('button');
        markAllBtn.textContent = 'Mark All as Read';
        markAllBtn.className = 'btn-primary';
        markAllBtn.onclick = async () => {
            await fetch('../api/notifications.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'mark_all_read' })
            });
            loadNotifications();
            closeModal();
        };
        
        modalBody.appendChild(notifContainer);
        modalBody.appendChild(markAllBtn);
    } else {
        modalBody.innerHTML = '<p class="empty-state">No notifications</p>';
    }
    
    document.getElementById('modal').classList.remove('hidden');
}

async function loadUserProfile() {
    const response = await fetch('../api/settings.php?action=get_profile');
    const data = await response.json();
    
    if (data.success && data.user.profile_image) {
        document.getElementById('userProfileImg').src = '../' + data.user.profile_image;
    }
}

function showSettingsModal() {
    document.getElementById('modalTitle').textContent = 'Settings';
    document.getElementById('modalBody').innerHTML = `
        <div class="settings-container">
            <div class="setting-item">
                <label>Profile Picture</label>
                <input type="file" id="profileImageInput" accept="image/*">
                <button id="uploadBtn" class="btn-primary">Upload</button>
            </div>
            <div class="setting-item">
                <label>Username</label>
                <input type="text" id="editUsername" placeholder="New username">
                <button id="updateUsernameBtn" class="btn-primary">Update Username</button>
            </div>
            <div class="setting-item">
                <label>Email</label>
                <input type="email" id="editEmail" placeholder="New email">
                <button id="updateEmailBtn" class="btn-primary">Update Email</button>
            </div>
            <div class="setting-item">
                <label>Change Password</label>
                <input type="password" id="currentPassword" placeholder="Current password">
                <input type="password" id="newPassword" placeholder="New password">
                <input type="password" id="confirmPassword" placeholder="Confirm new password">
                <button id="updatePasswordBtn" class="btn-primary">Update Password</button>
            </div>
            <div class="setting-item">
                <label>Notifications</label>
                <button id="modalNotificationBtn" class="btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    View Notifications
                </button>
            </div>
            <div class="setting-item">
                <button class="btn-danger" id="deleteAccountBtn">Delete Account</button>
            </div>
            <div class="setting-item">
                <button class="btn-secondary" id="logoutBtnSettings">Logout</button>
            </div>
        </div>
    `;
    document.getElementById('modal').classList.remove('hidden');
    
    // Add event listeners after modal is created
    document.getElementById('uploadBtn').addEventListener('click', uploadProfileImage);
    document.getElementById('updateUsernameBtn').addEventListener('click', updateUsername);
    document.getElementById('updateEmailBtn').addEventListener('click', updateEmail);
    document.getElementById('updatePasswordBtn').addEventListener('click', updatePassword);
    document.getElementById('deleteAccountBtn').addEventListener('click', showDeleteAccountConfirm);
    document.getElementById('logoutBtnSettings').addEventListener('click', logout);
    document.getElementById('modalNotificationBtn').addEventListener('click', showNotificationsModal);
}

async function uploadProfileImage() {
    const fileInput = document.getElementById('profileImageInput');
    if (!fileInput.files[0]) {
        alert('Please select an image');
        return;
    }
    
    const file = fileInput.files[0];
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        alert('Please select a valid image file (JPG, PNG, or GIF)');
        return;
    }
    
    // Validate file size (5MB)
    if (file.size > 5000000) {
        alert('File size must be less than 5MB');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_profile_image');
    formData.append('profile_image', file);
    
    try {
        const response = await fetch('../api/settings.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        console.log('Server response:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Failed to parse JSON:', text);
            alert('Server error: Invalid response. Check console for details.');
            return;
        }
        
        if (data.success) {
            alert('Profile image updated successfully!');
            loadUserProfile();
            closeModal();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Upload error:', error);
        alert('Failed to upload image. Error: ' + error.message);
    }
}

async function updateUsername() {
    const username = document.getElementById('editUsername').value.trim();
    if (!username) {
        alert('Please enter a username');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_username');
    formData.append('username', username);
    
    const response = await fetch('../api/settings.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    if (data.success) {
        alert('Username updated successfully!');
        location.reload();
    } else {
        alert('Error: ' + data.message);
    }
}

async function updateEmail() {
    const email = document.getElementById('editEmail').value.trim();
    if (!email) {
        alert('Please enter an email');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_email');
    formData.append('email', email);
    
    const response = await fetch('../api/settings.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    if (data.success) {
        alert('Email updated successfully!');
        closeModal();
    } else {
        alert('Error: ' + data.message);
    }
}

async function updatePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        alert('Please fill all password fields');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        alert('New passwords do not match');
        return;
    }
    
    if (newPassword.length < 6) {
        alert('Password must be at least 6 characters');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_password');
    formData.append('current_password', currentPassword);
    formData.append('new_password', newPassword);
    
    const response = await fetch('../api/settings.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    if (data.success) {
        alert('Password updated successfully!');
        closeModal();
    } else {
        alert('Error: ' + data.message);
    }
}

function showDeleteAccountConfirm() {
    if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
        const password = prompt('Enter your password to confirm:');
        if (password) {
            deleteAccount(password);
        }
    }
}

async function deleteAccount(password) {
    const formData = new FormData();
    formData.append('action', 'delete_account');
    formData.append('password', password);
    
    const response = await fetch('../api/settings.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    if (data.success) {
        alert('Account deleted');
        window.location.href = '../index.php';
    } else {
        alert(data.message);
    }
}

function closeModal() {
    document.getElementById('modal').classList.add('hidden');
}

async function logout() {
    // Update online status to offline
    updateOnlineStatus('offline');
    
    const formData = new FormData();
    formData.append('action', 'logout');
    
    await fetch('../api/auth.php', {
        method: 'POST',
        body: formData
    });
    
    window.location.href = '../index.php';
}


// ============================================
// NEW FEATURES: File Upload, Emoji, Online Status
// ============================================

// File upload handling
let selectedFile = null;

// Initialize new features
document.addEventListener('DOMContentLoaded', function() {
    // Attachment button
    if (document.getElementById('attachmentBtn')) {
        document.getElementById('attachmentBtn').addEventListener('click', () => {
            document.getElementById('fileInput').click();
        });
    }
    
    // File input change
    if (document.getElementById('fileInput')) {
        document.getElementById('fileInput').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                selectedFile = file;
                showFilePreview(file);
            }
        });
    }
    
    // Emoji button
    if (document.getElementById('emojiBtn')) {
        document.getElementById('emojiBtn').addEventListener('click', toggleEmojiPicker);
    }
    
    // Close emoji picker when clicking outside
    document.addEventListener('click', (e) => {
        const picker = document.getElementById('emojiPicker');
        const btn = document.getElementById('emojiBtn');
        if (picker && !picker.contains(e.target) && e.target !== btn) {
            picker.classList.add('hidden');
        }
    });
    
    // Update online status
    updateOnlineStatus('online');
    
    // Keep alive - update status every 30 seconds
    setInterval(() => {
        updateOnlineStatus('online');
    }, 30000);
    
    // Set offline when page unloads
    window.addEventListener('beforeunload', () => {
        updateOnlineStatus('offline');
    });
});

// Show file preview
function showFilePreview(file) {
    const preview = document.getElementById('filePreview');
    const uploadArea = document.getElementById('fileUploadArea');
    
    const fileSize = (file.size / 1024 / 1024).toFixed(2);
    
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="max-width: 60px; max-height: 60px; border-radius: 4px;">
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${fileSize} MB</div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        const icon = file.type.startsWith('video/') ? '🎥' : '📄';
        preview.innerHTML = `
            <div style="font-size: 40px;">${icon}</div>
            <div class="file-info">
                <div class="file-name">${file.name}</div>
                <div class="file-size">${fileSize} MB</div>
            </div>
        `;
    }
    
    uploadArea.classList.remove('hidden');
}

// Remove selected file
function removeFile() {
    selectedFile = null;
    document.getElementById('fileUploadArea').classList.add('hidden');
    document.getElementById('fileInput').value = '';
}

// Toggle emoji picker
function toggleEmojiPicker() {
    const picker = document.getElementById('emojiPicker');
    picker.classList.toggle('hidden');
    
    if (!picker.classList.contains('hidden')) {
        const emojis = ['😀','😂','😍','😊','😎','😢','😡','👍','👎','❤️','🎉','🔥','⭐','✅','❌','💯','🙏','👏','💪','🤔','😴','🤗','😱','🤩','😇'];
        const grid = document.getElementById('emojiGrid');
        grid.innerHTML = '';
        
        emojis.forEach(emoji => {
            const item = document.createElement('div');
            item.className = 'emoji-item';
            item.textContent = emoji;
            item.onclick = () => {
                const input = document.getElementById('messageInput');
                input.value += emoji;
                input.focus();
                picker.classList.add('hidden');
            };
            grid.appendChild(item);
        });
    }
}

// Update online status
function updateOnlineStatus(status) {
    const formData = new FormData();
    formData.append('action', 'update_online_status');
    formData.append('status', status);
    
    fetch('../api/auth.php', {
        method: 'POST',
        body: formData
    }).catch(err => console.error('Failed to update status:', err));
}

console.log('✅ New features loaded: File Upload, Emoji Picker, Online Status, Dialog Boxes');
