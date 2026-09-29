<?php

namespace App\Support;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $sessionName = Config::get('auth.session_name', 'crmwp_session');
        $lifetime = (int)Config::get('auth.session_lifetime', 7200);
        $secure = (bool)Config::get('auth.secure', false);
        $sameSite = Config::get('auth.same_site', 'Lax');

        // Check if request is over HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $cookieSecure = $secure || $isHttps;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string)$lifetime);

        session_name($sessionName);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $cookieSecure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);

        session_start();
        self::$started = true;

        // Verify session expiration
        $lastActivity = $_SESSION['__last_activity'] ?? null;
        if ($lastActivity !== null && (time() - $lastActivity) > $lifetime) {
            self::destroy();
            session_start();
            self::$started = true;
        }

        $_SESSION['__last_activity'] = time();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            self::$started = false;
        }
    }

    public static function getCsrfToken(): string
    {
        self::start();
        if (empty($_SESSION['__csrf_token'])) {
            $_SESSION['__csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['__csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        $stored = self::getCsrfToken();
        return hash_equals($stored, $token);
    }
}
