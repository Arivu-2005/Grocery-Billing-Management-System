<?php
/**
 * Direct Print Invoice Route
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

$invoiceId = (int)($_GET['id'] ?? 0);
if ($invoiceId > 0) {
    header("Location: " . BASE_URL . "/billing/invoice.php?id={$invoiceId}&print=1");
} else {
    header("Location: " . BASE_URL . "/reports/sales.php");
}
exit;
