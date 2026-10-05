<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Connection;
use Throwable;

class Csp
{
    private static ?array $cachedOrigins = null;

    /**
     * Parse and normalize origins from registered stores in multi-store context.
     * Extracts scheme, host, and port while stripping paths and query strings.
     *
     * @return string[] Unique list of allowed origin strings (e.g. ['https://harmonicashop.ir', 'http://woocommerce.local:8080'])
     */
    public static function getAllowedImageOrigins(): array
    {
        if (self::$cachedOrigins !== null) {
            return self::$cachedOrigins;
        }

        $origins = [];

        try {
            $pdo = Connection::get();
            $stmt = $pdo->query("SELECT url FROM stores WHERE url IS NOT NULL AND url != ''");
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $rawUrl = trim((string)($row['url'] ?? ''));
                if ($rawUrl === '') {
                    continue;
                }

                $origin = self::normalizeOrigin($rawUrl);
                if ($origin !== null && !in_array($origin, $origins, true)) {
                    $origins[] = $origin;
                }
            }
        } catch (Throwable $e) {
            // In case database is not initialized yet (e.g. during install), fallback gracefully
        }

        // Additional allowed origins from environment (optional comma-separated)
        $envOrigins = Env::get('ALLOWED_IMAGE_ORIGINS', '');
        if (!empty($envOrigins)) {
            $parts = explode(',', (string)$envOrigins);
            foreach ($parts as $p) {
                $origin = self::normalizeOrigin(trim($p));
                if ($origin !== null && !in_array($origin, $origins, true)) {
                    $origins[] = $origin;
                }
            }
        }

        self::$cachedOrigins = $origins;
        return $origins;
    }

    /**
     * Normalize a URL or origin to a strict origin (scheme://host[:port]).
     */
    public static function normalizeOrigin(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Add scheme if missing for parse_url
        if (!str_contains($url, '://')) {
            $url = 'https://' . $url;
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return null;
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $host = strtolower($parsed['host']);
        $port = isset($parsed['port']) ? ':' . (int)$parsed['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    /**
     * Clear cached origins (useful when stores are created, updated, or deleted).
     */
    public static function resetCache(): void
    {
        self::$cachedOrigins = null;
    }

    /**
     * Build the full secure Content-Security-Policy header string.
     */
    public static function getHeaderString(): string
    {
        $origins = self::getAllowedImageOrigins();
        $imgSources = ["'self'", 'data:', 'blob:'];

        foreach ($origins as $origin) {
            if (!in_array($origin, $imgSources, true)) {
                $imgSources[] = $origin;
            }
        }

        $imgSrcDirective = implode(' ', $imgSources);

        return "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src {$imgSrcDirective}; connect-src 'self'; frame-ancestors 'none';";
    }
}
