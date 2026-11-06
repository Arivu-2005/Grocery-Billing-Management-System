<?php
/**
 * Vercel Serverless Entry Point Router
 * GROCERY BILLING SYSTEM - HISTORY MAKER TECH
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = '/' . trim($path, '/');

// Base directory pointing to project root
$rootDir = dirname(__DIR__);

// Route mapping
if ($path === '/' || $path === '') {
    require $rootDir . '/index.php';
    exit;
}

// Direct file check
$targetFile = $rootDir . $path;
if (file_exists($targetFile) && is_file($targetFile)) {
    // If it's a PHP file, execute it
    if (substr($targetFile, -4) === '.php') {
        require $targetFile;
        exit;
    }
    // If it's a static file (css, js, image), serve with appropriate MIME type
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon'
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($targetFile);
    exit;
}

// Check if directory with index.php
if (is_dir($targetFile) && file_exists($targetFile . '/index.php')) {
    require $targetFile . '/index.php';
    exit;
}

// Check with .php extension
if (file_exists($targetFile . '.php')) {
    require $targetFile . '.php';
    exit;
}

// 404 Not Found
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested route <code>" . htmlspecialchars($path) . "</code> was not found.</p><a href='/'>Go to Home</a></body></html>";
exit;
