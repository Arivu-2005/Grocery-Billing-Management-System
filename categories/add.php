<?php
/**
 * Add Category
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security token mismatch.');
        header("Location: " . BASE_URL . "/categories/index.php");
        exit;
    }

    $categoryName = sanitizeInput($_POST['category_name'] ?? '');
    $description  = sanitizeInput($_POST['description'] ?? '');

    if (empty($categoryName)) {
        setFlashMessage('danger', 'Category name is required.');
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM `categories` WHERE `category_name` = ?");
            $check->execute([$categoryName]);
            if ($check->fetch()) {
                setFlashMessage('danger', 'A category with this name already exists.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO `categories` (`category_name`, `description`) VALUES (?, ?)");
                $stmt->execute([$categoryName, $description]);
                setFlashMessage('success', "Category '{$categoryName}' added successfully!");
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Database error: ' . $e->getMessage());
        }
    }
    header("Location: " . BASE_URL . "/categories/index.php");
    exit;
}

// If accessed directly via GET, redirect to categories index
header("Location: " . BASE_URL . "/categories/index.php");
exit;
