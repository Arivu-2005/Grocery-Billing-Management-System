<?php
/**
 * Edit Customer
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security validation failed.');
        header("Location: " . BASE_URL . "/customers/index.php");
        exit;
    }

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $name       = sanitizeInput($_POST['customer_name'] ?? '');
    $mobile     = sanitizeInput($_POST['mobile_number'] ?? '');
    $email      = sanitizeInput($_POST['email'] ?? '');
    $address    = sanitizeInput($_POST['address'] ?? '');

    if ($customerId <= 0 || empty($name) || empty($mobile)) {
        setFlashMessage('danger', 'Invalid customer data provided.');
    } else {
        try {
            $chk = $pdo->prepare("SELECT id FROM `customers` WHERE `mobile_number` = ? AND `id` != ?");
            $chk->execute([$mobile, $customerId]);
            if ($chk->fetch()) {
                setFlashMessage('danger', "Mobile number '{$mobile}' is in use by another customer.");
            } else {
                $upd = $pdo->prepare("UPDATE `customers` SET `customer_name` = ?, `mobile_number` = ?, `email` = ?, `address` = ? WHERE `id` = ?");
                $upd->execute([$name, $mobile, $email, $address, $customerId]);
                setFlashMessage('success', "Customer '{$name}' updated successfully!");
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Error updating customer: ' . $e->getMessage());
        }
    }
}

header("Location: " . BASE_URL . "/customers/index.php");
exit;
