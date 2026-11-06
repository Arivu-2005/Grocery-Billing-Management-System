<?php
/**
 * Sales Analytics & Reports
 * Grocery Billing Management System
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();

// Report Filter Range
$range = sanitizeInput($_GET['range'] ?? 'this_month');
$startDate = sanitizeInput($_GET['start_date'] ?? '');
$endDate = sanitizeInput($_GET['end_date'] ?? '');

$whereClause = "1=1";
$params = [];

if ($range === 'today') {
    $whereClause .= " AND DATE(s.created_at) = CURDATE()";
    $rangeLabel = "Today (" . date('d M Y') . ")";
} elseif ($range === 'yesterday') {
    $whereClause .= " AND DATE(s.created_at) = CURDATE() - INTERVAL 1 DAY";
    $rangeLabel = "Yesterday (" . date('d M Y', strtotime('-1 day')) . ")";
} elseif ($range === 'last_7_days') {
    $whereClause .= " AND DATE(s.created_at) >= CURDATE() - INTERVAL 6 DAY";
    $rangeLabel = "Last 7 Days";
} elseif ($range === 'this_month') {
    $whereClause .= " AND MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
    $rangeLabel = "This Month (" . date('F Y') . ")";
} elseif ($range === 'last_month') {
    $whereClause .= " AND MONTH(s.created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(s.created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
    $rangeLabel = "Last Month (" . date('F Y', strtotime('-1 month')) . ")";
} elseif ($range === 'custom' && !empty($startDate) && !empty($endDate)) {
    $whereClause .= " AND DATE(s.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $rangeLabel = date('d M Y', strtotime($startDate)) . " to " . date('d M Y', strtotime($endDate));
} else {
    $range = 'this_month';
    $whereClause .= " AND MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
    $rangeLabel = "This Month (" . date('F Y') . ")";
}

// 1. CSV EXPORT HANDLER
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvSql = "
        SELECT s.invoice_no, s.created_at, s.customer_name, s.customer_phone, 
               s.subtotal, s.discount_amount, s.tax_amount, s.grand_total, s.payment_mode
        FROM sales s
        WHERE {$whereClause}
        ORDER BY s.id DESC
    ";
    $csvStmt = $pdo->prepare($csvSql);
    $csvStmt->execute($params);
    $rows = $csvStmt->fetchAll();

    $fileName = 'FreshMart_Sales_Report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileName);

    $output = fopen('php://output', 'w');
    // Header Row
    fputcsv($output, ['Invoice No', 'Date & Time', 'Customer Name', 'Mobile', 'Subtotal (' . CURRENCY_CODE . ')', 'Discount (' . CURRENCY_CODE . ')', 'GST Tax (' . CURRENCY_CODE . ')', 'Grand Total (' . CURRENCY_CODE . ')', 'Payment Mode']);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['invoice_no'],
            $r['created_at'],
            $r['customer_name'],
            $r['customer_phone'] ?: 'N/A',
            $r['subtotal'],
            $r['discount_amount'],
            $r['tax_amount'],
            $r['grand_total'],
            $r['payment_mode']
        ]);
    }
    fclose($output);
    exit;
}

// 2. SUMMARY METRICS
$totalRevenue = 0.00;
$totalTransactions = 0;
$cashSales = 0.00;
$upiSales = 0.00;
$cardSales = 0.00;

try {
    $metricsSql = "
        SELECT 
            COUNT(s.id) as total_count,
            COALESCE(SUM(s.grand_total), 0.00) as total_rev,
            COALESCE(SUM(CASE WHEN s.payment_mode = 'Cash' THEN s.grand_total ELSE 0 END), 0.00) as cash_rev,
            COALESCE(SUM(CASE WHEN s.payment_mode = 'UPI' THEN s.grand_total ELSE 0 END), 0.00) as upi_rev,
            COALESCE(SUM(CASE WHEN s.payment_mode = 'Card' THEN s.grand_total ELSE 0 END), 0.00) as card_rev
        FROM sales s
        WHERE {$whereClause}
    ";
    $mStmt = $pdo->prepare($metricsSql);
    $mStmt->execute($params);
    $metrics = $mStmt->fetch();

    $totalTransactions = (int)$metrics['total_count'];
    $totalRevenue      = (float)$metrics['total_rev'];
    $cashSales         = (float)$metrics['cash_rev'];
    $upiSales          = (float)$metrics['upi_rev'];
    $cardSales         = (float)$metrics['card_rev'];

    // 3. DAILY REVENUE BREAKDOWN FOR CHART
    $trendSql = "
        SELECT DATE(s.created_at) as sale_day, COALESCE(SUM(s.grand_total), 0) as day_revenue
        FROM sales s
        WHERE {$whereClause}
        GROUP BY DATE(s.created_at)
        ORDER BY sale_day ASC
    ";
    $tStmt = $pdo->prepare($trendSql);
    $tStmt->execute($params);
    $dailyTrend = $tStmt->fetchAll();

    $trendDates = [];
    $trendAmounts = [];
    foreach ($dailyTrend as $d) {
        $trendDates[] = date('d M', strtotime($d['sale_day']));
        $trendAmounts[] = (float)$d['day_revenue'];
    }

    // 4. TOP SELLING PRODUCTS IN RANGE
    $topProdSql = "
        SELECT si.product_name, si.unit, SUM(si.quantity) as total_qty, SUM(si.total_price) as total_rev
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE {$whereClause}
        GROUP BY si.product_id, si.product_name, si.unit
        ORDER BY total_rev DESC
        LIMIT 5
    ";
    $tpStmt = $pdo->prepare($topProdSql);
    $tpStmt->execute($params);
    $topProducts = $tpStmt->fetchAll();

    // 5. SALES INVOICES LIST FOR TABLE
    $salesListSql = "
        SELECT s.*, (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as item_count
        FROM sales s
        WHERE {$whereClause}
        ORDER BY s.id DESC
    ";
    $sStmt = $pdo->prepare($salesListSql);
    $sStmt->execute($params);
    $salesList = $sStmt->fetchAll();

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Report calculation error: ' . e($e->getMessage()) . '</div>';
    $salesList = [];
    $topProducts = [];
}

$pageTitle = "Sales Reports & Analytics";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h2 class="page-header-title">Sales Reports & Analytics</h2>
        <p class="text-muted small mb-0">Financial analytics, payment channel distribution, and performance exports</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <!-- CSV Export Button -->
        <a href="<?= BASE_URL ?>/admin/reports.php?range=<?= urlencode($range) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&export=csv" class="btn btn-outline-success btn-sm shadow-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to CSV
        </a>
        <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Date Filter Form (No-print) -->
<div class="table-card p-3 mb-4 no-print">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-3">
            <label class="form-label smaller fw-bold text-muted mb-1">Time Range</label>
            <select name="range" id="reportRangeSelect" class="form-select form-select-sm">
                <option value="today" <?= ($range === 'today') ? 'selected' : '' ?>>Today</option>
                <option value="yesterday" <?= ($range === 'yesterday') ? 'selected' : '' ?>>Yesterday</option>
                <option value="last_7_days" <?= ($range === 'last_7_days') ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="this_month" <?= ($range === 'this_month') ? 'selected' : '' ?>>This Month</option>
                <option value="last_month" <?= ($range === 'last_month') ? 'selected' : '' ?>>Last Month</option>
                <option value="custom" <?= ($range === 'custom') ? 'selected' : '' ?>>Custom Date Range</option>
            </select>
        </div>

        <div class="col-md-3" id="repStartCol" style="<?= ($range === 'custom') ? '' : 'display:none;' ?>">
            <label class="form-label smaller fw-bold text-muted mb-1">Start Date</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
        </div>

        <div class="col-md-3" id="repEndCol" style="<?= ($range === 'custom') ? '' : 'display:none;' ?>">
            <label class="form-label smaller fw-bold text-muted mb-1">End Date</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
        </div>

        <div class="col-md-auto align-self-end">
            <button type="submit" class="btn btn-brand btn-sm px-4">Generate Report</button>
        </div>
    </form>
</div>

<!-- Report Title Bar for Print View -->
<div class="mb-4">
    <div class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 fs-6">
        <i class="bi bi-calendar3 me-1"></i> Active Reporting Period: <strong><?= e($rangeLabel) ?></strong>
    </div>
</div>

<!-- 5 Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl">
        <div class="card-stat">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value text-success"><?= formatCurrency($totalRevenue) ?></div>
            <div class="smaller text-muted mt-1">Gross sales turnover</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card-stat">
            <div class="stat-label">Transactions</div>
            <div class="stat-value text-dark"><?= number_format($totalTransactions) ?></div>
            <div class="smaller text-muted mt-1">Invoices billed</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card-stat">
            <div class="stat-label">Cash Collections</div>
            <div class="stat-value text-dark"><?= formatCurrency($cashSales) ?></div>
            <div class="smaller text-muted mt-1"><?= ($totalRevenue > 0) ? round(($cashSales / $totalRevenue) * 100, 1) : 0 ?>% of total</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card-stat">
            <div class="stat-label">UPI Payments</div>
            <div class="stat-value text-primary"><?= formatCurrency($upiSales) ?></div>
            <div class="smaller text-muted mt-1"><?= ($totalRevenue > 0) ? round(($upiSales / $totalRevenue) * 100, 1) : 0 ?>% of total</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card-stat">
            <div class="stat-label">Card Payments</div>
            <div class="stat-value text-info"><?= formatCurrency($cardSales) ?></div>
            <div class="smaller text-muted mt-1"><?= ($totalRevenue > 0) ? round(($cardSales / $totalRevenue) * 100, 1) : 0 ?>% of total</div>
        </div>
    </div>
</div>

<!-- Charts Row (No-print) -->
<div class="row g-4 mb-4 no-print">
    <!-- Revenue Trend Chart -->
    <div class="col-lg-8">
        <div class="table-card p-4 h-100">
            <h5 class="fw-bold mb-1">Sales Revenue Trend</h5>
            <p class="text-muted small mb-3">Daily gross performance across the selected date filter</p>
            <div style="height: 260px; position: relative;">
                <canvas id="reportTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Payment Mode Doughnut -->
    <div class="col-lg-4">
        <div class="table-card p-4 h-100">
            <h5 class="fw-bold mb-1">Payment Method Share</h5>
            <p class="text-muted small mb-3">Revenue split by payment channel</p>
            <div style="height: 260px; position: relative;" class="d-flex align-items-center justify-content-center">
                <?php if ($totalRevenue > 0): ?>
                    <canvas id="paymentShareChart"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted small">No revenue recorded in this period.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Selling Products & Invoices Detail Table -->
<div class="row g-4">
    <!-- Top Products Ranking -->
    <div class="col-lg-4">
        <div class="table-card p-4 h-100">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-trophy-fill text-warning"></i> Top Performing Items
            </h5>
            <?php if (!empty($topProducts)): ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($topProducts as $idx => $tp): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <div>
                                <span class="fw-bold text-dark"><?= ($idx + 1) ?>. <?= e($tp['product_name']) ?></span>
                                <div class="text-muted smaller"><?= formatQty($tp['total_qty'], $tp['unit']) ?> sold</div>
                            </div>
                            <span class="fw-bold text-success"><?= formatCurrency($tp['total_rev']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted small mb-0">No item sales recorded for this period.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detailed Transactions Breakdown -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Detailed Sales Invoices</h5>
                <span class="badge bg-light text-dark border"><?= count($salesList) ?> Invoices</span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Payment</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($salesList)): ?>
                            <?php foreach ($salesList as $sl): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/admin/invoice.php?id=<?= $sl['id'] ?>" class="fw-bold text-success text-decoration-none">
                                            <?= e($sl['invoice_no']) ?>
                                        </a>
                                    </td>
                                    <td><span class="small text-muted"><?= date('d M Y, h:i A', strtotime($sl['created_at'])) ?></span></td>
                                    <td><span class="fw-semibold text-dark"><?= e($sl['customer_name']) ?></span></td>
                                    <td><span class="small text-muted"><?= $sl['item_count'] ?> items</span></td>
                                    <td><span class="badge bg-light text-secondary border"><?= e($sl['payment_mode']) ?></span></td>
                                    <td class="text-end"><span class="fw-bold text-dark"><?= formatCurrency($sl['grand_total']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No transactions found in this period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Custom date visibility toggle
    const rangeSelect = document.getElementById('reportRangeSelect');
    const startCol = document.getElementById('repStartCol');
    const endCol = document.getElementById('repEndCol');

    if (rangeSelect && startCol && endCol) {
        rangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                startCol.style.display = 'block';
                endCol.style.display = 'block';
            } else {
                startCol.style.display = 'none';
                endCol.style.display = 'none';
            }
        });
    }

    // Trend Chart
    const trendCtx = document.getElementById('reportTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($trendDates) ?>,
                datasets: [{
                    label: 'Daily Revenue (<?= CURRENCY_SYMBOL ?>)',
                    data: <?= json_encode($trendAmounts) ?>,
                    backgroundColor: '#15803D',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return '<?= CURRENCY_SYMBOL ?>' + v; }
                        }
                    }
                }
            }
        });
    }

    // Payment Share Chart
    const payCtx = document.getElementById('paymentShareChart');
    if (payCtx) {
        new Chart(payCtx, {
            type: 'doughnut',
            data: {
                labels: ['Cash', 'UPI', 'Card'],
                datasets: [{
                    data: [<?= $cashSales ?>, <?= $upiSales ?>, <?= $cardSales ?>],
                    backgroundColor: ['#15803D', '#0EA5E9', '#F59E0B'],
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
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
