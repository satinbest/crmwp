<?php

namespace App\Controllers;

use App\Integrations\WooCommerce\WebhookManager;
use App\Repositories\StoreRepository;
use App\Repositories\WebhookLogRepository;
use App\Services\WooCommerceWebhookService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class WebhookController extends BaseController
{
    private WooCommerceWebhookService $webhookService;
    private WebhookLogRepository $logRepository;
    private WebhookManager $webhookManager;
    private StoreRepository $storeRepository;

    public function __construct(
        ?WooCommerceWebhookService $webhookService = null,
        ?WebhookLogRepository $logRepository = null,
        ?WebhookManager $webhookManager = null,
        ?StoreRepository $storeRepository = null
    ) {
        $this->webhookService = $webhookService ?? new WooCommerceWebhookService();
        $this->logRepository = $logRepository ?? new WebhookLogRepository();
        $this->webhookManager = $webhookManager ?? new WebhookManager();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }

    /**
     * Public WooCommerce Webhook Receiver.
     * POST /api/v1/webhooks/woocommerce/{store}
     */
    public function handle(Request $request): Response
    {
        $storeParam = $request->param('store') ?? $request->getRouteParam('store');

        if (empty($storeParam)) {
            return Response::error('MISSING_STORE_PARAM', 'شناسه فروشگاه در مسیر مشخص نشده است.', [], 400);
        }

        $result = $this->webhookService->handleIncoming($request, $storeParam);

        return Response::json(
            $result['response'],
            $result['http_status'],
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    /**
     * List Webhook Logs (Protected).
     * GET /api/v1/webhooks
     */
    public function index(Request $request): Response
    {
        try {
            $filters = [
                'store_id' => $request->query('store_id'),
                'status' => $request->query('status', 'all'),
                'event' => $request->query('event'),
                'search' => mb_substr(trim((string)$request->query('search', '')), 0, 100),
                'page' => max(1, (int)$request->query('page', 1)),
                'per_page' => max(1, min(100, (int)$request->query('per_page', 20))),
            ];

            $result = $this->logRepository->list($filters);

            return $this->success($result['data'], $result['meta']);
        } catch (Exception $e) {
            return $this->error('WEBHOOKS_LIST_FAILED', $e->getMessage(), [], 500);
        }
    }

    /**
     * Show single Webhook Log (Protected).
     * GET /api/v1/webhooks/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $id = (int)$request->param('id');
            $log = $this->logRepository->findById($id);

            if (!$log) {
                return $this->error('NOT_FOUND', 'لاگ وب‌هوک یافت نشد.', [], 404);
            }

            return $this->success($log);
        } catch (Exception $e) {
            return $this->error('WEBHOOK_FETCH_FAILED', $e->getMessage(), [], 500);
        }
    }

    /**
     * Retry a webhook (Protected).
     * POST /api/v1/webhooks/{id}/retry
     */
    public function retry(Request $request): Response
    {
        try {
            $id = (int)$request->param('id');
            $res = $this->webhookService->retryWebhook($id);

            return $this->success($res);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('WEBHOOK_RETRY_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * Ignore a webhook (Protected).
     * POST /api/v1/webhooks/{id}/ignore
     */
    public function ignore(Request $request): Response
    {
        try {
            $id = (int)$request->param('id');
            $res = $this->webhookService->ignoreWebhook($id);

            return $this->success($res);
        } catch (Exception $e) {
            return $this->error('WEBHOOK_IGNORE_FAILED', $e->getMessage(), [], 500);
        }
    }

    /**
     * List webhooks configured in WooCommerce for a store.
     * GET /api/v1/stores/{id}/webhooks
     */
    public function storeWebhooks(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);

            $webhooks = $this->webhookManager->listWebhooks($storeId);

            return $this->success($webhooks, ['total' => count($webhooks)]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('STORE_WEBHOOKS_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * Create webhook in WooCommerce store.
     * POST /api/v1/stores/{id}/webhooks
     */
    public function createStoreWebhook(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);

            $data = [
                'name' => $request->input('name'),
                'topic' => $request->input('topic'),
                'delivery_url' => $request->input('delivery_url'),
                'status' => $request->input('status', 'active'),
            ];

            if (empty($data['topic']) || empty($data['delivery_url'])) {
                return $this->error('VALIDATION_ERROR', 'موضوع (Topic) و آدرس دریافت (Delivery URL) الزامی هستند.', [], 422);
            }

            $webhook = $this->webhookManager->createWebhook($storeId, $data);
            return $this->success($webhook, [], 201);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('CREATE_WEBHOOK_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * Delete webhook from WooCommerce store.
     * DELETE /api/v1/stores/{id}/webhooks/{webhookId}
     */
    public function deleteStoreWebhook(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);
            $webhookId = (int)$request->param('webhookId');

            $deleted = $this->webhookManager->deleteWebhook($storeId, $webhookId);
            return $this->success(['deleted' => $deleted]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('DELETE_WEBHOOK_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * Auto setup standard CRMWP webhooks in WooCommerce.
     * POST /api/v1/stores/{id}/webhooks/auto-setup
     */
    public function autoSetupWebhooks(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);
            $baseUrl = $request->input('base_url') ?? (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : 'http://127.0.0.1:8000');

            $created = $this->webhookManager->registerStandardWebhooks($storeId, $baseUrl);

            return $this->success([
                'created_count' => count($created),
                'webhooks' => $created,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('AUTO_SETUP_FAILED', $e->getMessage(), [], $status);
        }
    }
}
