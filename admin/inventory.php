<?php
/**
 * Inventory & Stock Management
 * Grocery Billing Management System
 */

$pageTitle = "Inventory Management";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$currentUser = getLoggedInUser();

// Handle Stock Adjustments (IN, OUT, ADJUSTMENT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security validation failed. Please try again.');
        header("Location: " . BASE_URL . "/admin/inventory.php");
        exit;
    }

    if ($action === 'adjust_stock') {
        $productId   = (int)($_POST['product_id'] ?? 0);
        $type        = sanitizeInput($_POST['adjustment_type'] ?? 'IN'); // IN, OUT, ADJUSTMENT
        $quantity    = (float)($_POST['quantity'] ?? 0);
        $referenceId = sanitizeInput($_POST['reference_id'] ?? '');
        $notes       = sanitizeInput($_POST['notes'] ?? '');

        if ($productId <= 0 || ($type === 'ADJUSTMENT' ? $quantity < 0 : $quantity <= 0)) {
            setFlashMessage('danger', 'Please select a valid product and enter a valid quantity.');
        } else {
            try {
                // Begin Transaction for Stock Safety
                $pdo->beginTransaction();

                // Fetch current product stock with FOR UPDATE lock
                $pStmt = $pdo->prepare("SELECT id, product_name, available_stock, unit FROM `products` WHERE id = ? FOR UPDATE");
                $pStmt->execute([$productId]);
                $product = $pStmt->fetch();

                if (!$product) {
                    throw new Exception("Selected product was not found.");
                }

                $previousStock = (float)$product['available_stock'];
                $newStock = $previousStock;

                if ($type === 'IN') {
                    // Purchase intake / restock
                    $newStock = $previousStock + $quantity;
                } elseif ($type === 'OUT') {
                    // Damaged / Expired / Returned to vendor
                    if ($quantity > $previousStock) {
                        throw new Exception("Cannot deduct " . $quantity . " " . $product['unit'] . ". Current stock is only " . $previousStock . " " . $product['unit'] . ". Negative stock is prohibited.");
                    }
                    $newStock = $previousStock - $quantity;
                } elseif ($type === 'ADJUSTMENT') {
                    // Manual correction to set exact count
                    // In this mode, the input $quantity represents the actual physical count
                    $newStock = $quantity;
                    $diff = $newStock - $previousStock;
                } else {
                    throw new Exception("Invalid adjustment type.");
                }

                // Update product table
                $uStmt = $pdo->prepare("UPDATE `products` SET `available_stock` = ? WHERE `id` = ?");
                $uStmt->execute([$newStock, $productId]);

                // Record in stock_history
                $hStmt = $pdo->prepare("
                    INSERT INTO `stock_history` 
                    (`product_id`, `type`, `quantity`, `previous_stock`, `new_stock`, `reference_id`, `notes`, `created_by`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $hStmt->execute([
                    $productId, 
                    $type, 
                    $quantity, 
                    $previousStock, 
                    $newStock, 
                    $referenceId ?: 'MANUAL-ADJ', 
                    $notes ?: 'Manual inventory update by ' . $currentUser['full_name'], 
                    $currentUser['id']
                ]);

                $pdo->commit();
                setFlashMessage('success', "Stock for '{$product['product_name']}' updated successfully from {$previousStock} to {$newStock} {$product['unit']}!");

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlashMessage('danger', 'Error updating stock: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/inventory.php");
        exit;
    }
}

// Summary Metrics
$totalStockValueCost = 0.00;
$totalStockValueRetail = 0.00;
$totalLowStock = 0;
$totalOutOfStock = 0;

try {
    $sumStmt = $pdo->query("
        SELECT 
            COALESCE(SUM(available_stock * purchase_price), 0) AS total_cost_value,
            COALESCE(SUM(available_stock * selling_price), 0) AS total_retail_value,
            SUM(CASE WHEN available_stock <= low_stock_threshold AND available_stock > 0 THEN 1 ELSE 0 END) AS low_count,
            SUM(CASE WHEN available_stock <= 0 THEN 1 ELSE 0 END) AS out_count
        FROM products
    ");
    $sums = $sumStmt->fetch();
    $totalStockValueCost = (float)$sums['total_cost_value'];
    $totalStockValueRetail = (float)$sums['total_retail_value'];
    $totalLowStock = (int)$sums['low_count'];
    $totalOutOfStock = (int)$sums['out_count'];

    // All products for dropdown selection
    $allProducts = $pdo->query("SELECT id, product_name, product_code, unit, available_stock, low_stock_threshold FROM products ORDER BY product_name ASC")->fetchAll();

    // Query inventory table with filters
    $filter = sanitizeInput($_GET['filter'] ?? 'all');
    $search = sanitizeInput($_GET['search'] ?? '');

    $invSql = "
        SELECT p.*, c.category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $invSql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filter === 'low_stock') {
        $invSql .= " AND p.available_stock <= p.low_stock_threshold AND p.available_stock > 0";
    } elseif ($filter === 'out_of_stock') {
        $invSql .= " AND p.available_stock <= 0";
    }

    $invSql .= " ORDER BY p.available_stock ASC";
    $invStmt = $pdo->prepare($invSql);
    $invStmt->execute($params);
    $inventoryItems = $invStmt->fetchAll();

    // Fetch Recent Stock History (Audit Trail)
    $histStmt = $pdo->query("
        SELECT sh.*, p.product_name, p.product_code, p.unit, a.full_name as admin_name
        FROM stock_history sh
        JOIN products p ON sh.product_id = p.id
        LEFT JOIN admin a ON sh.created_by = a.id
        ORDER BY sh.id DESC
        LIMIT 25
    ");
    $stockHistory = $histStmt->fetchAll();

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Database error: ' . e($e->getMessage()) . '</div>';
    $inventoryItems = [];
    $stockHistory = [];
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="page-header-title">Inventory Management</h2>
        <p class="text-muted small mb-0">Monitor stock levels, execute stock intakes, track waste/adjustments, and review history</p>
    </div>
    <div>
        <button type="button" class="btn btn-brand shadow-sm" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
            <i class="bi bi-arrow-down-up me-1"></i> Adjust / Inward Stock
        </button>
    </div>
</div>

<!-- Stock Value & Status Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Stock Value (At Cost)</div>
            <div class="stat-value text-dark"><?= formatCurrency($totalStockValueCost) ?></div>
            <div class="smaller text-muted mt-1"><i class="bi bi-box-seam me-1"></i> Total inventory purchase cost</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Retail Value (At Selling)</div>
            <div class="stat-value text-success"><?= formatCurrency($totalStockValueRetail) ?></div>
            <div class="smaller text-muted mt-1"><i class="bi bi-tag me-1"></i> Potential retail realization</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Low Stock Alerts</div>
            <div class="stat-value text-warning"><?= $totalLowStock ?></div>
            <div class="smaller text-muted mt-1">
                <a href="<?= BASE_URL ?>/admin/inventory.php?filter=low_stock" class="text-decoration-none text-warning fw-semibold">
                    View Low Stock Items &rarr;
                </a>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-label">Out of Stock Items</div>
            <div class="stat-value text-danger"><?= $totalOutOfStock ?></div>
            <div class="smaller text-muted mt-1">
                <a href="<?= BASE_URL ?>/admin/inventory.php?filter=out_of_stock" class="text-decoration-none text-danger fw-semibold">
                    View Out of Stock Items &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs: Current Stock vs Stock Movement Audit History -->
<ul class="nav nav-pills mb-4" id="inventoryTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="stock-list-tab" data-bs-toggle="pill" data-bs-target="#stock-list-pane" type="button" role="tab">
            <i class="bi bi-boxes me-1"></i> Current Stock Levels
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="stock-history-tab" data-bs-toggle="pill" data-bs-target="#stock-history-pane" type="button" role="tab">
            <i class="bi bi-clock-history me-1"></i> Stock Movement Audit History
        </button>
    </li>
</ul>

<div class="tab-content" id="inventoryTabsContent">
    <!-- TAB 1: CURRENT STOCK LEVELS -->
    <div class="tab-pane fade show active" id="stock-list-pane" role="tabpanel">
        
        <!-- Filter Bar -->
        <div class="table-card p-3 mb-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search product or SKU..." value="<?= e($search) ?>" data-table-search="inventoryTable">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= ($filter === 'all') ? 'selected' : '' ?>>All Inventory Levels</option>
                        <option value="low_stock" <?= ($filter === 'low_stock') ? 'selected' : '' ?>>Low Stock Only (<?= $totalLowStock ?>)</option>
                        <option value="out_of_stock" <?= ($filter === 'out_of_stock') ? 'selected' : '' ?>>Out of Stock Only (<?= $totalOutOfStock ?>)</option>
                    </select>
                </div>
                <div class="col-md-3 text-md-end">
                    <?php if ($filter !== 'all' || !empty($search)): ?>
                        <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-outline-secondary btn-sm">Reset Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-custom" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Product Name & Code</th>
                            <th>Category</th>
                            <th>Available Stock</th>
                            <th>Reorder Threshold</th>
                            <th>Cost Value</th>
                            <th>Retail Value</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inventoryItems)): ?>
                            <?php foreach ($inventoryItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= e($item['product_name']) ?></div>
                                        <div class="text-muted smaller">SKU: <code><?= e($item['product_code']) ?></code></div>
                                    </td>
                                    <td><span class="badge bg-light text-secondary border"><?= e($item['category_name']) ?></span></td>
                                    <td>
                                        <span class="fw-bold fs-6 text-dark"><?= formatQty($item['available_stock'], $item['unit']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?= formatQty($item['low_stock_threshold'], $item['unit']) ?></span>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?= formatCurrency($item['available_stock'] * $item['purchase_price']) ?></span>
                                    </td>
                                    <td>
                                        <span class="small fw-semibold text-success"><?= formatCurrency($item['available_stock'] * $item['selling_price']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($item['available_stock'] <= 0): ?>
                                            <span class="badge-stock-out">Out of Stock</span>
                                        <?php elseif ($item['available_stock'] <= $item['low_stock_threshold']): ?>
                                            <span class="badge-stock-low">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge-stock-in">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" 
                                                class="btn btn-sm btn-brand-light btn-quick-stock"
                                                data-id="<?= $item['id'] ?>"
                                                data-name="<?= e($item['product_name']) ?>"
                                                data-stock="<?= $item['available_stock'] ?>"
                                                data-unit="<?= e($item['unit']) ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#adjustStockModal">
                                            <i class="bi bi-arrow-down-up me-1"></i> Adjust
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    No inventory records found for selected filter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: AUDIT LOG (STOCK HISTORY) -->
    <div class="tab-pane fade" id="stock-history-pane" role="tabpanel">
        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0">Stock Movement Log</h5>
                    <span class="text-muted small">Automatic audit trail of inward purchases, sales, and manual adjustments</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Movement Type</th>
                            <th>Previous</th>
                            <th>Change Qty</th>
                            <th>New Stock</th>
                            <th>Reference / Note</th>
                            <th>Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stockHistory)): ?>
                            <?php foreach ($stockHistory as $h): ?>
                                <tr>
                                    <td>
                                        <span class="small text-muted"><?= date('d M Y, h:i A', strtotime($h['created_at'])) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= e($h['product_name']) ?></span>
                                        <span class="text-muted smaller d-block">SKU: <?= e($h['product_code']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($h['type'] === 'IN'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-arrow-down-left"></i> IN (Purchased)</span>
                                        <?php elseif ($h['type'] === 'OUT'): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><i class="bi bi-arrow-up-right"></i> OUT (Damage/Return)</span>
                                        <?php elseif ($h['type'] === 'SALE'): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><i class="bi bi-bag-check"></i> SALE</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning"><i class="bi bi-sliders"></i> ADJUSTMENT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="text-muted"><?= formatQty($h['previous_stock'], $h['unit']) ?></span></td>
                                    <td>
                                        <span class="fw-bold <?= ($h['type'] === 'IN') ? 'text-success' : (($h['type'] === 'OUT' || $h['type'] === 'SALE') ? 'text-danger' : 'text-primary') ?>">
                                            <?= ($h['type'] === 'IN') ? '+' : (($h['type'] === 'OUT' || $h['type'] === 'SALE') ? '-' : '') ?><?= formatQty($h['quantity'], $h['unit']) ?>
                                        </span>
                                    </td>
                                    <td><span class="fw-bold text-dark"><?= formatQty($h['new_stock'], $h['unit']) ?></span></td>
                                    <td>
                                        <div class="small fw-semibold"><?= !empty($h['reference_id']) ? e($h['reference_id']) : '-' ?></div>
                                        <div class="text-muted smaller"><?= e($h['notes'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?= e($h['admin_name'] ?? 'System') ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    No stock history logged yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Adjust Stock -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="adjust_stock">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="adjustStockModalLabel">
                        <i class="bi bi-arrow-down-up me-1"></i> Stock Adjustment / Inward
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Product <span class="text-danger">*</span></label>
                        <select class="form-select" name="product_id" id="modal_product_select" required>
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($allProducts as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['available_stock'] ?>" data-unit="<?= e($p['unit']) ?>">
                                    <?= e($p['product_name']) ?> (Current: <?= formatQty($p['available_stock'], $p['unit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Movement Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="adjustment_type" id="modal_type_select" required>
                            <option value="IN">Stock IN / Received (+ Add to current stock)</option>
                            <option value="OUT">Stock OUT / Damage / Waste (- Deduct from current stock)</option>
                            <option value="ADJUSTMENT">Manual Override (= Set exact count)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" id="modal_qty_label">Quantity to Add <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="quantity" id="modal_quantity" placeholder="e.g. 20" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reference ID (e.g. Invoice # / PO #)</label>
                        <input type="text" class="form-control" name="reference_id" placeholder="e.g. PO-2026-889 or DAMAGE-01">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes / Reason</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="e.g. Received new shipment from distributor..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand px-4">Submit Stock Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick stock adjust button from table row
    const quickStockBtns = document.querySelectorAll('.btn-quick-stock');
    quickStockBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const pId = this.getAttribute('data-id');
            const selectEl = document.getElementById('modal_product_select');
            if (selectEl) {
                selectEl.value = pId;
            }
        });
    });

    // Dynamic label based on movement type
    const typeSelect = document.getElementById('modal_type_select');
    const qtyLabel = document.getElementById('modal_qty_label');
    if (typeSelect && qtyLabel) {
        typeSelect.addEventListener('change', function() {
            if (this.value === 'IN') {
                qtyLabel.innerHTML = 'Quantity to Add <span class="text-danger">*</span>';
            } else if (this.value === 'OUT') {
                qtyLabel.innerHTML = 'Quantity to Deduct <span class="text-danger">*</span>';
            } else {
                qtyLabel.innerHTML = 'New Exact Physical Count <span class="text-danger">*</span>';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
