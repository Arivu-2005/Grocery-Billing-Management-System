<?php
/**
 * Edit Product
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

    $productId     = (int)($_POST['product_id'] ?? 0);
    $productName   = sanitizeInput($_POST['product_name'] ?? '');
    $categoryId    = (int)($_POST['category_id'] ?? 0);
    $productCode   = strtoupper(trim(sanitizeInput($_POST['product_code'] ?? '')));
    $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
    $sellingPrice  = (float)($_POST['selling_price'] ?? 0);
    $unit          = sanitizeInput($_POST['unit'] ?? 'Piece');
    $lowThreshold  = (float)($_POST['low_stock_threshold'] ?? 10);

    if ($productId <= 0 || empty($productName) || $categoryId <= 0 || empty($productCode)) {
        setFlashMessage('danger', 'Invalid product data provided.');
    } elseif ($purchasePrice < 0 || $sellingPrice < 0) {
        setFlashMessage('danger', 'Prices cannot be negative.');
    } else {
        try {
            $chk = $pdo->prepare("SELECT id FROM `products` WHERE `product_code` = ? AND `id` != ?");
            $chk->execute([$productCode, $productId]);
            if ($chk->fetch()) {
                setFlashMessage('danger', "Product code '{$productCode}' is already used by another product.");
            } else {
                $upd = $pdo->prepare("
                    UPDATE `products` 
                    SET `product_name` = ?, `category_id` = ?, `product_code` = ?, `purchase_price` = ?, `selling_price` = ?, `unit` = ?, `low_stock_threshold` = ?
                    WHERE `id` = ?
                ");
                $upd->execute([$productName, $categoryId, $productCode, $purchasePrice, $sellingPrice, $unit, $lowThreshold, $productId]);
                setFlashMessage('success', "Product '{$productName}' updated successfully!");
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Error updating product: ' . $e->getMessage());
        }
    }
}

header("Location: " . BASE_URL . "/products/index.php");
exit;
