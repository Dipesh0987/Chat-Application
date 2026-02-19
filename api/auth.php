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
    
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
    $stmt = $db->prepare($query);
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    
    try {
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
    }
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
}
?>
