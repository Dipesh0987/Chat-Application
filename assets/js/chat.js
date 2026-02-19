let currentChatUser = null;
let messageInterval = null;
let notificationInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    loadChats();
    loadFriends();
    loadFriendRequests();
    loadNotifications();
    loadUserProfile();

    document.getElementById('settingsBtn').addEventListener('click', showSettingsModal);
    document.getElementById('notificationBtn').addEventListener('click', showNotificationsModal);
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
            chatItem.innerHTML = `
                <strong>${chat.username}</strong>
                <p>${chat.last_message || 'No messages yet'}</p>
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
            friendItem.innerHTML = `
                <span>${friend.username}</span>
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
            msgDiv.className = msg.sender_id == currentChatUser ? 'message received' : 'message sent';
            msgDiv.innerHTML = `<p>${msg.message}</p><span class="time">${new Date(msg.created_at).toLocaleTimeString()}</span>`;
            container.appendChild(msgDiv);
        });
        container.scrollTop = container.scrollHeight;
    }
}

async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message || !currentChatUser) return;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', currentChatUser);
    formData.append('message', message);
    
    const response = await fetch('../api/messages.php', {
        method: 'POST',
        body: formData
    });
    
    if (response.ok) {
        input.value = '';
        loadMessages();
        loadChats();
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
    document.getElementById('deleteAccountBtn').addEventListener('click', showDeleteAccountConfirm);
    document.getElementById('logoutBtnSettings').addEventListener('click', logout);
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
    const formData = new FormData();
    formData.append('action', 'logout');
    
    await fetch('../api/auth.php', {
        method: 'POST',
        body: formData
    });
    
    window.location.href = '../index.php';
}
