<?php

namespace App\Controllers;

use App\Services\RbacService;
use App\Services\UserService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class ProfileController extends BaseController
{
    private UserService $userService;
    private RbacService $rbacService;

    public function __construct(?UserService $userService = null, ?RbacService $rbacService = null)
    {
        $this->userService = $userService ?? new UserService();
        $this->rbacService = $rbacService ?? new RbacService();
    }

    private function requireAuth(Request $request): int
    {
        $user = $this->currentUser($request);
        if (!$user) {
            throw new Exception("احراز هویت الزامی است.", 401);
        }
        return (int)$user->id;
    }

    /**
     * GET /api/v1/me/profile
     */
    public function me(Request $request): Response
    {
        try {
            $userId = $this->requireAuth($request);
            $user = $this->userService->getUser($userId);
            return $this->success($user);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PROFILE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/me/profile
     */
    public function updateProfile(Request $request): Response
    {
        try {
            $userId = $this->requireAuth($request);

            $data = [];
            if ($request->has('first_name')) $data['first_name'] = $request->input('first_name');
            if ($request->has('last_name')) $data['last_name'] = $request->input('last_name');
            if ($request->has('email')) $data['email'] = $request->input('email');
            if ($request->has('avatar')) $data['avatar'] = $request->input('avatar');

            $updated = $this->userService->updateSelfProfile(
                $userId,
                $data,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($updated);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PROFILE_UPDATE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/me/password
     */
    public function changePassword(Request $request): Response
    {
        try {
            $userId = $this->requireAuth($request);

            $currentPassword = (string)$request->input('current_password', '');
            $newPassword = (string)$request->input('new_password', '');
            $confirmPassword = (string)($request->input('confirm_password') ?? $request->input('new_password_confirmation') ?? '');

            if (empty($currentPassword) || empty($newPassword)) {
                return $this->error('VALIDATION_ERROR', 'رمز عبور فعلی و جدید الزامی هستند.', [], 422);
            }

            $this->userService->changeSelfPassword(
                $userId,
                $currentPassword,
                $newPassword,
                $confirmPassword,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success(['message' => 'رمز عبور با موفقیت تغییر یافت.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PASSWORD_CHANGE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/me/avatar
     * Upload and update user avatar image
     */
    public function uploadAvatar(Request $request): Response
    {
        try {
            $userId = $this->requireAuth($request);

            if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                return $this->error('FILE_UPLOAD_ERROR', 'فایل آواتار معتبر ارسال نشده است.', [], 422);
            }

            $file = $_FILES['avatar'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            if ($file['size'] > $maxSize) {
                return $this->error('FILE_TOO_LARGE', 'حجم فایل آواتار حداکثر ۲ مگابایت است.', [], 422);
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowedMimes[$mime])) {
                return $this->error('INVALID_FILE_TYPE', 'تنها فرمت‌های JPG، PNG و WEBP مجاز هستند.', [], 422);
            }

            $ext = $allowedMimes[$mime];
            $filename = 'avatar_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $uploadDir = dirname(__DIR__, 2) . '/public/storage/avatars';

            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $targetPath = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return $this->error('UPLOAD_FAILED', 'خطا در ذخیره‌سازی فایل در سرور.', [], 500);
            }

            $avatarUrl = '/storage/avatars/' . $filename;
            $updated = $this->userService->updateSelfProfile(
                $userId,
                ['avatar' => $avatarUrl],
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success([
                'avatar' => $avatarUrl,
                'user' => $updated,
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AVATAR_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/me/permissions
     */
    public function myPermissions(Request $request): Response
    {
        try {
            $userId = $this->requireAuth($request);
            $perms = \App\Database\Connection::get()->prepare("
                SELECT DISTINCT p.name
                FROM `permissions` p
                JOIN `role_permissions` rp ON p.id = rp.permission_id
                JOIN `user_roles` ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ?
            ");
            $perms->execute([$userId]);
            $list = $perms->fetchAll(\PDO::FETCH_COLUMN) ?: [];

            // If admin, include all
            if ($this->rbacService->userHasRole($userId, 'admin')) {
                $list = \App\Database\Connection::get()->query("SELECT `name` FROM `permissions`")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            }

            return $this->success($list);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PERMISSIONS_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/me/stores
     */
    public function myStores(Request $request): Response
    {
        try {
            $userId = $this->requireAuth($request);
            $stores = $this->rbacService->getUserAccessibleStores($userId);
            return $this->success(array_map(fn($s) => $s->toArray(false), $stores));
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('STORES_ERROR', $e->getMessage(), [], $code);
        }
    }
}
