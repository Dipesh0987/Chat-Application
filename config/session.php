<?php
// Centralized session handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>