<?php
/**
 * View Customer Details
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

$customerId = (int)($_GET['id'] ?? 0);
header("Location: " . BASE_URL . "/customers/index.php" . ($customerId > 0 ? "?view_history={$customerId}" : ""));
exit;
