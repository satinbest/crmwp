<?php

namespace App\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use Exception;

class RoleService
{
    private RoleRepository $roleRepo;
    private PermissionRepository $permRepo;
    private AuditService $auditService;

    // Protected default system roles that cannot be deleted
    private const PROTECTED_SLUGS = ['admin', 'manager', 'sales', 'inventory_manager', 'support', 'viewer'];

    public function __construct(
        ?RoleRepository $roleRepo = null,
        ?PermissionRepository $permRepo = null,
        ?AuditService $auditService = null
    ) {
        $this->roleRepo = $roleRepo ?? new RoleRepository();
        $this->permRepo = $permRepo ?? new PermissionRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    public function listRoles(array $filters = []): array
    {
        $roles = $this->roleRepo->all($filters);
        return array_map(fn($r) => $r->toArray(), $roles);
    }

    public function getRole(int $id): ?array
    {
        $role = $this->roleRepo->findById($id);
        return $role ? $role->toArray() : null;
    }

    public function createRole(array $data, int $actorUserId, ?string $ip = null, ?string $userAgent = null): array
    {
        $name = trim($data['name'] ?? '');
        $displayName = trim($data['display_name'] ?? $name);
        $slug = trim($data['slug'] ?? '');

        if (empty($name)) {
            throw new Exception("نام نقش نمی‌تواند خالی باشد.", 422);
        }

        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        }

        if ($this->roleRepo->findBySlug($slug)) {
            throw new Exception("شناسه نقش (Slug) قبلاً استفاده شده است.", 422);
        }

        $role = $this->roleRepo->create([
            'name' => $name,
            'slug' => $slug,
            'display_name' => $displayName,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
            'permissions' => $data['permissions'] ?? [],
        ]);

        $this->auditService->log(
            $actorUserId,
            null,
            'role.created',
            'role',
            (string)$role->id,
            null,
            $role->toArray(),
            $ip,
            $userAgent
        );

        return $role->toArray();
    }

    public function updateRole(int $id, array $data, int $actorUserId, ?string $ip = null, ?string $userAgent = null): ?array
    {
        $existing = $this->roleRepo->findById($id);
        if (!$existing) {
            return null;
        }

        // Slug change validation
        if (!empty($data['slug']) && $data['slug'] !== $existing->slug) {
            if (in_array($existing->slug, self::PROTECTED_SLUGS, true)) {
                throw new Exception("امکان تغییر شناسه (Slug) نقش‌های پیش‌فرض سیستم وجود ندارد.", 422);
            }
            if ($this->roleRepo->findBySlug($data['slug'])) {
                throw new Exception("شناسه نقش (Slug) قبلاً ثبت شده است.", 422);
            }
        }

        $updated = $this->roleRepo->update($id, $data);

        $this->auditService->log(
            $actorUserId,
            null,
            'role.updated',
            'role',
            (string)$id,
            $existing->toArray(),
            $updated->toArray(),
            $ip,
            $userAgent
        );

        return $updated->toArray();
    }

    public function deleteRole(int $id, int $actorUserId, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existing = $this->roleRepo->findById($id);
        if (!$existing) {
            throw new Exception("نقش مورد نظر یافت نشد.", 404);
        }

        if (in_array($existing->slug, self::PROTECTED_SLUGS, true)) {
            throw new Exception("نقش‌های پیش‌فرض سامانه قابل حذف نیستند.", 422);
        }

        $usersCount = $this->roleRepo->countUsersWithRole($id);
        if ($usersCount > 0) {
            throw new Exception("این نقش به {$usersCount} کاربر تخصیص داده شده است و قبل از حذف باید کاربران آن به نقش دیگری منتقل شوند.", 422);
        }

        $deleted = $this->roleRepo->delete($id);

        if ($deleted) {
            $this->auditService->log(
                $actorUserId,
                null,
                'role.deleted',
                'role',
                (string)$id,
                $existing->toArray(),
                null,
                $ip,
                $userAgent
            );
        }

        return $deleted;
    }

    public function duplicateRole(int $id, string $newName, ?string $newSlug, int $actorUserId, ?string $ip = null, ?string $userAgent = null): array
    {
        $existing = $this->roleRepo->findById($id);
        if (!$existing) {
            throw new Exception("نقش مبدا برای تکثیر یافت نشد.", 404);
        }

        $name = trim($newName);
        if (empty($name)) {
            throw new Exception("نام نقش جدید الزامی است.", 422);
        }

        $slug = $newSlug ? trim($newSlug) : (strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name)) . '_' . rand(100, 999));
        if ($this->roleRepo->findBySlug($slug)) {
            $slug .= '_' . rand(100, 999);
        }

        $duplicated = $this->roleRepo->duplicate($id, $name, $slug);

        $this->auditService->log(
            $actorUserId,
            null,
            'role.duplicated',
            'role',
            (string)$duplicated->id,
            ['source_role_id' => $id],
            $duplicated->toArray(),
            $ip,
            $userAgent
        );

        return $duplicated->toArray();
    }

    public function getRolePermissions(int $roleId): array
    {
        $existing = $this->roleRepo->findById($roleId);
        if (!$existing) {
            throw new Exception("نقش مورد نظر یافت نشد.", 404);
        }

        return $this->roleRepo->getPermissionsForRole($roleId);
    }

    public function syncRolePermissions(int $roleId, array $permissionIdsOrNames, int $actorUserId, ?string $ip = null, ?string $userAgent = null): array
    {
        $existing = $this->roleRepo->findById($roleId);
        if (!$existing) {
            throw new Exception("نقش مورد نظر یافت نشد.", 404);
        }

        $oldPerms = $this->roleRepo->getPermissionsForRole($roleId);
        $this->roleRepo->syncPermissions($roleId, $permissionIdsOrNames);
        $newPerms = $this->roleRepo->getPermissionsForRole($roleId);

        $this->auditService->log(
            $actorUserId,
            null,
            'role.permissions_updated',
            'role',
            (string)$roleId,
            ['permissions_count' => count($oldPerms)],
            ['permissions_count' => count($newPerms)],
            $ip,
            $userAgent
        );

        return $newPerms;
    }
}
