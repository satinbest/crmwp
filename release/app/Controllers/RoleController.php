<?php

namespace App\Controllers;

use App\Repositories\PermissionRepository;
use App\Services\RbacService;
use App\Services\RoleService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class RoleController extends BaseController
{
    private RoleService $roleService;
    private RbacService $rbacService;
    private PermissionRepository $permRepo;

    public function __construct(
        ?RoleService $roleService = null,
        ?RbacService $rbacService = null,
        ?PermissionRepository $permRepo = null
    ) {
        $this->roleService = $roleService ?? new RoleService();
        $this->rbacService = $rbacService ?? new RbacService();
        $this->permRepo = $permRepo ?? new PermissionRepository();
    }

    private function authorizePermission(Request $request, string $permission): int
    {
        $user = $this->currentUser($request);
        if (!$user) {
            throw new Exception("احراز هویت الزامی است.", 401);
        }

        $userId = (int)$user->id;
        if (!$this->rbacService->userHasPermission($userId, $permission)) {
            throw new Exception("شما مجوز دسترسی لازم ({$permission}) را ندارید.", 403);
        }

        return $userId;
    }

    /**
     * GET /api/v1/roles
     */
    public function index(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'roles.view');

            $filters = [];
            if ($request->has('search') || $request->query('search')) {
                $filters['search'] = $request->query('search') ?? $request->input('search');
            }
            if ($request->has('status') || $request->query('status')) {
                $filters['status'] = $request->query('status') ?? $request->input('status');
            }

            $roles = $this->roleService->listRoles($filters);
            return $this->success($roles);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ROLES_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/roles/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'roles.view');
            $id = (int)$request->param('id');

            $role = $this->roleService->getRole($id);
            if (!$role) {
                return $this->error('NOT_FOUND', 'نقش مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($role);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ROLES_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/roles
     */
    public function store(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'roles.create');

            $data = [
                'name' => $request->input('name'),
                'display_name' => $request->input('display_name'),
                'slug' => $request->input('slug'),
                'description' => $request->input('description'),
                'status' => $request->input('status', 'active'),
                'permissions' => $request->input('permissions', []),
            ];

            $created = $this->roleService->createRole(
                $data,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($created, [], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ROLE_CREATE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/roles/{id}
     */
    public function update(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'roles.update');
            $id = (int)$request->param('id');

            $data = [];
            if ($request->has('name')) $data['name'] = $request->input('name');
            if ($request->has('display_name')) $data['display_name'] = $request->input('display_name');
            if ($request->has('slug')) $data['slug'] = $request->input('slug');
            if ($request->has('description')) $data['description'] = $request->input('description');
            if ($request->has('status')) $data['status'] = $request->input('status');
            if ($request->has('permissions')) $data['permissions'] = $request->input('permissions');

            $updated = $this->roleService->updateRole(
                $id,
                $data,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            if (!$updated) {
                return $this->error('NOT_FOUND', 'نقش مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($updated);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ROLE_UPDATE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * DELETE /api/v1/roles/{id}
     */
    public function destroy(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'roles.delete');
            $id = (int)$request->param('id');

            $deleted = $this->roleService->deleteRole(
                $id,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            if (!$deleted) {
                return $this->error('NOT_FOUND', 'نقش مورد نظر یافت نشد.', [], 404);
            }

            return $this->success(['message' => 'نقش با موفقیت حذف شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ROLE_DELETE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/roles/{id}/duplicate
     */
    public function duplicate(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'roles.create');
            $id = (int)$request->param('id');

            $newName = (string)$request->input('name', '');
            $newSlug = (string)$request->input('slug', '');

            if (empty($newName)) {
                $orig = $this->roleService->getRole($id);
                $newName = ($orig['display_name'] ?? 'نقش') . ' (کپی)';
            }

            $duplicated = $this->roleService->duplicateRole(
                $id,
                $newName,
                $newSlug ?: null,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($duplicated, [], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ROLE_DUPLICATE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/roles/{id}/permissions
     */
    public function permissions(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'roles.view');
            $id = (int)$request->param('id');

            $perms = $this->roleService->getRolePermissions($id);
            return $this->success($perms);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ROLES_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PUT /api/v1/roles/{id}/permissions
     */
    public function syncPermissions(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'roles.manage');
            $id = (int)$request->param('id');
            $permissions = $request->input('permissions', []);

            if (!is_array($permissions)) {
                return $this->error('VALIDATION_ERROR', 'فهرست مجوزها باید آرایه باشد.', [], 422);
            }

            $synced = $this->roleService->syncRolePermissions(
                $id,
                $permissions,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($synced);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ROLES_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/permissions
     */
    public function allPermissions(Request $request): Response
    {
        try {
            // Accessible to anyone with roles.view or users.view
            $user = $this->currentUser($request);
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            $userId = (int)$user->id;
            if (!$this->rbacService->userHasPermission($userId, 'roles.view') &&
                !$this->rbacService->userHasPermission($userId, 'users.view')) {
                return $this->error('FORBIDDEN', 'شما مجوز دسترسی لازم را ندارید.', [], 403);
            }

            $grouped = $this->permRepo->groupedWithMetadata();
            return $this->success($grouped);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PERMISSIONS_ERROR', $e->getMessage(), [], $code);
        }
    }
}
