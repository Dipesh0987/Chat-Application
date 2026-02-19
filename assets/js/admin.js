document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    document.getElementById('logoutBtn').addEventListener('click', logout);
});

async function loadUsers() {
    try {
        const response = await fetch('../api/admin.php?action=get_users');
        const data = await response.json();
        
        const userList = document.getElementById('userList');
        userList.innerHTML = '';
        
        if (data.success && data.users.length > 0) {
            const table = document.createElement('table');
            table.className = 'user-table';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Warnings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody"></tbody>
            `;
            userList.appendChild(table);
            
            const tbody = document.getElementById('userTableBody');
            data.users.forEach(user => {
                const status = user.is_banned ? `Banned until ${user.ban_until}` : 'Active';
                const profileImg = user.profile_image 
                    ? `../${user.profile_image}` 
                    : '../assets/images/default-avatar.svg';
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><img src="${profileImg}" alt="Profile" class="admin-profile-img"></td>
                    <td>${user.id}</td>
                    <td>${user.username}</td>
                    <td>${user.email}</td>
                    <td>${user.warnings}</td>
                    <td>${status}</td>
                    <td>
                        <button onclick="warnUser(${user.id})" class="btn-warn">Warn</button>
                        <button onclick="banUser(${user.id})" class="btn-ban">Ban</button>
                        <button onclick="deleteUser(${user.id})" class="btn-delete">Delete</button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            userList.innerHTML = '<p>No users found</p>';
        }
    } catch (error) {
        console.error('Error loading users:', error);
        const userList = document.getElementById('userList');
        userList.innerHTML = '<p style="color: red;">Error loading users. Check console for details.</p>';
    }
}

async function warnUser(userId) {
    if (!confirm('Issue warning to this user?')) return;
    
    const formData = new FormData();
    formData.append('action', 'warn_user');
    formData.append('user_id', userId);
    
    try {
        const response = await fetch('../api/admin.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            alert(data.message);
            loadUsers();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error warning user:', error);
        alert('Failed to warn user');
    }
}

async function banUser(userId) {
    if (!confirm('Ban this user for 3 days?')) return;
    
    const formData = new FormData();
    formData.append('action', 'ban_user');
    formData.append('user_id', userId);
    
    try {
        const response = await fetch('../api/admin.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            alert(data.message);
            loadUsers();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error banning user:', error);
        alert('Failed to ban user');
    }
}

async function deleteUser(userId) {
    if (!confirm('Permanently delete this user?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);
    
    try {
        const response = await fetch('../api/admin.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            alert(data.message);
            loadUsers();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        alert('Failed to delete user');
    }
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
