<?php
/**
 * Add Customer
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

    $name    = sanitizeInput($_POST['customer_name'] ?? '');
    $mobile  = sanitizeInput($_POST['mobile_number'] ?? '');
    $email   = sanitizeInput($_POST['email'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');

    if (empty($name) || empty($mobile)) {
        setFlashMessage('danger', 'Customer name and mobile number are required.');
    } else {
        try {
            $chk = $pdo->prepare("SELECT id FROM `customers` WHERE `mobile_number` = ?");
            $chk->execute([$mobile]);
            if ($chk->fetch()) {
                setFlashMessage('danger', "Customer with mobile '{$mobile}' already exists.");
            } else {
                $ins = $pdo->prepare("INSERT INTO `customers` (`customer_name`, `mobile_number`, `email`, `address`) VALUES (?, ?, ?, ?)");
                $ins->execute([$name, $mobile, $email, $address]);
                setFlashMessage('success', "Customer '{$name}' created successfully!");
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Error adding customer: ' . $e->getMessage());
        }
    }
}

header("Location: " . BASE_URL . "/customers/index.php");
exit;
