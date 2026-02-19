<?php
require_once 'config/session.php';
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat App - Register</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container">
        <div class="auth-box">
            <h2>Register</h2>
            <div id="message"></div>
            <form id="registerForm">
                <input type="text" id="username" name="username" placeholder="Username" required minlength="3">
                <small id="usernameStatus" style="display:block;margin-top:-10px;margin-bottom:10px;"></small>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required minlength="6">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="submit">Register</button>
            </form>
            <p>Already have an account? <a href="index.php">Login here</a></p>
        </div>
    </div>
    <script src="assets/js/dialog.js"></script>
    <script src="assets/js/auth.js"></script>
</body>

</html>