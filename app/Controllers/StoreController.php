<?php

namespace App\Controllers;

use App\Services\StoreService;
use App\Support\Request;
use App\Support\Response;
use App\Integrations\WooCommerce\WooCommerceApiException;
use Exception;

class StoreController extends BaseController
{
    private StoreService $storeService;

    public function __construct(?StoreService $storeService = null)
    {
        $this->storeService = $storeService ?? new StoreService();
    }

    /**
     * GET /api/v1/stores
     * Non-admin users only receive stores they have access to.
     */
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        $userId = $user ? (int)$user->id : 0;

        $stores = $this->storeService->listStoresForUser($userId);

        return $this->success($stores, [
            'total' => count($stores),
        ]);
    }

    /**
     * GET /api/v1/stores/{id}
     */
    public function show(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        $store = $this->storeService->getStore($id);
        if (!$store) {
            return $this->error('STORE_NOT_FOUND', 'فروشگاه مورد نظر یافت نشد.', [], 404);
        }

        return $this->success($store);
    }

    /**
     * POST /api/v1/stores
     */
    public function store(Request $request): Response
    {
        $user = $this->currentUser($request);
        $data = $request->all();

        try {
            $created = $this->storeService->createStore($data, $user ? $user->id : 1);
            return $this->success($created, ['message' => 'فروشگاه با موفقیت متصل و ثبت گردید.'], 201);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            $details = json_decode($e->getMessage(), true);
            $msg = is_array($details) ? 'اطلاعات وارد شده نامعتبر است.' : $e->getMessage();
            return $this->error('VALIDATION_ERROR', $msg, is_array($details) ? $details : [], $status);
        }
    }

    /**
     * PATCH /api/v1/stores/{id}
     */
    public function update(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        $user = $this->currentUser($request);
        $data = $request->all();

        try {
            $updated = $this->storeService->updateStore($id, $data, $user ? $user->id : 1);
            return $this->success($updated, ['message' => 'اطلاعات فروشگاه با موفقیت بروزرسانی شد.']);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            $details = json_decode($e->getMessage(), true);
            $msg = is_array($details) ? 'اطلاعات وارد شده نامعتبر است.' : $e->getMessage();
            return $this->error('STORE_UPDATE_FAILED', $msg, is_array($details) ? $details : [], $status);
        }
    }

    /**
     * DELETE /api/v1/stores/{id}
     */
    public function destroy(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        $user = $this->currentUser($request);

        try {
            $this->storeService->deleteStore($id, $user ? $user->id : 1);
            return $this->success(['message' => 'فروشگاه با موفقیت از سامانه حذف شد.']);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('STORE_DELETE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/stores/{id}/crm-counts
     */
    public function crmCounts(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        try {
            $counts = $this->storeService->getStoreCrmCounts($id);
            return $this->success($counts);
        } catch (Exception $e) {
            return $this->error('CRM_COUNTS_FAILED', $e->getMessage(), [], 400);
        }
    }

    /**
     * POST /api/v1/stores/{id}/toggle-status
     */
    public function toggleStatus(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        $user = $this->currentUser($request);
        $status = (string)$request->input('status', 'active');

        try {
            $updated = $this->storeService->toggleStoreStatus($id, $status, $user ? $user->id : 1);
            return $this->success($updated, ['message' => 'وضعیت فروشگاه با موفقیت تغییر یافت.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('STATUS_TOGGLE_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/stores/test-connection (Unsaved)
     */
    public function testConnection(Request $request): Response
    {
        $url = (string)$request->input('url', '');
        $key = (string)$request->input('consumer_key', '');
        $secret = (string)$request->input('consumer_secret', '');

        if (empty($url) || empty($key) || empty($secret)) {
            return $this->error('VALIDATION_ERROR', 'آدرس فروشگاه، کلید دسترسی و رمز دسترسی الزامی هستند.', [
                'url' => empty($url) ? 'آدرس فروشگاه الزامی است.' : null,
                'consumer_key' => empty($key) ? 'Consumer Key الزامی است.' : null,
                'consumer_secret' => empty($secret) ? 'Consumer Secret الزامی است.' : null,
            ], 422);
        }

        try {
            $result = $this->storeService->testUnsavedConnection($url, $key, $secret);
            return $this->success($result);
        } catch (WooCommerceApiException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage(), $e->getDetails(), $e->getHttpStatus());
        } catch (Exception $e) {
            return $this->error('CONNECTION_TEST_FAILED', $e->getMessage(), [], 422);
        }
    }

    /**
     * POST /api/v1/stores/{id}/test (Saved)
     */
    public function testSaved(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        try {
            $result = $this->storeService->testSavedConnection($id);
            return $this->success($result);
        } catch (WooCommerceApiException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage(), $e->getDetails(), $e->getHttpStatus());
        } catch (Exception $e) {
            return $this->error('CONNECTION_TEST_FAILED', $e->getMessage(), [], 400);
        }
    }

    /**
     * GET /api/v1/stores/{id}/capabilities
     */
    public function capabilities(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        try {
            $caps = $this->storeService->getStoreCapabilities($id);
            return $this->success($caps);
        } catch (Exception $e) {
            return $this->error('CAPABILITIES_FETCH_FAILED', $e->getMessage(), [], 404);
        }
    }

    /**
     * GET /api/v1/stores/{id}/health (Section 26 & 42)
     */
    public function health(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        try {
            $health = $this->storeService->getStoreHealth($id);
            return $this->success($health);
        } catch (Exception $e) {
            return $this->error('HEALTH_FETCH_FAILED', $e->getMessage(), [], 404);
        }
    }

    /**
     * GET /api/v1/stores/{id}/users (Section 42)
     */
    public function users(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        try {
            $users = $this->storeService->listStoreUsers($id);
            return $this->success($users);
        } catch (Exception $e) {
            return $this->error('STORE_USERS_FAILED', $e->getMessage(), [], 404);
        }
    }

    /**
     * POST /api/v1/stores/{id}/users (Section 42)
     */
    public function addUser(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        $targetUserId = (int)$request->input('user_id');
        if ($targetUserId <= 0) {
            return $this->error('VALIDATION_ERROR', 'شناسه کاربر الزامی است.', [], 422);
        }

        $user = $this->currentUser($request);

        try {
            $this->storeService->addUserToStore($id, $targetUserId, $user ? $user->id : 1);
            return $this->success(['message' => 'دسترسی کاربر به فروشگاه با موفقیت برقرار شد.']);
        } catch (Exception $e) {
            return $this->error('USER_ASSIGN_FAILED', $e->getMessage(), [], 400);
        }
    }

    /**
     * DELETE /api/v1/stores/{id}/users/{userId} (Section 42)
     */
    public function removeUser(Request $request): Response
    {
        $id = (int)$request->param('id');
        $this->validateStoreAccess($request, $id);

        $targetUserId = (int)$request->param('userId');
        if ($targetUserId <= 0) {
            return $this->error('VALIDATION_ERROR', 'شناسه کاربر نامعتبر است.', [], 422);
        }

        $user = $this->currentUser($request);

        try {
            $this->storeService->removeUserFromStore($id, $targetUserId, $user ? $user->id : 1);
            return $this->success(['message' => 'دسترسی کاربر به فروشگاه با موفقیت حذف شد.']);
        } catch (Exception $e) {
            return $this->error('USER_REMOVE_FAILED', $e->getMessage(), [], 400);
        }
    }
}
