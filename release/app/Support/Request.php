<?php

namespace App\Support;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $headers;
    private array $queryParams;
    private array $body;
    private array $routeParams = [];
    private array $cookies;
    private string $ip;
    private string $userAgent;
    private ?object $user = null;
    private string $rawBody = '';
    private string $requestId;
    public static ?string $currentRequestId = null;

    public function __construct(?string $rawBody = null)
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?? '/';
        $this->headers = $this->extractHeaders();
        $this->queryParams = $_GET ?? [];
        $this->cookies = $_COOKIE ?? [];
        $this->ip = $this->resolveClientIp();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $this->rawBody = $rawBody ?? (string)file_get_contents('php://input');
        $this->body = $this->parseBody();

        $incomingId = $this->headers['x-request-id'] ?? null;
        if (!empty($incomingId) && preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $incomingId)) {
            $this->requestId = $incomingId;
        } else {
            $this->requestId = self::generateRequestId();
        }
        self::$currentRequestId = $this->requestId;
    }

    public static function generateRequestId(): string
    {
        return 'req_' . bin2hex(random_bytes(8));
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public static function currentRequestId(): string
    {
        if (self::$currentRequestId === null) {
            self::$currentRequestId = self::generateRequestId();
        }
        return self::$currentRequestId;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function setRawBody(string $raw): void
    {
        $this->rawBody = $raw;
        $this->body = $this->parseBody();
    }

    public static function capture(): self
    {
        return new self();
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return strcasecmp($this->method, $method) === 0;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->queryParams) || array_key_exists($key, $this->routeParams);
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->body, $this->routeParams);
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function getRouteParam(string $key, mixed $default = null): mixed
    {
        return $this->param($key, $default);
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower(str_replace('_', '-', $name));
        return $this->headers[$normalized] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->getHeader($name, $default);
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function setUser(?object $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?object
    {
        return $this->user;
    }

    private function parseBody(): array
    {
        $contentType = $this->headers['content-type'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = $this->rawBody;
            if (empty($raw)) {
                return [];
            }
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        if ($this->method === 'POST') {
            return $_POST ?? [];
        }

        return [];
    }

    private function extractHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }

    private function resolveClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
