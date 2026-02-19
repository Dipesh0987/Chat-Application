<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Check for hardcoded admin
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['user_id'] = 0;
        $_SESSION['username'] = 'admin';
        $_SESSION['is_admin'] = true;
        echo json_encode(['success' => true, 'is_admin' => true]);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, password, is_banned, ban_until FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if user is banned
        if ($user['is_banned']) {
            $ban_until = strtotime($user['ban_until']);
            if ($ban_until > time()) {
                echo json_encode(['success' => false, 'message' => 'Your account is banned until ' . date('Y-m-d H:i:s', $ban_until)]);
                exit();
            } else {
                // Unban user
                $update = "UPDATE users SET is_banned = FALSE, ban_until = NULL WHERE id = :id";
                $stmt_update = $db->prepare($update);
                $stmt_update->bindParam(':id', $user['id']);
                $stmt_update->execute();
            }
        }
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = false;
            echo json_encode(['success' => true, 'is_admin' => false]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if username already exists
    $check_username = "SELECT id FROM users WHERE username = :username";
    $stmt_check = $db->prepare($check_username);
    $stmt_check->bindParam(':username', $username);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists. Please try another one.']);
        exit();
    }
    
    // Check if email already exists
    $check_email = "SELECT id FROM users WHERE email = :email";
    $stmt_check_email = $db->prepare($check_email);
    $stmt_check_email->bindParam(':email', $email);
    $stmt_check_email->execute();
    
    if ($stmt_check_email->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists. Please use another email.']);
        exit();
    }
    
    $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
    $stmt = $db->prepare($query);
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    
    try {
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Registration successful! You can now login.']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
}

if ($action === 'check_username') {
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['available' => false, 'message' => 'Username cannot be empty']);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['available' => false, 'message' => 'Username already taken']);
    } else {
        echo json_encode(['available' => true, 'message' => 'Username available']);
    }
}

if ($action === 'update_online_status') {
    $status = $_POST['status'] ?? 'offline';
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if ($user_id > 0) {
        $database = new Database();
        $db = $database->getConnection();
        
        $is_online = ($status === 'online') ? 1 : 0;
        $query = "UPDATE users SET is_online = :is_online, last_seen = NOW() WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':is_online', $is_online);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}

if ($action === 'logout') {
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if ($user_id > 0) {
        $database = new Database();
        $db = $database->getConnection();
        
        // Set user offline
        $query = "UPDATE users SET is_online = FALSE, last_seen = NOW() WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }
    
    session_destroy();
    echo json_encode(['success' => true]);
}
?>
