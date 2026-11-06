<?php
/**
 * Add Product
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$currentUser = getLoggedInUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security validation failed.');
        header("Location: " . BASE_URL . "/products/index.php");
        exit;
    }

    $productName   = sanitizeInput($_POST['product_name'] ?? '');
    $categoryId    = (int)($_POST['category_id'] ?? 0);
    $productCode   = strtoupper(trim(sanitizeInput($_POST['product_code'] ?? '')));
    $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
    $sellingPrice  = (float)($_POST['selling_price'] ?? 0);
    $initialStock  = (float)($_POST['available_stock'] ?? 0);
    $unit          = sanitizeInput($_POST['unit'] ?? 'Piece');
    $lowThreshold  = (float)($_POST['low_stock_threshold'] ?? 10);

    if (empty($productName) || $categoryId <= 0 || empty($productCode)) {
        setFlashMessage('danger', 'Product name, category, and SKU are required.');
    } elseif ($purchasePrice < 0 || $sellingPrice < 0 || $initialStock < 0) {
        setFlashMessage('danger', 'Prices and stock quantities cannot be negative.');
    } else {
        try {
            $chk = $pdo->prepare("SELECT id FROM `products` WHERE `product_code` = ?");
            $chk->execute([$productCode]);
            if ($chk->fetch()) {
                setFlashMessage('danger', "Product SKU '{$productCode}' is already taken.");
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO `products` 
                    (`product_name`, `category_id`, `product_code`, `purchase_price`, `selling_price`, `available_stock`, `unit`, `low_stock_threshold`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$productName, $categoryId, $productCode, $purchasePrice, $sellingPrice, $initialStock, $unit, $lowThreshold]);
                $newId = $pdo->lastInsertId();

                if ($initialStock > 0) {
                    $hist = $pdo->prepare("
                        INSERT INTO `stock_history` 
                        (`product_id`, `type`, `quantity`, `previous_stock`, `new_stock`, `reference_id`, `notes`, `created_by`) 
                        VALUES (?, 'IN', ?, 0, ?, 'INITIAL-STOCK', 'Initial intake', ?)
                    ");
                    $hist->execute([$newId, $initialStock, $initialStock, $currentUser['id']]);
                }

                setFlashMessage('success', "Product '{$productName}' added successfully!");
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Error adding product: ' . $e->getMessage());
        }
    }
}

header("Location: " . BASE_URL . "/products/index.php");
exit;
