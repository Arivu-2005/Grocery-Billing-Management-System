<?php
/**
 * Admin Login Page
 * Grocery Billing Management System
 */

require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit;
}

$error = '';
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $username  = sanitizeInput($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    // Verify CSRF
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } elseif (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM `admin` WHERE `username` = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Secure Session
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $admin['id'];
                $_SESSION['admin_username']  = $admin['username'];
                $_SESSION['admin_name']      = $admin['full_name'];
                $_SESSION['admin_email']     = $admin['email'];
                $_SESSION['admin_role']      = $admin['role'];

                setFlashMessage('success', "Welcome back, " . $admin['full_name'] . "!");
                header("Location: " . BASE_URL . "/admin/dashboard.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - FreshMart Grocery POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-body">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100 py-5">
        <div class="col-md-7 col-lg-5 col-xl-4">
            
            <!-- Brand Badge -->
            <div class="text-center mb-4">
                <div class="brand-avatar mx-auto mb-3 shadow">
                    <i class="bi bi-cart4 text-white fs-1"></i>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?= e(STORE_NAME) ?></h3>
                <p class="text-muted small"><?= e(STORE_TAGLINE) ?></p>
            </div>

            <!-- Login Card -->
            <div class="card login-card shadow-lg border-0 rounded-4">
                <div class="card-body p-4 p-sm-5">
                    <h5 class="fw-bold text-dark mb-1">Welcome Back</h5>
                    <p class="text-muted small mb-4">Sign in with your administrator credentials</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center py-2 px-3 small" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-2 fs-6"></i>
                            <div><?= e($error) ?></div>
                            <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" autocomplete="off" id="loginForm">
                        <?= renderCsrfInput() ?>

                        <div class="mb-3">
                            <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" placeholder="e.g. admin" required autofocus value="<?= isset($_POST['username']) ? e($_POST['username']) : 'admin' ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="bi bi-key"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="password" name="password" placeholder="••••••••" required value="admin123">
                                <button class="btn btn-outline-light border border-start-0 text-muted" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 py-2 fw-semibold rounded-3 shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to POS
                        </button>
                    </form>

                    <!-- Hint box for quick testing -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <div class="badge bg-light text-secondary border px-3 py-2 small fw-normal">
                            <i class="bi bi-info-circle me-1 text-success"></i> Default: <strong>admin</strong> / <strong>admin123</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer copyright -->
            <div class="text-center mt-4">
                <small class="text-muted">© <?= date('Y') ?> <?= e(STORE_NAME) ?>. All rights reserved.</small>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    }
</script>
</body>
</html>
