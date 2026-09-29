<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use Exception;

class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function csrf(Request $request): Response
    {
        $token = Session::getCsrfToken();
        return $this->success(['csrf_token' => $token]);
    }

    public function login(Request $request): Response
    {
        $username = (string)$request->input('username', '');
        $password = (string)$request->input('password', '');

        if (empty($username) || empty($password)) {
            return $this->error('VALIDATION_ERROR', 'نام کاربری و کلمه عبور الزامی است.', [
                'username' => empty($username) ? 'نام کاربری نمی‌تواند خالی باشد.' : null,
                'password' => empty($password) ? 'کلمه عبور نمی‌تواند خالی باشد.' : null,
            ], 422);
        }

        try {
            $user = $this->authService->login(
                $username,
                $password,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success([
                'user' => $user->toArray(),
                'csrf_token' => Session::getCsrfToken(),
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 401;
            return $this->error('AUTH_FAILED', $e->getMessage(), [], $code);
        }
    }

    public function me(Request $request): Response
    {
        $user = $this->currentUser($request);

        if (!$user) {
            return $this->error('UNAUTHENTICATED', 'کاربر وارد نشده است.', [], 401);
        }

        return $this->success([
            'user' => $user->toArray(),
            'csrf_token' => Session::getCsrfToken(),
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout($request->getIp(), $request->getUserAgent());
        return $this->success(['message' => 'با موفقیت خارج شدید.']);
    }
}
