<?php

namespace App\Support;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $content = null;

    public function __construct(mixed $content = null, int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;

        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), camera=(), microphone=()',
            'Content-Security-Policy' => Csp::getHeaderString(),
            'X-Request-Id' => Request::currentRequestId(),
        ];

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        if ($isHttps) {
            $defaultHeaders['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $this->headers = array_merge($defaultHeaders, $headers);
    }

    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        return new self($data, $statusCode, $headers);
    }

    public static function success(mixed $data = [], array $meta = [], int $statusCode = 200): self
    {
        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => (object)$meta,
        ];

        return new self($payload, $statusCode);
    }

    public static function error(string $code, string $message, array $details = [], int $statusCode = 400): self
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object)$details,
                'request_id' => Request::currentRequestId(),
            ],
        ];

        return new self($payload, $statusCode);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $headerName => $headerValue) {
            if (strcasecmp($headerName, $name) === 0) {
                return $headerValue;
            }
        }
        return null;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->content !== null) {
            if (is_array($this->content) || is_object($this->content)) {
                echo json_encode($this->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo $this->content;
            }
        }
    }
}
