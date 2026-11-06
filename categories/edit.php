<?php
/**
 * Edit Category
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security validation failed.');
        header("Location: " . BASE_URL . "/categories/index.php");
        exit;
    }

    $categoryId   = (int)($_POST['category_id'] ?? 0);
    $categoryName = sanitizeInput($_POST['category_name'] ?? '');
    $description  = sanitizeInput($_POST['description'] ?? '');

    if ($categoryId <= 0 || empty($categoryName)) {
        setFlashMessage('danger', 'Invalid category data provided.');
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM `categories` WHERE `category_name` = ? AND `id` != ?");
            $check->execute([$categoryName, $categoryId]);
            if ($check->fetch()) {
                setFlashMessage('danger', 'Another category with this name already exists.');
            } else {
                $upd = $pdo->prepare("UPDATE `categories` SET `category_name` = ?, `description` = ? WHERE `id` = ?");
                $upd->execute([$categoryName, $description, $categoryId]);
                setFlashMessage('success', "Category '{$categoryName}' updated successfully!");
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Database error: ' . $e->getMessage());
        }
    }
    header("Location: " . BASE_URL . "/categories/index.php");
    exit;
}

header("Location: " . BASE_URL . "/categories/index.php");
exit;
