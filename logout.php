<?php
/**
 * Admin Logout Page
 * Grocery Billing Management System
 */

require_once __DIR__ . '/includes/auth.php';

// Unset all session values
$_SESSION = [];

// Destroy session cookie if exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Restart new session to pass flash message
session_start();
$_SESSION['flash_message'] = [
    'type' => 'success',
    'message' => 'You have been successfully logged out.'
];

header("Location: " . BASE_URL . "/login.php");
exit;
