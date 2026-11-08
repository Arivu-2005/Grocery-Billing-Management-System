<?php
/**
 * Authentication, Session & Security Helpers
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.cookie_path', '/');
    }
    if (!@session_start()) {
        @session_save_path(sys_get_temp_dir());
        @session_start();
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if the user is currently logged in as admin
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && !empty($_SESSION['admin_id']);
}

/**
 * Require login. If not authenticated, redirect to login page.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = "Please log in to access the system.";
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

/**
 * Get current logged in admin user data from session
 * @return array
 */
function getLoggedInUser() {
    return [
        'id'        => $_SESSION['admin_id'] ?? null,
        'username'  => $_SESSION['admin_username'] ?? 'Admin',
        'full_name' => $_SESSION['admin_name'] ?? 'Store Manager',
        'email'     => $_SESSION['admin_email'] ?? '',
        'role'      => $_SESSION['admin_role'] ?? 'admin'
    ];
}

/**
 * Generate CSRF token and store in session
 * @return string
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * Supports both primary token and recent previous token to tolerate rapid submits and tab switches
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken($token) {
    if (empty($token)) {
        return false;
    }
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    if (!empty($_SESSION['csrf_token_prev']) && hash_equals($_SESSION['csrf_token_prev'], $token)) {
        return true;
    }
    return false;
}

/**
 * Output hidden input field for CSRF token
 * @return string
 */
function renderCsrfInput() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Set flash message
 * @param string $type success|danger|warning|info
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Display flash message if present
 * @return string
 */
function displayFlashMessage() {
    $output = '';
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        $type = e($msg['type']);
        $text = e($msg['message']);
        $icon = ($type === 'success') ? 'bi-check-circle-fill' : (($type === 'danger') ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');
        
        $output = <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
            <i class="bi {$icon} me-2 fs-5"></i>
            <div>{$text}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
HTML;
        unset($_SESSION['flash_message']);
    }
    return $output;
}
