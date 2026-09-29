<?php

namespace App\Controllers;

use App\Services\AutomationService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class AutomationController extends BaseController
{
    private AutomationService $service;

    public function __construct(?AutomationService $service = null)
    {
        $this->service = $service ?? new AutomationService();
    }

    /**
     * List automations with filters and pagination.
     * GET /api/v1/automations
     */
    public function index(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);

            $filters = [
                'status' => $request->query('status', 'all'),
                'trigger_type' => $request->query('trigger_type'),
                'search' => $request->query('search'),
            ];

            $page = max(1, (int)$request->query('page', 1));
            $perPage = min(100, max(1, (int)$request->query('per_page', 20)));

            $result = $this->service->listAutomations($storeId, $filters, $page, $perPage);

            return $this->success($result['data'], $result['meta']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATIONS_LIST_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Return schema definitions for triggers, conditions, operators, actions and variables.
     * GET /api/v1/automations/schema
     */
    public function schema(Request $request): Response
    {
        try {
            $schema = $this->service->getSchema();
            return $this->success($schema);
        } catch (Exception $e) {
            return $this->error('SCHEMA_FETCH_FAILED', $e->getMessage(), [], 500);
        }
    }

    /**
     * Get single automation details.
     * GET /api/v1/automations/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $id = (int)$request->param('id');

            $automation = $this->service->getAutomation($id, $storeId);
            if (!$automation) {
                return $this->error('NOT_FOUND', 'اتوماسیون مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($automation);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATION_FETCH_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Create a new automation.
     * POST /api/v1/automations
     */
    public function store(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $userId = (int)($request->user['id'] ?? 1);

            $data = $request->all();
            $data['store_id'] = $storeId;

            $automation = $this->service->createAutomation($data, $userId, $storeId);

            return $this->success($automation, [], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATION_CREATE_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Update an automation.
     * PATCH /api/v1/automations/{id}
     */
    public function update(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $id = (int)$request->param('id');
            $userId = (int)($request->user['id'] ?? 1);

            $updated = $this->service->updateAutomation($id, $request->all(), $userId, $storeId);

            return $this->success($updated);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATION_UPDATE_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Delete an automation.
     * DELETE /api/v1/automations/{id}
     */
    public function destroy(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $id = (int)$request->param('id');
            $userId = (int)($request->user['id'] ?? 1);

            $this->service->deleteAutomation($id, $userId, $storeId);

            return $this->success(['deleted' => true]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATION_DELETE_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Enable an automation.
     * POST /api/v1/automations/{id}/enable
     */
    public function enable(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $id = (int)$request->param('id');
            $userId = (int)($request->user['id'] ?? 1);

            $updated = $this->service->setStatus($id, 'active', $userId, $storeId);

            return $this->success($updated);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATION_ENABLE_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Disable an automation.
     * POST /api/v1/automations/{id}/disable
     */
    public function disable(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $id = (int)$request->param('id');
            $userId = (int)($request->user['id'] ?? 1);

            $updated = $this->service->setStatus($id, 'inactive', $userId, $storeId);

            return $this->success($updated);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATION_DISABLE_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Dry-run test an automation without executing mutations.
     * POST /api/v1/automations/{id}/test
     */
    public function test(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $id = (int)$request->param('id');

            $samplePayload = $request->input('sample_data');
            $result = $this->service->testDryRun($id, $samplePayload, $storeId);

            return $this->success($result);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATION_TEST_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Manually trigger an automation.
     * POST /api/v1/automations/{id}/run
     */
    public function run(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $id = (int)$request->param('id');
            $userId = (int)($request->user['id'] ?? 1);

            $customData = $request->input('data');
            $result = $this->service->runManual($id, $userId, $customData, $storeId);

            return $this->success($result);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTOMATION_RUN_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * List execution runs for an automation.
     * GET /api/v1/automations/{id}/runs
     */
    public function runs(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $id = (int)$request->param('id');

            $page = max(1, (int)$request->query('page', 1));
            $perPage = min(100, max(1, (int)$request->query('per_page', 20)));

            $result = $this->service->listRuns($id, $page, $perPage, $storeId);

            return $this->success($result['data'], $result['meta']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('RUNS_LIST_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Get single run details.
     * GET /api/v1/automations/{id}/runs/{runId}
     */
    public function showRun(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $runId = (int)$request->param('runId');

            $run = $this->service->getRun($runId, $storeId);
            if (!$run) {
                return $this->error('NOT_FOUND', 'لاگ اجرای مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($run);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('RUN_FETCH_FAILED', $e->getMessage(), [], $code);
        }
    }

    /**
     * Retry a failed run.
     * POST /api/v1/automations/{id}/runs/{runId}/retry
     */
    public function retryRun(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $runId = (int)$request->param('runId');
            $userId = (int)($request->user['id'] ?? 1);

            $result = $this->service->retryRun($runId, $userId, $storeId);

            return $this->success($result);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('RUN_RETRY_FAILED', $e->getMessage(), [], $code);
        }
    }
}
