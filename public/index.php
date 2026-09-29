<?php

declare(strict_types=1);

// If accessing static files directly from public directory (assets, fonts, images, etc.):
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$staticFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $requestPath);

if ($requestPath !== '/' && $requestPath !== '/index.php' && is_file($staticFile)) {
    $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
    $mimes = [
        'js'    => 'application/javascript; charset=utf-8',
        'mjs'   => 'application/javascript; charset=utf-8',
        'css'   => 'text/css; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'map'   => 'application/json; charset=utf-8',
    ];
    $contentType = $mimes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . (string)filesize($staticFile));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($staticFile);
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Config;
use App\Support\Env;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\Router;
use App\Support\Session;

// Load environment variables
Env::load(dirname(__DIR__) . '/.env');
Config::setPath(dirname(__DIR__) . '/config');
Logger::setLogDir(dirname(__DIR__) . '/storage/logs');

// Start secure session
Session::start();

// Handle uncaught exceptions and errors gracefully
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    Logger::error("PHP Notice/Warning: {$errstr}", ['file' => $errfile, 'line' => $errline]);
    return true;
});

set_exception_handler(function (Throwable $e) {
    $requestId = Request::currentRequestId();
    Logger::error("Uncaught exception [{$requestId}]: " . $e->getMessage(), [
        'request_id' => $requestId,
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    $isDebug = (bool)Config::get('app.debug', false);

    $response = Response::error(
        'SERVER_ERROR',
        $isDebug ? $e->getMessage() : 'خطایی در پردازش درخواست رخ داده است. لطفاً با شناسه پیگیری به پشتیبانی مراجعه کنید.',
        $isDebug ? ['file' => $e->getFile(), 'line' => $e->getLine()] : [],
        500
    );
    $response->send();
    exit;
});

$request = Request::capture();
$path = $request->getPath();

// Maintenance Mode Check (Phase 16)
$isMaintenance = filter_var(Env::get('APP_MAINTENANCE', Config::get('app.maintenance', false)), FILTER_VALIDATE_BOOLEAN);
if ($isMaintenance) {
    $healthEndpoints = ['/api/v1/system/health', '/api/v1/health', '/api/v1/health/ready'];
    if (!in_array($path, $healthEndpoints, true)) {
        $response = Response::error(
            'MAINTENANCE_MODE',
            'سامانه در حال بروزرسانی و ارتقای دوره‌ای است. لطفاً دقایقی دیگر مراجعه فرمایید.',
            ['retry_after' => 300],
            503
        );
        $response->header('Retry-After', '300');
        $response->send();
        exit;
    }
}

// Route Installer Web Wizard (Phase 17)
if ($path === '/install' || $path === '/install/') {
    $installer = new \App\Controllers\InstallController();
    $response = $installer->index($request);
    $response->send();
    exit;
}

// Route API requests
if (str_starts_with($path, '/api/')) {
    $router = new Router();
    require_once dirname(__DIR__) . '/routes/api.php';
    $response = $router->dispatch($request);
    $response->send();
    exit;
}

// Serve SPA index.html for web requests
$spaFile = __DIR__ . '/index.html';
if (file_exists($spaFile)) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none';");
    header('X-Request-Id: ' . Request::currentRequestId());
    readfile($spaFile);
    exit;
}

// Fallback message if frontend is not yet built
echo '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>WooCommerce Management & CRM</title>
    <style>body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #0f172a; color: #f8fafc; direction: rtl; }</style>
</head>
<body>
    <div style="text-align: center;">
        <h2>سامانه مدیریت و CRM ووکامرس</h2>
        <p>بخش فرانت‌اند در حال بارگذاری است. لطفاً دستور <code>npm run build</code> را اجرا کنید.</p>
        <p><a href="/api/v1/system/health" style="color: #6366f1;">بررسی وضعیت سلامت API</a></p>
    </div>
</body>
</html>';
