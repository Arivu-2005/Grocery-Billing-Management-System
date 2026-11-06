<?php
/**
 * POS Billing Terminal & Register
 * Grocery Billing Management System
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$currentUser = getLoggedInUser();

// Handle AJAX / POST Sale Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine if it's quick customer add or complete sale
    $action = $_POST['action'] ?? '';

    // 1. QUICK ADD CUSTOMER FROM POS MODAL
    if ($action === 'quick_add_customer') {
        header('Content-Type: application/json');
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
            exit;
        }

        $cName   = sanitizeInput($_POST['customer_name'] ?? '');
        $cMobile = sanitizeInput($_POST['mobile_number'] ?? '');
        $cEmail  = sanitizeInput($_POST['email'] ?? '');
        $cAddr   = sanitizeInput($_POST['address'] ?? '');

        if (empty($cName) || empty($cMobile)) {
            echo json_encode(['success' => false, 'message' => 'Name and Mobile Number are required.']);
            exit;
        }

        try {
            // Check duplicate phone
            $checkStmt = $pdo->prepare("SELECT id FROM `customers` WHERE `mobile_number` = ?");
            $checkStmt->execute([$cMobile]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'A customer with this mobile number already exists.']);
                exit;
            }

            $insStmt = $pdo->prepare("INSERT INTO `customers` (`customer_name`, `mobile_number`, `email`, `address`) VALUES (?, ?, ?, ?)");
            $insStmt->execute([$cName, $cMobile, $cEmail, $cAddr]);
            $newCustId = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'customer' => [
                    'id'    => $newCustId,
                    'name'  => $cName,
                    'phone' => $cMobile
                ]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    // 2. PROCESS COMPLETE SALE / CHECKOUT
    if ($action === 'complete_sale') {
        header('Content-Type: application/json');
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh and try again.']);
            exit;
        }

        $cartJson = $_POST['cart_data'] ?? '[]';
        $cartItems = json_decode($cartJson, true);

        if (!is_array($cartItems) || count($cartItems) === 0) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty. Please add items to cart before checkout.']);
            exit;
        }

        $customerId      = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $customerName    = sanitizeInput($_POST['customer_name'] ?? 'Walk-in Customer');
        $customerPhone   = sanitizeInput($_POST['customer_phone'] ?? '');
        $discountPercent = max(0, min(100, (float)($_POST['discount_percent'] ?? 0)));
        $taxPercent      = max(0, (float)($_POST['tax_percent'] ?? 0));
        $paymentMode     = in_array($_POST['payment_mode'] ?? '', ['Cash', 'UPI', 'Card']) ? $_POST['payment_mode'] : 'Cash';
        $notes           = sanitizeInput($_POST['notes'] ?? '');

        // Begin Transaction for 100% data integrity & stock validation
        try {
            $pdo->beginTransaction();

            $subtotal = 0.00;
            $verifiedItems = [];

            // Server-side verification of every product
            foreach ($cartItems as $item) {
                $pId = (int)($item['id'] ?? 0);
                $qty = (float)($item['qty'] ?? 0);

                if ($pId <= 0 || $qty <= 0) {
                    throw new Exception("Invalid item or quantity in cart.");
                }

                // Lock product row to prevent race conditions
                $pStmt = $pdo->prepare("SELECT id, product_name, product_code, unit, selling_price, available_stock FROM `products` WHERE id = ? FOR UPDATE");
                $pStmt->execute([$pId]);
                $product = $pStmt->fetch();

                if (!$product) {
                    throw new Exception("Product ID #{$pId} does not exist in inventory.");
                }

                // Check stock availability
                if ($product['available_stock'] < $qty) {
                    throw new Exception("Insufficient stock for '{$product['product_name']}'. Available: {$product['available_stock']} {$product['unit']}, Requested: {$qty} {$product['unit']}.");
                }

                $unitPrice = (float)$product['selling_price'];
                $itemTotal = round($unitPrice * $qty, 2);
                $subtotal += $itemTotal;

                $verifiedItems[] = [
                    'product_id'   => $product['id'],
                    'product_name' => $product['product_name'],
                    'product_code' => $product['product_code'],
                    'unit'         => $product['unit'],
                    'unit_price'   => $unitPrice,
                    'quantity'     => $qty,
                    'total_price'  => $itemTotal,
                    'prev_stock'   => (float)$product['available_stock']
                ];
            }

            // Calculate totals strictly in PHP
            $subtotal       = round($subtotal, 2);
            $discountAmount = round(($subtotal * $discountPercent) / 100, 2);
            $taxableAmount  = max(0, $subtotal - $discountAmount);
            $taxAmount      = round(($taxableAmount * $taxPercent) / 100, 2);
            $grandTotal     = round($taxableAmount + $taxAmount, 2);

            // Generate Unique Invoice Number (INV-YYYYMMDD-XXXX)
            $datePrefix = date('Ymd');
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `sales` WHERE DATE(`created_at`) = CURDATE()");
            $countStmt->execute();
            $dailyCount = (int)$countStmt->fetchColumn() + 1;
            $invoiceNo = 'INV-' . $datePrefix . '-' . str_pad($dailyCount, 4, '0', STR_PAD_LEFT);

            // Ensure invoice number uniqueness
            $checkInv = $pdo->prepare("SELECT id FROM `sales` WHERE `invoice_no` = ?");
            $checkInv->execute([$invoiceNo]);
            if ($checkInv->fetch()) {
                $invoiceNo .= '-' . rand(10, 99);
            }

            // Insert into sales table
            $saleStmt = $pdo->prepare("
                INSERT INTO `sales` 
                (`invoice_no`, `customer_id`, `customer_name`, `customer_phone`, `subtotal`, `discount_percentage`, `discount_amount`, `tax_percentage`, `tax_amount`, `grand_total`, `payment_mode`, `payment_status`, `notes`, `created_by`, `created_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', ?, ?, NOW())
            ");
            $saleStmt->execute([
                $invoiceNo,
                $customerId,
                $customerName,
                $customerPhone,
                $subtotal,
                $discountPercent,
                $discountAmount,
                $taxPercent,
                $taxAmount,
                $grandTotal,
                $paymentMode,
                $notes,
                $currentUser['id']
            ]);
            $saleId = $pdo->lastInsertId();

            // Insert Sale Items & Deduct Inventory Stock
            $itemStmt = $pdo->prepare("
                INSERT INTO `sale_items` 
                (`sale_id`, `product_id`, `product_name`, `product_code`, `unit`, `unit_price`, `quantity`, `total_price`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stockUpdateStmt = $pdo->prepare("UPDATE `products` SET `available_stock` = `available_stock` - ? WHERE `id` = ?");

            $historyStmt = $pdo->prepare("
                INSERT INTO `stock_history` 
                (`product_id`, `type`, `quantity`, `previous_stock`, `new_stock`, `reference_id`, `notes`, `created_by`) 
                VALUES (?, 'SALE', ?, ?, ?, ?, ?, ?)
            ");

            foreach ($verifiedItems as $v) {
                // 1. Insert item
                $itemStmt->execute([
                    $saleId,
                    $v['product_id'],
                    $v['product_name'],
                    $v['product_code'],
                    $v['unit'],
                    $v['unit_price'],
                    $v['quantity'],
                    $v['total_price']
                ]);

                // 2. Reduce stock
                $stockUpdateStmt->execute([$v['quantity'], $v['product_id']]);

                // 3. Record in stock_history
                $newStock = $v['prev_stock'] - $v['quantity'];
                $historyStmt->execute([
                    $v['product_id'],
                    $v['quantity'],
                    $v['prev_stock'],
                    $newStock,
                    $invoiceNo,
                    'Billed in ' . $invoiceNo . ' to ' . $customerName,
                    $currentUser['id']
                ]);
            }

            // Commit Transaction
            $pdo->commit();

            echo json_encode([
                'success'    => true,
                'message'    => 'Sale completed successfully!',
                'invoice_id' => $saleId,
                'invoice_no' => $invoiceNo,
                'redirect'   => BASE_URL . '/admin/invoice.php?id=' . $saleId
            ]);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Billing Failed: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}

// Fetch categories for filter pills
$categories = $pdo->query("SELECT * FROM `categories` ORDER BY `category_name` ASC")->fetchAll();

// Fetch all active products
$products = $pdo->query("
    SELECT p.*, c.category_name 
    FROM `products` p 
    JOIN `categories` c ON p.category_id = c.id 
    ORDER BY p.product_name ASC
")->fetchAll();

// Fetch customers for selector
$customers = $pdo->query("SELECT id, customer_name, mobile_number FROM `customers` ORDER BY `customer_name` ASC")->fetchAll();

$pageTitle = "POS Billing Terminal";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pos-container">

    <!-- LEFT: PRODUCT CATALOG & SEARCH SECTION -->
    <div class="pos-products-area">
        
        <!-- Search Bar and Barcode input -->
        <div class="table-card p-3 mb-3">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-success">
                            <i class="bi bi-upc-scan fs-5"></i>
                        </span>
                        <input type="text" id="posProductSearch" class="form-control border-start-0" placeholder="Type product name or scan barcode / SKU (Press Enter)..." autofocus>
                        <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn">Clear</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Filter Pills -->
        <div class="pos-category-pills">
            <button type="button" class="category-pill active" data-category-id="all">
                <i class="bi bi-grid-fill me-1"></i> All Products
            </button>
            <?php foreach ($categories as $cat): ?>
                <button type="button" class="category-pill" data-category-id="<?= $cat['id'] ?>">
                    <?= e($cat['category_name']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Product Cards Grid -->
        <div class="pos-products-grid" id="posProductsGrid">
            <?php foreach ($products as $p): ?>
                <?php 
                    $isOut = ($p['available_stock'] <= 0); 
                    $isLow = ($p['available_stock'] <= $p['low_stock_threshold']);
                ?>
                <div class="pos-product-card <?= $isOut ? 'out-of-stock' : '' ?>"
                     data-id="<?= $p['id'] ?>"
                     data-name="<?= e($p['product_name']) ?>"
                     data-price="<?= $p['selling_price'] ?>"
                     data-sku="<?= e($p['product_code']) ?>"
                     data-unit="<?= e($p['unit']) ?>"
                     data-stock="<?= $p['available_stock'] ?>"
                     data-category="<?= $p['category_id'] ?>">
                    
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;">
                                <?= e($p['product_code']) ?>
                            </span>
                            <?php if ($isOut): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($isLow): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php endif; ?>
                        </div>
                        <div class="pos-product-name"><?= e($p['product_name']) ?></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-end mt-2 pt-2 border-top">
                        <div>
                            <div class="text-muted smaller">Stock: <?= formatQty($p['available_stock'], $p['unit']) ?></div>
                            <div class="pos-product-price"><?= formatCurrency($p['selling_price']) ?></div>
                        </div>
                        <button type="button" class="btn btn-sm <?= $isOut ? 'btn-secondary' : 'btn-brand-light' ?> p-1 px-2" <?= $isOut ? 'disabled' : '' ?>>
                            <i class="bi bi-cart-plus-fill"></i> Add
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- RIGHT: ACTIVE BILL / REGISTER PANEL -->
    <div class="pos-cart-panel">
        
        <!-- Customer Selection Header -->
        <div class="p-3 border-bottom bg-light rounded-top">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-dark small text-uppercase">Customer Information</span>
                <button type="button" class="btn btn-sm btn-outline-success p-1 px-2" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                    <i class="bi bi-person-plus-fill me-1"></i> New
                </button>
            </div>
            <select class="form-select form-select-sm" id="posCustomerSelect">
                <option value="" data-name="Walk-in Customer" data-phone="">Walk-in Customer (Default)</option>
                <?php foreach ($customers as $cust): ?>
                    <option value="<?= $cust['id'] ?>" data-name="<?= e($cust['customer_name']) ?>" data-phone="<?= e($cust['mobile_number']) ?>">
                        <?= e($cust['customer_name']) ?> (<?= e($cust['mobile_number']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Cart Items List Table -->
        <div class="pos-cart-items-wrapper">
            <div id="cartEmptyState" class="text-center py-5 text-muted">
                <i class="bi bi-cart-x fs-1 d-block text-secondary mb-2"></i>
                <div class="fw-semibold">Cart is currently empty</div>
                <div class="smaller">Click any product or scan barcode to add</div>
            </div>

            <div id="cartItemsContainer" style="display: none;">
                <!-- Dynamically populated rows -->
            </div>
        </div>

        <!-- Calculations & Checkout Controls -->
        <div class="pos-bill-summary">
            <!-- Subtotal -->
            <div class="summary-line">
                <span>Subtotal</span>
                <span class="fw-bold text-dark" id="dispSubtotal"><?= CURRENCY_SYMBOL ?> 0.00</span>
            </div>

            <!-- Discount Input -->
            <div class="summary-line align-items-center">
                <div class="d-flex align-items-center gap-1">
                    <span>Discount (%)</span>
                    <input type="number" step="0.5" min="0" max="100" class="form-control form-control-sm text-end" id="inputDiscount" value="0" style="width: 65px; height: 26px;">
                </div>
                <span class="text-danger" id="dispDiscount">- <?= CURRENCY_SYMBOL ?> 0.00</span>
            </div>

            <!-- GST Tax Selector -->
            <div class="summary-line align-items-center">
                <div class="d-flex align-items-center gap-1">
                    <span>GST Rate</span>
                    <select class="form-select form-select-sm" id="selectTax" style="width: 85px; height: 28px;">
                        <option value="0">0% (None)</option>
                        <option value="5" selected>5% GST</option>
                        <option value="12">12% GST</option>
                        <option value="18">18% GST</option>
                    </select>
                </div>
                <span class="text-dark" id="dispTax">+ <?= CURRENCY_SYMBOL ?> 0.00</span>
            </div>

            <!-- Grand Total -->
            <div class="summary-total">
                <span>Grand Total</span>
                <span id="dispGrandTotal"><?= CURRENCY_SYMBOL ?> 0.00</span>
            </div>

            <!-- Payment Method Pills -->
            <div class="mt-3">
                <label class="form-label smaller fw-bold text-uppercase text-muted mb-1">Payment Method</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="payment_mode" id="payCash" value="Cash" checked>
                    <label class="btn btn-outline-success btn-sm" for="payCash"><i class="bi bi-cash-stack"></i> Cash</label>

                    <input type="radio" class="btn-check" name="payment_mode" id="payUpi" value="UPI">
                    <label class="btn btn-outline-success btn-sm" for="payUpi"><i class="bi bi-qr-code-scan"></i> UPI</label>

                    <input type="radio" class="btn-check" name="payment_mode" id="payCard" value="Card">
                    <label class="btn btn-outline-success btn-sm" for="payCard"><i class="bi bi-credit-card"></i> Card</label>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-outline-danger btn-sm px-3" id="clearCartBtn" title="Clear Cart">
                    <i class="bi bi-trash"></i>
                </button>
                <button type="button" class="btn btn-brand w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2" id="checkoutBtn" disabled>
                    <i class="bi bi-printer-fill"></i>
                    <span>Complete & Print Bill</span>
                </button>
            </div>
        </div>

    </div>

</div>

<!-- Modal: Quick Add Customer -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="quickCustomerForm">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="quick_add_customer">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus-fill me-1"></i> Quick Add Customer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div id="quickCustomerAlert" class="alert alert-danger d-none small"></div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="customer_name" required placeholder="e.g. Rahul Verma">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="mobile_number" required placeholder="10-digit mobile number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email (Optional)</label>
                        <input type="email" class="form-control" name="email" placeholder="customer@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Address (Optional)</label>
                        <textarea class="form-control" name="address" rows="2" placeholder="Local address..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand px-4">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: UPI QR Code Checkout Prompt -->
<div class="modal fade" id="upiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow text-center p-4">
            <h5 class="fw-bold mb-2">Scan & Pay via UPI</h5>
            <p class="text-muted small mb-3">Ask customer to scan using Google Pay, PhonePe, or Paytm</p>
            
            <div class="p-3 bg-light rounded d-inline-block mx-auto mb-3 border">
                <!-- Visual SVG QR Code placeholder -->
                <svg width="180" height="180" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" fill="#FFFFFF"/>
                    <!-- Finder Pattern Top-Left -->
                    <rect x="10" y="10" width="25" height="25" fill="#15803D" rx="4"/>
                    <rect x="15" y="15" width="15" height="15" fill="#FFFFFF"/>
                    <rect x="18" y="18" width="9" height="9" fill="#15803D"/>
                    <!-- Finder Pattern Top-Right -->
                    <rect x="65" y="10" width="25" height="25" fill="#15803D" rx="4"/>
                    <rect x="70" y="15" width="15" height="15" fill="#FFFFFF"/>
                    <rect x="73" y="18" width="9" height="9" fill="#15803D"/>
                    <!-- Finder Pattern Bottom-Left -->
                    <rect x="10" y="65" width="25" height="25" fill="#15803D" rx="4"/>
                    <rect x="15" y="70" width="15" height="15" fill="#FFFFFF"/>
                    <rect x="18" y="73" width="9" height="9" fill="#15803D"/>
                    <!-- Pixel clusters -->
                    <rect x="42" y="15" width="6" height="6" fill="#1E293B"/>
                    <rect x="52" y="25" width="6" height="6" fill="#1E293B"/>
                    <rect x="42" y="35" width="8" height="8" fill="#15803D"/>
                    <rect x="55" y="45" width="6" height="6" fill="#1E293B"/>
                    <rect x="25" y="45" width="8" height="8" fill="#1E293B"/>
                    <rect x="65" y="55" width="10" height="10" fill="#15803D"/>
                    <rect x="45" y="65" width="12" height="12" fill="#1E293B"/>
                    <rect x="75" y="75" width="10" height="10" fill="#15803D"/>
                </svg>
            </div>

            <h4 class="fw-bold text-success mb-2" id="upiAmountText"><?= CURRENCY_SYMBOL ?> 0.00</h4>
            <div class="text-muted smaller mb-4">Merchant ID: <strong><?= e(STORE_NAME) ?></strong> (UPI ID: freshmart@upi)</div>

            <div class="d-grid gap-2">
                <button type="button" class="btn btn-brand py-2 fw-semibold" id="confirmUpiPaymentBtn">
                    <i class="bi bi-check-circle-fill me-1"></i> Payment Received & Complete Sale
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- POS JavaScript Logic -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const CURRENCY_SYM = '<?= CURRENCY_SYMBOL ?> ';
    const CSRF_TOKEN = '<?= generateCsrfToken() ?>';

    // State
    let cart = []; // array of { id, name, sku, price, qty, unit, stock }

    // Elements
    const productsGrid      = document.getElementById('posProductsGrid');
    const searchInput       = document.getElementById('posProductSearch');
    const clearSearchBtn    = document.getElementById('clearSearchBtn');
    const categoryPills     = document.querySelectorAll('.category-pill');
    const cartContainer     = document.getElementById('cartItemsContainer');
    const cartEmptyState    = document.getElementById('cartEmptyState');
    const customerSelect    = document.getElementById('posCustomerSelect');
    const inputDiscount     = document.getElementById('inputDiscount');
    const selectTax         = document.getElementById('selectTax');
    const dispSubtotal      = document.getElementById('dispSubtotal');
    const dispDiscount      = document.getElementById('dispDiscount');
    const dispTax           = document.getElementById('dispTax');
    const dispGrandTotal    = document.getElementById('dispGrandTotal');
    const checkoutBtn       = document.getElementById('checkoutBtn');
    const clearCartBtn      = document.getElementById('clearCartBtn');

    // 1. Add to Cart from Card Click
    productsGrid.addEventListener('click', function (e) {
        const card = e.target.closest('.pos-product-card');
        if (!card || card.classList.contains('out-of-stock')) return;

        const id    = parseInt(card.dataset.id);
        const name  = card.dataset.name;
        const sku   = card.dataset.sku;
        const price = parseFloat(card.dataset.price);
        const unit  = card.dataset.unit;
        const stock = parseFloat(card.dataset.stock);

        addToCart(id, name, sku, price, unit, stock);
    });

    function addToCart(id, name, sku, price, unit, stock) {
        const existing = cart.find(item => item.id === id);
        if (existing) {
            if (existing.qty + 1 > stock) {
                alert(`Cannot add more. Maximum available stock for "${name}" is ${stock} ${unit}.`);
                return;
            }
            existing.qty += 1;
        } else {
            if (stock < 1) {
                alert(`Product "${name}" is out of stock.`);
                return;
            }
            cart.push({
                id: id,
                name: name,
                sku: sku,
                price: price,
                qty: 1,
                unit: unit,
                stock: stock
            });
        }
        renderCart();
    }

    // 2. Render Cart
    function renderCart() {
        if (cart.length === 0) {
            cartEmptyState.style.display = 'block';
            cartContainer.style.display = 'none';
            cartContainer.innerHTML = '';
            checkoutBtn.disabled = true;
        } else {
            cartEmptyState.style.display = 'none';
            cartContainer.style.display = 'block';
            checkoutBtn.disabled = false;

            let html = '';
            cart.forEach((item, index) => {
                const lineTotal = (item.price * item.qty).toFixed(2);
                html += `
                    <div class="cart-item-row" data-index="${index}">
                        <div style="flex: 1; padding-right: 8px;">
                            <div class="fw-bold text-dark text-truncate" style="max-width: 170px;">${item.name}</div>
                            <div class="smaller text-muted">
                                ${CURRENCY_SYM}${item.price.toFixed(2)} / ${item.unit}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="qty-control-btn btn-cart-minus" data-index="${index}"><i class="bi bi-dash"></i></button>
                            <input type="number" step="any" min="0.01" max="${item.stock}" value="${item.qty}" class="form-control form-control-sm text-center cart-qty-input p-0" style="width: 52px; height: 26px;" data-index="${index}">
                            <button type="button" class="qty-control-btn btn-cart-plus" data-index="${index}"><i class="bi bi-plus"></i></button>
                        </div>
                        <div class="text-end" style="width: 75px;">
                            <div class="fw-bold text-dark">${CURRENCY_SYM}${lineTotal}</div>
                            <a href="#" class="smaller text-danger text-decoration-none btn-cart-remove" data-index="${index}">Remove</a>
                        </div>
                    </div>
                `;
            });
            cartContainer.innerHTML = html;
        }

        recalculateTotals();
    }

    // 3. Cart Events (Plus, Minus, Direct Input, Remove)
    cartContainer.addEventListener('click', function (e) {
        if (e.target.closest('.btn-cart-plus')) {
            const index = parseInt(e.target.closest('.btn-cart-plus').dataset.index);
            const item = cart[index];
            if (item.qty + 1 > item.stock) {
                alert(`Maximum available stock is ${item.stock} ${item.unit}.`);
                return;
            }
            item.qty += 1;
            renderCart();
        } else if (e.target.closest('.btn-cart-minus')) {
            const index = parseInt(e.target.closest('.btn-cart-minus').dataset.index);
            if (cart[index].qty > 1) {
                cart[index].qty -= 1;
            } else {
                cart.splice(index, 1);
            }
            renderCart();
        } else if (e.target.closest('.btn-cart-remove')) {
            e.preventDefault();
            const index = parseInt(e.target.closest('.btn-cart-remove').dataset.index);
            cart.splice(index, 1);
            renderCart();
        }
    });

    cartContainer.addEventListener('change', function (e) {
        if (e.target.classList.contains('cart-qty-input')) {
            const index = parseInt(e.target.dataset.index);
            let val = parseFloat(e.target.value);
            const item = cart[index];

            if (isNaN(val) || val <= 0) {
                val = 1;
            } else if (val > item.stock) {
                alert(`Stock limit reached! Max available: ${item.stock} ${item.unit}`);
                val = item.stock;
            }
            item.qty = val;
            renderCart();
        }
    });

    // 4. Calculations (Subtotal, Discount, Tax, Grand Total)
    function recalculateTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += (item.price * item.qty);
        });

        const discountPct = parseFloat(inputDiscount.value) || 0;
        const discountAmt = (subtotal * discountPct) / 100;
        const taxable = Math.max(0, subtotal - discountAmt);

        const taxPct = parseFloat(selectTax.value) || 0;
        const taxAmt = (taxable * taxPct) / 100;

        const grandTotal = taxable + taxAmt;

        dispSubtotal.textContent   = CURRENCY_SYM + subtotal.toFixed(2);
        dispDiscount.textContent   = '- ' + CURRENCY_SYM + discountAmt.toFixed(2);
        dispTax.textContent        = '+ ' + CURRENCY_SYM + taxAmt.toFixed(2);
        dispGrandTotal.textContent = CURRENCY_SYM + grandTotal.toFixed(2);

        document.getElementById('upiAmountText').textContent = CURRENCY_SYM + grandTotal.toFixed(2);
    }

    inputDiscount.addEventListener('input', recalculateTotals);
    selectTax.addEventListener('change', recalculateTotals);

    // 5. Clear Cart
    clearCartBtn.addEventListener('click', function () {
        if (cart.length > 0 && confirm('Are you sure you want to clear the active bill?')) {
            cart = [];
            inputDiscount.value = 0;
            renderCart();
        }
    });

    // 6. Category Filtering
    categoryPills.forEach(pill => {
        pill.addEventListener('click', function () {
            categoryPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');

            const catId = this.dataset.categoryId;
            const cards = productsGrid.querySelectorAll('.pos-product-card');

            cards.forEach(card => {
                if (catId === 'all' || card.dataset.category === catId) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // 7. Live Product & Barcode Search
    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const cards = productsGrid.querySelectorAll('.pos-product-card');

        cards.forEach(card => {
            const name = card.dataset.name.toLowerCase();
            const sku  = card.dataset.sku.toLowerCase();

            if (name.includes(query) || sku.includes(query)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Barcode Enter-Key Fast Add
    searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = this.value.toUpperCase().trim();
            if (!query) return;

            // Find exact match by SKU
            const matchingCard = Array.from(productsGrid.querySelectorAll('.pos-product-card')).find(c => c.dataset.sku === query);
            if (matchingCard && !matchingCard.classList.contains('out-of-stock')) {
                addToCart(
                    parseInt(matchingCard.dataset.id),
                    matchingCard.dataset.name,
                    matchingCard.dataset.sku,
                    parseFloat(matchingCard.dataset.price),
                    matchingCard.dataset.unit,
                    parseFloat(matchingCard.dataset.stock)
                );
                this.value = '';
                // trigger input event to show all
                searchInput.dispatchEvent(new Event('input'));
            } else if (matchingCard && matchingCard.classList.contains('out-of-stock')) {
                alert(`Product with SKU ${query} is out of stock!`);
            }
        }
    });

    clearSearchBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
    });

    // 8. Checkout & Server Processing
    checkoutBtn.addEventListener('click', function () {
        if (cart.length === 0) {
            alert('Please add items to cart before completing sale.');
            return;
        }

        const paymentMode = document.querySelector('input[name="payment_mode"]:checked').value;
        if (paymentMode === 'UPI') {
            const upiModal = new bootstrap.Modal(document.getElementById('upiModal'));
            upiModal.show();
        } else {
            submitSale(paymentMode);
        }
    });

    document.getElementById('confirmUpiPaymentBtn').addEventListener('click', function () {
        const upiModalEl = document.getElementById('upiModal');
        const modal = bootstrap.Modal.getInstance(upiModalEl);
        if (modal) modal.hide();
        submitSale('UPI');
    });

    function submitSale(paymentMode) {
        const selectedOpt = customerSelect.options[customerSelect.selectedIndex];
        const customerId = customerSelect.value || null;
        const customerName = selectedOpt.getAttribute('data-name') || 'Walk-in Customer';
        const customerPhone = selectedOpt.getAttribute('data-phone') || '';

        checkoutBtn.disabled = true;
        checkoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing Sale...';

        const formData = new FormData();
        formData.append('action', 'complete_sale');
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('customer_id', customerId || '');
        formData.append('customer_name', customerName);
        formData.append('customer_phone', customerPhone);
        formData.append('cart_data', JSON.stringify(cart));
        formData.append('discount_percent', inputDiscount.value || 0);
        formData.append('tax_percent', selectTax.value || 0);
        formData.append('payment_mode', paymentMode);

        fetch('<?= BASE_URL ?>/admin/billing.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Clear cart and redirect to invoice
                cart = [];
                window.location.href = data.redirect;
            } else {
                alert('Sale Error: ' + data.message);
                checkoutBtn.disabled = false;
                checkoutBtn.innerHTML = '<i class="bi bi-printer-fill"></i> <span>Complete & Print Bill</span>';
            }
        })
        .catch(err => {
            alert('Network or server error while completing sale: ' + err);
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = '<i class="bi bi-printer-fill"></i> <span>Complete & Print Bill</span>';
        });
    }

    // 9. Quick Add Customer AJAX
    const quickCustomerForm = document.getElementById('quickCustomerForm');
    const quickCustomerAlert = document.getElementById('quickCustomerAlert');
    quickCustomerForm.addEventListener('submit', function (e) {
        e.preventDefault();
        quickCustomerAlert.classList.add('d-none');

        const formData = new FormData(this);
        fetch('<?= BASE_URL ?>/admin/billing.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Add new customer to select dropdown and select it
                const newOpt = document.createElement('option');
                newOpt.value = data.customer.id;
                newOpt.textContent = `${data.customer.name} (${data.customer.phone})`;
                newOpt.setAttribute('data-name', data.customer.name);
                newOpt.setAttribute('data-phone', data.customer.phone);
                customerSelect.appendChild(newOpt);
                customerSelect.value = data.customer.id;

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('newCustomerModal'));
                if (modal) modal.hide();
                quickCustomerForm.reset();
            } else {
                quickCustomerAlert.textContent = data.message;
                quickCustomerAlert.classList.remove('d-none');
            }
        })
        .catch(err => {
            quickCustomerAlert.textContent = 'Failed to save customer: ' + err;
            quickCustomerAlert.classList.remove('d-none');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
