<?php
/**
 * Database & Application Configuration
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

require_once __DIR__ . '/config.php';

// Database Credentials (Supports Environment Variables for Cloud Deployment & Standard XAMPP Defaults)
defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'grocery_billing');
defined('DB_PORT') or define('DB_PORT', getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

// Calculate Dynamic Base URL
if (!defined('BASE_URL')) {
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // If hosted on Vercel or running at root domain
    if (isset($_SERVER['VERCEL']) || strpos($host, 'vercel.app') !== false) {
        define('BASE_URL', '');
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        $projectRoot = str_replace('\\', '/', dirname(__DIR__));
        
        $relativeDir = '';
        if ($docRoot && strpos($projectRoot, $docRoot) === 0) {
            $relativeDir = substr($projectRoot, strlen($docRoot));
        } else {
            // Fallback using SCRIPT_NAME across all subdirectories
            $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
            $subdirs = ['/admin', '/categories', '/products', '/customers', '/billing', '/inventory', '/reports', '/database', '/api'];
            $relativeDir = rtrim($script, '/');
            foreach ($subdirs as $sd) {
                if (substr($script, -strlen($sd)) === $sd) {
                    $relativeDir = substr($script, 0, -strlen($sd));
                    break;
                }
            }
        }
        
        $baseUrl = rtrim($protocol . $host . $relativeDir, '/');
        define('BASE_URL', $baseUrl);
    }
}

/**
 * Returns a singleton or new PDO instance.
 * @return PDO
 */
function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        // Try primary DB_NAME, with fallback to grocery_billing_db if primary fails
        $dbCandidates = [DB_NAME];
        if (DB_NAME === 'grocery_billing') {
            $dbCandidates[] = 'grocery_billing_db';
        } else {
            $dbCandidates[] = 'grocery_billing';
        }

        $lastError = null;
        foreach ($dbCandidates as $db) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . $db . ";charset=" . DB_CHARSET;
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                break;
            } catch (PDOException $e) {
                $lastError = $e;
            }
        }

        if ($pdo === null) {
            // Check if error is unknown database (error code 1049)
            if ($lastError && $lastError->getCode() == 1049) {
                die(renderDbMissingError($lastError->getMessage()));
            } else {
                die(renderDbConnectionError($lastError ? $lastError->getMessage() : 'Database connection error'));
            }
        }
    }

    return $pdo;
}

/**
 * Render user-friendly HTML error if database is not created yet
 */
function renderDbMissingError($errorMsg) {
    $seedUrl = BASE_URL . '/database/seed.php';
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup Required - FreshMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #F8FAFC; color: #1E293B; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .setup-card { max-width: 580px; width: 100%; border-radius: 1rem; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.07); }
        .btn-green { background-color: #15803D; color: #fff; font-weight: 600; padding: 0.75rem 1.5rem; border-radius: 0.5rem; text-decoration: none; display: inline-block; }
        .btn-green:hover { background-color: #14532D; color: #fff; }
    </style>
</head>
<body>
    <div class="card setup-card p-4 p-md-5 bg-white text-center">
        <div class="mb-3 text-success">
            <svg width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.492-3.287.813V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.321-2.107-.693-3.287-.813-1.094-.11-2.278-.037-3.213.493V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
            </svg>
        </div>
        <h3 class="fw-bold mb-2">Database Setup Required</h3>
        <p class="text-muted mb-4">The MySQL database was not found on your MySQL server.</p>
        <div class="alert alert-warning text-start small mb-4">
            <strong>System Error:</strong> {$errorMsg}<br>
            Make sure MySQL is running in your XAMPP Control Panel.
        </div>
        <div class="d-grid gap-2">
            <a href="{$seedUrl}" class="btn-green">Run 1-Click Database Installer & Seed</a>
        </div>
        <p class="text-muted small mt-3">Or import <code>database/grocery_billing.sql</code> directly in phpMyAdmin.</p>
    </div>
</body>
</html>
HTML;
}

/**
 * Render user-friendly HTML error if DB connection fails
 */
function renderDbConnectionError($errorMsg) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Error - FreshMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #F8FAFC; color: #1E293B; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .setup-card { max-width: 580px; width: 100%; border-radius: 1rem; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.07); }
    </style>
</head>
<body>
    <div class="card setup-card p-4 p-md-5 bg-white text-center">
        <h3 class="fw-bold text-danger mb-3">Database Connection Failed</h3>
        <p class="text-muted">Could not connect to MySQL server at <code>localhost</code>.</p>
        <div class="alert alert-danger text-start small mb-3">
            <strong>Error Details:</strong> {$errorMsg}
        </div>
        <ul class="text-start text-muted small mb-4">
            <li>Ensure <strong>Apache</strong> and <strong>MySQL</strong> are started in the XAMPP Control Panel.</li>
            <li>Default MySQL credentials are user: <code>root</code> with empty password.</li>
            <li>If your MySQL uses a password or custom port, update <code>config/database.php</code> or set environment variables.</li>
        </ul>
        <button onclick="location.reload()" class="btn btn-primary px-4">Retry Connection</button>
    </div>
</body>
</html>
HTML;
}
