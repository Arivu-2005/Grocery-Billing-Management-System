<?php
/**
 * Delete Category
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

    $categoryId = (int)($_POST['category_id'] ?? 0);
    if ($categoryId <= 0) {
        setFlashMessage('danger', 'Invalid category ID.');
    } else {
        try {
            // Check if assigned to any product
            $chk = $pdo->prepare("SELECT COUNT(*) FROM `products` WHERE `category_id` = ?");
            $chk->execute([$categoryId]);
            $assignedCount = (int)$chk->fetchColumn();

            if ($assignedCount > 0) {
                setFlashMessage('danger', "Cannot delete this category because {$assignedCount} product(s) are currently assigned to it. Please reassign or delete the products first.");
            } else {
                $del = $pdo->prepare("DELETE FROM `categories` WHERE `id` = ?");
                $del->execute([$categoryId]);
                setFlashMessage('success', 'Category deleted successfully!');
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Error deleting category: ' . $e->getMessage());
        }
    }
}

header("Location: " . BASE_URL . "/categories/index.php");
exit;
