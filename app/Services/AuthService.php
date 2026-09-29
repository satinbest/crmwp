<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\Security;
use App\Support\Session;
use Exception;

class AuthService
{
    private UserRepository $userRepo;
    private AuditService $auditService;

    public function __construct(?UserRepository $userRepo = null, ?AuditService $auditService = null)
    {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    public function login(string $identifier, string $password, string $ip, string $userAgent): User
    {
        $identifier = trim($identifier);
        if (empty($identifier) || empty($password)) {
            throw new Exception("شناسه کاربری و کلمه عبور الزامی است.", 422);
        }

        $user = $this->userRepo->findByUsernameOrEmail($identifier);
        if (!$user) {
            throw new Exception("نام کاربری یا رمز عبور اشتباه است.", 401);
        }

        if (!Security::verifyPassword($password, $user->password_hash)) {
            throw new Exception("نام کاربری یا رمز عبور اشتباه است.", 401);
        }

        if (!$user->is_active) {
            throw new Exception("حساب کاربری شما غیرفعال شده است. لطفاً با مدیر سیستم تماس بگیرید.", 403);
        }

        // Regenerate session id to protect against session fixation
        Session::start();
        Session::regenerate();
        Session::set('user_id', $user->id);
        Session::set('auth_time', time());

        // Update last login
        $this->userRepo->updateLastLogin($user->id, $ip);

        // Audit log
        $this->auditService->log(
            $user->id,
            null,
            'USER_LOGIN',
            'user',
            (string)$user->id,
            null,
            ['username' => $user->username],
            $ip,
            $userAgent
        );

        return $this->userRepo->findById($user->id);
    }

    public function getCurrentUser(): ?User
    {
        Session::start();
        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }

        return $this->userRepo->findById((int)$userId);
    }

    public function logout(string $ip, string $userAgent): void
    {
        Session::start();
        $userId = Session::get('user_id');

        if ($userId) {
            $this->auditService->log(
                (int)$userId,
                null,
                'USER_LOGOUT',
                'user',
                (string)$userId,
                null,
                null,
                $ip,
                $userAgent
            );
        }

        Session::destroy();
    }
}
