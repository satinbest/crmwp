<?php

namespace App\Controllers;

use App\Services\RbacService;
use App\Services\UserService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class UserController extends BaseController
{
    private UserService $userService;
    private RbacService $rbacService;

    public function __construct(?UserService $userService = null, ?RbacService $rbacService = null)
    {
        $this->userService = $userService ?? new UserService();
        $this->rbacService = $rbacService ?? new RbacService();
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
     * GET /api/v1/users
     */
    public function index(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'users.view');

            $page = max(1, (int)$request->query('page', 1));
            $perPage = min(100, max(1, (int)$request->query('per_page', 25)));

            $filters = [];
            if ($request->has('search') || $request->query('search')) {
                $filters['search'] = $request->query('search') ?? $request->input('search');
            }
            if ($request->has('status') || $request->query('status')) {
                $filters['status'] = $request->query('status') ?? $request->input('status');
            }
            if ($request->has('role_id') || $request->query('role_id')) {
                $filters['role_id'] = (int)($request->query('role_id') ?? $request->input('role_id'));
            }
            if ($request->has('store_id') || $request->query('store_id')) {
                $filters['store_id'] = (int)($request->query('store_id') ?? $request->input('store_id'));
            }

            $res = $this->userService->listUsers($filters, $page, $perPage);
            return $this->success($res['users'], $res['meta']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('USERS_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/users/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'users.view');
            $id = (int)$request->param('id');

            $user = $this->userService->getUser($id);
            if (!$user) {
                return $this->error('NOT_FOUND', 'کاربر مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($user);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('USERS_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/users
     */
    public function store(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'users.create');

            $data = [
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'confirm_password' => $request->input('confirm_password') ?? $request->input('password_confirmation'),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'avatar' => $request->input('avatar'),
                'is_active' => $request->input('is_active', true),
                'roles' => $request->input('roles', []),
                'stores' => $request->input('stores', []),
            ];

            $created = $this->userService->createUser(
                $data,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($created, [], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('USER_CREATE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/users/{id}
     */
    public function update(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'users.update');
            $id = (int)$request->param('id');

            $data = [];
            if ($request->has('username')) $data['username'] = $request->input('username');
            if ($request->has('email')) $data['email'] = $request->input('email');
            if ($request->has('first_name')) $data['first_name'] = $request->input('first_name');
            if ($request->has('last_name')) $data['last_name'] = $request->input('last_name');
            if ($request->has('avatar')) $data['avatar'] = $request->input('avatar');
            if ($request->has('is_active')) $data['is_active'] = $request->input('is_active');
            if ($request->has('status')) $data['is_active'] = ($request->input('status') === 'active');
            if ($request->has('password') && !empty($request->input('password'))) {
                $data['password'] = $request->input('password');
            }
            if ($request->has('roles')) $data['roles'] = $request->input('roles');
            if ($request->has('stores')) $data['stores'] = $request->input('stores');

            $updated = $this->userService->updateUser(
                $id,
                $data,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            if (!$updated) {
                return $this->error('NOT_FOUND', 'کاربر مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($updated);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('USER_UPDATE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * DELETE /api/v1/users/{id}
     */
    public function destroy(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'users.delete');
            $id = (int)$request->param('id');

            $deleted = $this->userService->deleteUser(
                $id,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            if (!$deleted) {
                return $this->error('NOT_FOUND', 'کاربر مورد نظر یافت نشد.', [], 404);
            }

            return $this->success(['message' => 'کاربر با موفقیت حذف شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('USER_DELETE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/users/{id}/activate
     */
    public function activate(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'users.manage');
            $id = (int)$request->param('id');

            $this->userService->activateUser(
                $id,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success(['message' => 'حساب کاربر با موفقیت فعال شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('USER_STATUS_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/users/{id}/deactivate
     */
    public function deactivate(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'users.manage');
            $id = (int)$request->param('id');

            $this->userService->deactivateUser(
                $id,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success(['message' => 'حساب کاربر با موفقیت غیرفعال شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('USER_STATUS_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/users/{id}/reset-password
     */
    public function resetPassword(Request $request): Response
    {
        try {
            $actorId = $this->authorizePermission($request, 'users.manage');
            $id = (int)$request->param('id');
            $newPassword = (string)$request->input('password');

            if (empty($newPassword)) {
                return $this->error('VALIDATION_ERROR', 'رمز عبور جدید نمی‌تواند خالی باشد.', [], 422);
            }

            $this->userService->resetPassword(
                $id,
                $newPassword,
                $actorId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success(['message' => 'رمز عبور کاربر با موفقیت بازنشانی شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PASSWORD_RESET_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/users/{id}/activity
     */
    public function activity(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'users.view');
            $id = (int)$request->param('id');

            $activities = $this->userService->getUserRecentActivity($id, 30);
            return $this->success($activities);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ACTIVITY_ERROR', $e->getMessage(), [], $code);
        }
    }
}
