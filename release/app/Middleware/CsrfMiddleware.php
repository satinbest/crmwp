<?php

namespace App\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

class CsrfMiddleware implements MiddlewareInterface
{
    private array $exemptRoutes = [
        '/api/v1/auth/login',
        '/api/v1/auth/csrf',
        '/api/v1/webhooks',
    ];

    public function handle(Request $request, callable $next): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Safe methods do not require CSRF
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Check exempt paths
        foreach ($this->exemptRoutes as $exempt) {
            if (str_starts_with($path, $exempt)) {
                return $next($request);
            }
        }

        // Retrieve token from header or body
        $token = $request->getHeader('x-csrf-token') ?: $request->input('_csrf_token');

        if (!Session::validateCsrfToken($token)) {
            return Response::error('CSRF_MISMATCH', 'توکن امنیتی (CSRF) نامعتبر است یا منقضی شده است.', [], 419);
        }

        return $next($request);
    }
}
