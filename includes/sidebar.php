<?php
/**
 * Modern Sidebar Component
 * Grocery Billing Management System
 */

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar">
    <!-- Brand Logo -->
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-logo text-decoration-none">
            <div class="sidebar-logo-icon">
                <i class="bi bi-basket-fill"></i>
            </div>
            <div>
                <span class="d-block fw-bold text-white lh-1">FreshMart</span>
                <span class="text-white-50" style="font-size: 0.72rem; letter-spacing: 0.05em;">GROCERY POS</span>
            </div>
        </a>
    </div>

    <!-- Navigation Menu -->
    <div class="sidebar-menu">
        <div class="sidebar-heading">Main Operations</div>

        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/billing.php" class="sidebar-link <?= ($currentPage === 'billing.php') ? 'active' : '' ?>">
            <i class="bi bi-upc-scan"></i>
            <span>POS Billing</span>
            <span class="badge bg-success text-white">POS</span>
        </a>

        <div class="sidebar-heading mt-2">Catalog & Stock</div>

        <a href="<?= BASE_URL ?>/admin/products.php" class="sidebar-link <?= ($currentPage === 'products.php') ? 'active' : '' ?>">
            <i class="bi bi-box-seam-fill"></i>
            <span>Products</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/categories.php" class="sidebar-link <?= ($currentPage === 'categories.php') ? 'active' : '' ?>">
            <i class="bi bi-tags-fill"></i>
            <span>Categories</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/inventory.php" class="sidebar-link <?= ($currentPage === 'inventory.php') ? 'active' : '' ?>">
            <i class="bi bi-boxes"></i>
            <span>Inventory</span>
        </a>

        <div class="sidebar-heading mt-2">Sales & Customers</div>

        <a href="<?= BASE_URL ?>/admin/customers.php" class="sidebar-link <?= ($currentPage === 'customers.php') ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Customers</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/sales.php" class="sidebar-link <?= ($currentPage === 'sales.php' || $currentPage === 'invoice.php') ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i>
            <span>Sales & Invoices</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/reports.php" class="sidebar-link <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line-fill"></i>
            <span>Reports & Analytics</span>
        </a>

        <div class="sidebar-heading mt-2">System</div>

        <a href="<?= BASE_URL ?>/database/seed.php" class="sidebar-link <?= ($currentPage === 'seed.php') ? 'active' : '' ?>">
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
            <span class="small">v1.0.0 Core PHP</span>
            <span class="badge bg-success bg-opacity-25 text-success">Online</span>
        </div>
    </div>
</aside>
