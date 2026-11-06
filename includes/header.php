<?php
/**
 * Common Admin Header Layout
 * Grocery Billing Management System
 */

require_once __DIR__ . '/auth.php';

// Ensure user is logged in
requireLogin();

$currentUser = getLoggedInUser();
$activePage = basename($_SERVER['PHP_SELF']);

// Fetch low stock count for notification badge
$lowStockCount = 0;
try {
    $pdo = getDbConnection();
    $lowStockStmt = $pdo->query("SELECT COUNT(*) AS count FROM `products` WHERE `available_stock` <= `low_stock_threshold`");
    $lowStockCount = (int)$lowStockStmt->fetch()['count'];
} catch (Exception $e) {
    // Silently handle if table not initialized
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e(STORE_NAME) ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Chart.js for reports and dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Custom App CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<div class="app-wrapper">

    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Container -->
    <div class="app-main">

        <!-- Top Navigation Bar -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button class="btn btn-light d-lg-none border shadow-sm p-2" type="button" id="sidebarToggle" title="Toggle Navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-md-flex align-items-center text-muted small fw-medium">
                    <i class="bi bi-shop me-2 text-success"></i> <?= e(STORE_NAME) ?> &bull; <span class="ms-1 text-secondary"><?= e(STORE_TAGLINE) ?></span>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Live Clock -->
                <div class="live-clock-badge d-none d-sm-flex">
                    <i class="bi bi-clock-history text-success"></i>
                    <span id="liveClock">Loading time...</span>
                </div>

                <!-- Quick POS Button -->
                <a href="<?= BASE_URL ?>/admin/billing.php" class="btn btn-brand btn-sm px-3 shadow-sm d-flex align-items-center gap-1">
                    <i class="bi bi-cart-plus-fill"></i>
                    <span class="d-none d-sm-inline">New POS Bill</span>
                </a>

                <!-- Low Stock Alert Notification -->
                <div class="dropdown">
                    <button class="btn btn-light position-relative border p-2 rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Low Stock Alerts">
                        <i class="bi bi-bell-fill text-secondary"></i>
                        <?php if ($lowStockCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white">
                                <?= $lowStockCount ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="min-width: 250px;">
                        <li class="dropdown-header text-uppercase fw-bold small">Inventory Alerts</li>
                        <?php if ($lowStockCount > 0): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="<?= BASE_URL ?>/admin/inventory.php?filter=low_stock">
                                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                                    <div>
                                        <div class="fw-bold small"><?= $lowStockCount ?> Product(s) Low in Stock</div>
                                        <div class="text-muted smaller">Click to restock inventory</div>
                                    </div>
                                </a>
                            </li>
                        <?php else: ?>
                            <li><span class="dropdown-item text-muted small text-center py-2">All product stocks healthy</span></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Admin Profile Dropdown -->
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.9rem;">
                            <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                        </div>
                        <div class="d-none d-md-block text-start" style="line-height: 1.2;">
                            <span class="d-block fw-semibold small"><?= e($currentUser['full_name']) ?></span>
                            <span class="text-muted smaller text-capitalize"><?= e($currentUser['role']) ?></span>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2 mt-2">
                        <li>
                            <div class="px-3 py-1">
                                <span class="d-block fw-bold small"><?= e($currentUser['full_name']) ?></span>
                                <span class="text-muted smaller"><?= e($currentUser['email']) ?></span>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item small d-flex align-items-center gap-2" href="<?= BASE_URL ?>/admin/billing.php">
                                <i class="bi bi-calculator"></i> POS Register
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item small d-flex align-items-center gap-2" href="<?= BASE_URL ?>/admin/reports.php">
                                <i class="bi bi-graph-up"></i> Sales Reports
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item small text-danger d-flex align-items-center gap-2" href="<?= BASE_URL ?>/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Sign Out
                            </a>
                        </li>
                    </ul>
                </div>

            </div>
        </header>

        <!-- Dynamic Page Content Starts -->
        <main class="page-content">
            <!-- Flash Message Banner -->
            <?= displayFlashMessage() ?>
