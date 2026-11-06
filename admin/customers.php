<?php
/**
 * Customer Management
 * Grocery Billing Management System
 */

$pageTitle = "Customer Management";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$currentUser = getLoggedInUser();

// Handle Form Submissions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security validation failed.');
        header("Location: " . BASE_URL . "/admin/customers.php");
        exit;
    }

    // 1. ADD CUSTOMER
    if ($action === 'add') {
        $name    = sanitizeInput($_POST['customer_name'] ?? '');
        $mobile  = sanitizeInput($_POST['mobile_number'] ?? '');
        $email   = sanitizeInput($_POST['email'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');

        if (empty($name) || empty($mobile)) {
            setFlashMessage('danger', 'Customer name and mobile number are required.');
        } else {
            try {
                // Check duplicate mobile
                $checkStmt = $pdo->prepare("SELECT id FROM `customers` WHERE `mobile_number` = ?");
                $checkStmt->execute([$mobile]);
                if ($checkStmt->fetch()) {
                    setFlashMessage('danger', "A customer with mobile '{$mobile}' already exists.");
                } else {
                    $ins = $pdo->prepare("INSERT INTO `customers` (`customer_name`, `mobile_number`, `email`, `address`) VALUES (?, ?, ?, ?)");
                    $ins->execute([$name, $mobile, $email, $address]);
                    setFlashMessage('success', "Customer '{$name}' created successfully!");
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error adding customer: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/customers.php");
        exit;
    }

    // 2. EDIT CUSTOMER
    if ($action === 'edit') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $name       = sanitizeInput($_POST['customer_name'] ?? '');
        $mobile     = sanitizeInput($_POST['mobile_number'] ?? '');
        $email      = sanitizeInput($_POST['email'] ?? '');
        $address    = sanitizeInput($_POST['address'] ?? '');

        if ($customerId <= 0 || empty($name) || empty($mobile)) {
            setFlashMessage('danger', 'Invalid customer data.');
        } else {
            try {
                // Check duplicate mobile excluding this customer
                $checkStmt = $pdo->prepare("SELECT id FROM `customers` WHERE `mobile_number` = ? AND `id` != ?");
                $checkStmt->execute([$mobile, $customerId]);
                if ($checkStmt->fetch()) {
                    setFlashMessage('danger', "Mobile number '{$mobile}' is already in use by another customer.");
                } else {
                    $upd = $pdo->prepare("UPDATE `customers` SET `customer_name` = ?, `mobile_number` = ?, `email` = ?, `address` = ? WHERE `id` = ?");
                    $upd->execute([$name, $mobile, $email, $address, $customerId]);
                    setFlashMessage('success', "Customer '{$name}' updated successfully!");
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error updating customer: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/customers.php");
        exit;
    }

    // 3. DELETE CUSTOMER
    if ($action === 'delete') {
        $customerId = (int)($_POST['customer_id'] ?? 0);

        if ($customerId <= 1) {
            setFlashMessage('danger', 'The default Walk-in Customer record cannot be deleted.');
        } else {
            try {
                $del = $pdo->prepare("DELETE FROM `customers` WHERE `id` = ?");
                $del->execute([$customerId]);
                setFlashMessage('success', 'Customer deleted successfully!');
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error deleting customer: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/customers.php");
        exit;
    }
}

// Check if viewing purchase history for a specific customer
$viewHistoryId = (int)($_GET['view_history'] ?? 0);
$customerInvoices = [];
$selectedCustomer = null;

if ($viewHistoryId > 0) {
    $cStmt = $pdo->prepare("SELECT * FROM `customers` WHERE id = ?");
    $cStmt->execute([$viewHistoryId]);
    $selectedCustomer = $cStmt->fetch();

    if ($selectedCustomer) {
        $invStmt = $pdo->prepare("SELECT * FROM `sales` WHERE `customer_id` = ? ORDER BY `id` DESC");
        $invStmt->execute([$viewHistoryId]);
        $customerInvoices = $invStmt->fetchAll();
    }
}

// Fetch all customers with lifetime order count and total spent
$search = sanitizeInput($_GET['search'] ?? '');
try {
    $sql = "
        SELECT 
            c.*, 
            (SELECT COUNT(*) FROM `sales` s WHERE s.customer_id = c.id) AS total_orders, 
            (SELECT COALESCE(SUM(s.grand_total), 0.00) FROM `sales` s WHERE s.customer_id = c.id) AS total_spent 
        FROM `customers` c 
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (c.customer_name LIKE ? OR c.mobile_number LIKE ? OR c.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY c.id DESC";
    $custStmt = $pdo->prepare($sql);
    $custStmt->execute($params);
    $customers = $custStmt->fetchAll();

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . e($e->getMessage()) . '</div>';
    $customers = [];
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="page-header-title">Customer Management</h2>
        <p class="text-muted small mb-0">Maintain customer directory, contact records, and analyze purchase loyalty</p>
    </div>
    <div>
        <button type="button" class="btn btn-brand shadow-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="bi bi-person-plus-fill me-1"></i> Add New Customer
        </button>
    </div>
</div>

<!-- If Purchase History is active, show history panel -->
<?php if ($selectedCustomer): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-3">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i> Purchase History: <?= e($selectedCustomer['customer_name']) ?></h5>
                <small>Mobile: <?= e($selectedCustomer['mobile_number']) ?> &bull; Email: <?= e($selectedCustomer['email'] ?: 'N/A') ?></small>
            </div>
            <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-light btn-sm">
                <i class="bi bi-x-lg me-1"></i> Close History
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date & Time</th>
                            <th>Payment Mode</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customerInvoices)): ?>
                            <?php foreach ($customerInvoices as $inv): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/admin/invoice.php?id=<?= $inv['id'] ?>" class="fw-bold text-success text-decoration-none">
                                            <?= e($inv['invoice_no']) ?>
                                        </a>
                                    </td>
                                    <td><span class="small text-muted"><?= date('d M Y, h:i A', strtotime($inv['created_at'])) ?></span></td>
                                    <td><span class="badge bg-light text-secondary border"><?= e($inv['payment_mode']) ?></span></td>
                                    <td><span class="badge bg-success bg-opacity-25 text-success">Paid</span></td>
                                    <td><span class="fw-bold text-dark"><?= formatCurrency($inv['grand_total']) ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/admin/invoice.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-printer"></i> View Invoice
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No sales invoices found for this customer.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Search and Filter Bar -->
<div class="table-card p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by customer name, mobile, or email..." value="<?= e($search) ?>" data-table-search="customersTable">
            </div>
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-link text-muted">Clear</a>
            <?php endif; ?>
        </div>
        <div class="col text-md-end text-muted small">
            Total Customers: <strong><?= count($customers) ?></strong>
        </div>
    </form>
</div>

<!-- Customers Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-custom" id="customersTable">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Mobile Number</th>
                    <th>Email Address</th>
                    <th>Address</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?= e($c['customer_name']) ?></div>
                                <div class="text-muted smaller">Registered: <?= date('d M Y', strtotime($c['created_at'])) ?></div>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark"><i class="bi bi-telephone text-success me-1"></i> <?= e($c['mobile_number']) ?></span>
                            </td>
                            <td>
                                <span class="small text-muted"><?= !empty($c['email']) ? e($c['email']) : '<span class="text-black-50 italic">None</span>' ?></span>
                            </td>
                            <td>
                                <span class="small text-muted text-truncate d-inline-block" style="max-width: 180px;">
                                    <?= !empty($c['address']) ? e($c['address']) : '<span class="text-black-50 italic">None</span>' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="bi bi-bag-check me-1"></i> <?= $c['total_orders'] ?> Orders
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-success fs-6"><?= formatCurrency($c['total_spent']) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- History Button -->
                                    <a href="<?= BASE_URL ?>/admin/customers.php?view_history=<?= $c['id'] ?>" class="btn btn-outline-info" title="View Purchase History">
                                        <i class="bi bi-clock-history"></i>
                                    </a>

                                    <!-- Edit Button -->
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-edit-customer"
                                            data-id="<?= $c['id'] ?>"
                                            data-name="<?= e($c['customer_name']) ?>"
                                            data-mobile="<?= e($c['mobile_number']) ?>"
                                            data-email="<?= e($c['email'] ?? '') ?>"
                                            data-address="<?= e($c['address'] ?? '') ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editCustomerModal"
                                            title="Edit Customer">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Delete Button Form -->
                                    <?php if ($c['id'] != 1): // Do not delete default walk-in ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete customer \'<?= addslashes(e($c['customer_name'])) ?>\'?');">
                                            <?= renderCsrfInput() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete Customer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-person-x fs-1 d-block text-secondary mb-2"></i>
                            No customer records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Customer -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus-fill me-1"></i> Add New Customer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="customer_name" placeholder="e.g. Ramesh Kumar" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="mobile_number" placeholder="10-digit mobile number" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address (Optional)</label>
                        <input type="email" class="form-control" name="email" placeholder="ramesh@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Address / Delivery Location</label>
                        <textarea class="form-control" name="address" rows="3" placeholder="Apartment / street address..."></textarea>
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

<!-- Modal: Edit Customer -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="customer_id" id="edit_customer_id" value="">
                
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil-square me-1"></i> Edit Customer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_customer_name" name="customer_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_mobile_number" name="mobile_number" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand px-4">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.btn-edit-customer');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_customer_id').value = this.getAttribute('data-id');
            document.getElementById('edit_customer_name').value = this.getAttribute('data-name');
            document.getElementById('edit_mobile_number').value = this.getAttribute('data-mobile');
            document.getElementById('edit_email').value = this.getAttribute('data-email');
            document.getElementById('edit_address').value = this.getAttribute('data-address');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
