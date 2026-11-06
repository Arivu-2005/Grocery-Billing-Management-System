<?php
/**
 * Admin Profile Management
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

$pageTitle = "Admin Profile";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$userSession = getLoggedInUser();

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Security validation failed.');
        header("Location: " . BASE_URL . "/admin/profile.php");
        exit;
    }

    // Update Profile Information
    if ($action === 'update_profile') {
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $email    = sanitizeInput($_POST['email'] ?? '');

        if (empty($fullName) || empty($email)) {
            setFlashMessage('danger', 'Full Name and Email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('danger', 'Please enter a valid email address.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE `admin` SET `full_name` = ?, `email` = ? WHERE `id` = ?");
                $stmt->execute([$fullName, $email, $userSession['id']]);
                
                $_SESSION['admin_name']  = $fullName;
                $_SESSION['admin_email'] = $email;

                setFlashMessage('success', 'Profile information updated successfully!');
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error updating profile: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/profile.php");
        exit;
    }

    // Change Password
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            setFlashMessage('danger', 'All password fields are required.');
        } elseif ($newPass !== $confirmPass) {
            setFlashMessage('danger', 'New password and confirmation password do not match.');
        } elseif (strlen($newPass) < 6) {
            setFlashMessage('danger', 'New password must be at least 6 characters.');
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT `password` FROM `admin` WHERE `id` = ?");
                $stmt->execute([$userSession['id']]);
                $dbPass = $stmt->fetchColumn();

                if ($dbPass && password_verify($currentPass, $dbPass)) {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare("UPDATE `admin` SET `password` = ? WHERE `id` = ?");
                    $upd->execute([$newHash, $userSession['id']]);
                    setFlashMessage('success', 'Password changed successfully!');
                } else {
                    setFlashMessage('danger', 'Current password is incorrect.');
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error changing password: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "/admin/profile.php");
        exit;
    }
}

// Fetch fresh admin details
$stmt = $pdo->prepare("SELECT * FROM `admin` WHERE `id` = ?");
$stmt->execute([$userSession['id']]);
$admin = $stmt->fetch();
if (!$admin) {
    $admin = $userSession;
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="page-header-title">Administrator Profile</h2>
        <p class="text-muted small mb-0">Manage account credentials, security settings, and store manager details</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Profile Card -->
    <div class="col-lg-4">
        <div class="table-card p-4 text-center">
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow" style="width: 80px; height: 80px; font-size: 2rem;">
                <?= strtoupper(substr($admin['full_name'] ?? 'Admin', 0, 1)) ?>
            </div>
            <h4 class="fw-bold mb-1"><?= e($admin['full_name'] ?? 'Store Manager') ?></h4>
            <span class="badge bg-success bg-opacity-10 text-success border border-success mb-3 px-3 py-1 text-uppercase">
                <?= e($admin['role'] ?? 'admin') ?>
            </span>
            <div class="text-muted small">
                <div><i class="bi bi-person me-1"></i> <strong>Username:</strong> <?= e($admin['username'] ?? 'admin') ?></div>
                <div class="mt-1"><i class="bi bi-envelope me-1"></i> <?= e($admin['email'] ?? 'admin@freshmart.com') ?></div>
                <div class="mt-1"><i class="bi bi-calendar-check me-1"></i> Member since <?= isset($admin['created_at']) ? date('d M Y', strtotime($admin['created_at'])) : '2026' ?></div>
            </div>
        </div>

        <div class="card border-0 bg-light p-3 mt-3 small text-muted">
            <div class="fw-bold text-dark mb-1"><i class="bi bi-building me-1 text-success"></i> <?= e(DEVELOPED_BY) ?></div>
            <div><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></div>
            <div class="mt-1">Standard PHP &bull; MySQL &bull; Bootstrap POS Architecture</div>
        </div>
    </div>

    <!-- Right Column: Edit Forms -->
    <div class="col-lg-8">
        <!-- Edit Profile Form -->
        <div class="table-card p-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-lines-fill text-success me-2"></i> Update Personal Info</h5>
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" class="form-control" value="<?= e($admin['username'] ?? 'admin') ?>" disabled readonly>
                        <small class="text-muted">Username cannot be changed</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" value="<?= e($admin['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?= e($admin['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-brand px-4">
                        <i class="bi bi-check-circle me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="table-card p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shield-lock text-success me-2"></i> Change Password</h5>
            <form method="POST" action="">
                <?= renderCsrfInput() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="current_password" required placeholder="Enter current password">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_password" required placeholder="At least 6 characters">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" required placeholder="Re-enter new password">
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-outline-dark px-4">
                        <i class="bi bi-key me-1"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
