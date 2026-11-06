<?php
/**
 * Delete Product
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security validation failed.');
        header("Location: " . BASE_URL . "/products/index.php");
        exit;
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId <= 0) {
        setFlashMessage('danger', 'Invalid product selected.');
    } else {
        try {
            // Check if product is in sale_items
            $saleCheck = $pdo->prepare("SELECT COUNT(*) FROM `sale_items` WHERE `product_id` = ?");
            $saleCheck->execute([$productId]);
            $count = (int)$saleCheck->fetchColumn();

            if ($count > 0) {
                setFlashMessage('danger', "Cannot delete this product because it is referenced in {$count} past invoice(s). To preserve accounting records, products with sales cannot be deleted.");
            } else {
                $del = $pdo->prepare("DELETE FROM `products` WHERE `id` = ?");
                $del->execute([$productId]);
                setFlashMessage('success', 'Product deleted successfully!');
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Error deleting product: ' . $e->getMessage());
        }
    }
}

header("Location: " . BASE_URL . "/products/index.php");
exit;
