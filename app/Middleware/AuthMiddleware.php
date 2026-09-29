<?php

namespace App\Middleware;

use App\Services\AuthService;
use App\Support\Request;
use App\Support\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return Response::error('UNAUTHENTICATED', 'لطفاً ابتدا وارد سامانه شوید.', [], 401);
        }

        if (!$user->is_active) {
            return Response::error('ACCOUNT_DISABLED', 'حساب کاربری شما غیرفعال شده است.', [], 403);
        }

        $request->setUser($user);

        return $next($request);
    }
}
