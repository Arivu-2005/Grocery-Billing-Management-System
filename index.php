<?php
/**
 * Root Router / Entry Point
 * Grocery Billing Management System
 */

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
} else {
    header("Location: " . BASE_URL . "/login.php");
}
exit;
