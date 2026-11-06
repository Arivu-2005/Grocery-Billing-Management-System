<?php
/**
 * Invoice View & Print (A4 & Thermal Receipt)
 * Grocery Billing Management System
 */

$pageTitle = "Invoice Details";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$saleId = (int)($_GET['id'] ?? 0);

if ($saleId <= 0) {
    setFlashMessage('danger', 'Invalid invoice ID.');
    header("Location: " . BASE_URL . "/admin/sales.php");
    exit;
}

try {
    // Fetch Sale Header
    $saleStmt = $pdo->prepare("
        SELECT s.*, c.email AS customer_email, c.address AS customer_address, a.full_name AS cashier_name
        FROM `sales` s
        LEFT JOIN `customers` c ON s.customer_id = c.id
        LEFT JOIN `admin` a ON s.created_by = a.id
        WHERE s.id = ?
    ");
    $saleStmt->execute([$saleId]);
    $sale = $saleStmt->fetch();

    if (!$sale) {
        setFlashMessage('danger', 'Invoice not found.');
        header("Location: " . BASE_URL . "/admin/sales.php");
        exit;
    }

    // Fetch Sale Items
    $itemsStmt = $pdo->prepare("SELECT * FROM `sale_items` WHERE `sale_id` = ? ORDER BY `id` ASC");
    $itemsStmt->execute([$saleId]);
    $items = $itemsStmt->fetchAll();

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error retrieving invoice: ' . e($e->getMessage()) . '</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<!-- Action Bar (Hidden when printing) -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/sales.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Sales
        </a>
        <h4 class="fw-bold mb-0">Invoice: <?= e($sale['invoice_no']) ?></h4>
    </div>
    
    <div class="d-flex flex-wrap gap-2">
        <!-- Layout Switch Buttons -->
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-success active" id="btnA4View">
                <i class="bi bi-file-earmark-text me-1"></i> Standard A4
            </button>
            <button type="button" class="btn btn-outline-success" id="btnThermalView">
                <i class="bi bi-receipt me-1"></i> 80mm Thermal Receipt
            </button>
        </div>

        <button type="button" class="btn btn-brand btn-sm shadow-sm" onclick="window.print();">
            <i class="bi bi-printer-fill me-1"></i> Print Invoice
        </button>

        <a href="<?= BASE_URL ?>/admin/billing.php" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-plus-circle me-1"></i> New Bill
        </a>
    </div>
</div>

<!-- ========================================== -->
<!-- 1. STANDARD A4 INVOICE FORMAT               -->
<!-- ========================================== -->
<div class="invoice-card p-4 p-md-5 mb-5" id="a4InvoiceSection">
    
    <!-- Invoice Header -->
    <div class="row align-items-start border-bottom pb-4 mb-4">
        <div class="col-sm-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="bg-success text-white rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="bi bi-basket-fill fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= e(STORE_NAME) ?></h3>
                    <div class="text-success small fw-semibold"><?= e(STORE_TAGLINE) ?></div>
                </div>
            </div>
            <div class="text-muted small">
                <div><?= e(STORE_ADDRESS) ?></div>
                <div><strong>Phone:</strong> <?= e(STORE_PHONE) ?> | <strong>Email:</strong> <?= e(STORE_EMAIL) ?></div>
                <div><strong>GSTIN:</strong> <?= e(STORE_GSTIN) ?></div>
            </div>
        </div>

        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
            <h4 class="text-uppercase fw-bold text-success mb-1">Tax Invoice</h4>
            <div class="fw-bold fs-5 text-dark"><?= e($sale['invoice_no']) ?></div>
            <div class="text-muted small mt-1">
                <div><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></div>
                <div><strong>Cashier:</strong> <?= e($sale['cashier_name'] ?? 'Counter #1') ?></div>
                <div><strong>Payment:</strong> <span class="badge bg-light text-dark border"><?= e($sale['payment_mode']) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Customer Details -->
    <div class="card bg-light border-0 p-3 mb-4">
        <div class="row g-2 small">
            <div class="col-md-6">
                <span class="text-muted text-uppercase smaller fw-bold d-block mb-1">Billed To (Customer):</span>
                <div class="fw-bold text-dark fs-6"><?= e($sale['customer_name']) ?></div>
                <?php if (!empty($sale['customer_phone'])): ?>
                    <div class="text-muted"><i class="bi bi-telephone me-1"></i> <?= e($sale['customer_phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($sale['customer_address'])): ?>
                    <div class="text-muted"><i class="bi bi-geo-alt me-1"></i> <?= e($sale['customer_address']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted text-uppercase smaller fw-bold d-block mb-1">Payment Status:</span>
                <span class="badge bg-success fs-6 fw-bold px-3 py-1"><?= e($sale['payment_status']) ?></span>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered">
            <thead class="table-light text-uppercase small">
                <tr>
                    <th style="width: 50px;" class="text-center">#</th>
                    <th>Item Description</th>
                    <th>SKU</th>
                    <th class="text-end" style="width: 120px;">Price</th>
                    <th class="text-center" style="width: 100px;">Qty</th>
                    <th class="text-end" style="width: 130px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $idx = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center text-muted small"><?= $idx++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($item['product_name']) ?></div>
                            <div class="smaller text-muted">Unit: <?= e($item['unit']) ?></div>
                        </td>
                        <td class="small text-muted font-monospace"><?= e($item['product_code']) ?></td>
                        <td class="text-end small"><?= formatCurrency($item['unit_price']) ?></td>
                        <td class="text-center fw-semibold"><?= formatQty($item['quantity'], $item['unit']) ?></td>
                        <td class="text-end fw-bold text-dark"><?= formatCurrency($item['total_price']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Invoice Summary & Totals -->
    <div class="row align-items-start">
        <div class="col-md-7">
            <div class="card border-0 bg-light p-3 small text-muted">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i> Terms & Conditions:</h6>
                <ul class="mb-0 ps-3">
                    <li>Goods once sold will not be returned or exchanged after 48 hours.</li>
                    <li>For perishable items, issues must be reported on the same day.</li>
                    <li>Keep this invoice for all future inquiries and warranty claims.</li>
                </ul>
            </div>
        </div>

        <div class="col-md-5 mt-3 mt-md-0">
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="text-muted">Subtotal:</td>
                    <td class="text-end fw-semibold"><?= formatCurrency($sale['subtotal']) ?></td>
                </tr>
                <?php if ($sale['discount_amount'] > 0): ?>
                    <tr>
                        <td class="text-muted">Discount (<?= $sale['discount_percentage'] ?>%):</td>
                        <td class="text-end text-danger">- <?= formatCurrency($sale['discount_amount']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="text-muted">GST Tax (<?= $sale['tax_percentage'] ?>%):</td>
                    <td class="text-end text-dark">+ <?= formatCurrency($sale['tax_amount']) ?></td>
                </tr>
                <tr class="border-top border-dark border-2">
                    <td class="fs-5 fw-bold text-success pt-2">Grand Total:</td>
                    <td class="fs-5 fw-bold text-success text-end pt-2"><?= formatCurrency($sale['grand_total']) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Footer Thank You Note -->
    <div class="text-center border-top pt-4 mt-4 text-muted small">
        <div class="fw-bold text-dark fs-6 mb-1">Thank you for shopping with <?= e(STORE_NAME) ?>!</div>
        <div>Please visit again! For online orders, visit support@freshmart.com</div>
    </div>
</div>

<!-- ========================================== -->
<!-- 2. 80MM THERMAL RECEIPT FORMAT (POS STYLE)  -->
<!-- ========================================== -->
<div class="thermal-receipt mb-5" id="thermalReceiptSection" style="display: none;">
    <div class="text-center mb-2">
        <div class="fw-bold fs-6"><?= strtoupper(e(STORE_NAME)) ?></div>
        <div><?= e(STORE_ADDRESS) ?></div>
        <div>Tel: <?= e(STORE_PHONE) ?></div>
        <div>GSTIN: <?= e(STORE_GSTIN) ?></div>
    </div>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <div>
        <div>Inv No: <strong><?= e($sale['invoice_no']) ?></strong></div>
        <div>Date: <?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?></div>
        <div>Cust: <?= e($sale['customer_name']) ?> (<?= e($sale['customer_phone'] ?: 'N/A') ?>)</div>
        <div>Cashier: <?= e($sale['cashier_name'] ?? 'Admin') ?></div>
    </div>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <table style="width: 100%; font-size: 0.8rem;">
        <thead>
            <tr style="border-bottom: 1px dashed #000;">
                <th style="text-align: left;">Item</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Rate</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td colspan="4" style="padding-top: 3px; font-weight: bold;"><?= e($item['product_name']) ?></td>
                </tr>
                <tr>
                    <td style="color: #444; font-size: 0.75rem;"><?= e($item['product_code']) ?></td>
                    <td style="text-align: center;"><?= formatQty($item['quantity'], '') ?></td>
                    <td style="text-align: right;"><?= number_format($item['unit_price'], 2) ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($item['total_price'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <div style="font-size: 0.85rem;">
        <div style="display: flex; justify-content: space-between;">
            <span>Subtotal:</span>
            <span><?= CURRENCY_SYMBOL ?> <?= number_format($sale['subtotal'], 2) ?></span>
        </div>
        <?php if ($sale['discount_amount'] > 0): ?>
            <div style="display: flex; justify-content: space-between;">
                <span>Discount (<?= $sale['discount_percentage'] ?>%):</span>
                <span>- <?= CURRENCY_SYMBOL ?> <?= number_format($sale['discount_amount'], 2) ?></span>
            </div>
        <?php endif; ?>
        <div style="display: flex; justify-content: space-between;">
            <span>GST Tax (<?= $sale['tax_percentage'] ?>%):</span>
            <span>+ <?= CURRENCY_SYMBOL ?> <?= number_format($sale['tax_amount'], 2) ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1rem; border-top: 1px solid #000; margin-top: 4px; padding-top: 4px;">
            <span>NET PAYABLE:</span>
            <span><?= CURRENCY_SYMBOL ?> <?= number_format($sale['grand_total'], 2) ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 3px;">
            <span>Payment Mode:</span>
            <span><?= strtoupper(e($sale['payment_mode'])) ?> (PAID)</span>
        </div>
    </div>

    <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>

    <div class="text-center" style="font-size: 0.75rem;">
        <div>*** THANK YOU! VISIT AGAIN ***</div>
        <div>No exchange after 48 hrs</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnA4 = document.getElementById('btnA4View');
    const btnThermal = document.getElementById('btnThermalView');
    const a4Sec = document.getElementById('a4InvoiceSection');
    const thermalSec = document.getElementById('thermalReceiptSection');

    btnA4.addEventListener('click', function() {
        btnA4.classList.add('active');
        btnThermal.classList.remove('active');
        a4Sec.style.display = 'block';
        thermalSec.style.display = 'none';
    });

    btnThermal.addEventListener('click', function() {
        btnThermal.classList.add('active');
        btnA4.classList.remove('active');
        a4Sec.style.display = 'none';
        thermalSec.style.display = 'block';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
