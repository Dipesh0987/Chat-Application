document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const messageDiv = document.getElementById('message');
    const usernameInput = document.getElementById('username');
    let usernameTimeout;

    // Username availability check
    if (usernameInput) {
        usernameInput.addEventListener('input', function(e) {
            clearTimeout(usernameTimeout);
            const username = e.target.value.trim();
            const statusEl = document.getElementById('usernameStatus');
            
            if (username.length < 3) {
                statusEl.textContent = '';
                return;
            }
            
            statusEl.textContent = 'Checking...';
            statusEl.style.color = '#666';
            
            usernameTimeout = setTimeout(async () => {
                const formData = new FormData();
                formData.append('action', 'check_username');
                formData.append('username', username);
                
                try {
                    const response = await fetch('api/auth.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.available) {
                        statusEl.textContent = '✓ Username available';
                        statusEl.style.color = '#28a745';
                    } else {
                        statusEl.textContent = '✗ Username already taken';
                        statusEl.style.color = '#dc3545';
                    }
                } catch (error) {
                    statusEl.textContent = '';
                }
            }, 500);
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(loginForm);
            formData.append('action', 'login');

            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    if (typeof dialog !== 'undefined') {
                        dialog.success('Login successful!');
                    }
                    setTimeout(() => {
                        if (data.is_admin) {
                            window.location.href = 'admin/dashboard.php';
                        } else {
                            window.location.href = 'user/chat.php';
                        }
                    }, 500);
                } else {
                    if (typeof dialog !== 'undefined') {
                        dialog.error(data.message, 'Login Failed');
                    } else {
                        showMessage(data.message, 'error');
                    }
                }
            } catch (error) {
                if (typeof dialog !== 'undefined') {
                    dialog.error('An error occurred. Please try again.');
                } else {
                    showMessage('An error occurred', 'error');
                }
            }
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = registerForm.querySelector('[name="password"]').value;
            const confirmPassword = registerForm.querySelector('[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                if (typeof dialog !== 'undefined') {
                    dialog.error('Passwords do not match!', 'Registration Error');
                } else {
                    showMessage('Passwords do not match', 'error');
                }
                return;
            }
            
            if (password.length < 6) {
                if (typeof dialog !== 'undefined') {
                    dialog.error('Password must be at least 6 characters long!', 'Registration Error');
                } else {
                    showMessage('Password must be at least 6 characters', 'error');
                }
                return;
            }
            
            const formData = new FormData(registerForm);
            formData.append('action', 'register');

            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    if (typeof dialog !== 'undefined') {
                        dialog.success(data.message || 'Registration successful! Redirecting to login...', 'Success');
                    } else {
                        showMessage('Registration successful! Redirecting to login...', 'success');
                    }
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else {
                    if (typeof dialog !== 'undefined') {
                        dialog.error(data.message, 'Registration Failed');
                    } else {
                        showMessage(data.message, 'error');
                    }
                }
            } catch (error) {
                if (typeof dialog !== 'undefined') {
                    dialog.error('An error occurred. Please try again.');
                } else {
                    showMessage('An error occurred', 'error');
                }
            }
        });
    }

    function showMessage(message, type) {
        if (messageDiv) {
            messageDiv.textContent = message;
            messageDiv.className = 'message ' + type;
            messageDiv.style.display = 'block';
        }
    }
});
