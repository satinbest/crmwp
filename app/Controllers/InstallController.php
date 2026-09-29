<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\MigrationRunner;
use App\Database\Seeders\DatabaseSeeder;
use App\Support\Request;
use App\Support\Response;
use PDO;
use Throwable;

class InstallController
{
    private string $lockFile;
    private string $lockFileAlt;

    public function __construct()
    {
        $this->lockFile = dirname(__DIR__, 2) . '/storage/installed.lock';
        $this->lockFileAlt = dirname(__DIR__, 2) . '/storage/locks/installed.lock';
    }

    public function isInstalled(): bool
    {
        return file_exists($this->lockFile) || file_exists($this->lockFileAlt);
    }

    /**
     * Serve the installer UI
     */
    public function index(Request $request): Response
    {
        if ($this->isInstalled()) {
            return $this->renderAlreadyInstalled();
        }

        return $this->renderInstallerWizard();
    }

    /**
     * API: Check system environment and prerequisites
     */
    public function check(Request $request): Response
    {
        if ($this->isInstalled()) {
            return Response::error('ALREADY_INSTALLED', 'سیستم قبلاً نصب شده است.', [], 403);
        }

        $rootDir = dirname(__DIR__, 2);
        $reqExtensions = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'curl', 'json', 'session'];
        $extStatus = [];
        $allExtOk = true;

        foreach ($reqExtensions as $ext) {
            $loaded = extension_loaded($ext);
            $extStatus[$ext] = $loaded;
            if (!$loaded) {
                $allExtOk = false;
            }
        }

        $dirs = [
            'storage/cache'   => $rootDir . '/storage/cache',
            'storage/logs'    => $rootDir . '/storage/logs',
            'storage/uploads' => $rootDir . '/storage/uploads',
            'storage/locks'   => $rootDir . '/storage/locks',
        ];

        $dirStatus = [];
        $allDirOk = true;
        foreach ($dirs as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $writable = is_writable($path);
            $dirStatus[$label] = $writable;
            if (!$writable) {
                $allDirOk = false;
            }
        }

        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.2.0', '>=');

        $canInstall = $phpOk && $allExtOk && $allDirOk;

        return Response::success([
            'php_version' => $phpVersion,
            'php_ok' => $phpOk,
            'extensions' => $extStatus,
            'extensions_ok' => $allExtOk,
            'directories' => $dirStatus,
            'directories_ok' => $allDirOk,
            'can_install' => $canInstall,
            'version' => defined('CRM_APP_VERSION') ? CRM_APP_VERSION : '1.0.0',
        ]);
    }

    /**
     * API: Test Database Connection
     */
    public function testDatabase(Request $request): Response
    {
        if ($this->isInstalled()) {
            return Response::error('ALREADY_INSTALLED', 'سیستم قبلاً نصب شده است.', [], 403);
        }

        $host = trim((string)$request->input('db_host', '127.0.0.1'));
        $port = (int)$request->input('db_port', 3306);
        $name = trim((string)$request->input('db_name', ''));
        $user = trim((string)$request->input('db_user', ''));
        $pass = (string)$request->input('db_pass', '');

        if (empty($name) || empty($user)) {
            return Response::error('VALIDATION_ERROR', 'نام پایگاه‌داده و نام‌کاربری الزامی هستند.', [], 422);
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            return Response::success([
                'connected' => true,
                'db_version' => $version,
                'message' => 'اتصال به پایگاه‌داده با موفقیت برقرار شد.',
            ]);
        } catch (Throwable $e) {
            return Response::error('DB_CONNECTION_FAILED', 'خطا در اتصال به پایگاه‌داده: ' . $e->getMessage(), [], 400);
        }
    }

    /**
     * API: Run installation, migrations and create initial administrator
     */
    public function setup(Request $request): Response
    {
        if ($this->isInstalled()) {
            return Response::error('ALREADY_INSTALLED', 'سیستم قبلاً نصب شده است. نصب مجدد امکان‌پذیر نیست.', [], 403);
        }

        $dbHost = trim((string)$request->input('db_host', '127.0.0.1'));
        $dbPort = (int)$request->input('db_port', 3306);
        $dbName = trim((string)$request->input('db_name', ''));
        $dbUser = trim((string)$request->input('db_user', ''));
        $dbPass = (string)$request->input('db_pass', '');

        $adminName = trim((string)$request->input('admin_name', ''));
        $adminUser = trim((string)$request->input('admin_username', ''));
        $adminEmail = trim((string)$request->input('admin_email', ''));
        $adminPass = (string)$request->input('admin_password', '');

        if (empty($dbName) || empty($dbUser)) {
            return Response::error('VALIDATION_ERROR', 'اطلاعات پایگاه‌داده ناقص است.', [], 422);
        }

        if (empty($adminUser) || empty($adminEmail) || strlen($adminPass) < 8) {
            return Response::error('VALIDATION_ERROR', 'مشخصات مدیر ارشد ناقص است یا کلمه عبور کمتر از ۸ کاراکتر است.', [], 422);
        }

        try {
            // 1. Establish PDO Connection
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // 2. Run Database Migrations
            $runner = new MigrationRunner($pdo);
            $applied = $runner->migrate();

            // 3. Seed Core System Roles & Permissions (Production Clean Seed)
            $seeder = new DatabaseSeeder($pdo);
            $seeder->run(false);

            // 4. Create/Update Administrator Account
            $passHash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $nameParts = explode(' ', $adminName, 2);
            $firstName = $nameParts[0] ?? $adminUser;
            $lastName = $nameParts[1] ?? '';

            $userStmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), first_name = VALUES(first_name), last_name = VALUES(last_name), is_active = 1
            ");
            $userStmt->execute([$adminUser, $adminEmail, $passHash, $firstName, $lastName]);
            $adminId = (int)$pdo->lastInsertId();
            if ($adminId === 0) {
                $idStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $idStmt->execute([$adminUser]);
                $adminId = (int)$idStmt->fetchColumn();
            }

            // Assign Admin Role (ID 1)
            $roleStmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, 1)");
            $roleStmt->execute([$adminId]);

            // 5. Generate Secure Encryption Key, Application Secret and Cron Secret
            $encryptionKey = bin2hex(random_bytes(32));
            $appSecret = bin2hex(random_bytes(32));
            $cronSecret = bin2hex(random_bytes(24));

            // 6. Write Production .env File
            $rootDir = dirname(__DIR__, 2);
            $envPath = $rootDir . '/.env';

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $scheme = $isHttps ? 'https://' : 'http://';
            $appUrl = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost');

            $envContent = "# WooCommerce Management & CRM Platform - Production Environment\n" .
                "APP_NAME=\"WooCommerce Management & CRM\"\n" .
                "APP_ENV=production\n" .
                "APP_DEBUG=false\n" .
                "APP_URL={$appUrl}\n" .
                "APP_SECRET={$appSecret}\n" .
                "ENCRYPTION_KEY={$encryptionKey}\n" .
                "CRON_SECRET={$cronSecret}\n\n" .
                "# Database Configuration\n" .
                "DB_HOST={$dbHost}\n" .
                "DB_PORT={$dbPort}\n" .
                "DB_DATABASE={$dbName}\n" .
                "DB_USERNAME={$dbUser}\n" .
                "DB_PASSWORD=\"{$dbPass}\"\n" .
                "DB_CHARSET=utf8mb4\n" .
                "DB_COLLATION=utf8mb4_unicode_ci\n\n" .
                "# Session Security\n" .
                "SESSION_LIFETIME=7200\n" .
                "SESSION_SECURE=" . ($isHttps ? "true\n" : "false\n") .
                "SESSION_SAME_SITE=Lax\n\n" .
                "# Application Settings\n" .
                "TIMEZONE=Asia/Tehran\n" .
                "LOCALE=fa\n" .
                "APP_VERSION=1.0.0\n";

            file_put_contents($envPath, $envContent);

            // 7. Write Installation Locks
            $lockContent = json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'admin_username' => $adminUser,
                'admin_email' => $adminEmail,
                'version' => defined('CRM_APP_VERSION') ? CRM_APP_VERSION : '1.0.0',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            file_put_contents($this->lockFile, $lockContent);
            if (!is_dir(dirname($this->lockFileAlt))) {
                @mkdir(dirname($this->lockFileAlt), 0755, true);
            }
            file_put_contents($this->lockFileAlt, $lockContent);

            return Response::success([
                'installed' => true,
                'applied_migrations_count' => count($applied),
                'admin_username' => $adminUser,
                'redirect' => '/login',
                'message' => 'سامانه با موفقیت نصب و پیکربندی شد.',
            ]);
        } catch (Throwable $e) {
            return Response::error('INSTALLATION_FAILED', 'خطا در نصب سامانه: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function getVazirmatnFontFaces(): string
    {
        $assetsDir = dirname(__DIR__, 2) . '/public/assets';
        $weightMap = [
            100 => 'Thin',
            200 => 'ExtraLight',
            300 => 'Light',
            400 => 'Regular',
            500 => 'Medium',
            600 => 'SemiBold',
            700 => 'Bold',
            800 => 'ExtraBold',
            900 => 'Black',
        ];

        $css = '';
        foreach ($weightMap as $weight => $name) {
            $files = glob($assetsDir . '/Vazirmatn-' . $name . '-*.woff2');
            if (empty($files)) {
                $files = glob($assetsDir . '/Vazirmatn-' . $name . '.woff2');
            }
            if (!empty($files)) {
                $filename = basename($files[0]);
                $css .= "
        @font-face {
            font-family: 'Vazirmatn';
            src: url('/assets/{$filename}') format('woff2');
            font-weight: {$weight};
            font-style: normal;
            font-display: swap;
        }";
            }
        }

        return $css;
    }

    private function renderAlreadyInstalled(): Response
    {
        $fontFaces = $this->getVazirmatnFontFaces();
        $html = '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه قبلاً نصب شده است</title>
    <style>
        ' . $fontFaces . '
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, input, button, select, textarea, label, h1, h2, h3, p, a, .btn {
            font-family: \'Vazirmatn\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;
        }
        body { background-color: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; direction: rtl; text-align: right; line-height: 1.6; }
        .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 1rem; max-width: 480px; width: 100%; padding: 2.5rem; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        .icon { width: 56px; height: 56px; margin: 0 auto 1.5rem; background: rgba(16, 185, 129, 0.15); color: #10b981; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; line-height: 1.5; }
        p { color: #94a3b8; font-size: 0.875rem; line-height: 1.6; margin-bottom: 2rem; }
        .btn { display: inline-block; background-color: #6366f1; color: white; padding: 0.75rem 2rem; border-radius: 0.75rem; text-decoration: none; font-weight: 600; font-size: 0.875rem; transition: background-color 0.2s; }
        .btn:hover { background-color: #4f46e5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>سیستم قبلاً با موفقیت نصب شده است</h1>
        <p>به منظور حفظ امنیت پایگاه‌داده و حساب‌های کاربری، امکان اجرای مجدد فرایند نصب غیرفعال است. جهت ورود به پنل کاربری روی دکمه زیر کلیک نمایید.</p>
        <a href="/login" class="btn">ورود به سامانه</a>
    </div>
</body>
</html>';

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function renderInstallerWizard(): Response
    {
        $version = defined('CRM_APP_VERSION') ? CRM_APP_VERSION : '1.0.0';
        $fontFaces = $this->getVazirmatnFontFaces();
        $html = '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب سامانه مدیریت و CRM ووکامرس</title>
    <style>
        ' . $fontFaces . '
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, input, button, select, textarea, label, h1, h2, h3, h4, p, span, div, a, .btn, .step-label {
            font-family: \'Vazirmatn\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;
        }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; direction: rtl; text-align: right; line-height: 1.6; }
        .container { max-width: 680px; width: 100%; background: #131b2e; border: 1px solid #1e293b; border-radius: 1.25rem; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 1px solid #1e293b; padding-bottom: 1.5rem; }
        .header h1 { font-size: 1.35rem; font-weight: 800; color: #f8fafc; margin-bottom: 0.5rem; line-height: 1.5; }
        .header p { font-size: 0.825rem; color: #94a3b8; line-height: 1.5; }
        .step-indicators { display: flex; justify-content: space-between; margin-bottom: 2rem; position: relative; }
        .step { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; z-index: 1; }
        .step-circle { width: 32px; height: 32px; border-radius: 50%; background: #1e293b; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; margin-bottom: 0.5rem; border: 2px solid transparent; }
        .step.active .step-circle { background: #4f46e5; color: white; border-color: #818cf8; }
        .step.done .step-circle { background: #10b981; color: white; }
        .step-label { font-size: 0.75rem; color: #94a3b8; text-align: center; font-weight: 500; }
        .step.active .step-label { color: #f8fafc; font-weight: 700; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.8rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.4rem; line-height: 1.4; }
        input[type="text"], input[type="password"], input[type="email"], input[type="number"] { width: 100%; padding: 0.65rem 0.85rem; border-radius: 0.65rem; background: #0a0f1d; border: 1px solid #334155; color: #f8fafc; font-size: 0.85rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s; line-height: 1.5; }
        input.ltr-input { direction: ltr; text-align: left; }
        input:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2); }
        .grid-2 { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media(min-width: 640px) { .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.65rem 1.25rem; border-radius: 0.65rem; font-size: 0.85rem; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; user-select: none; line-height: 1.5; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-primary:hover { background: #4338ca; }
        .btn-secondary { background: #1e293b; color: #cbd5e1; border: 1px solid #334155; }
        .btn-secondary:hover { background: #334155; color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .check-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #0a0f1d; border: 1px solid #1e293b; border-radius: 0.65rem; margin-bottom: 0.5rem; font-size: 0.825rem; line-height: 1.5; }
        .badge { padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .actions { display: flex; justify-content: space-between; margin-top: 2rem; border-top: 1px solid #1e293b; padding-top: 1.25rem; }
        .alert { padding: 0.75rem 1rem; border-radius: 0.65rem; font-size: 0.825rem; margin-bottom: 1rem; line-height: 1.6; }
        .alert-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; }
        .footer { text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: #64748b; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>نصب و راه‌اندازی سامانه مدیریت و CRM ووکامرس</h1>
            <p>نسخه ' . htmlspecialchars($version, ENT_QUOTES) . ' — پیکربندی خودکار پایگاه‌داده و امنیت سرور</p>
        </div>

        <div class="step-indicators">
            <div class="step active" id="si-1"><div class="step-circle">۱</div><div class="step-label">بررسی سرور</div></div>
            <div class="step" id="si-2"><div class="step-circle">۲</div><div class="step-label">پایگاه‌داده</div></div>
            <div class="step" id="si-3"><div class="step-circle">۳</div><div class="step-label">مدیر ارشد</div></div>
            <div class="step" id="si-4"><div class="step-circle">۴</div><div class="step-label">پایان نصب</div></div>
        </div>

        <div id="alertBox" class="alert" style="display: none;"></div>

        <!-- Step 1: Environment Checks -->
        <div id="step-1">
            <div id="checkList">در حال بررسی محیط سرور...</div>
            <div class="actions">
                <div></div>
                <button type="button" id="btnStep1Next" class="btn btn-primary" onclick="goToStep(2)" disabled>مرحله بعد: پایگاه‌داده</button>
            </div>
        </div>

        <!-- Step 2: Database Configuration -->
        <div id="step-2" style="display: none;">
            <div class="grid-2">
                <div class="form-group">
                    <label>میزبان پایگاه‌داده (Database Host)</label>
                    <input type="text" id="db_host" class="ltr-input" value="127.0.0.1">
                </div>
                <div class="form-group">
                    <label>پورت (Port)</label>
                    <input type="number" id="db_port" class="ltr-input" value="3306">
                </div>
            </div>
            <div class="form-group">
                <label>نام پایگاه‌داده (Database Name)</label>
                <input type="text" id="db_name" class="ltr-input" value="crmwp" placeholder="مثال: crmwp">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>نام‌کاربری دیتابیس (Username)</label>
                    <input type="text" id="db_user" class="ltr-input" value="root">
                </div>
                <div class="form-group">
                    <label>کلمه عبور دیتابیس (Password)</label>
                    <input type="password" id="db_pass" class="ltr-input" placeholder="رمز دیتابیس">
                </div>
            </div>
            <div style="margin-bottom: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="testDatabaseConnection()">بررسی اتصال دیتابیس</button>
                <span id="dbTestStatus" style="font-size: 0.75rem; margin-right: 0.5rem; color: #94a3b8;"></span>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="goToStep(1)">بازگشت</button>
                <button type="button" id="btnStep2Next" class="btn btn-primary" onclick="goToStep(3)">مرحله بعد: مدیر ارشد</button>
            </div>
        </div>

        <!-- Step 3: Administrator Setup -->
        <div id="step-3" style="display: none;">
            <div class="form-group">
                <label>نام و نام‌خانوادگی مدیر ارشد</label>
                <input type="text" id="admin_name" value="مدیر ارشد سامانه">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>نام کاربری (Username)</label>
                    <input type="text" id="admin_username" class="ltr-input" value="admin">
                </div>
                <div class="form-group">
                    <label>ایمیل (Email)</label>
                    <input type="email" id="admin_email" class="ltr-input" value="admin@example.com">
                </div>
            </div>
            <div class="form-group">
                <label>کلمه عبور مدیر ارشد (حداقل ۸ کاراکتر)</label>
                <input type="password" id="admin_password" class="ltr-input" placeholder="کلمه عبور امن">
            </div>
            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="goToStep(2)">بازگشت</button>
                <button type="button" id="btnInstall" class="btn btn-primary" onclick="submitInstallation()">شروع نصب و پیکربندی</button>
            </div>
        </div>

        <!-- Step 4: Finished -->
        <div id="step-4" style="display: none; text-align: center; padding: 2rem 0;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 1.5rem;">✓</div>
            <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem;">سامانه با موفقیت نصب شد!</h2>
            <p style="color: #94a3b8; font-size: 0.85rem; line-height: 1.6; margin-bottom: 2rem;">مایگریشن‌ها با موفقیت اجرا، نقش‌های امنیتی مقداردهی، کلیدهای رمزنگاری تولید و قفل نصب فعال گردید.</p>
            <a href="/login" class="btn btn-primary" style="text-decoration: none;">ورود به سامانه مدیریت</a>
        </div>
    </div>
    <div class="footer">WooCommerce Management & CRM Platform &copy; ' . date('Y') . '</div>

    <script>
        let currentStep = 1;

        async function initCheck() {
            try {
                const res = await fetch("/api/v1/install/check");
                const json = await res.json();
                if (!json.success) {
                    showAlert(json.error.message || "خطا در بررسی پیش‌نیازها", "danger");
                    return;
                }
                const data = json.data;
                let html = "";
                html += renderCheck("نسخه PHP (8.2+)", data.php_version, data.php_ok);
                for (const [ext, ok] of Object.entries(data.extensions)) {
                    html += renderCheck("اکستنشن " + ext, ok ? "نصب شده" : "یافت نشد", ok);
                }
                for (const [dir, ok] of Object.entries(data.directories)) {
                    html += renderCheck("دسترسی پوشه " + dir, ok ? "قابل نوشتن" : "غیرقابل نوشتن", ok);
                }
                document.getElementById("checkList").innerHTML = html;
                if (data.can_install) {
                    document.getElementById("btnStep1Next").removeAttribute("disabled");
                } else {
                    showAlert("پیش‌نیازهای سرور کامل نیستند. لطفاً موارد قرمز را برطرف فرمایید.", "danger");
                }
            } catch (e) {
                showAlert("ارتباط با سرور برقرار نشد: " + e.message, "danger");
            }
        }

        function renderCheck(title, val, ok) {
            return `<div class="check-item"><span>${title} (${val})</span><span class="badge ${ok ? "badge-success" : "badge-danger"}">${ok ? "تأیید ✓" : "عدم تأیید ✗"}</span></div>`;
        }

        function showAlert(msg, type) {
            const b = document.getElementById("alertBox");
            b.className = "alert alert-" + type;
            b.innerText = msg;
            b.style.display = "block";
        }
        function hideAlert() { document.getElementById("alertBox").style.display = "none"; }

        function goToStep(s) {
            hideAlert();
            document.getElementById("step-" + currentStep).style.display = "none";
            document.getElementById("si-" + currentStep).classList.remove("active");
            if (s > currentStep) document.getElementById("si-" + currentStep).classList.add("done");
            currentStep = s;
            document.getElementById("step-" + s).style.display = "block";
            document.getElementById("si-" + s).classList.add("active");
        }

        async function testDatabaseConnection() {
            const host = document.getElementById("db_host").value;
            const port = document.getElementById("db_port").value;
            const name = document.getElementById("db_name").value;
            const user = document.getElementById("db_user").value;
            const pass = document.getElementById("db_pass").value;
            const statusEl = document.getElementById("dbTestStatus");
            statusEl.innerText = "در حال تست اتصال...";

            try {
                const res = await fetch("/api/v1/install/database", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ db_host: host, db_port: port, db_name: name, db_user: user, db_pass: pass })
                });
                const json = await res.json();
                if (json.success) {
                    statusEl.style.color = "#34d399";
                    statusEl.innerText = "✓ " + json.data.message + " (" + json.data.db_version + ")";
                } else {
                    statusEl.style.color = "#f87171";
                    statusEl.innerText = "✗ " + (json.error ? json.error.message : "خطا در اتصال");
                }
            } catch (e) {
                statusEl.style.color = "#f87171";
                statusEl.innerText = "✗ خطا: " + e.message;
            }
        }

        async function submitInstallation() {
            hideAlert();
            const btn = document.getElementById("btnInstall");
            btn.disabled = true;
            btn.innerText = "در حال اجرای نصب و مایگریشن‌ها...";

            const payload = {
                db_host: document.getElementById("db_host").value,
                db_port: document.getElementById("db_port").value,
                db_name: document.getElementById("db_name").value,
                db_user: document.getElementById("db_user").value,
                db_pass: document.getElementById("db_pass").value,
                admin_name: document.getElementById("admin_name").value,
                admin_username: document.getElementById("admin_username").value,
                admin_email: document.getElementById("admin_email").value,
                admin_password: document.getElementById("admin_password").value,
            };

            try {
                const res = await fetch("/api/v1/install/setup", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.success) {
                    goToStep(4);
                } else {
                    btn.disabled = false;
                    btn.innerText = "شروع نصب و پیکربندی";
                    showAlert(json.error ? json.error.message : "خطای ناشناخته در نصب", "danger");
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerText = "شروع نصب و پیکربندی";
                showAlert("خطای ارتباط: " + e.message, "danger");
            }
        }

        window.onload = initCheck;
    </script>
</body>
</html>';

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
