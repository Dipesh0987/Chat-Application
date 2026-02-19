<?php
require_once '../config/session.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="admin-container">
        <div class="admin-header">
            <h2>Admin Dashboard</h2>
            <button id="logoutBtn">Logout</button>
        </div>
        <div class="admin-content">
            <h3>User Management</h3>
            <div id="userList"></div>
        </div>
    </div>
    <script src="../assets/js/admin.js"></script>
</body>

</html>