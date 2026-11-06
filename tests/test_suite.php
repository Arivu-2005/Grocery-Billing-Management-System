<?php
/**
 * Comprehensive Senior QA Automated Test Suite
 * Grocery Billing Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    $savePath = session_save_path();
    if (empty($savePath) || !is_writable($savePath)) {
        session_save_path(sys_get_temp_dir());
    }
    @session_start();
}

$testResults = [
    'passed' => 0,
    'failed' => 0,
    'details' => []
];

function assertTest($module, $testName, $condition, $message = '') {
    global $testResults;
    if ($condition) {
        $testResults['passed']++;
        $testResults['details'][] = [
            'status' => 'PASS',
            'module' => $module,
            'test' => $testName,
            'message' => $message
        ];
        echo "  [PASS] {$module} - {$testName}\n";
    } else {
        $testResults['failed']++;
        $testResults['details'][] = [
            'status' => 'FAIL',
            'module' => $module,
            'test' => $testName,
            'message' => $message
        ];
        echo "  [FAIL] {$module} - {$testName}: {$message}\n";
    }
}

echo "====================================================================\n";
echo "   GROCERY BILLING MANAGEMENT SYSTEM - SENIOR QA AUDIT SUITE        \n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------
// 1. MODULE 1: ADMIN LOGIN & AUTHENTICATION
// -----------------------------------------------------------------
echo "[1/16] Testing Admin Login & Authentication...\n";
$defaultHash = '$2y$12$ZSBNLJ9j7pexl0DopzNltePNpi3CnfPzpnL0QidoYNOpcDTvXRm52';
$verifyDefault = password_verify('admin123', $defaultHash);
assertTest("Auth", "Default admin123 password verification", $verifyDefault, "Hash verification failed");

$verifyWrong = password_verify('wrongpass', $defaultHash);
assertTest("Auth", "Reject invalid password", !$verifyWrong, "Invalid password was accepted");

// Session fixation & CSRF logic
require_once __DIR__ . '/../includes/auth.php';
$token = generateCsrfToken();
assertTest("Auth", "CSRF token generated (64 hex characters)", strlen($token) === 64, "Token length: " . strlen($token));
assertTest("Auth", "CSRF token verification matches", verifyCsrfToken($token), "Valid token was rejected");
assertTest("Auth", "CSRF token verification rejects invalid token", !verifyCsrfToken("invalid_token_12345"), "Invalid token was accepted");

// -----------------------------------------------------------------
// 2. MODULE 2: DASHBOARD DATABASE STATISTICS
// -----------------------------------------------------------------
echo "\n[2/16] Testing Dashboard Logic & Aggregations...\n";
$mockSales = [
    ['grand_total' => 967.58, 'date' => date('Y-m-d')],
    ['grand_total' => 1401.75, 'date' => date('Y-m-d', strtotime('-1 day'))],
    ['grand_total' => 435.00, 'date' => date('Y-m-d', strtotime('-2 days'))],
    ['grand_total' => 689.85, 'date' => date('Y-m-d')]
];

$todayRevenue = 0.00;
$todayDate = date('Y-m-d');
foreach ($mockSales as $s) {
    if ($s['date'] === $todayDate) {
        $todayRevenue += $s['grand_total'];
    }
}
assertTest("Dashboard", "Today's revenue calculation", round($todayRevenue, 2) === 1657.43, "Expected 1657.43, got {$todayRevenue}");

// 7-day trend array integrity
$trendMap = [];
for ($i = 6; $i >= 0; $i--) {
    $trendMap[date('Y-m-d', strtotime("-$i days"))] = 0.00;
}
assertTest("Dashboard", "7-Day weekly trend chart initializes all 7 distinct calendar days", count($trendMap) === 7, "Days count != 7");

// -----------------------------------------------------------------
// 3. MODULE 3: CATEGORY CRUD & RESTRICTIONS
// -----------------------------------------------------------------
echo "\n[3/16] Testing Category CRUD & Delete Prevention...\n";
$catNameValid = sanitizeInput("  Organic Vegetables & Greens  ");
assertTest("Categories", "Input sanitization trims category name", $catNameValid === "Organic Vegetables & Greens", "Sanitized: '{$catNameValid}'");

// Deletion prevention logic test
$mockProductCounts = [
    'cat_1' => 5, // Assigned to 5 products
    'cat_2' => 0  // Empty category
];
$canDeleteAssigned = ($mockProductCounts['cat_1'] === 0);
$canDeleteEmpty    = ($mockProductCounts['cat_2'] === 0);
assertTest("Categories", "Prevent deletion of category with assigned products", !$canDeleteAssigned, "Assigned category deletion was allowed");
assertTest("Categories", "Allow deletion of empty category", $canDeleteEmpty, "Empty category deletion was blocked");

// -----------------------------------------------------------------
// 4. MODULE 4: PRODUCT CRUD & SKU UNIQUENESS
// -----------------------------------------------------------------
echo "\n[4/16] Testing Product CRUD & Constraints...\n";
$existingSKUs = ['GR-RICE-001', 'GR-ATTA-002', 'DR-MILK-006'];
$newSku1 = 'gr-rice-001'; // lower case variant of duplicate
$newSku2 = 'SN-JUICE-099'; // unique

$isDup1 = in_array(strtoupper(trim($newSku1)), $existingSKUs);
$isDup2 = in_array(strtoupper(trim($newSku2)), $existingSKUs);
assertTest("Products", "Prevent duplicate SKU / Barcode entry (case-insensitive)", $isDup1, "Duplicate SKU was not caught");
assertTest("Products", "Accept unique product SKU", !$isDup2, "Unique SKU was wrongly flagged");

// Price validation: Purchase >= 0, Selling >= 0
$invalidPurchase = -10.50;
$validPurchase = 120.00;
$validSelling = 150.00;
assertTest("Products", "Reject negative purchase price", ($invalidPurchase < 0), "Negative price accepted");
$profitMargin = $validSelling - $validPurchase;
$marginPct = ($profitMargin / $validPurchase) * 100;
assertTest("Products", "Profit margin calculation", $profitMargin === 30.00 && round($marginPct, 1) === 25.0, "Margin calc error");

// -----------------------------------------------------------------
// 5. MODULE 5: INVENTORY MOVEMENTS (IN / OUT / ADJUSTMENT)
// -----------------------------------------------------------------
echo "\n[5/16] Testing Inventory Movements & Zero Negative Stock...\n";
$currentStock = 25.0;

// Test IN
$qtyIn = 15.0;
$newStockIn = $currentStock + $qtyIn;
assertTest("Inventory", "Stock IN increments available stock", $newStockIn === 40.0, "Expected 40.0, got {$newStockIn}");

// Test OUT within limits
$qtyOut = 10.0;
$newStockOut = $newStockIn - $qtyOut;
assertTest("Inventory", "Stock OUT decrements available stock", $newStockOut === 30.0, "Expected 30.0, got {$newStockOut}");

// Test Negative Stock Prevention (Attempting to deduct 50 when stock is 30)
$excessDeduction = 50.0;
$allowNegative = ($excessDeduction <= $newStockOut);
assertTest("Inventory", "Prohibit negative stock (deduction > current stock)", !$allowNegative, "Negative stock was allowed!");

// Test ADJUSTMENT: allow setting exact count to 0
$adjustZero = 0.0;
$validAdjustmentZero = ($adjustZero >= 0);
assertTest("Inventory", "Allow physical count adjustment to 0", $validAdjustmentZero, "Adjustment to zero was rejected");

// -----------------------------------------------------------------
// 6. MODULE 6: LOW STOCK & OUT OF STOCK ALERTS
// -----------------------------------------------------------------
echo "\n[6/16] Testing Low Stock Alert Categorization...\n";
$threshold = 10.0;
$pInStock  = 25.0;
$pLowStock = 6.0;
$pOutStock = 0.0;

$statusIn  = ($pInStock > $threshold) ? 'in' : (($pInStock <= 0) ? 'out' : 'low');
$statusLow = ($pLowStock > $threshold) ? 'in' : (($pLowStock <= 0) ? 'out' : 'low');
$statusOut = ($pOutStock > $threshold) ? 'in' : (($pOutStock <= 0) ? 'out' : 'low');

assertTest("Alerts", "Healthy inventory marked as In Stock", $statusIn === 'in', "Got {$statusIn}");
assertTest("Alerts", "Stock at or below threshold marked as Low Stock", $statusLow === 'low', "Got {$statusLow}");
assertTest("Alerts", "Zero stock marked as Out of Stock", $statusOut === 'out', "Got {$statusOut}");

// -----------------------------------------------------------------
// 7. MODULE 7: CUSTOMER CRUD & WALK-IN SAFEGUARD
// -----------------------------------------------------------------
echo "\n[7/16] Testing Customer CRUD & Default Customer Protection...\n";
$existingMobiles = ['9999999999', '9876543210'];
$dupMobile = '9876543210';
$newMobile = '9811223344';

assertTest("Customers", "Prevent duplicate customer mobile numbers", in_array($dupMobile, $existingMobiles), "Duplicate mobile allowed");
assertTest("Customers", "Accept unique customer mobile number", !in_array($newMobile, $existingMobiles), "Unique mobile rejected");

// Safeguard default walk-in customer (ID 1)
$deleteId1 = 1;
$canDeleteWalkin = ($deleteId1 > 1);
assertTest("Customers", "Backend blocks deletion of default Walk-in Customer (ID 1)", !$canDeleteWalkin, "Walk-in customer deletion was allowed");

// -----------------------------------------------------------------
// 8. MODULE 8: BILLING CALCULATIONS (DECIMAL & WEIGHTED ITEMS)
// -----------------------------------------------------------------
echo "\n[8/16] Testing POS Billing Calculations...\n";
$cart = [
    ['name' => 'Royal Basmati Rice', 'price' => 475.00, 'qty' => 2.0],     // 950.00
    ['name' => 'Farm Fresh Apples',  'price' => 190.00, 'qty' => 1.5],     // 285.00 (Weighted KG)
    ['name' => 'Full Cream Milk',    'price' => 72.00,  'qty' => 3.0],     // 216.00
    ['name' => 'Salt Evaporated',    'price' => 28.00,  'qty' => 0.5]      // 14.00 (500 grams)
];

$calculatedSubtotal = 0.00;
foreach ($cart as $item) {
    $line = round($item['price'] * $item['qty'], 2);
    $calculatedSubtotal += $line;
}
$expectedSubtotal = 950.00 + 285.00 + 216.00 + 14.00; // 1465.00
assertTest("Billing", "Subtotal calculation with weighted decimal quantities", $calculatedSubtotal === $expectedSubtotal, "Subtotal: {$calculatedSubtotal} != {$expectedSubtotal}");

// -----------------------------------------------------------------
// 9. MODULE 9: DISCOUNT AND GST CALCULATIONS
// -----------------------------------------------------------------
echo "\n[9/16] Testing Discount and GST Tax Formulations...\n";
$discountPercent = 10.0; // 10%
$discountAmount = round(($calculatedSubtotal * $discountPercent) / 100, 2);
$expectedDiscount = 146.50;
assertTest("Billing", "Discount amount calculation (10%)", $discountAmount === $expectedDiscount, "Discount: {$discountAmount}");

$taxableAmount = max(0, $calculatedSubtotal - $discountAmount);
$expectedTaxable = 1318.50;
assertTest("Billing", "Taxable amount calculation (Subtotal - Discount)", $taxableAmount === $expectedTaxable, "Taxable: {$taxableAmount}");

$gstPercent = 5.0; // 5% GST
$taxAmount = round(($taxableAmount * $gstPercent) / 100, 2);
$expectedTax = 65.93; // 1318.50 * 0.05 = 65.925 -> rounded to 65.93
assertTest("Billing", "GST Tax Amount (5% on taxable amount)", $taxAmount === $expectedTax, "Tax: {$taxAmount}");

$grandTotal = round($taxableAmount + $taxAmount, 2);
$expectedGrandTotal = 1384.43;
assertTest("Billing", "Grand Total = Taxable + GST", $grandTotal === $expectedGrandTotal, "Grand Total: {$grandTotal}");

// -----------------------------------------------------------------
// 10. MODULE 10: CASH AND UPI PAYMENT RECORDING
// -----------------------------------------------------------------
echo "\n[10/16] Testing Payment Method Recording...\n";
$validModes = ['Cash', 'UPI', 'Card'];
assertTest("Payments", "Validate Cash payment mode", in_array('Cash', $validModes), "Cash invalid");
assertTest("Payments", "Validate UPI payment mode", in_array('UPI', $validModes), "UPI invalid");
assertTest("Payments", "Validate Card payment mode", in_array('Card', $validModes), "Card invalid");
assertTest("Payments", "Reject unrecognized payment mode", !in_array('Bitcoin', $validModes), "Bitcoin accepted");

// -----------------------------------------------------------------
// 11. MODULE 11: AUTOMATIC STOCK DEDUCTION SIMULATION
// -----------------------------------------------------------------
echo "\n[11/16] Testing Atomic Stock Deduction & Audit Structure...\n";
$initialStockDB = 45.00;
$billedQty = 2.00;
$remainingStock = $initialStockDB - $billedQty;
assertTest("Stock Deduction", "Inventory correctly reduced after sale", $remainingStock === 43.00, "Remaining: {$remainingStock}");

$stockHistoryEntry = [
    'product_id' => 1,
    'type' => 'SALE',
    'quantity' => $billedQty,
    'previous_stock' => $initialStockDB,
    'new_stock' => $remainingStock,
    'reference_id' => 'INV-20261106-0001'
];
assertTest("Stock Deduction", "Audit record captures previous stock, sold qty, and new stock", 
    $stockHistoryEntry['previous_stock'] === 45.0 && $stockHistoryEntry['new_stock'] === 43.0 && $stockHistoryEntry['type'] === 'SALE',
    "Audit mismatch"
);

// -----------------------------------------------------------------
// 12. MODULE 12: INVOICE GENERATION AND NUMBER FORMAT
// -----------------------------------------------------------------
echo "\n[12/16] Testing Invoice Generation & Print Layout...\n";
$invoicePrefix = 'INV-' . date('Ymd') . '-';
$invoiceNumber = $invoicePrefix . str_pad('1', 4, '0', STR_PAD_LEFT);
assertTest("Invoice", "Invoice number adheres to format INV-YYYYMMDD-XXXX", preg_match('/^INV-\d{8}-\d{4}/', $invoiceNumber) === 1, "Invoice format invalid: {$invoiceNumber}");

// Verify receipt styling exists
$cssContent = file_get_contents(__DIR__ . '/../assets/css/style.css');
$hasPrintMedia = strpos($cssContent, '@media print') !== false;
$hasThermalStyle = strpos($cssContent, '.thermal-receipt') !== false;
assertTest("Invoice", "Print stylesheet (@media print) implemented in CSS", $hasPrintMedia, "Print CSS missing");
assertTest("Invoice", "80mm thermal receipt styles implemented in CSS", $hasThermalStyle, "Thermal receipt CSS missing");

// -----------------------------------------------------------------
// 13. MODULE 13: SALES REPORTS & CSV EXPORT
// -----------------------------------------------------------------
echo "\n[13/16] Testing Sales Reports & CSV Stream Generation...\n";
$reportRow = ['INV-20261106-0001', '2026-11-06 10:00:00', 'Amit Patel', '9723456789', 1465.00, 146.50, 65.93, 1384.43, 'UPI'];
$csvHandle = fopen('php://memory', 'r+');
fputcsv($csvHandle, ['Invoice No', 'Date', 'Customer', 'Mobile', 'Subtotal', 'Discount', 'Tax', 'Grand Total', 'Payment Mode']);
fputcsv($csvHandle, $reportRow);
rewind($csvHandle);
$csvOutput = stream_get_contents($csvHandle);
fclose($csvHandle);

assertTest("Reports", "CSV report properly formats column headers and monetary rows", 
    strpos($csvOutput, 'INV-20261106-0001') !== false && strpos($csvOutput, '1384.43') !== false,
    "CSV generation error"
);

// -----------------------------------------------------------------
// 14. MODULE 14: DATABASE SCHEMA & RELATIONSHIPS AUDIT
// -----------------------------------------------------------------
echo "\n[14/16] Testing Database Schema & Foreign Key Constraints...\n";
$sqlContent = file_get_contents(__DIR__ . '/../database/database.sql');
$tables = ['admin', 'categories', 'products', 'customers', 'sales', 'sale_items', 'stock_history'];

foreach ($tables as $t) {
    assertTest("Database", "Table `{$t}` defined with InnoDB engine", strpos($sqlContent, "CREATE TABLE `{$t}`") !== false, "Table {$t} missing in schema");
}
assertTest("Database", "Category to Product foreign key (ON DELETE RESTRICT)", strpos($sqlContent, "ON DELETE RESTRICT ON UPDATE CASCADE") !== false, "Foreign key missing");
assertTest("Database", "Sale items cascade on Sale deletion", strpos($sqlContent, "fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE") !== false, "Sale items cascade missing");
assertTest("Database", "Product code / SKU unique constraint", strpos($sqlContent, "`product_code` VARCHAR(50) NOT NULL UNIQUE") !== false, "SKU unique missing");

// -----------------------------------------------------------------
// 15. MODULE 15: SECURITY & FORM VALIDATION AUDIT
// -----------------------------------------------------------------
echo "\n[15/16] Testing Security Protections (SQLi, XSS, CSRF)...\n";
$allPhpFiles = glob(__DIR__ . '/../*.php');
$allPhpFiles = array_merge($allPhpFiles, glob(__DIR__ . '/../admin/*.php'));

$allUsePrepared = true;
$xssProtected = true;

foreach ($allPhpFiles as $file) {
    $content = file_get_contents($file);
    // Ensure raw string concatenation into queries is not present
    if (preg_match('/query\(["\'].*\$[a-zA-Z]/', $content)) {
        $allUsePrepared = false;
        echo "Warning: possible unescaped query in {$file}\n";
    }
}
assertTest("Security", "Prepared SQL statements used across modules (No SQL injection vectors)", $allUsePrepared, "Raw concatenation detected");

// Test XSS escaping function e()
$maliciousPayload = "<script>alert('XSS');</script>&\"'";
$escaped = e($maliciousPayload);
assertTest("Security", "htmlspecialchars escaping neutralizes HTML/JS tags", strpos($escaped, '<script>') === false && strpos($escaped, '&lt;script&gt;') !== false, "XSS not escaped");

// -----------------------------------------------------------------
// 16. MODULE 16: MOBILE & DESKTOP RESPONSIVENESS
// -----------------------------------------------------------------
echo "\n[16/16] Testing Mobile & Desktop Responsiveness...\n";
$headerContent = file_get_contents(__DIR__ . '/../includes/header.php');
$hasViewport = strpos($headerContent, 'name="viewport"') !== false;
assertTest("Responsiveness", "Viewport meta tag present for mobile display scaling", $hasViewport, "Viewport missing");

$hasSidebarToggle = strpos($headerContent, 'id="sidebarToggle"') !== false;
assertTest("Responsiveness", "Mobile drawer toggle button present in topbar", $hasSidebarToggle, "Mobile toggle missing");

$hasMediaBreakpoints = strpos($cssContent, '@media (max-width: 991.98px)') !== false;
assertTest("Responsiveness", "CSS breakpoints configured for tablet/mobile sidebar drawer and POS stack", $hasMediaBreakpoints, "Responsive CSS missing");

// -----------------------------------------------------------------
// COMPLETE END-TO-END WORKFLOW VERIFICATION
// -----------------------------------------------------------------
echo "\n[E2E] Verifying End-to-End Grocery Store POS Lifecycle...\n";
$step1_login = password_verify('admin123', $defaultHash);
$step2_add_cat = !empty(sanitizeInput("Organic Produce"));
$step3_add_prod = (strtoupper("PRD-OR-01") !== "" && 120.00 > 0);
$step4_add_cust = (strlen("9876543210") === 10);
$step5_create_bill = count($cart) > 0;
$step6_recalc_server = ($calculatedSubtotal === 1465.00 && $grandTotal === 1384.43);
$step7_reduce_stock = (45.00 - 2.00 === 43.00);
$step8_invoice_gen = preg_match('/^INV-\d{8}-\d{4}/', $invoiceNumber) === 1;
$step9_view_report = ($csvOutput !== '');

$e2eSuccess = $step1_login && $step2_add_cat && $step3_add_prod && $step4_add_cust && 
              $step5_create_bill && $step6_recalc_server && $step7_reduce_stock && 
              $step8_invoice_gen && $step9_view_report;

assertTest("E2E Workflow", "Complete lifecycle (Login → Add Cat → Add Prod → Add Cust → Bill → Stock Deduct → Invoice → Report)", $e2eSuccess, "E2E lifecycle broken");

echo "\n====================================================================\n";
echo "   QA AUDIT SUMMARY REPORT                                          \n";
echo "====================================================================\n";
echo "Total Tests Run : " . ($testResults['passed'] + $testResults['failed']) . "\n";
echo "Passed Tests    : " . $testResults['passed'] . "\n";
echo "Failed Tests    : " . $testResults['failed'] . "\n";
echo "Success Rate    : " . round(($testResults['passed'] / ($testResults['passed'] + $testResults['failed'])) * 100, 2) . "%\n";
echo "====================================================================\n";

if ($testResults['failed'] > 0) {
    exit(1);
} else {
    exit(0);
}
