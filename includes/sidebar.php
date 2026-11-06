<?php
/**
 * Modern Sidebar Component
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$isDashboard  = (strpos($script, 'dashboard.php') !== false);
$isBilling    = (strpos($script, '/billing') !== false || strpos($script, 'billing.php') !== false) && strpos($script, 'invoice.php') === false;
$isProducts   = (strpos($script, '/products') !== false || strpos($script, 'products.php') !== false);
$isCategories = (strpos($script, '/categories') !== false || strpos($script, 'categories.php') !== false);
$isInventory  = (strpos($script, '/inventory') !== false || strpos($script, 'inventory.php') !== false);
$isCustomers  = (strpos($script, '/customers') !== false || strpos($script, 'customers.php') !== false);
$isSales      = (strpos($script, '/sales.php') !== false || strpos($script, 'invoice.php') !== false);
$isReports    = (strpos($script, '/reports') !== false || strpos($script, 'reports.php') !== false) && strpos($script, 'sales.php') === false;
$isProfile    = (strpos($script, 'profile.php') !== false);
$isSeed       = (strpos($script, 'seed.php') !== false);
?>
<aside class="app-sidebar">
    <!-- Brand Logo -->
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-logo text-decoration-none">
            <div class="sidebar-logo-icon">
                <i class="bi bi-basket-fill"></i>
            </div>
            <div>
                <span class="d-block fw-bold text-white lh-1" style="font-size: 1.05rem;"><?= e(STORE_NAME) ?></span>
                <span class="text-white-50" style="font-size: 0.70rem; letter-spacing: 0.05em;"><?= e(DEVELOPED_BY) ?></span>
            </div>
        </a>
    </div>

    <!-- Navigation Menu -->
    <div class="sidebar-menu">
        <div class="sidebar-heading">Main Operations</div>

        <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-link <?= $isDashboard ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>/billing/index.php" class="sidebar-link <?= $isBilling ? 'active' : '' ?>">
            <i class="bi bi-upc-scan"></i>
            <span>POS Billing</span>
            <span class="badge bg-success text-white">POS</span>
        </a>

        <div class="sidebar-heading mt-2">Catalog & Stock</div>

        <a href="<?= BASE_URL ?>/products/index.php" class="sidebar-link <?= $isProducts ? 'active' : '' ?>">
            <i class="bi bi-box-seam-fill"></i>
            <span>Products</span>
        </a>

        <a href="<?= BASE_URL ?>/categories/index.php" class="sidebar-link <?= $isCategories ? 'active' : '' ?>">
            <i class="bi bi-tags-fill"></i>
            <span>Categories</span>
        </a>

        <a href="<?= BASE_URL ?>/inventory/index.php" class="sidebar-link <?= $isInventory ? 'active' : '' ?>">
            <i class="bi bi-boxes"></i>
            <span>Inventory</span>
        </a>

        <div class="sidebar-heading mt-2">Sales & Customers</div>

        <a href="<?= BASE_URL ?>/customers/index.php" class="sidebar-link <?= $isCustomers ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Customers</span>
        </a>

        <a href="<?= BASE_URL ?>/reports/sales.php" class="sidebar-link <?= $isSales ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i>
            <span>Sales & Invoices</span>
        </a>

        <a href="<?= BASE_URL ?>/reports/daily.php" class="sidebar-link <?= $isReports ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line-fill"></i>
            <span>Reports & Analytics</span>
        </a>

        <div class="sidebar-heading mt-2">Administration</div>

        <a href="<?= BASE_URL ?>/admin/profile.php" class="sidebar-link <?= $isProfile ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i>
            <span>My Profile</span>
        </a>

        <a href="<?= BASE_URL ?>/database/seed.php" class="sidebar-link <?= $isSeed ? 'active' : '' ?>">
            <i class="bi bi-database-gear"></i>
            <span>DB Setup & Seed</span>
        </a>

        <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link text-danger-emphasis">
            <i class="bi bi-box-arrow-left text-danger"></i>
            <span class="text-danger">Logout</span>
        </a>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between">
            <span class="small" style="font-size: 0.75rem;">v<?= e(APP_VERSION) ?> &bull; <?= e(DEVELOPED_BY) ?></span>
            <span class="badge bg-success bg-opacity-25 text-success">Active</span>
        </div>
    </div>
</aside>
