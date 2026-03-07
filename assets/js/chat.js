let currentChatUser = null;
let messageInterval = null;
let notificationInterval = null;
let chatListInterval = null;
let notificationsEnabled = true;
let lastUnreadCount = 0;
let lastActiveChatCount = 0;

const inChatSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');
const newMessageSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2354/2354-preview.mp3');

document.addEventListener('DOMContentLoaded', function () {
    loadChats();
    loadFriends();
    loadFriendRequests();
    loadNotifications();
    loadUserProfile();

    // Load initial notification setting
    fetch('../api/settings.php?action=get_profile')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.user) {
                notificationsEnabled = parseInt(data.user.notifications_enabled) === 1;
            }
        });

    document.getElementById('settingsBtn').addEventListener('click', showSettingsModal);
    document.getElementById('newChatBtn').addEventListener('click', showNewChatModal);
    document.getElementById('addFriendBtn').addEventListener('click', showAddFriendModal);
    document.getElementById('sendBtn').addEventListener('click', sendMessage);

    document.getElementById('toggleInfoBtn').addEventListener('click', toggleChatInfoSidebar);
    document.getElementById('closeInfoBtn').addEventListener('click', toggleChatInfoSidebar);

    document.getElementById('messageInput').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
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

    // Heartbeat: update online status every 20 seconds
    updateOnlineStatus();
    setInterval(updateOnlineStatus, 20000);

    // Detect browser close/tab close and set user offline
    window.addEventListener('beforeunload', function () {
        // Use sendBeacon for reliable offline status update
        const formData = new FormData();
        formData.append('action', 'update_online_status');
        formData.append('status', 'offline');

        // sendBeacon is more reliable than fetch for page unload
        navigator.sendBeacon('../api/auth.php', formData);
    });

    // Also handle visibility change (tab switching)
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            // User switched tabs or minimized browser
            // Don't set offline immediately, let the heartbeat handle it
        } else {
            // User came back, update status
            updateOnlineStatus();
        }
    });

    initSidebarResize();
});

async function updateOnlineStatus() {
    try {
        await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=update_online_status&status=online'
        });
    } catch (error) {
        console.error('Heartbeat failed:', error);
    }
}

async function loadChats() {
    try {
        const response = await fetch('../api/users.php?action=get_chats');
        const data = await response.json();

        const chatList = document.getElementById('chatList');
        if (!chatList) return;

        if (data.success && data.chats.length > 0) {
            let currentTotalUnread = 0;
            data.chats.forEach(chat => {
                currentTotalUnread += parseInt(chat.unread_count || 0);
            });

            // Background Message Sound Logic
            if (notificationsEnabled && currentTotalUnread > lastUnreadCount) {
                let backgroundUnread = 0;
                data.chats.forEach(chat => {
                    if (chat.user_id != currentChatUser) {
                        backgroundUnread += parseInt(chat.unread_count || 0);
                    }
                });

                if (typeof lastBackgroundUnread !== 'undefined' && backgroundUnread > lastBackgroundUnread) {
                    newMessageSound.play().catch(e => console.log('Audio playback blocked'));
                }
                window.lastBackgroundUnread = backgroundUnread;
            } else if (typeof lastBackgroundUnread === 'undefined') {
                let initialBackgroundUnread = 0;
                data.chats.forEach(chat => {
                    if (chat.user_id != currentChatUser) {
                        initialBackgroundUnread += parseInt(chat.unread_count || 0);
                    }
                });
                window.lastBackgroundUnread = initialBackgroundUnread;
            }
            lastUnreadCount = currentTotalUnread;

            chatList.innerHTML = '';
            data.chats.forEach(chat => {
                const chatItem = document.createElement('div');
                chatItem.className = 'chat-item';
                chatItem.style.position = 'relative';

                if (chat.user_id == currentChatUser) {
                    chatItem.classList.add('active');
                }

                if (chat.unread_count > 0) {
                    chatItem.classList.add('has-unread');
                }

                const unreadBadge = chat.unread_count > 0
                    ? `<span class="unread-badge">${chat.unread_count}</span>`
                    : '';

                let statusTime = '';
                if (!chat.is_online && chat.status_text && chat.status_text !== 'Offline' && chat.status_text !== 'Online') {
                    statusTime = `<span class="status-time">${chat.status_text}</span>`;
                }

                const onlineStatus = chat.is_online ?
                    `<span class="online-status online"></span>` :
                    `<span class="online-status offline"></span>`;

                let displayMessage = chat.last_message || 'No messages yet';
                if (displayMessage !== 'No messages yet') {
                    const words = displayMessage.split(' ');
                    const hasLongWord = words.some(word => word.length > 30);

                    if (hasLongWord) {
                        if (displayMessage.length > 50) {
                            displayMessage = displayMessage.substring(0, 50) + '...';
                        }
                    } else if (words.length > 5) {
                        displayMessage = words.slice(0, 5).join(' ') + '...';
                    } else if (displayMessage.length > 60) {
                        displayMessage = displayMessage.substring(0, 60) + '...';
                    }
                }

                chatItem.innerHTML = `
                <div class="chat-item-header">
                    <strong>${chat.username} ${onlineStatus}${statusTime}</strong>
                    ${unreadBadge}
                </div>
                <p>${displayMessage}</p>
            `;
                chatItem.addEventListener('click', () => openChat(chat.user_id, chat.username));
                chatList.appendChild(chatItem);
            });
        } else {
            chatList.innerHTML = '<p class="empty-state">No chats yet</p>';
        }
    } catch (error) {
        console.error('Load chats error:', error);
    }
}

async function loadFriends() {
    try {
        const response = await fetch('../api/users.php?action=get_friends');
        const data = await response.json();

        const friendList = document.getElementById('friendList');
        friendList.innerHTML = '';

        if (data.success && data.friends && data.friends.length > 0) {
            data.friends.forEach(friend => {
                const friendItem = document.createElement('div');
                friendItem.className = 'friend-item';

                let statusTime = '';
                if (!friend.is_online && friend.status_text && friend.status_text !== 'Offline' && friend.status_text !== 'Online') {
                    statusTime = `<span class="status-time">${friend.status_text}</span>`;
                }

                const onlineStatus = friend.is_online ?
                    `<span class="online-status online"></span>` :
                    `<span class="online-status offline"></span>`;

                friendItem.innerHTML = `
                    <div class="friend-info">
                        <span>${friend.username} ${onlineStatus}${statusTime}</span>
                    </div>
                    <div class="friend-actions">
                        <button class="message-btn" onclick="openChat(${friend.id}, '${friend.username}')">Message</button>
                        <button class="reject-btn" style="padding: 8px;" onclick="deleteFriend(${friend.id}, '${friend.username}')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 6h18"></path>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                `;
                friendList.appendChild(friendItem);
            });

            // Load friend requests below friends list
            await loadFriendRequestsInFriendsTab();
        } else {
            friendList.innerHTML = '<p class="empty-state">No friends yet</p>';

            // Still load friend requests even if no friends
            await loadFriendRequestsInFriendsTab();
        }
    } catch (error) {
        console.error('Error loading friends:', error);
        const friendList = document.getElementById('friendList');
        friendList.innerHTML = '<p class="empty-state">No friends yet. Try adding some!</p>';
    }
}

async function loadFriendRequestsInFriendsTab() {
    const response = await fetch('../api/users.php?action=get_friend_requests');
    const data = await response.json();

    const friendList = document.getElementById('friendList');

    if (data.success && data.requests.length > 0) {
        // Add friend requests section
        const requestsSection = document.createElement('div');
        requestsSection.className = 'friend-requests-section';
        requestsSection.innerHTML = '<h4>Friend Requests</h4>';

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
            requestsSection.appendChild(reqItem);
        });

        friendList.appendChild(requestsSection);
    }
}

function openChat(userId, username) {
    currentChatUser = userId;

    // Manage active state in sidebar
    document.querySelectorAll('.chat-item').forEach(item => {
        item.classList.remove('active');
        // Check if the strong tag's text content (which includes username and status) contains the current username
        // This is a simple way to identify the chat item for the current user.
        // A more robust solution might involve data attributes or unique IDs.
        if (item.querySelector('strong') && item.querySelector('strong').textContent.includes(username)) {
            item.classList.add('active');
        }
    });

    // Update sidebar username immediately
    document.getElementById('infoUserName').textContent = username;

    document.getElementById('chatHeader').innerHTML = `
        <div class="chat-header-info">
            <button class="back-btn-mobile" id="mobileBackBtn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
            </button>
            <strong>
                ${username}
                <span id="headerOnlineStatus" class="online-status offline"></span>
            </strong>
        </div>
        <button id="toggleInfoBtn" class="icon-btn" title="Chat Info">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    `;

    const toggleBtn = document.getElementById('toggleInfoBtn');
    if (toggleBtn) toggleBtn.classList.remove('hidden');
    updateHeaderStatus(userId);

    // Add mobile back button listener
    const mobileBackBtn = document.getElementById('mobileBackBtn');
    if (mobileBackBtn) {
        mobileBackBtn.addEventListener('click', () => {
            document.querySelector('.chat-area').classList.remove('active');
        });
    }

    // Activate chat area for mobile
    document.querySelector('.chat-area').classList.add('active');

    // Re-add event listener to the new button
    document.getElementById('toggleInfoBtn').addEventListener('click', toggleChatInfoSidebar);


    document.getElementById('messageInputArea').classList.remove('hidden');

    // Hide sidebar if switching chat
    document.getElementById('chatInfoSidebar').classList.add('hidden');

    if (messageInterval) clearInterval(messageInterval);
    loadMessages();
    messageInterval = setInterval(loadMessages, 2000);

    // Check blocking status
    checkBlockingStatus(userId);
}

async function checkBlockingStatus(userId) {
    const response = await fetch(`../api/users.php?action=get_user_info&user_id=${userId}`);
    const data = await response.json();

    const inputArea = document.getElementById('messageInputArea');
    const blockedMessage = document.getElementById('blockedMessage');

    if (data.success && data.user) {
        if (data.user.is_blocked) {
            // Current user has blocked the other user
            inputArea.classList.add('hidden');
            if (!blockedMessage) {
                const msg = document.createElement('div');
                msg.id = 'blockedMessage';
                msg.style.cssText = 'padding: 15px; text-align: center; border-top: 1px solid #ddd; background: #f9f9f9;';
                msg.innerHTML = `<p style="margin-bottom: 10px;">You have blocked this user.</p>
                                 <button class="btn-primary" onclick="unblockUser(${userId})">Unblock User</button>`;
                inputArea.after(msg);
            } else {
                blockedMessage.innerHTML = `<p style="margin-bottom: 10px;">You have blocked this user.</p>
                                             <button class="btn-primary" onclick="unblockUser(${userId})">Unblock User</button>`;
                blockedMessage.classList.remove('hidden');
            }
        } else if (data.user.blocked_by) {
            // Current user is blocked BY the other user
            inputArea.classList.add('hidden');
            if (!blockedMessage) {
                const msg = document.createElement('div');
                msg.id = 'blockedMessage';
                msg.style.cssText = 'padding: 15px; text-align: center; border-top: 1px solid #ddd; background: #f9f9f9;';
                msg.innerHTML = `<p>This user is not available.</p>`;
                inputArea.after(msg);
            } else {
                blockedMessage.innerHTML = `<p>This user is not available.</p>`;
                blockedMessage.classList.remove('hidden');
            }
        } else {
            // No block
            inputArea.classList.remove('hidden');
            if (blockedMessage) blockedMessage.classList.add('hidden');
        }
    }
}

async function unblockUser(userId) {
    const confirmed = await dialog.confirm('Unblock this user?');
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'unblock_user');
    formData.append('user_id', userId);

    const response = await fetch('../api/users.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();
    if (data.success) {
        dialog.success('User unblocked');
        checkBlockingStatus(userId); // Refresh UI
        loadChats();
    } else {
        dialog.error(data.message || 'Failed to unblock user');
    }
}

async function updateHeaderStatus(userId) {
    const response = await fetch(`../api/users.php?action=get_user_info&user_id=${userId}`);
    const data = await response.json();

    if (data.success && data.user) {
        const statusDot = document.getElementById('headerOnlineStatus');
        if (statusDot) {
            statusDot.className = data.user.is_online ? 'online-status online' : 'online-status offline';

            // Show time next to dot (only if not online and not offline and has time)
            let statusTextElem = document.getElementById('headerStatusText');
            if (!statusTextElem) {
                statusTextElem = document.createElement('span');
                statusTextElem.id = 'headerStatusText';
                statusTextElem.className = 'status-time';
                statusDot.after(statusTextElem);
            }

            // Only show time if user is not currently online and not completely offline
            if (!data.user.is_online && data.user.status_text &&
                data.user.status_text !== 'Offline' && data.user.status_text !== 'Online') {
                statusTextElem.textContent = data.user.status_text;
                statusTextElem.style.display = 'inline';
            } else {
                statusTextElem.style.display = 'none';
            }
        }
    }
}

async function loadMessages() {
    if (!currentChatUser) return;

    try {
        const response = await fetch(`../api/messages.php?action=get&user_id=${currentChatUser}`);
        const data = await response.json();

        const container = document.getElementById('messagesContainer');
        container.innerHTML = '';

        if (data.success && data.messages.length > 0) {
            // In-Chat Message Sound Logic
            // Compare current message count with previous count
            // Also ensure we only play for RECEIVED messages (not own messages)
            const receivedMessages = data.messages.filter(m => m.sender_id == currentChatUser);
            if (notificationsEnabled && receivedMessages.length > lastActiveChatCount) {
                // If this isn't the first load of this chat
                if (lastActiveChatCount > 0) {
                    inChatSound.play().catch(e => console.log('Audio playback blocked'));
                }
            }
            lastActiveChatCount = receivedMessages.length;

            data.messages.forEach(msg => {
                const msgDiv = document.createElement('div');
                const isSent = msg.sender_id != currentChatUser;
                msgDiv.className = isSent ? 'message sent' : 'message received';

                let content = '';

                // Handle different message types
                if (msg.message_type === 'image') {
                    content = `
                        <div class="message-file">
                            <img src="../${msg.file_path}" alt="${msg.file_name}" onclick="openImageLightbox('../${msg.file_path}')" style="max-width: 250px; max-height: 250px; cursor: pointer; border-radius: 8px;">
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
                    const tickColor = msg.is_read ? 'tick read' : 'tick';
                    if (msg.is_read || msg.is_delivered) {
                        // Double tick for delivered and read
                        statusIcon = `
                            <div class="status-ticks">
                                <svg class="${tickColor}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <svg class="${tickColor}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: -10px;">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </div>`;
                    } else {
                        // Single tick for sent
                        statusIcon = `
                            <div class="status-ticks">
                                <svg class="tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </div>`;
                    }
                }

                msgDiv.innerHTML = `
                    ${content}
                    <span class="time">${new Date(msg.created_at).toLocaleTimeString()} ${statusIcon}</span>
                `;
                container.appendChild(msgDiv);
            });
            container.scrollTop = container.scrollHeight;
        } else if (data.success && data.messages.length === 0) {
            lastActiveChatCount = 0;
            container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No messages yet. Start the conversation!</p>';
        }
    } catch (error) {
        console.error('Load messages error:', error);
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

    document.getElementById('searchUser').addEventListener('input', async function (e) {
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
        <input type="text" id="searchFriend" placeholder="Search users..." style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">
        <div id="friendSearchResults" style="max-height: 300px; overflow-y: auto;"></div>
    `;
    document.getElementById('modal').classList.remove('hidden');

    const searchInput = document.getElementById('searchFriend');
    const results = document.getElementById('friendSearchResults');

    searchInput.addEventListener('input', async function (e) {
        const search = e.target.value.trim();

        if (search.length < 2) {
            results.innerHTML = '<p style="padding: 10px; color: #999; text-align: center;">Type at least 2 characters to search</p>';
            return;
        }

        results.innerHTML = '<p style="padding: 10px; color: #999; text-align: center;">Searching...</p>';

        try {
            const response = await fetch(`../api/users.php?action=search&search=${encodeURIComponent(search)}`);

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            results.innerHTML = '';

            if (data.success && data.users && data.users.length > 0) {
                data.users.forEach(user => {
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user-result';
                    userDiv.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #eee; cursor: pointer;';
                    userDiv.innerHTML = `
                        <span style="font-weight: 500;">${user.username}</span>
                        <button onclick="addFriend(${user.id})" style="background: #0084ff; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer;">Add</button>
                    `;
                    userDiv.addEventListener('mouseenter', function () {
                        this.style.background = '#f5f5f5';
                    });
                    userDiv.addEventListener('mouseleave', function () {
                        this.style.background = 'white';
                    });
                    results.appendChild(userDiv);
                });
            } else {
                results.innerHTML = '<p style="padding: 20px; color: #666; text-align: center;">No users found</p>';
            }
        } catch (error) {
            console.error('Search error:', error);
            results.innerHTML = '<p style="padding: 20px; color: #666; text-align: center;">Error searching. Please try again.</p>';
        }
    });

    // Focus on search input
    setTimeout(() => searchInput.focus(), 100);
}

async function addFriend(friendId) {
    const formData = new FormData();
    formData.append('action', 'send_friend_request');
    formData.append('friend_id', friendId);

    try {
        const response = await fetch('../api/users.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            dialog.success(data.message || 'Friend request sent!');
            closeModal();
            loadFriends(); // Refresh friends list
        } else {
            dialog.error(data.message || 'Failed to send friend request');
        }
    } catch (error) {
        console.error('Error adding friend:', error);
        dialog.error('Failed to send friend request. Please try again.');
    }
}

async function loadFriendRequests() {
    const requestList = document.getElementById('requestList');
    requestList.innerHTML = '<h3 style="padding: 15px; margin: 0;">Message Requests</h3>';

    // Load message requests (messages from non-friends)
    const response = await fetch('../api/users.php?action=get_message_requests');
    const data = await response.json();

    if (data.success && data.requests.length > 0) {
        data.requests.forEach(req => {
            const reqItem = document.createElement('div');
            reqItem.className = 'request-item';

            const onlineStatus = req.is_online ?
                '<span class="online-status online"></span>' :
                '<span class="online-status offline"></span>';

            reqItem.innerHTML = `
                <div class="request-info">
                    <span>${req.username} ${onlineStatus}</span>
                    <p class="request-message">${req.last_message}</p>
                    <small>${new Date(req.created_at).toLocaleString()}</small>
                </div>
                <div class="request-actions">
                    <button class="accept-btn" onclick="acceptMessageRequest(${req.user_id}, '${req.username}')">Accept</button>
                    <button class="reject-btn" onclick="rejectMessageRequest(${req.user_id})">Delete</button>
                </div>
            `;
            requestList.appendChild(reqItem);
        });
    } else {
        requestList.innerHTML += '<p class="empty-state">No message requests</p>';
    }
}

async function acceptMessageRequest(userId, username) {
    // Accept means add as friend and open chat
    const formData = new FormData();
    formData.append('action', 'send_friend_request');
    formData.append('friend_id', userId);

    const response = await fetch('../api/users.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();

    // Delete the message_request notification for this user
    const deleteNotifData = new FormData();
    deleteNotifData.append('action', 'delete_message_request_notification');
    deleteNotifData.append('from_user_id', userId);

    await fetch('../api/notifications.php', {
        method: 'POST',
        body: deleteNotifData
    });

    // Open chat regardless of friend request status
    openChat(userId, username);

    // Refresh the message requests list to remove this user
    loadFriendRequests();

    // Also refresh friends list and notifications
    loadFriends();
    loadNotifications();
}

async function rejectMessageRequest(userId) {
    // Delete all messages from this user
    const formData = new FormData();
    formData.append('action', 'delete_message_request');
    formData.append('user_id', userId);

    const response = await fetch('../api/users.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();
    if (data.success) {
        dialog.success('Message request deleted');
        loadFriendRequests();
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
            } else if (notif.type === 'message_request') {
                message = `${notif.from_username} sent you a message request`;
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
        <div class="settings-menu">
            <button class="settings-menu-item" id="menuEditProfile">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Edit Profile
            </button>
            <button class="settings-menu-item" id="menuNotifications">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                View Notifications
            </button>
            <button class="settings-menu-item danger" id="menuDeleteAccount">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18"></path>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Delete Account
            </button>
            <button class="settings-menu-item" id="menuLogout">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Logout
            </button>
            <div class="settings-menu-item no-hover" style="display: flex; justify-content: space-between; align-items: center; cursor: default;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 5L6 9H2v6h4l5 4V5z"></path>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path>
                        <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                    </svg>
                    Notification Sounds
                </div>
                <label class="switch">
                    <input type="checkbox" id="notifToggle" ${notificationsEnabled ? 'checked' : ''}>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    `;
    document.getElementById('modal').classList.remove('hidden');

    document.getElementById('menuEditProfile').addEventListener('click', showEditProfile);
    document.getElementById('menuNotifications').addEventListener('click', showNotificationsModal);
    document.getElementById('menuDeleteAccount').addEventListener('click', showDeleteAccountConfirm);
    document.getElementById('menuLogout').addEventListener('click', logout);

    const notifToggle = document.getElementById('notifToggle');
    notifToggle.addEventListener('change', async function () {
        notificationsEnabled = this.checked;
        const formData = new FormData();
        formData.append('action', 'toggle_notifications');
        formData.append('enabled', notificationsEnabled ? '1' : '0');

        try {
            const response = await fetch('../api/settings.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (!data.success) {
                dialog.error('Failed to save notification settings');
                // Revert toggle if failed
                notificationsEnabled = !notificationsEnabled;
                this.checked = notificationsEnabled;
            } else {
                // Play a test sound to confirm
                if (notificationsEnabled) {
                    newMessageSound.play().catch(e => console.log('Autoplay blocked'));
                }
            }
        } catch (error) {
            console.error('Settings error:', error);
        }
    });
}

function showEditProfile() {
    document.getElementById('modalTitle').textContent = 'Edit Profile';
    document.getElementById('modalBody').innerHTML = `
        <button class="back-btn" id="settingsBackBtn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Settings
        </button>
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
        </div>
    `;

    document.getElementById('settingsBackBtn').addEventListener('click', showSettingsModal);
    document.getElementById('uploadBtn').addEventListener('click', uploadProfileImage);
    document.getElementById('updateUsernameBtn').addEventListener('click', updateUsername);
    document.getElementById('updateEmailBtn').addEventListener('click', updateEmail);
    document.getElementById('updatePasswordBtn').addEventListener('click', updatePassword);
}

function toggleChatInfoSidebar() {
    const sidebar = document.getElementById('chatInfoSidebar');
    sidebar.classList.toggle('hidden');

    if (!sidebar.classList.contains('hidden') && currentChatUser) {
        console.log('Loading chat media for user:', currentChatUser); // Debug
        loadChatMedia(currentChatUser);
        attachChatActionListeners();
    } else if (!sidebar.classList.contains('hidden') && !currentChatUser) {
        console.error('No current chat user selected!'); // Debug
    }
}

function attachChatActionListeners() {
    document.getElementById('blockUserBtn').onclick = blockUser;
    document.getElementById('reportUserBtn').onclick = reportUser;
    document.getElementById('deleteChatBtn').onclick = deleteChat;
}



async function blockUser() {
    if (!currentChatUser) return;

    const confirmed = await dialog.confirm('Block this user? They will not be able to send you messages.');
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'block_user');
    formData.append('user_id', currentChatUser);

    const response = await fetch('../api/users.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();
    if (data.success) {
        dialog.success('User blocked successfully');
        toggleChatInfoSidebar();
        currentChatUser = null;
        document.getElementById('chatHeader').innerHTML = '<h3>Select a chat</h3>';
        document.getElementById('messageInputArea').classList.add('hidden');
        loadChats();
    } else {
        dialog.error(data.message || 'Failed to block user');
    }
}

async function reportUser() {
    if (!currentChatUser) return;

    const reason = prompt('Please provide a reason for reporting this user:');
    if (!reason || reason.trim() === '') return;

    const formData = new FormData();
    formData.append('action', 'report_user');
    formData.append('user_id', currentChatUser);
    formData.append('reason', reason);

    const response = await fetch('../api/users.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();
    if (data.success) {
        dialog.success('User reported successfully. Admin will review your report.');
        toggleChatInfoSidebar();
    } else {
        dialog.error(data.message || 'Failed to report user');
    }
}

async function deleteChat() {
    if (!currentChatUser) return;

    const confirmed = await dialog.confirm('Delete this entire chat? This will remove all messages and cannot be undone.');
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'delete_chat');
    formData.append('user_id', currentChatUser);

    const response = await fetch('../api/messages.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();
    if (data.success) {
        dialog.success('Chat deleted successfully');
        toggleChatInfoSidebar();
        currentChatUser = null;
        document.getElementById('chatHeader').innerHTML = '<h3>Select a chat</h3>';
        document.getElementById('messageInputArea').classList.add('hidden');
        loadChats();
    } else {
        dialog.error('Failed to delete chat');
    }
}

async function loadChatMedia(userId) {
    console.log('loadChatMedia called with userId:', userId); // Debug

    const response = await fetch(`../api/messages.php?action=get_media&user_id=${userId}`);
    const data = await response.json();

    if (data.success) {
        const mediaGrid = document.getElementById('mediaGrid');
        const docsList = document.getElementById('docsList');
        const mediaCount = document.getElementById('mediaCount');

        mediaGrid.innerHTML = '';
        docsList.innerHTML = '';
        mediaCount.textContent = data.media.length;

        // Fetch partner details
        const userResponse = await fetch(`../api/users.php?action=get_user_info&user_id=${userId}`);
        const userData = await userResponse.json();

        console.log('User data received:', userData); // Debug

        if (userData.success && userData.user) {
            console.log('Setting username to:', userData.user.username); // Debug
            document.getElementById('infoUserName').textContent = userData.user.username;
            if (userData.user.profile_image) {
                document.getElementById('infoUserProfileImg').src = '../' + userData.user.profile_image;
            } else {
                document.getElementById('infoUserProfileImg').src = '../assets/images/default-avatar.png';
            }
        }

        data.media.forEach(item => {
            if (item.message_type === 'image') {
                const img = document.createElement('div');
                img.className = 'grid-item';
                img.innerHTML = `<img src="../${item.file_path}" alt="${item.file_name}" onclick="window.open('../${item.file_path}', '_blank')">`;
                mediaGrid.appendChild(img);
            } else if (item.message_type === 'video') {
                const video = document.createElement('div');
                video.className = 'grid-item';
                video.innerHTML = `<video src="../${item.file_path}" onclick="window.open('../${item.file_path}', '_blank')"></video>`;
                mediaGrid.appendChild(video);
            } else if (item.message_type === 'document') {
                const doc = document.createElement('div');
                doc.className = 'doc-item';
                doc.onclick = () => window.open('../${item.file_path}', '_blank');
                doc.innerHTML = `
                    <div class="doc-icon">📄</div>
                    <div class="doc-info">
                        <span class="doc-name">${item.file_name}</span>
                        <span class="doc-date">${new Date(item.created_at).toLocaleDateString()}</span>
                    </div>
                `;
                docsList.appendChild(doc);
            }
        });

        if (data.media.length === 0) {
            mediaGrid.innerHTML = '<p style="grid-column: span 3; color: #666; font-size: 0.8rem;">No media shared yet</p>';
        }
    }
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

async function deleteFriend(friendId, username) {
    const confirmed = await dialog.confirm(`Are you sure you want to remove ${username} from your friends?`);
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'remove_friend');
    formData.append('user_id', friendId);

    try {
        const response = await fetch('../api/users.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            dialog.success('Friend removed successfully');
            loadFriends();
            loadChats(); // Refresh chat list if they were chatting
        } else {
            dialog.error(data.message || 'Failed to remove friend');
        }
    } catch (error) {
        console.error('Delete friend error:', error);
        dialog.error('Failed to remove friend');
    }
}

async function logout() {
    // Update online status to offline before logout
    const formData = new FormData();
    formData.append('action', 'update_online_status');
    formData.append('status', 'offline');

    await fetch('../api/auth.php', {
        method: 'POST',
        body: formData
    });

    // Now logout
    const logoutData = new FormData();
    logoutData.append('action', 'logout');

    await fetch('../api/auth.php', {
        method: 'POST',
        body: logoutData
    });

    window.location.href = '../index.php';
}


// ============================================
// NEW FEATURES: File Upload, Emoji, Online Status
// ============================================

// File upload handling
let selectedFile = null;

// Initialize new features
document.addEventListener('DOMContentLoaded', function () {
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
                <img src="${e.target.result}" alt="Preview" style="max-width: 60px; max-height: 60px; border-radius: 4px; border: 1px solid var(--neon-cyan); box-shadow: 0 0 10px var(--neon-cyan);">
                <div class="file-info">
                    <div class="file-name" style="color: var(--neon-cyan); font-family: var(--font-header); font-size: 11px;">${file.name}</div>
                    <div class="file-size" style="color: white; font-family: var(--font-mono); font-size: 10px;">${fileSize} MB</div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        const icon = file.type.startsWith('video/') ? '🎥' : '📄';
        preview.innerHTML = `
            <div style="font-size: 40px; color: var(--neon-cyan); text-shadow: 0 0 10px var(--neon-cyan);">${icon}</div>
            <div class="file-info">
                <div class="file-name" style="color: var(--neon-cyan); font-family: var(--font-header); font-size: 11px;">${file.name}</div>
                <div class="file-size" style="color: white; font-family: var(--font-mono); font-size: 10px;">${fileSize} MB</div>
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

// Sidebar resizing logic
function initSidebarResize() {
    const resizer = document.getElementById('chatResizer');
    const sidebar = document.getElementById('chatSidebar');
    let isResizing = false;

    if (!resizer || !sidebar) return;

    // Load saved width
    const savedWidth = localStorage.getItem('sidebarWidth');
    if (savedWidth) {
        sidebar.style.width = savedWidth + 'px';
    }

    resizer.addEventListener('mousedown', function (e) {
        isResizing = true;
        document.body.style.cursor = 'col-resize';
        resizer.classList.add('active');
        // Prevent text selection while resizing
        document.body.style.userSelect = 'none';
    });

    document.addEventListener('mousemove', function (e) {
        if (!isResizing) return;

        // Disable resizing on mobile
        if (window.innerWidth <= 768) return;

        let newWidth = e.clientX;

        // Boundaries
        if (newWidth < 260) newWidth = 260; // Increased minWidth to prevent element clipping
        if (newWidth > 600) newWidth = 600;

        sidebar.style.width = newWidth + 'px';
        localStorage.setItem('sidebarWidth', newWidth);
    });

    document.addEventListener('mouseup', function (e) {
        if (isResizing) {
            isResizing = false;
            document.body.style.cursor = 'default';
            resizer.classList.remove('active');
            document.body.style.userSelect = 'auto';
        }
    });
}

// Toggle emoji picker
function toggleEmojiPicker() {
    const picker = document.getElementById('emojiPicker');
    picker.classList.toggle('hidden');

    if (!picker.classList.contains('hidden')) {
        const grid = document.getElementById('emojiGrid');
        grid.innerHTML = '';

        const emojis = ['😀', '😂', '😍', '😊', '😎', '😢', '😡', '👍', '👎', '❤️', '🎉', '🔥', '⭐', '✅', '❌', '💯', '🙏', '👏', '💪', '🤔', '😴', '🤗', '😱', '🤩', '😇'];

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

// Image Lightbox Functions
function openImageLightbox(imageSrc) {
    const lightbox = document.getElementById('imageLightbox');
    const lightboxImage = document.getElementById('lightboxImage');
    lightboxImage.src = imageSrc;
    lightbox.classList.add('active');
}

function closeImageLightbox() {
    const lightbox = document.getElementById('imageLightbox');
    lightbox.classList.remove('active');
}

// Initialize lightbox close handlers
document.addEventListener('DOMContentLoaded', function () {
    const lightbox = document.getElementById('imageLightbox');
    const closeLightboxBtn = document.querySelector('.close-lightbox');

    if (closeLightboxBtn) {
        closeLightboxBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeImageLightbox();
        });
    }

    if (lightbox) {
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                closeImageLightbox();
            }
        });
    }

    // ESC key to close lightbox
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeImageLightbox();
        }
    });
});

console.log('✅ New features loaded: File Upload, Emoji Picker, Online Status, Dialog Boxes, Image Lightbox');
