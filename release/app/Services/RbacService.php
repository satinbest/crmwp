<?php

namespace App\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StoreRepository;
use App\Repositories\UserRepository;

class RbacService
{
    private UserRepository $userRepo;
    private RoleRepository $roleRepo;
    private PermissionRepository $permRepo;
    private StoreRepository $storeRepo;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?RoleRepository $roleRepo = null,
        ?PermissionRepository $permRepo = null,
        ?StoreRepository $storeRepo = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->roleRepo = $roleRepo ?? new RoleRepository();
        $this->permRepo = $permRepo ?? new PermissionRepository();
        $this->storeRepo = $storeRepo ?? new StoreRepository();
    }

    public function userHasPermission(int $userId, string $permission): bool
    {
        // 1. Super Admin role always has all permissions
        if ($this->userHasRole($userId, 'admin') || $this->userHasRole($userId, 'Admin')) {
            return true;
        }

        // 2. Check granular permissions
        $permissions = $this->userRepo->getUserPermissions($userId);
        return in_array($permission, $permissions, true);
    }

    public function userHasRole(int $userId, string $roleNameOrSlug): bool
    {
        $roles = $this->userRepo->getUserRoles($userId);
        $search = strtolower(trim($roleNameOrSlug));

        foreach ($roles as $role) {
            $name = strtolower($role['name'] ?? '');
            $slug = strtolower($role['slug'] ?? '');
            if ($name === $search || $slug === $search) {
                return true;
            }
        }
        return false;
    }

    public function userHasStoreAccess(int $userId, int $storeId): bool
    {
        // Super Admin has access to all stores
        if ($this->userHasRole($userId, 'admin') || $this->userHasRole($userId, 'Admin')) {
            return true;
        }

        return $this->storeRepo->userHasAccess($userId, $storeId, false);
    }

    public function getUserAccessibleStores(int $userId): array
    {
        $isAdmin = $this->userHasRole($userId, 'admin') || $this->userHasRole($userId, 'Admin');
        return $this->storeRepo->listForUser($userId, $isAdmin);
    }

    public function isLastActiveAdmin(int $userId): bool
    {
        if (!$this->userRepo->isUserAdmin($userId)) {
            return false;
        }

        $activeAdmins = $this->userRepo->countActiveAdmins();
        return $activeAdmins <= 1;
    }

    public function canManageUser(int $currentUserId, int $targetUserId): bool
    {
        if ($currentUserId === $targetUserId) {
            return true;
        }

        $isCurrentAdmin = $this->userHasRole($currentUserId, 'admin') || $this->userHasRole($currentUserId, 'Admin');
        if ($isCurrentAdmin) {
            return true;
        }

        $isTargetAdmin = $this->userHasRole($targetUserId, 'admin') || $this->userHasRole($targetUserId, 'Admin');
        if ($isTargetAdmin) {
            return false; // Non-admin cannot modify an admin
        }

        return $this->userHasPermission($currentUserId, 'users.manage') || $this->userHasPermission($currentUserId, 'users.update');
    }

    public function getAllRoles(array $filters = []): array
    {
        return array_map(fn($r) => $r->toArray(), $this->roleRepo->all($filters));
    }

    public function getAllPermissionsGrouped(): array
    {
        return $this->permRepo->groupedWithMetadata();
    }
}
