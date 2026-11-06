<?php
/**
 * Category Management
 * Grocery Billing Management System
 */

$pageTitle = "Categories";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$currentUser = getLoggedInUser();

// Handle Form Submissions (Create, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security token mismatch. Please try again.');
        header("Location: " . BASE_URL . "/admin/categories.php");
        exit;
    }

    // 1. ADD CATEGORY
    if ($action === 'add') {
        $categoryName = sanitizeInput($_POST['category_name'] ?? '');
        $description  = sanitizeInput($_POST['description'] ?? '');

        if (empty($categoryName)) {
            setFlashMessage('danger', 'Category name is required.');
        } else {
            try {
                // Check duplicate
                $checkStmt = $pdo->prepare("SELECT id FROM `categories` WHERE `category_name` = ?");
                $checkStmt->execute([$categoryName]);
                if ($checkStmt->fetch()) {
                    setFlashMessage('danger', 'A category with this name already exists.');
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO `categories` (`category_name`, `description`) VALUES (?, ?)");
                    $insertStmt->execute([$categoryName, $description]);
                    setFlashMessage('success', "Category '{$categoryName}' added successfully!");
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error adding category: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/categories.php");
        exit;
    }

    // 2. EDIT CATEGORY
    if ($action === 'edit') {
        $categoryId   = (int)($_POST['category_id'] ?? 0);
        $categoryName = sanitizeInput($_POST['category_name'] ?? '');
        $description  = sanitizeInput($_POST['description'] ?? '');

        if ($categoryId <= 0 || empty($categoryName)) {
            setFlashMessage('danger', 'Invalid category data provided.');
        } else {
            try {
                // Check duplicate name excluding current category
                $checkStmt = $pdo->prepare("SELECT id FROM `categories` WHERE `category_name` = ? AND `id` != ?");
                $checkStmt->execute([$categoryName, $categoryId]);
                if ($checkStmt->fetch()) {
                    setFlashMessage('danger', 'Another category with this name already exists.');
                } else {
                    $updateStmt = $pdo->prepare("UPDATE `categories` SET `category_name` = ?, `description` = ? WHERE `id` = ?");
                    $updateStmt->execute([$categoryName, $description, $categoryId]);
                    setFlashMessage('success', "Category updated successfully!");
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error updating category: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/categories.php");
        exit;
    }

    // 3. DELETE CATEGORY
    if ($action === 'delete') {
        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($categoryId <= 0) {
            setFlashMessage('danger', 'Invalid category selected for deletion.');
        } else {
            try {
                // Check if category is assigned to products
                $prodCheck = $pdo->prepare("SELECT COUNT(*) FROM `products` WHERE `category_id` = ?");
                $prodCheck->execute([$categoryId]);
                $assignedCount = (int)$prodCheck->fetchColumn();

                if ($assignedCount > 0) {
                    setFlashMessage('danger', "Cannot delete this category because {$assignedCount} product(s) are currently assigned to it. Please reassign or delete the products first.");
                } else {
                    $delStmt = $pdo->prepare("DELETE FROM `categories` WHERE `id` = ?");
                    $delStmt->execute([$categoryId]);
                    setFlashMessage('success', 'Category deleted successfully!');
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error deleting category: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/categories.php");
        exit;
    }
}

// Fetch all categories with product counts
$searchQuery = sanitizeInput($_GET['search'] ?? '');
try {
    if (!empty($searchQuery)) {
        $catStmt = $pdo->prepare("
            SELECT c.*, (SELECT COUNT(*) FROM `products` p WHERE p.category_id = c.id) AS product_count 
            FROM `categories` c 
            WHERE c.category_name LIKE ? OR c.description LIKE ?
            ORDER BY c.id DESC
        ");
        $catStmt->execute(["%$searchQuery%", "%$searchQuery%"]);
    } else {
        $catStmt = $pdo->query("
            SELECT c.*, (SELECT COUNT(*) FROM `products` p WHERE p.category_id = c.id) AS product_count 
            FROM `categories` c 
            ORDER BY c.id DESC
        ");
    }
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error retrieving categories: ' . e($e->getMessage()) . '</div>';
    $categories = [];
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="page-header-title">Category Management</h2>
        <p class="text-muted small mb-0">Organize grocery products into clean, searchable departments</p>
    </div>
    <div>
        <button type="button" class="btn btn-brand shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Category
        </button>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="table-card p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search category name or description..." value="<?= e($searchQuery) ?>" data-table-search="categoriesTable">
            </div>
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            <?php if (!empty($searchQuery)): ?>
                <a href="<?= BASE_URL ?>/admin/categories.php" class="btn btn-link text-muted">Clear</a>
            <?php endif; ?>
        </div>
        <div class="col text-md-end text-muted small">
            Total Categories: <strong><?= count($categories) ?></strong>
        </div>
    </form>
</div>

<!-- Categories Data Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-custom" id="categoriesTable">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th style="width: 140px;">Products</th>
                    <th style="width: 180px;">Created Date</th>
                    <th style="width: 140px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="text-muted fw-semibold">#<?= $cat['id'] ?></td>
                            <td>
                                <span class="fw-bold text-dark fs-6"><?= e($cat['category_name']) ?></span>
                            </td>
                            <td>
                                <span class="text-muted small"><?= !empty($cat['description']) ? e($cat['description']) : '<span class="text-black-50 italic">No description</span>' ?></span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/products.php?category_id=<?= $cat['id'] ?>" class="badge bg-light text-success border px-2 py-1 text-decoration-none">
                                    <i class="bi bi-box-seam me-1"></i> <?= $cat['product_count'] ?> Products
                                </a>
                            </td>
                            <td>
                                <span class="small text-muted"><?= date('d M Y, h:i A', strtotime($cat['created_at'])) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Edit Button -->
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-edit-category" 
                                            data-id="<?= $cat['id'] ?>"
                                            data-name="<?= e($cat['category_name']) ?>"
                                            data-desc="<?= e($cat['description'] ?? '') ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editCategoryModal"
                                            title="Edit Category">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Delete Button Form -->
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete category \'<?= addslashes(e($cat['category_name'])) ?>\'?');">
                                        <?= renderCsrfInput() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Delete Category" <?= ($cat['product_count'] > 0) ? 'data-bs-toggle="tooltip" title="Cannot delete: has assigned products"' : '' ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-folder2-open fs-1 d-block text-secondary mb-2"></i>
                            No categories found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="addCategoryModalLabel">
                        <i class="bi bi-plus-circle me-1"></i> Add Product Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="category_name" class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="category_name" placeholder="e.g. Dairy & Frozen" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief note about the products in this category..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand px-4">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Category -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" id="edit_category_id" value="">
                
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="editCategoryModalLabel">
                        <i class="bi bi-pencil-square me-1"></i> Edit Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label small fw-semibold">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand px-4">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Populate Edit Category Modal
    const editButtons = document.querySelectorAll('.btn-edit-category');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_category_id').value = this.getAttribute('data-id');
            document.getElementById('edit_category_name').value = this.getAttribute('data-name');
            document.getElementById('edit_description').value = this.getAttribute('data-desc');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
