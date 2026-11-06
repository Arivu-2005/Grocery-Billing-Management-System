<?php
/**
 * Admin Dashboard
 * Grocery Billing Management System
 */

$pageTitle = "Dashboard";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();

// Fetch Dashboard Metrics
$totalProducts = 0;
$totalCategories = 0;
$totalCustomers = 0;
$totalSalesCount = 0;
$todayRevenue = 0.00;
$monthlyRevenue = 0.00;
$lowStockCount = 0;

try {
    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) FROM `products`");
    $totalProducts = (int)$stmt->fetchColumn();

    // Total Categories
    $stmt = $pdo->query("SELECT COUNT(*) FROM `categories`");
    $totalCategories = (int)$stmt->fetchColumn();

    // Total Customers (excluding default walk-in if desired, or all)
    $stmt = $pdo->query("SELECT COUNT(*) FROM `customers`");
    $totalCustomers = (int)$stmt->fetchColumn();

    // Total Sales count
    $stmt = $pdo->query("SELECT COUNT(*) FROM `sales`");
    $totalSalesCount = (int)$stmt->fetchColumn();

    // Today's Revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(`grand_total`), 0.00) FROM `sales` WHERE DATE(`created_at`) = CURDATE()");
    $todayRevenue = (float)$stmt->fetchColumn();

    // Current Month's Revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(`grand_total`), 0.00) FROM `sales` WHERE MONTH(`created_at`) = MONTH(CURDATE()) AND YEAR(`created_at`) = YEAR(CURDATE())");
    $monthlyRevenue = (float)$stmt->fetchColumn();

    // Low Stock Products Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `available_stock` <= `low_stock_threshold`");
    $lowStockCount = (int)$stmt->fetchColumn();

    // Recent 5 Transactions
    $recentSalesStmt = $pdo->query("SELECT s.*, c.customer_name AS reg_customer_name FROM `sales` s LEFT JOIN `customers` c ON s.customer_id = c.id ORDER BY s.id DESC LIMIT 5");
    $recentSales = $recentSalesStmt->fetchAll();

    // Low Stock Items (top 5 critical)
    $lowStockStmt = $pdo->query("SELECT p.*, c.category_name FROM `products` p JOIN `categories` c ON p.category_id = c.id WHERE p.available_stock <= p.low_stock_threshold ORDER BY p.available_stock ASC LIMIT 5");
    $lowStockItems = $lowStockStmt->fetchAll();

    // Last 7 Days Revenue Trend for Chart
    $chartDaysStmt = $pdo->query("
        SELECT 
            DATE(created_at) as sale_date,
            COALESCE(SUM(grand_total), 0) as total_amount
        FROM sales
        WHERE created_at >= CURDATE() - INTERVAL 6 DAY
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC
    ");
    $salesTrend = $chartDaysStmt->fetchAll();

    // Map sales to past 7 days ensuring every day has an entry
    $trendMap = [];
    for ($i = 6; $i >= 0; $i--) {
        $dayKey = date('Y-m-d', strtotime("-$i days"));
        $trendMap[$dayKey] = 0.00;
    }
    foreach ($salesTrend as $row) {
        if (isset($trendMap[$row['sale_date']])) {
            $trendMap[$row['sale_date']] = (float)$row['total_amount'];
        }
    }

    $chartLabels = [];
    $chartValues = [];
    foreach ($trendMap as $dateStr => $amount) {
        $chartLabels[] = date('D, M d', strtotime($dateStr));
        $chartValues[] = $amount;
    }

    // Sales by Category for Doughnut Chart
    $catSalesStmt = $pdo->query("
        SELECT c.category_name, COALESCE(SUM(si.total_price), 0) as category_total
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        LEFT JOIN sale_items si ON p.id = si.product_id
        GROUP BY c.id, c.category_name
        HAVING category_total > 0
        ORDER BY category_total DESC
        LIMIT 5
    ");
    $catSales = $catSalesStmt->fetchAll();

    $catLabels = [];
    $catValues = [];
    foreach ($catSales as $cs) {
        $catLabels[] = $cs['category_name'];
        $catValues[] = (float)$cs['category_total'];
    }

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error loading dashboard data: ' . e($e->getMessage()) . '</div>';
}
?>

<!-- Dashboard Header & Quick Actions -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="page-header-title">Store Dashboard</h2>
        <p class="text-muted small mb-0">Real-time overview of sales, stock alerts, and grocery inventory</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>/admin/billing.php" class="btn btn-brand shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-cart4 fs-5"></i>
            <span>Open POS Terminal</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/products.php?action=new" class="btn btn-outline-secondary d-flex align-items-center gap-1">
            <i class="bi bi-plus-circle"></i> Add Product
        </a>
        <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-outline-secondary d-flex align-items-center gap-1">
            <i class="bi bi-arrow-down-up"></i> Restock
        </a>
    </div>
</div>

<!-- 7 Key Metric Cards -->
<div class="row g-3 mb-4">
    <!-- Today's Revenue -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Today's Revenue</div>
                    <div class="stat-value text-success"><?= formatCurrency($todayRevenue) ?></div>
                    <div class="smaller text-muted mt-1"><i class="bi bi-calendar-check me-1"></i> Today's collections</div>
                </div>
                <div class="stat-icon stat-icon-green shadow-sm">
                    <i class="bi bi-currency-rupee"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Monthly Revenue</div>
                    <div class="stat-value text-primary"><?= formatCurrency($monthlyRevenue) ?></div>
                    <div class="smaller text-muted mt-1"><i class="bi bi-graph-up-arrow me-1"></i> <?= date('F Y') ?></div>
                </div>
                <div class="stat-icon stat-icon-blue shadow-sm">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Invoices / Sales -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Total Invoices</div>
                    <div class="stat-value"><?= number_format($totalSalesCount) ?></div>
                    <div class="smaller text-muted mt-1"><i class="bi bi-receipt me-1"></i> All completed sales</div>
                </div>
                <div class="stat-icon stat-icon-purple shadow-sm">
                    <i class="bi bi-bag-check-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-value <?= ($lowStockCount > 0) ? 'text-danger' : 'text-success' ?>">
                        <?= $lowStockCount ?>
                    </div>
                    <div class="smaller text-muted mt-1">
                        <a href="<?= BASE_URL ?>/admin/inventory.php?filter=low_stock" class="text-decoration-none <?= ($lowStockCount > 0) ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            <i class="bi bi-arrow-right-circle me-1"></i> <?= ($lowStockCount > 0) ? 'Needs reorder' : 'Healthy stock' ?>
                        </a>
                    </div>
                </div>
                <div class="stat-icon <?= ($lowStockCount > 0) ? 'stat-icon-red' : 'stat-icon-green' ?> shadow-sm">
                    <i class="bi bi-exclamation-diamond-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Metrics: Products, Categories, Customers -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-stat py-3">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon-green">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <div>
                    <div class="stat-value fs-4"><?= number_format($totalProducts) ?></div>
                    <div class="stat-label mb-0">Total Products In Catalog</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-stat py-3">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon-amber">
                    <i class="bi bi-tags-fill"></i>
                </div>
                <div>
                    <div class="stat-value fs-4"><?= number_format($totalCategories) ?></div>
                    <div class="stat-label mb-0">Product Categories</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-stat py-3">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon-blue">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="stat-value fs-4"><?= number_format($totalCustomers) ?></div>
                    <div class="stat-label mb-0">Registered Customers</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Charts Section -->
<div class="row g-4 mb-4">
    <!-- 7-Day Revenue Trend Line Chart -->
    <div class="col-lg-8">
        <div class="table-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Weekly Sales Trend</h5>
                    <p class="text-muted small mb-0">Daily gross revenue for the past 7 days</p>
                </div>
                <span class="badge bg-light text-secondary border px-2 py-1 small">
                    <i class="bi bi-activity text-success me-1"></i> Live Chart
                </span>
            </div>
            <div style="height: 280px; position: relative;">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Sales Breakdown Doughnut Chart -->
    <div class="col-lg-4">
        <div class="table-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Top Categories</h5>
                    <p class="text-muted small mb-0">Sales revenue distribution</p>
                </div>
                <span class="badge bg-light text-secondary border px-2 py-1 small">
                    <i class="bi bi-pie-chart text-success me-1"></i> Share
                </span>
            </div>
            <div style="height: 280px; position: relative;" class="d-flex align-items-center justify-content-center">
                <?php if (!empty($catLabels)): ?>
                    <canvas id="categorySalesChart"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted small">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 text-secondary"></i>
                        No category sales recorded yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions & Low Stock Alerts Table Section -->
<div class="row g-4">
    <!-- Recent Sales Table -->
    <div class="col-lg-7">
        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0">Recent Billing Transactions</h5>
                    <span class="text-muted small">Latest sales generated through POS</span>
                </div>
                <a href="<?= BASE_URL ?>/admin/sales.php" class="btn btn-sm btn-brand-light">
                    View All Sales <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Date & Time</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentSales)): ?>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/admin/invoice.php?id=<?= $sale['id'] ?>" class="fw-bold text-success text-decoration-none">
                                            <?= e($sale['invoice_no']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark"><?= e($sale['customer_name']) ?></div>
                                        <?php if (!empty($sale['customer_phone'])): ?>
                                            <div class="text-muted smaller"><?= e($sale['customer_phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?= date('d M, h:i A', strtotime($sale['created_at'])) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= formatCurrency($sale['grand_total']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border">
                                            <?= e($sale['payment_mode']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/admin/invoice.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-success p-1 px-2" title="View / Print Invoice">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No sales transactions recorded yet. <a href="<?= BASE_URL ?>/admin/billing.php">Create first sale</a>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert List -->
    <div class="col-lg-5">
        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0 text-danger d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i> Low Stock Alerts
                    </h5>
                    <span class="text-muted small">Products at or below reorder threshold</span>
                </div>
                <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-sm btn-outline-secondary">
                    Restock All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Stock</th>
                            <th>Threshold</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($lowStockItems)): ?>
                            <?php foreach ($lowStockItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium text-dark"><?= e($item['product_name']) ?></div>
                                        <div class="text-muted smaller"><?= e($item['product_code']) ?> &bull; <?= e($item['category_name']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($item['available_stock'] <= 0): ?>
                                            <span class="badge-stock-out">0 <?= e($item['unit']) ?> (Out)</span>
                                        <?php else: ?>
                                            <span class="badge-stock-low"><?= formatQty($item['available_stock'], $item['unit']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?= formatQty($item['low_stock_threshold'], $item['unit']) ?></span>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/admin/inventory.php?product_id=<?= $item['id'] ?>#stockModal" class="btn btn-sm btn-brand-light p-1 px-2" title="Add Stock">
                                            <i class="bi bi-plus-lg"></i> In
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i>
                                    All grocery products have healthy stock levels!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Scripts Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Sales Trend Line Chart
    const trendCtx = document.getElementById('salesTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Revenue (<?= CURRENCY_SYMBOL ?>)',
                    data: <?= json_encode($chartValues) ?>,
                    borderColor: '#15803D',
                    backgroundColor: 'rgba(21, 128, 61, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#15803D',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: <?= CURRENCY_SYMBOL ?> ' + context.parsed.y.toLocaleString('en-IN', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#F1F5F9' },
                        ticks: {
                            callback: function(value) {
                                return '<?= CURRENCY_SYMBOL ?>' + value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Category Doughnut Chart
    const catCtx = document.getElementById('categorySalesChart');
    if (catCtx) {
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    data: <?= json_encode($catValues) ?>,
                    backgroundColor: [
                        '#15803D',
                        '#22C55E',
                        '#0EA5E9',
                        '#F59E0B',
                        '#8B5CF6'
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
