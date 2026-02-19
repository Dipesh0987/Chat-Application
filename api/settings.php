<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Enable error logging for debugging
error_log("Settings.php called with action: " . ($_GET['action'] ?? $_POST['action'] ?? 'none'));

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($action === 'get_profile') {
    $query = "SELECT id, username, email, profile_image, created_at FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'user' => $user]);
}

if ($action === 'upload_profile_image') {
    error_log("Upload profile image called");

    if (!isset($_FILES['profile_image'])) {
        error_log("No file in FILES array");
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit();
    }

    $file = $_FILES['profile_image'];
    error_log("File received: " . $file['name'] . ", size: " . $file['size'] . ", error: " . $file['error']);

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        $error_msg = $error_messages[$file['error']] ?? 'Unknown upload error';
        error_log("Upload error: " . $error_msg);
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit();
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF']);
        exit();
    }

    if ($file['size'] > 5000000) { // 5MB
        echo json_encode(['success' => false, 'message' => 'File too large. Max size: 5MB']);
        exit();
    }

    $upload_dir = '../uploads/profiles/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit();
        }
    }

    $new_filename = $user_id . '_' . time() . '.' . $ext;
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Delete old profile image
        $query = "SELECT profile_image FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $old_image = $result ? $result['profile_image'] : null;

        if ($old_image && file_exists('../' . $old_image)) {
            @unlink('../' . $old_image);
        }

        // Update database
        $image_path = 'uploads/profiles/' . $new_filename;
        $query = "UPDATE users SET profile_image = :image WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':image', $image_path);
        $stmt->bindParam(':user_id', $user_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'image_path' => $image_path, 'message' => 'Profile image uploaded successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Check directory permissions']);
    }
    exit();
}

if ($action === 'delete_account') {
    $password = $_POST['password'] ?? '';

    // Verify password
    $query = "SELECT password FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
        exit();
    }

    // Delete profile image
    $query = "SELECT profile_image FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $image = $stmt->fetch(PDO::FETCH_ASSOC)['profile_image'];

    if ($image && file_exists('../' . $image)) {
        unlink('../' . $image);
    }

    // Delete user (cascade will handle related records)
    $query = "DELETE FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Account deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete account']);
    }
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
}

if ($action === 'update_username') {
    $username = trim($_POST['username'] ?? '');

    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username cannot be empty']);
        exit();
    }

    // Check if username already exists
    $check = "SELECT id FROM users WHERE username = :username AND id != :user_id";
    $stmt_check = $db->prepare($check);
    $stmt_check->bindParam(':username', $username);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->execute();

    if ($stmt_check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        exit();
    }

    $query = "UPDATE users SET username = :username WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        $_SESSION['username'] = $username;
        echo json_encode(['success' => true, 'message' => 'Username updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update username']);
    }
}

if ($action === 'update_email') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
    }

    // Check if email already exists
    $check = "SELECT id FROM users WHERE email = :email AND id != :user_id";
    $stmt_check = $db->prepare($check);
    $stmt_check->bindParam(':email', $email);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->execute();

    if ($stmt_check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        exit();
    }

    $query = "UPDATE users SET email = :email WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Email updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update email']);
    }
}

if ($action === 'update_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($current_password) || empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    // Verify current password
    $query = "SELECT password FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }

    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $query = "UPDATE users SET password = :password WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    }
}
?>