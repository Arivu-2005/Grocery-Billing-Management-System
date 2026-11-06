<?php
/**
 * Common Top Navigation Bar Component
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

$currentUser = getLoggedInUser();
$lowStockCount = getLowStockCount(getDbConnection());
?>
<header class="app-topbar">
    <div class="topbar-left">
        <button class="btn btn-light d-lg-none border shadow-sm p-2" type="button" id="sidebarToggle" title="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="d-none d-md-flex align-items-center text-muted small fw-medium">
            <i class="bi bi-shop me-2 text-success"></i> <?= e(STORE_NAME) ?> 
            <span class="mx-2 text-muted">&bull;</span>
            <span class="badge bg-success bg-opacity-10 text-success border border-success"><?= e(DEVELOPED_BY) ?></span>
        </div>
    </div>

    <div class="topbar-right">
        <!-- Live Clock -->
        <div class="live-clock-badge d-none d-sm-flex">
            <i class="bi bi-clock-history text-success"></i>
            <span id="liveClock">Loading time...</span>
        </div>

        <!-- Quick POS Button -->
        <a href="<?= BASE_URL ?>/billing/index.php" class="btn btn-brand btn-sm px-3 shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-cart-plus-fill"></i>
            <span class="d-none d-sm-inline">New Bill</span>
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
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="min-width: 260px;">
                <li class="dropdown-header text-uppercase fw-bold small">Inventory Alerts</li>
                <?php if ($lowStockCount > 0): ?>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="<?= BASE_URL ?>/inventory/low_stock.php">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div>
                                <div class="fw-bold small"><?= $lowStockCount ?> Item(s) Low in Stock</div>
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
                    <a class="dropdown-item small d-flex align-items-center gap-2" href="<?= BASE_URL ?>/admin/profile.php">
                        <i class="bi bi-person-circle"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item small d-flex align-items-center gap-2" href="<?= BASE_URL ?>/billing/index.php">
                        <i class="bi bi-calculator"></i> POS Billing
                    </a>
                </li>
                <li>
                    <a class="dropdown-item small d-flex align-items-center gap-2" href="<?= BASE_URL ?>/reports/sales.php">
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
