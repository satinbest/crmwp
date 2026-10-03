<?php

namespace App\Integrations\WooCommerce;

use App\Support\Logger;

class WooCommerceClient
{
    private string $storeUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private int $timeout;
    private int $maxRetries;
    private int $retryDelayMs;

    public function __construct(
        string|\App\Models\Store $storeOrUrl,
        ?string $consumerKey = null,
        ?string $consumerSecret = null,
        int $timeout = 15,
        int $maxRetries = 2,
        int $retryDelayMs = 500
    ) {
        if ($storeOrUrl instanceof \App\Models\Store) {
            $creds = $storeOrUrl->getDecryptedCredentials();
            $this->storeUrl = rtrim($storeOrUrl->url, '/');
            $this->consumerKey = (string)($creds['consumer_key'] ?? '');
            $this->consumerSecret = (string)($creds['consumer_secret'] ?? '');
        } else {
            $this->storeUrl = rtrim($storeOrUrl, '/');
            $this->consumerKey = (string)$consumerKey;
            $this->consumerSecret = (string)$consumerSecret;
        }
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->retryDelayMs = $retryDelayMs;
    }

    public function getStoreUrl(): string
    {
        return $this->storeUrl;
    }

    public function get(string $endpoint, array $queryParams = []): array
    {
        return $this->request('GET', $endpoint, $queryParams);
    }

    public function getWithHeaders(string $endpoint, array $queryParams = []): array
    {
        return $this->requestWithHeaders('GET', $endpoint, $queryParams);
    }

    public function post(string $endpoint, array $body = []): array
    {
        return $this->request('POST', $endpoint, [], $body);
    }

    public function put(string $endpoint, array $body = []): array
    {
        return $this->request('PUT', $endpoint, [], $body);
    }

    public function delete(string $endpoint, array $queryParams = []): array
    {
        return $this->request('DELETE', $endpoint, $queryParams);
    }

    public function request(string $method, string $endpoint, array $queryParams = [], array $body = []): array
    {
        $res = $this->requestWithHeaders($method, $endpoint, $queryParams, $body);
        return $res['data'];
    }

    public function requestWithHeaders(string $method, string $endpoint, array $queryParams = [], array $body = []): array
    {
        $path = '/' . ltrim($endpoint, '/');
        // If path doesn't start with /wp-json/, prepend standard /wp-json/wc/v3
        if (!str_starts_with($path, '/wp-json/')) {
            $path = '/wp-json/wc/v3' . $path;
        }

        $url = $this->storeUrl . $path;
        if (!empty($queryParams)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            $attempt++;

            try {
                return $this->executeCurl($method, $url, $body);
            } catch (WooCommerceApiException $e) {
                $status = $e->getHttpStatus();
                $lastException = $e;

                // Transient errors eligible for retry: 429, 502, 503, 504
                $isTransient = in_array($status, [429, 502, 503, 504], true);

                if ($isTransient && $attempt <= $this->maxRetries) {
                    Logger::warning("WooCommerce API transient error (HTTP {$status}). Retrying attempt {$attempt}/{$this->maxRetries}...", [
                        'store_url' => $this->storeUrl,
                        'endpoint' => $endpoint,
                        'status' => $status,
                    ]);

                    // If 429 rate limited, inspect retry delay
                    $delay = $this->retryDelayMs * ($attempt * 1.5);
                    usleep((int)($delay * 1000));
                    continue;
                }

                // Permanent error or out of retries: throw immediately
                throw $e;
            } catch (\Exception $e) {
                $lastException = $e;
                if ($attempt <= $this->maxRetries) {
                    Logger::warning("WooCommerce network error. Retrying attempt {$attempt}/{$this->maxRetries}...", [
                        'store_url' => $this->storeUrl,
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage(),
                    ]);
                    usleep((int)($this->retryDelayMs * 1000));
                    continue;
                }
                throw new WooCommerceApiException(
                    "خطا در برقراری ارتباط با فروشگاه: " . $e->getMessage(),
                    'NETWORK_ERROR',
                    503,
                    ['original_message' => $e->getMessage()],
                    $e
                );
            }
        }

        throw $lastException ?? new WooCommerceApiException("خطای ناشناخته در ارتباط با ووکامرس", 'UNKNOWN_ERROR', 500);
    }

    private function executeCurl(string $method, string $url, array $body = []): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Security: verify SSL certificates in production; allow self-signed in local development
        $isLocal = str_contains($url, 'localhost') || str_contains($url, '127.0.0.1') || str_contains($url, '.test') || str_contains($url, '.local');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$isLocal);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $isLocal ? 0 : 2);

        $caBundle = dirname(__DIR__, 3) . '/storage/cacert.pem';
        if (file_exists($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        // Authentication: Basic Auth
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->consumerKey}:{$this->consumerSecret}");

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        });

        $headers = [
            'Accept: application/json',
            'User-Agent: Antigravity-WooCommerce-CRM/1.0',
        ];

        if (!empty($body) || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            // Safe logging without credentials
            Logger::error("cURL error connecting to WooCommerce", [
                'url' => parse_url($url, PHP_URL_HOST),
                'errno' => $curlErrno,
                'error' => $curlError,
            ]);

            $message = match ($curlErrno) {
                CURLE_OPERATION_TIMEOUTED => "مهلت زمانی اتصال به فروشگاه به پایان رسید (Timeout).",
                CURLE_COULDNT_RESOLVE_HOST => "آدرس دامنه فروشگاه پیدا نشد یا DNS در دسترس نیست.",
                CURLE_COULDNT_CONNECT => "امکان اتصال به سرور فروشگاه وجود ندارد (سرور ممکن است خاموش یا مسدود باشد).",
                CURLE_SSL_CONNECT_ERROR => "خطا در برقراری اتصال امن SSL با فروشگاه.",
                default => "خطا در ارتباط با سرور فروشگاه: {$curlError}",
            };

            throw new WooCommerceApiException($message, 'CONNECTION_FAILED', 503, [
                'curl_errno' => $curlErrno,
            ]);
        }

        $decoded = json_decode($response, true);

        // Handle HTTP Statuses
        if ($httpCode >= 200 && $httpCode < 300) {
            $data = is_array($decoded) ? $decoded : ['raw' => $response];
            return ['data' => $data, 'headers' => $responseHeaders];
        }

        // Extract WooCommerce error message if present
        $wcCode = $decoded['code'] ?? 'WOOCOMMERCE_ERROR';
        $wcMessage = $decoded['message'] ?? null;

        // Friendly Persian error translation
        $friendlyMessage = match ($httpCode) {
            401 => "اطلاعات احراز هویت (Consumer Key یا Consumer Secret) نادرست است یا دسترسی منقضی شده است.",
            403 => "دسترسی به این منبع ووکامرس برای کلیدهای ارائه شده مجاز نیست (مجوز خواندن/نوشتن ناکافی است).",
            404 => "مسیر یا داده مورد نظر در فروشگاه ووکامرس یافت نشد. لطفاً فعال بودن ووکامرس و ساختار پیوندهای یکتا (Permalinks) را بررسی کنید.",
            422 => "داده‌های ارسالی توسط ووکامرس معتبر تشخیص داده نشد.",
            429 => "تعداد درخواست‌های ارسالی به فروشگاه بیش از حد مجاز است (Rate Limit).",
            500 => "سرور فروشگاه با خطای داخلی مواجه شد (PHP Error در وردپرس).",
            502 => "سرور فروشگاه در دسترس نیست یا پاسخ نامعتبر ارسال کرد (Bad Gateway).",
            503 => "سرویس فروشگاه موقتاً در دسترس نیست (Service Unavailable).",
            504 => "پاسخ از سرور فروشگاه با وقفه مواجه شد (Gateway Timeout).",
            default => $wcMessage ?: "پاسخ خطای غیرمنتظره از ووکامرس دریافت شد (کد وضعیت {$httpCode}).",
        };

        if ($wcMessage && $httpCode !== 401 && $httpCode !== 403) {
            $friendlyMessage .= " [پیام سرور: " . strip_tags($wcMessage) . "]";
        }

        Logger::error("WooCommerce API returned HTTP error", [
            'status' => $httpCode,
            'wc_code' => $wcCode,
            'host' => parse_url($url, PHP_URL_HOST),
        ]);

        throw new WooCommerceApiException($friendlyMessage, $wcCode, $httpCode, [
            'http_status' => $httpCode,
            'woocommerce_error' => $decoded,
        ]);
    }
}
