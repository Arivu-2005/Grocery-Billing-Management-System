<?php
/**
 * Sales & Invoice History
 * Grocery Billing Management System
 */

$pageTitle = "Sales & Invoices";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();

// Filters
$search      = sanitizeInput($_GET['search'] ?? '');
$filterDate  = sanitizeInput($_GET['date_filter'] ?? 'all');
$paymentMode = sanitizeInput($_GET['payment_mode'] ?? 'all');
$startDate   = sanitizeInput($_GET['start_date'] ?? '');
$endDate     = sanitizeInput($_GET['end_date'] ?? '');

$sql = "
    SELECT s.*, c.customer_name as reg_customer_name, a.full_name as cashier_name,
           (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as item_count
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN admin a ON s.created_by = a.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (s.invoice_no LIKE ? OR s.customer_name LIKE ? OR s.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($paymentMode !== 'all' && !empty($paymentMode)) {
    $sql .= " AND s.payment_mode = ?";
    $params[] = $paymentMode;
}

if ($filterDate === 'today') {
    $sql .= " AND DATE(s.created_at) = CURDATE()";
} elseif ($filterDate === 'yesterday') {
    $sql .= " AND DATE(s.created_at) = CURDATE() - INTERVAL 1 DAY";
} elseif ($filterDate === 'this_week') {
    $sql .= " AND YEARWEEK(s.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filterDate === 'this_month') {
    $sql .= " AND MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
} elseif ($filterDate === 'custom' && !empty($startDate) && !empty($endDate)) {
    $sql .= " AND DATE(s.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$sql .= " ORDER BY s.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    // Summary calculations for filtered results
    $filteredCount = count($sales);
    $filteredTotalRevenue = 0.00;
    foreach ($sales as $s) {
        $filteredTotalRevenue += (float)$s['grand_total'];
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . e($e->getMessage()) . '</div>';
    $sales = [];
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="page-header-title">Sales & Billing History</h2>
        <p class="text-muted small mb-0">Browse generated invoices, reprint receipts, and track register transactions</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/billing.php" class="btn btn-brand shadow-sm">
            <i class="bi bi-cart-plus-fill me-1"></i> New POS Bill
        </a>
    </div>
</div>

<!-- Summary Metrics for current filter -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card-stat py-3">
            <div class="stat-label">Invoices Count</div>
            <div class="stat-value text-dark"><?= number_format($filteredCount) ?></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card-stat py-3">
            <div class="stat-label">Total Filtered Revenue</div>
            <div class="stat-value text-success"><?= formatCurrency($filteredTotalRevenue) ?></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card-stat py-3">
            <div class="stat-label">Avg Ticket Size</div>
            <div class="stat-value text-primary">
                <?= formatCurrency($filteredCount > 0 ? ($filteredTotalRevenue / $filteredCount) : 0) ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card-stat py-3">
            <div class="stat-label">Quick Report</div>
            <div class="pt-1">
                <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-sm btn-brand-light w-100">
                    <i class="bi bi-bar-chart-fill me-1"></i> Open Analytics
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Search & Date Filter Card -->
<div class="table-card p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <!-- Search Keyword -->
        <div class="col-md-3">
            <input type="text" name="search" class="form-control" placeholder="Search Invoice # or Customer..." value="<?= e($search) ?>" data-table-search="salesTable">
        </div>

        <!-- Date Filter Dropdown -->
        <div class="col-md-2">
            <select name="date_filter" id="dateFilterSelect" class="form-select">
                <option value="all" <?= ($filterDate === 'all') ? 'selected' : '' ?>>All Time</option>
                <option value="today" <?= ($filterDate === 'today') ? 'selected' : '' ?>>Today</option>
                <option value="yesterday" <?= ($filterDate === 'yesterday') ? 'selected' : '' ?>>Yesterday</option>
                <option value="this_week" <?= ($filterDate === 'this_week') ? 'selected' : '' ?>>This Week</option>
                <option value="this_month" <?= ($filterDate === 'this_month') ? 'selected' : '' ?>>This Month</option>
                <option value="custom" <?= ($filterDate === 'custom') ? 'selected' : '' ?>>Custom Range</option>
            </select>
        </div>

        <!-- Custom Dates (hidden unless custom selected) -->
        <div class="col-md-2" id="customStartDateCol" style="<?= ($filterDate === 'custom') ? '' : 'display:none;' ?>">
            <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>" placeholder="Start Date">
        </div>
        <div class="col-md-2" id="customEndDateCol" style="<?= ($filterDate === 'custom') ? '' : 'display:none;' ?>">
            <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>" placeholder="End Date">
        </div>

        <!-- Payment Mode Filter -->
        <div class="col-md-2">
            <select name="payment_mode" class="form-select">
                <option value="all" <?= ($paymentMode === 'all') ? 'selected' : '' ?>>All Payment Modes</option>
                <option value="Cash" <?= ($paymentMode === 'Cash') ? 'selected' : '' ?>>Cash Only</option>
                <option value="UPI" <?= ($paymentMode === 'UPI') ? 'selected' : '' ?>>UPI Only</option>
                <option value="Card" <?= ($paymentMode === 'Card') ? 'selected' : '' ?>>Card Only</option>
            </select>
        </div>

        <div class="col-md-auto">
            <button type="submit" class="btn btn-brand px-3">Filter</button>
            <?php if ($filterDate !== 'all' || !empty($search) || $paymentMode !== 'all'): ?>
                <a href="<?= BASE_URL ?>/admin/sales.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Sales Data Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-custom" id="salesTable">
            <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Date & Time</th>
                    <th>Customer Name</th>
                    <th>Items</th>
                    <th>Subtotal</th>
                    <th>Disc / Tax</th>
                    <th>Grand Total</th>
                    <th>Payment</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sales)): ?>
                    <?php foreach ($sales as $s): ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/invoice.php?id=<?= $s['id'] ?>" class="fw-bold text-success text-decoration-none">
                                    <?= e($s['invoice_no']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="small text-muted"><?= date('d M Y, h:i A', strtotime($s['created_at'])) ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($s['customer_name']) ?></div>
                                <?php if (!empty($s['customer_phone'])): ?>
                                    <div class="text-muted smaller"><?= e($s['customer_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border"><?= $s['item_count'] ?> items</span>
                            </td>
                            <td>
                                <span class="small text-muted"><?= formatCurrency($s['subtotal']) ?></span>
                            </td>
                            <td>
                                <span class="small text-danger">-<?= formatCurrency($s['discount_amount']) ?></span>
                                <span class="small text-muted d-block">+<?= formatCurrency($s['tax_amount']) ?> GST</span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-6"><?= formatCurrency($s['grand_total']) ?></span>
                            </td>
                            <td>
                                <?php if ($s['payment_mode'] === 'Cash'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-cash"></i> Cash</span>
                                <?php elseif ($s['payment_mode'] === 'UPI'): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><i class="bi bi-qr-code"></i> UPI</span>
                                <?php else: ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info"><i class="bi bi-credit-card"></i> Card</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/admin/invoice.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-success px-2 py-1" title="View / Print Invoice">
                                    <i class="bi bi-printer-fill me-1"></i> Print
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt fs-1 d-block text-secondary mb-2"></i>
                            No sales records found for this period.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateFilterSelect = document.getElementById('dateFilterSelect');
    const startCol = document.getElementById('customStartDateCol');
    const endCol = document.getElementById('customEndDateCol');

    if (dateFilterSelect && startCol && endCol) {
        dateFilterSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                startCol.style.display = 'block';
                endCol.style.display = 'block';
            } else {
                startCol.style.display = 'none';
                endCol.style.display = 'none';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
