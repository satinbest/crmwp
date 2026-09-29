<?php

declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8000';

echo "Testing Live HTTP endpoints on {$baseUrl}...\n";

// 1. Health endpoint
$ch = curl_init("{$baseUrl}/api/v1/health");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "1. GET /api/v1/health -> HTTP {$httpCode}\n";
echo "   Has X-Request-Id: " . (preg_match('/X-Request-Id:\s*(req_[a-zA-Z0-9]+)/i', $headers, $m) ? "YES ({$m[1]})" : "NO") . "\n";
echo "   Has CSP: " . (str_contains($headers, 'Content-Security-Policy') ? "YES" : "NO") . "\n";
echo "   Has X-Content-Type-Options: " . (str_contains($headers, 'X-Content-Type-Options') ? "YES" : "NO") . "\n";
echo "   Has X-Frame-Options: " . (str_contains($headers, 'X-Frame-Options') ? "YES" : "NO") . "\n";
echo "   Body: " . substr(trim($body), 0, 120) . "...\n\n";

// 2. Liveness endpoint
$ch = curl_init("{$baseUrl}/api/v1/health/liveness");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "2. GET /api/v1/health/liveness -> HTTP {$httpCode}: {$body}\n\n";

// 3. Readiness endpoint
$ch = curl_init("{$baseUrl}/api/v1/health/ready");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "3. GET /api/v1/health/ready -> HTTP {$httpCode}: {$body}\n\n";

// 4. Unauthenticated access on protected route
$ch = curl_init("{$baseUrl}/api/v1/me/profile");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);
echo "4. GET /api/v1/me/profile (Unauthenticated) -> HTTP {$httpCode}\n";
echo "   Body: {$body}\n";
