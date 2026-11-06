<?php
/**
 * Customer Purchase History
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

$customerId = (int)($_GET['id'] ?? ($_GET['view_history'] ?? 0));
header("Location: " . BASE_URL . "/customers/index.php" . ($customerId > 0 ? "?view_history={$customerId}" : ""));
exit;
