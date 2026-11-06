<?php
/**
 * Product Management
 * Grocery Billing Management System
 */

$pageTitle = "Products";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$currentUser = getLoggedInUser();

// Handle Form Submissions (Create, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security token mismatch. Please try again.');
        header("Location: " . BASE_URL . "/admin/products.php");
        exit;
    }

    // 1. ADD PRODUCT
    if ($action === 'add') {
        $productName   = sanitizeInput($_POST['product_name'] ?? '');
        $categoryId    = (int)($_POST['category_id'] ?? 0);
        $productCode   = strtoupper(trim(sanitizeInput($_POST['product_code'] ?? '')));
        $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
        $sellingPrice  = (float)($_POST['selling_price'] ?? 0);
        $initialStock  = (float)($_POST['available_stock'] ?? 0);
        $unit          = sanitizeInput($_POST['unit'] ?? 'Piece');
        $lowThreshold  = (float)($_POST['low_stock_threshold'] ?? 10);

        if (empty($productName) || $categoryId <= 0 || empty($productCode)) {
            setFlashMessage('danger', 'Product name, category, and SKU / code are required.');
        } elseif ($purchasePrice < 0 || $sellingPrice < 0 || $initialStock < 0) {
            setFlashMessage('danger', 'Prices and stock quantities cannot be negative.');
        } else {
            try {
                // Check duplicate SKU
                $checkSku = $pdo->prepare("SELECT id FROM `products` WHERE `product_code` = ?");
                $checkSku->execute([$productCode]);
                if ($checkSku->fetch()) {
                    setFlashMessage('danger', "Product code / SKU '{$productCode}' is already taken. Please use a unique code.");
                } else {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO `products` 
                        (`product_name`, `category_id`, `product_code`, `purchase_price`, `selling_price`, `available_stock`, `unit`, `low_stock_threshold`) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insertStmt->execute([
                        $productName, $categoryId, $productCode, $purchasePrice, $sellingPrice, $initialStock, $unit, $lowThreshold
                    ]);
                    $newProductId = $pdo->lastInsertId();

                    // Record initial stock in stock_history if stock > 0
                    if ($initialStock > 0) {
                        $histStmt = $pdo->prepare("
                            INSERT INTO `stock_history` 
                            (`product_id`, `type`, `quantity`, `previous_stock`, `new_stock`, `reference_id`, `notes`, `created_by`) 
                            VALUES (?, 'IN', ?, 0, ?, 'INITIAL-STOCK', 'Initial product creation intake', ?)
                        ");
                        $histStmt->execute([$newProductId, $initialStock, $initialStock, $currentUser['id']]);
                    }

                    setFlashMessage('success', "Product '{$productName}' added successfully!");
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error adding product: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/products.php");
        exit;
    }

    // 2. EDIT PRODUCT
    if ($action === 'edit') {
        $productId     = (int)($_POST['product_id'] ?? 0);
        $productName   = sanitizeInput($_POST['product_name'] ?? '');
        $categoryId    = (int)($_POST['category_id'] ?? 0);
        $productCode   = strtoupper(trim(sanitizeInput($_POST['product_code'] ?? '')));
        $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
        $sellingPrice  = (float)($_POST['selling_price'] ?? 0);
        $unit          = sanitizeInput($_POST['unit'] ?? 'Piece');
        $lowThreshold  = (float)($_POST['low_stock_threshold'] ?? 10);

        if ($productId <= 0 || empty($productName) || $categoryId <= 0 || empty($productCode)) {
            setFlashMessage('danger', 'Invalid product data provided.');
        } elseif ($purchasePrice < 0 || $sellingPrice < 0) {
            setFlashMessage('danger', 'Prices cannot be negative numbers.');
        } else {
            try {
                // Check duplicate SKU excluding this product
                $checkSku = $pdo->prepare("SELECT id FROM `products` WHERE `product_code` = ? AND `id` != ?");
                $checkSku->execute([$productCode, $productId]);
                if ($checkSku->fetch()) {
                    setFlashMessage('danger', "Product code / SKU '{$productCode}' is already used by another product.");
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE `products` 
                        SET `product_name` = ?, `category_id` = ?, `product_code` = ?, `purchase_price` = ?, `selling_price` = ?, `unit` = ?, `low_stock_threshold` = ?
                        WHERE `id` = ?
                    ");
                    $updateStmt->execute([
                        $productName, $categoryId, $productCode, $purchasePrice, $sellingPrice, $unit, $lowThreshold, $productId
                    ]);
                    setFlashMessage('success', "Product '{$productName}' updated successfully!");
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error updating product: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/products.php");
        exit;
    }

    // 3. DELETE PRODUCT
    if ($action === 'delete') {
        $productId = (int)($_POST['product_id'] ?? 0);

        if ($productId <= 0) {
            setFlashMessage('danger', 'Invalid product selected for deletion.');
        } else {
            try {
                // Check if product is referenced in sale_items
                $saleCheck = $pdo->prepare("SELECT COUNT(*) FROM `sale_items` WHERE `product_id` = ?");
                $saleCheck->execute([$productId]);
                $salesCount = (int)$saleCheck->fetchColumn();

                if ($salesCount > 0) {
                    setFlashMessage('danger', "Cannot delete this product because it is referenced in {$salesCount} past billing invoices. To preserve transaction audit integrity, products with sales cannot be deleted.");
                } else {
                    $delStmt = $pdo->prepare("DELETE FROM `products` WHERE `id` = ?");
                    $delStmt->execute([$productId]);
                    setFlashMessage('success', 'Product deleted successfully!');
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error deleting product: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/products.php");
        exit;
    }
}

// Fetch categories for dropdowns & filters
$allCategories = $pdo->query("SELECT * FROM `categories` ORDER BY `category_name` ASC")->fetchAll();

// Build Query with Filters & Search
$search = sanitizeInput($_GET['search'] ?? '');
$filterCat = (int)($_GET['category_id'] ?? 0);
$filterStock = sanitizeInput($_GET['stock_status'] ?? '');

$sql = "
    SELECT p.*, c.category_name 
    FROM `products` p 
    JOIN `categories` c ON p.category_id = c.id 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterCat > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $filterCat;
}

if ($filterStock === 'low') {
    $sql .= " AND p.available_stock > 0 AND p.available_stock <= p.low_stock_threshold";
} elseif ($filterStock === 'out') {
    $sql .= " AND p.available_stock <= 0";
} elseif ($filterStock === 'in') {
    $sql .= " AND p.available_stock > p.low_stock_threshold";
}

$sql .= " ORDER BY p.id DESC";

$prodStmt = $pdo->prepare($sql);
$prodStmt->execute($params);
$products = $prodStmt->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="page-header-title">Product Catalog</h2>
        <p class="text-muted small mb-0">Manage grocery items, SKU codes, pricing, units, and inventory thresholds</p>
    </div>
    <div>
        <button type="button" class="btn btn-brand shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Product
        </button>
    </div>
</div>

<!-- Filters & Search Toolbar -->
<div class="table-card p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <!-- Search Keyword -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name or SKU..." value="<?= e($search) ?>" data-table-search="productsTable">
            </div>
        </div>

        <!-- Category Filter -->
        <div class="col-md-3">
            <select name="category_id" class="form-select" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($allCategories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($filterCat == $c['id']) ? 'selected' : '' ?>>
                        <?= e($c['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Stock Status Filter -->
        <div class="col-md-3">
            <select name="stock_status" class="form-select" onchange="this.form.submit()">
                <option value="" <?= ($filterStock === '') ? 'selected' : '' ?>>All Stock Status</option>
                <option value="in" <?= ($filterStock === 'in') ? 'selected' : '' ?>>In Stock</option>
                <option value="low" <?= ($filterStock === 'low') ? 'selected' : '' ?>>Low Stock</option>
                <option value="out" <?= ($filterStock === 'out') ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>

        <div class="col-md-2 text-md-end">
            <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
        </div>
    </form>
</div>

<!-- Products Data Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-custom" id="productsTable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Purchase Price</th>
                    <th>Selling Price</th>
                    <th>Available Stock</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?= e($p['product_name']) ?></div>
                                <div class="text-muted smaller">
                                    <span class="badge bg-light text-secondary border font-monospace">SKU: <?= e($p['product_code']) ?></span>
                                    <span class="ms-1">&bull; Unit: <?= e($p['unit']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= e($p['category_name']) ?></span>
                            </td>
                            <td>
                                <span class="text-muted small"><?= formatCurrency($p['purchase_price']) ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-success fs-6"><?= formatCurrency($p['selling_price']) ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark"><?= formatQty($p['available_stock'], $p['unit']) ?></span>
                                <div class="text-muted smaller">Min: <?= formatQty($p['low_stock_threshold'], $p['unit']) ?></div>
                            </td>
                            <td>
                                <?php if ($p['available_stock'] <= 0): ?>
                                    <span class="badge-stock-out"><i class="bi bi-x-circle-fill me-1"></i> Out of Stock</span>
                                <?php elseif ($p['available_stock'] <= $p['low_stock_threshold']): ?>
                                    <span class="badge-stock-low"><i class="bi bi-exclamation-triangle-fill me-1"></i> Low Stock</span>
                                <?php else: ?>
                                    <span class="badge-stock-in"><i class="bi bi-check-circle-fill me-1"></i> In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- View Details Button -->
                                    <button type="button" 
                                            class="btn btn-outline-info btn-view-product"
                                            data-id="<?= $p['id'] ?>"
                                            data-name="<?= e($p['product_name']) ?>"
                                            data-category="<?= e($p['category_name']) ?>"
                                            data-sku="<?= e($p['product_code']) ?>"
                                            data-purchase="<?= $p['purchase_price'] ?>"
                                            data-selling="<?= $p['selling_price'] ?>"
                                            data-stock="<?= $p['available_stock'] ?>"
                                            data-unit="<?= e($p['unit']) ?>"
                                            data-threshold="<?= $p['low_stock_threshold'] ?>"
                                            data-created="<?= date('d M Y, h:i A', strtotime($p['created_at'])) ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewProductModal"
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <!-- Edit Button -->
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-edit-product"
                                            data-id="<?= $p['id'] ?>"
                                            data-name="<?= e($p['product_name']) ?>"
                                            data-category-id="<?= $p['category_id'] ?>"
                                            data-sku="<?= e($p['product_code']) ?>"
                                            data-purchase="<?= $p['purchase_price'] ?>"
                                            data-selling="<?= $p['selling_price'] ?>"
                                            data-unit="<?= e($p['unit']) ?>"
                                            data-threshold="<?= $p['low_stock_threshold'] ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editProductModal"
                                            title="Edit Product">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Delete Button Form -->
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete product \'<?= addslashes(e($p['product_name'])) ?>\'?');">
                                        <?= renderCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Delete Product">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-box2 fs-1 d-block text-secondary mb-2"></i>
                            No products found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Product -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="addProductModalLabel">
                        <i class="bi bi-plus-circle me-1"></i> Add New Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="product_name" placeholder="e.g. Fortune Sunflower Oil 1L" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($allCategories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Product SKU / Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="product_code" placeholder="e.g. OIL-001" required>
                            <small class="text-muted">Unique barcode / SKU</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Unit Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="unit" required>
                                <option value="Piece">Piece (pc)</option>
                                <option value="KG">KG (Kilogram)</option>
                                <option value="Gram">Gram (g)</option>
                                <option value="Litre">Litre (L)</option>
                                <option value="Pack">Pack</option>
                                <option value="Box">Box</option>
                                <option value="Bottle">Bottle</option>
                                <option value="Bag">Bag</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Initial Stock</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="available_stock" value="10" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Purchase Price (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="purchase_price" placeholder="0.00" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Selling Price (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="selling_price" placeholder="0.00" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Low Stock Threshold</label>
                            <input type="number" step="0.01" min="1" class="form-control" name="low_stock_threshold" value="10" required>
                            <small class="text-muted">Alert triggers at or below</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand px-4">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Product -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" id="edit_product_id" value="">
                
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="editProductModalLabel">
                        <i class="bi bi-pencil-square me-1"></i> Edit Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_product_name" name="product_name" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_category_id" name="category_id" required>
                                <?php foreach ($allCategories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Product SKU / Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="edit_product_code" name="product_code" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Unit Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_unit" name="unit" required>
                                <option value="Piece">Piece (pc)</option>
                                <option value="KG">KG (Kilogram)</option>
                                <option value="Gram">Gram (g)</option>
                                <option value="Litre">Litre (L)</option>
                                <option value="Pack">Pack</option>
                                <option value="Box">Box</option>
                                <option value="Bottle">Bottle</option>
                                <option value="Bag">Bag</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Low Stock Threshold</label>
                            <input type="number" step="0.01" min="1" class="form-control" id="edit_low_stock_threshold" name="low_stock_threshold" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Purchase Price (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_purchase_price" name="purchase_price" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Selling Price (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_selling_price" name="selling_price" required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand px-4">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: View Product Details -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-info-circle text-primary me-1"></i> Product Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <h4 class="fw-bold text-success mb-1" id="view_name"></h4>
                <div class="text-muted small mb-3">Category: <span class="fw-semibold text-dark" id="view_category"></span></div>

                <div class="card bg-light border-0 p-3 mb-3">
                    <div class="row g-2 small">
                        <div class="col-6"><strong>Product SKU:</strong> <code id="view_sku"></code></div>
                        <div class="col-6"><strong>Unit:</strong> <span id="view_unit"></span></div>
                        <div class="col-6"><strong>Purchase Price:</strong> <span class="text-dark fw-bold" id="view_purchase"></span></div>
                        <div class="col-6"><strong>Selling Price:</strong> <span class="text-success fw-bold" id="view_selling"></span></div>
                        <div class="col-6"><strong>Profit Margin:</strong> <span class="badge bg-success" id="view_margin"></span></div>
                        <div class="col-6"><strong>Available Stock:</strong> <span class="fw-bold" id="view_stock"></span></div>
                        <div class="col-6"><strong>Low Threshold:</strong> <span id="view_threshold"></span></div>
                        <div class="col-6"><strong>Created Date:</strong> <span id="view_created"></span></div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-boxes me-1"></i> Stock Adjustments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Populate Edit Product Modal
    const editBtns = document.querySelectorAll('.btn-edit-product');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_product_id').value = this.getAttribute('data-id');
            document.getElementById('edit_product_name').value = this.getAttribute('data-name');
            document.getElementById('edit_category_id').value = this.getAttribute('data-category-id');
            document.getElementById('edit_product_code').value = this.getAttribute('data-sku');
            document.getElementById('edit_purchase_price').value = this.getAttribute('data-purchase');
            document.getElementById('edit_selling_price').value = this.getAttribute('data-selling');
            document.getElementById('edit_unit').value = this.getAttribute('data-unit');
            document.getElementById('edit_low_stock_threshold').value = this.getAttribute('data-threshold');
        });
    });

    // Populate View Product Modal
    const viewBtns = document.querySelectorAll('.btn-view-product');
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const purchase = parseFloat(this.getAttribute('data-purchase')) || 0;
            const selling = parseFloat(this.getAttribute('data-selling')) || 0;
            const margin = (selling - purchase);
            const marginPct = (purchase > 0) ? ((margin / purchase) * 100).toFixed(1) + '%' : 'N/A';

            document.getElementById('view_name').textContent = this.getAttribute('data-name');
            document.getElementById('view_category').textContent = this.getAttribute('data-category');
            document.getElementById('view_sku').textContent = this.getAttribute('data-sku');
            document.getElementById('view_unit').textContent = this.getAttribute('data-unit');
            document.getElementById('view_purchase').textContent = '<?= CURRENCY_SYMBOL ?> ' + purchase.toFixed(2);
            document.getElementById('view_selling').textContent = '<?= CURRENCY_SYMBOL ?> ' + selling.toFixed(2);
            document.getElementById('view_stock').textContent = this.getAttribute('data-stock') + ' ' + this.getAttribute('data-unit');
            document.getElementById('view_threshold').textContent = this.getAttribute('data-threshold') + ' ' + this.getAttribute('data-unit');
            document.getElementById('view_created').textContent = this.getAttribute('data-created');
            document.getElementById('view_margin').textContent = '<?= CURRENCY_SYMBOL ?> ' + margin.toFixed(2) + ' (' + marginPct + ')';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
