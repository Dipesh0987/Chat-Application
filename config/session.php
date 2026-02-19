<?php
// config/session.php
// Redirect session files to a local directory to avoid permission issues with XAMPP's default tmp folder
$session_path = dirname(__DIR__) . '/sessions';

if (!is_dir($session_path)) {
    mkdir($session_path, 0777, true);
}

session_save_path($session_path);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>