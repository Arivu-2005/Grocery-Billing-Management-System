<?php
/**
 * Shared Helper & Business Logic Functions
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

require_once __DIR__ . '/../config/database.php';

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        $data = trim((string)$data);
        $data = stripslashes($data);
        return $data;
    }
}

if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $decimals = 2) {
        return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, $decimals, '.', ',');
    }
}

if (!function_exists('formatQty')) {
    function formatQty($qty, $unit = 'Piece') {
        $formatted = (floatval($qty) == intval($qty)) ? number_format($qty, 0) : number_format($qty, 2);
        return $formatted . ' ' . e($unit);
    }
}

if (!function_exists('generateInvoiceNumber')) {
    function generateInvoiceNumber($pdo) {
        $datePrefix = date('Ymd');
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `sales` WHERE DATE(`created_at`) = CURDATE()");
        $countStmt->execute();
        $dailyCount = (int)$countStmt->fetchColumn() + 1;
        $invoiceNo = 'INV-' . $datePrefix . '-' . str_pad($dailyCount, 4, '0', STR_PAD_LEFT);

        $checkInv = $pdo->prepare("SELECT id FROM `sales` WHERE `invoice_no` = ?");
        $checkInv->execute([$invoiceNo]);
        if ($checkInv->fetch()) {
            $invoiceNo .= '-' . rand(10, 99);
        }
        return $invoiceNo;
    }
}

if (!function_exists('checkStockAvailability')) {
    function checkStockAvailability($pdo, $productId, $requestedQty) {
        $stmt = $pdo->prepare("SELECT id, product_name, available_stock, unit FROM `products` WHERE id = ?");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch();
        if (!$prod) {
            return ['available' => false, 'current_stock' => 0, 'unit' => '', 'name' => 'Unknown Product'];
        }
        $curr = (float)$prod['available_stock'];
        return [
            'available'     => ($curr >= (float)$requestedQty),
            'current_stock' => $curr,
            'unit'          => $prod['unit'],
            'name'          => $prod['product_name']
        ];
    }
}

if (!function_exists('getLowStockCount')) {
    function getLowStockCount($pdo) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS count FROM `products` WHERE `available_stock` <= `low_stock_threshold`");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}
