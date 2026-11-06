<?php
/**
 * 1-Click Database Setup & Seeder Script
 * Grocery Billing Management System
 */

require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';
$installed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_installer'])) {
    try {
        // Connect to MySQL server without selecting DB first
        $pdoServer = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        );

        // Read database.sql file
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("database.sql file was not found in " . __DIR__);
        }

        $sqlContent = file_get_contents($sqlFile);

        // Execute SQL script
        $pdoServer->exec($sqlContent);

        // Ensure default admin password hash is fresh and valid
        $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdoServer->prepare("UPDATE `grocery_billing_db`.`admin` SET `password` = ? WHERE `username` = 'admin'");
        $stmt->execute([$adminHash]);

        $installed = true;
        $message = "Database <strong>grocery_billing_db</strong> and all 7 tables with sample grocery data were installed successfully!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Seeder - FreshMart POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #F8FAFC;
            color: #1E293B;
            font-family: 'Inter', -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-card {
            max-width: 650px;
            width: 100%;
            border-radius: 1rem;
            border: 1px solid #E2E8F0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            background: #FFFFFF;
        }
        .brand-header {
            background: linear-gradient(135deg, #15803D 0%, #14532D 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem 1rem 0 0;
            text-align: center;
        }
        .btn-brand {
            background-color: #15803D;
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-brand:hover {
            background-color: #14532D;
            color: white;
        }
        .table-pill {
            display: inline-block;
            background: #EBF7EE;
            color: #15803D;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.25rem 0.65rem;
            border-radius: 1rem;
            margin: 0.2rem;
        }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="brand-header">
        <i class="bi bi-basket3-fill fs-1 text-light"></i>
        <h3 class="fw-bold mt-2 mb-1">FreshMart POS Database Installer</h3>
        <p class="mb-0 text-white-50 small">Automatic setup & seed tool for Grocery Billing Management System</p>
    </div>

    <div class="p-4 p-md-5">
        <?php if ($installed): ?>
            <div class="alert alert-success d-flex align-items-center mb-4">
                <i class="bi bi-check-circle-fill fs-3 me-3 text-success"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Installation Complete!</h5>
                    <p class="mb-0 small"><?= $message ?></p>
                </div>
            </div>

            <div class="card bg-light border-0 p-3 mb-4">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-lock-fill text-success me-1"></i> Default Login Credentials:</h6>
                <div class="row g-2 small">
                    <div class="col-6"><strong>Username:</strong> <code>admin</code></div>
                    <div class="col-6"><strong>Password:</strong> <code>admin123</code></div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-brand py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Admin Login
                </a>
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary py-2">
                    Go to Dashboard
                </a>
            </div>

        <?php else: ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-danger"></i>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Installation Failed</h6>
                        <p class="mb-0 small"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <p class="text-muted mb-3">
                This installer will create the database <strong><code>grocery_billing_db</code></strong>, set up all 7 relational tables with constraints and indexes, and seed sample grocery store products, categories, stock, and default admin credentials.
            </p>

            <div class="mb-4">
                <h6 class="fw-bold mb-2 small text-uppercase text-secondary">Tables to be created:</h6>
                <span class="table-pill"><i class="bi bi-table"></i> admin</span>
                <span class="table-pill"><i class="bi bi-table"></i> categories</span>
                <span class="table-pill"><i class="bi bi-table"></i> products</span>
                <span class="table-pill"><i class="bi bi-table"></i> customers</span>
                <span class="table-pill"><i class="bi bi-table"></i> sales</span>
                <span class="table-pill"><i class="bi bi-table"></i> sale_items</span>
                <span class="table-pill"><i class="bi bi-table"></i> stock_history</span>
            </div>

            <div class="card bg-light border-0 p-3 mb-4">
                <h6 class="fw-bold text-dark mb-2 small"><i class="bi bi-hdd-network-fill me-1 text-success"></i> MySQL Server Connection Settings:</h6>
                <div class="row g-2 small text-muted">
                    <div class="col-6">Host: <code><?= DB_HOST ?></code></div>
                    <div class="col-6">Port: <code><?= DB_PORT ?></code></div>
                    <div class="col-6">User: <code><?= DB_USER ?></code></div>
                    <div class="col-6">Target DB: <code><?= DB_NAME ?></code></div>
                </div>
            </div>

            <form method="POST" action="">
                <button type="submit" name="run_installer" value="1" class="btn btn-brand w-100 py-3 fs-6">
                    <i class="bi bi-database-check me-2"></i> Create Database & Run Seed
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/login.php" class="text-decoration-none small text-muted">
                    <i class="bi bi-arrow-left"></i> Already installed? Go to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
