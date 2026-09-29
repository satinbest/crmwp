<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AuditLogRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StoreRepository;
use App\Repositories\UserRepository;
use App\Support\Security;
use Exception;

class UserService
{
    private UserRepository $userRepo;
    private RoleRepository $roleRepo;
    private StoreRepository $storeRepo;
    private RbacService $rbacService;
    private AuditService $auditService;
    private AuditLogRepository $auditRepo;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?RoleRepository $roleRepo = null,
        ?StoreRepository $storeRepo = null,
        ?RbacService $rbacService = null,
        ?AuditService $auditService = null,
        ?AuditLogRepository $auditRepo = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->roleRepo = $roleRepo ?? new RoleRepository();
        $this->storeRepo = $storeRepo ?? new StoreRepository();
        $this->rbacService = $rbacService ?? new RbacService();
        $this->auditService = $auditService ?? new AuditService();
        $this->auditRepo = $auditRepo ?? new AuditLogRepository();
    }

    public function listUsers(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $limit = max(1, min(100, $perPage));
        $offset = ($page - 1) * $limit;

        $users = $this->userRepo->listFiltered($filters, $limit, $offset);
        $total = $this->userRepo->countFiltered($filters);

        return [
            'users' => array_map(fn($u) => $u->toArray(), $users),
            'meta' => [
                'page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    public function getUser(int $id): ?array
    {
        $user = $this->userRepo->findById($id);
        return $user ? $user->toArray() : null;
    }

    public function createUser(array $data, int $actorUserId, ?string $ip = null, ?string $userAgent = null): array
    {
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? ($data['password_confirmation'] ?? $password);

        if (empty($username) || mb_strlen($username) < 3) {
            throw new Exception("نام کاربری باید حداقل ۳ کاراکتر باشد.", 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("فرمت ایمیل نامعتبر است.", 422);
        }

        if ($password !== $confirmPassword) {
            throw new Exception("رمز عبور و تکرار آن یکسان نیستند.", 422);
        }

        $passwordError = $this->validatePasswordStrength($password);
        if ($passwordError) {
            throw new Exception($passwordError, 422);
        }

        if ($this->userRepo->findByUsername($username)) {
            throw new Exception("این نام کاربری قبلاً در سامانه ثبت شده است.", 422);
        }

        if ($this->userRepo->findByEmail($email)) {
            throw new Exception("این آدرس ایمیل قبلاً در سامانه ثبت شده است.", 422);
        }

        // Validate Roles & Privilege Escalation check
        $roles = $data['roles'] ?? [];
        if (!empty($roles)) {
            $isActorAdmin = $this->rbacService->userHasRole($actorUserId, 'admin');
            foreach ($roles as $r) {
                $roleModel = is_numeric($r) ? $this->roleRepo->findById((int)$r) : $this->roleRepo->findBySlug((string)$r);
                if (!$roleModel) {
                    throw new Exception("نقش انتخاب‌شده نامعتبر است.", 422);
                }
                if (($roleModel->slug === 'admin' || $roleModel->name === 'Admin') && !$isActorAdmin) {
                    throw new Exception("تنها مدیران ارشد سیستم مجاز به اعطای نقش Administrator هستند.", 403);
                }
            }
        }

        // Validate Stores
        $stores = $data['stores'] ?? [];
        if (!empty($stores)) {
            foreach ($stores as $sid) {
                if (!$this->storeRepo->findById((int)$sid)) {
                    throw new Exception("فروشگاه با شناسه {$sid} یافت نشد.", 422);
                }
            }
        }

        $passwordHash = Security::hashPassword($password);

        $userData = [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            'roles' => $roles,
            'stores' => $stores,
        ];

        $user = $this->userRepo->create($userData);

        $this->auditService->log(
            $actorUserId,
            null,
            'user.created',
            'user',
            (string)$user->id,
            null,
            ['username' => $user->username, 'email' => $user->email, 'roles' => $roles],
            $ip,
            $userAgent
        );

        return $user->toArray();
    }

    public function updateUser(int $id, array $data, int $actorUserId, ?string $ip = null, ?string $userAgent = null): ?array
    {
        $existing = $this->userRepo->findById($id);
        if (!$existing) {
            return null;
        }

        if (!$this->rbacService->canManageUser($actorUserId, $id)) {
            throw new Exception("شما دسترسی مجاز برای ویرایش اطلاعات این کاربر را ندارید.", 403);
        }

        $updateData = [];

        if (isset($data['username'])) {
            $newUsername = trim($data['username']);
            if (empty($newUsername) || mb_strlen($newUsername) < 3) {
                throw new Exception("نام کاربری باید حداقل ۳ کاراکتر باشد.", 422);
            }
            if ($newUsername !== $existing->username && $this->userRepo->findByUsername($newUsername)) {
                throw new Exception("این نام کاربری قبلاً استفاده شده است.", 422);
            }
            $updateData['username'] = $newUsername;
        }

        if (isset($data['email'])) {
            $newEmail = trim($data['email']);
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("فرمت ایمیل نامعتبر است.", 422);
            }
            if ($newEmail !== $existing->email && $this->userRepo->findByEmail($newEmail)) {
                throw new Exception("این ایمیل قبلاً ثبت شده است.", 422);
            }
            $updateData['email'] = $newEmail;
        }

        if (array_key_exists('first_name', $data)) {
            $updateData['first_name'] = $data['first_name'];
        }

        if (array_key_exists('last_name', $data)) {
            $updateData['last_name'] = $data['last_name'];
        }

        if (array_key_exists('avatar', $data)) {
            $updateData['avatar'] = $data['avatar'];
        }

        // Active Status & Last Admin Protection
        if (isset($data['is_active'])) {
            $newActive = (bool)$data['is_active'];
            if (!$newActive && $existing->is_active && $this->rbacService->isLastActiveAdmin($id)) {
                throw new Exception("امکان غیرفعال‌سازی آخرین مدیر ارشد فعال سیستم وجود ندارد.", 422);
            }
            $updateData['is_active'] = $newActive ? 1 : 0;
        }

        // Password update
        if (!empty($data['password'])) {
            $pwd = $data['password'];
            $pwdErr = $this->validatePasswordStrength($pwd);
            if ($pwdErr) {
                throw new Exception($pwdErr, 422);
            }
            $updateData['password_hash'] = Security::hashPassword($pwd);
        }

        // Roles update & Last Admin Protection
        if (isset($data['roles']) && is_array($data['roles'])) {
            $newRoles = $data['roles'];
            $isActorAdmin = $this->rbacService->userHasRole($actorUserId, 'admin');

            $hasAdminInNew = false;
            foreach ($newRoles as $r) {
                $roleModel = is_numeric($r) ? $this->roleRepo->findById((int)$r) : $this->roleRepo->findBySlug((string)$r);
                if (!$roleModel) {
                    throw new Exception("نقش انتخاب‌شده نامعتبر است.", 422);
                }
                if ($roleModel->slug === 'admin' || $roleModel->name === 'Admin') {
                    $hasAdminInNew = true;
                    if (!$isActorAdmin) {
                        throw new Exception("تنها مدیر ارشد می‌تواند نقش Administrator را اعطا کند.", 403);
                    }
                }
            }

            // Check if removing admin from last admin
            if (!$hasAdminInNew && $this->userRepo->isUserAdmin($id) && $this->rbacService->isLastActiveAdmin($id)) {
                throw new Exception("امکان حذف نقش مدیریت ارشد از آخرین مدیر فعال سیستم وجود ندارد.", 422);
            }

            $updateData['roles'] = $newRoles;
        }

        // Stores update
        if (isset($data['stores']) && is_array($data['stores'])) {
            foreach ($data['stores'] as $sid) {
                if (!$this->storeRepo->findById((int)$sid)) {
                    throw new Exception("فروشگاه با شناسه {$sid} یافت نشد.", 422);
                }
            }
            $updateData['stores'] = $data['stores'];
        }

        $updated = $this->userRepo->update($id, $updateData);

        $this->auditService->log(
            $actorUserId,
            null,
            'user.updated',
            'user',
            (string)$id,
            $existing->toArray(),
            $updated->toArray(),
            $ip,
            $userAgent
        );

        return $updated->toArray();
    }

    public function activateUser(int $id, int $actorUserId, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existing = $this->userRepo->findById($id);
        if (!$existing) {
            throw new Exception("کاربر مورد نظر یافت نشد.", 404);
        }

        $res = $this->userRepo->setActive($id, true);

        $this->auditService->log(
            $actorUserId,
            null,
            'user.activated',
            'user',
            (string)$id,
            ['is_active' => $existing->is_active],
            ['is_active' => true],
            $ip,
            $userAgent
        );

        return $res;
    }

    public function deactivateUser(int $id, int $actorUserId, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existing = $this->userRepo->findById($id);
        if (!$existing) {
            throw new Exception("کاربر مورد نظر یافت نشد.", 404);
        }

        if ($this->rbacService->isLastActiveAdmin($id)) {
            throw new Exception("امکان غیرفعال‌سازی آخرین مدیر ارشد فعال سیستم وجود ندارد.", 422);
        }

        $res = $this->userRepo->setActive($id, false);

        $this->auditService->log(
            $actorUserId,
            null,
            'user.deactivated',
            'user',
            (string)$id,
            ['is_active' => $existing->is_active],
            ['is_active' => false],
            $ip,
            $userAgent
        );

        return $res;
    }

    public function deleteUser(int $id, int $actorUserId, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existing = $this->userRepo->findById($id);
        if (!$existing) {
            throw new Exception("کاربر مورد نظر یافت نشد.", 404);
        }

        if ($this->rbacService->isLastActiveAdmin($id)) {
            throw new Exception("امکان حذف آخرین مدیر ارشد فعال سیستم وجود ندارد.", 422);
        }

        $deleted = $this->userRepo->delete($id);

        if ($deleted) {
            $this->auditService->log(
                $actorUserId,
                null,
                'user.deleted',
                'user',
                (string)$id,
                $existing->toArray(),
                null,
                $ip,
                $userAgent
            );
        }

        return $deleted;
    }

    public function resetPassword(int $id, string $newPassword, int $actorUserId, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existing = $this->userRepo->findById($id);
        if (!$existing) {
            throw new Exception("کاربر مورد نظر یافت نشد.", 404);
        }

        if (!$this->rbacService->canManageUser($actorUserId, $id)) {
            throw new Exception("شما اجازه تغییر رمز عبور این کاربر را ندارید.", 403);
        }

        $pwdErr = $this->validatePasswordStrength($newPassword);
        if ($pwdErr) {
            throw new Exception($pwdErr, 422);
        }

        $hash = Security::hashPassword($newPassword);
        $res = $this->userRepo->updatePassword($id, $hash);

        $this->auditService->log(
            $actorUserId,
            null,
            'user.password_reset',
            'user',
            (string)$id,
            null,
            ['reset_by' => $actorUserId],
            $ip,
            $userAgent
        );

        return $res;
    }

    public function updateSelfProfile(int $userId, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $existing = $this->userRepo->findById($userId);
        if (!$existing) {
            throw new Exception("کاربر یافت نشد.", 404);
        }

        $updateData = [];

        if (!empty($data['first_name'])) {
            $updateData['first_name'] = trim($data['first_name']);
        }

        if (!empty($data['last_name'])) {
            $updateData['last_name'] = trim($data['last_name']);
        }

        if (!empty($data['email'])) {
            $newEmail = trim($data['email']);
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("فرمت ایمیل نامعتبر است.", 422);
            }
            if ($newEmail !== $existing->email && $this->userRepo->findByEmail($newEmail)) {
                throw new Exception("این ایمیل قبلاً ثبت شده است.", 422);
            }
            $updateData['email'] = $newEmail;
        }

        if (array_key_exists('avatar', $data)) {
            $updateData['avatar'] = $data['avatar'];
        }

        // Strictly disallow updating roles, stores, or active status via self profile!
        $updated = $this->userRepo->update($userId, $updateData);

        $this->auditService->log(
            $userId,
            null,
            'user.profile_updated',
            'user',
            (string)$userId,
            $existing->toArray(),
            $updated->toArray(),
            $ip,
            $userAgent
        );

        return $updated->toArray();
    }

    public function changeSelfPassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existing = $this->userRepo->findById($userId);
        if (!$existing) {
            throw new Exception("کاربر یافت نشد.", 404);
        }

        if (!Security::verifyPassword($currentPassword, $existing->password_hash)) {
            throw new Exception("کلمه عبور فعلی نادرست است.", 422);
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception("کلمه عبور جدید با تکرار آن همخوانی ندارد.", 422);
        }

        $pwdErr = $this->validatePasswordStrength($newPassword);
        if ($pwdErr) {
            throw new Exception($pwdErr, 422);
        }

        $hash = Security::hashPassword($newPassword);
        $res = $this->userRepo->updatePassword($userId, $hash);

        $this->auditService->log(
            $userId,
            null,
            'user.password_changed',
            'user',
            (string)$userId,
            null,
            ['changed_by_self' => true],
            $ip,
            $userAgent
        );

        return $res;
    }

    public function getUserRecentActivity(int $userId, int $limit = 20): array
    {
        $stmt = \App\Database\Connection::get()->prepare("
            SELECT id, user_id, action, entity_type, entity_id, ip_address, user_agent, created_at
            FROM `audit_logs`
            WHERE `user_id` = ? OR (`entity_type` = 'user' AND `entity_id` = ?)
            ORDER BY `id` DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, (string)$userId, \PDO::PARAM_STR);
        $stmt->bindValue(3, max(1, min(50, $limit)), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function validatePasswordStrength(string $password): ?string
    {
        if (mb_strlen($password) < 8) {
            return "کلمه عبور باید حداقل ۸ کاراکتر باشد.";
        }
        if (!preg_match('/[A-Z]/', $password) && !preg_match('/[a-z]/', $password)) {
            return "کلمه عبور باید شامل حروف انگلیسی باشد.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            return "کلمه عبور باید شامل حداقل یک عدد باشد.";
        }
        return null;
    }
}
