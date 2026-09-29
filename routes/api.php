<?php

use App\Controllers\AuthController;
use App\Controllers\AutomationController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ReconciliationController;
use App\Controllers\RoleController;
use App\Controllers\StoreController;
use App\Controllers\SystemController;
use App\Controllers\UserController;
use App\Controllers\WebhookController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Support\Router;

/** @var Router $router */

$router->group(['prefix' => '/api/v1'], function (Router $api) {
    // Health, Liveness and Readiness (Phase 16)
    $api->get('/health', [SystemController::class, 'health']);
    $api->get('/health/liveness', [SystemController::class, 'liveness']);
    $api->get('/health/ready', [SystemController::class, 'readiness']);
    $api->get('/system/health', [SystemController::class, 'health']);

    // Installer Endpoints (Phase 17)
    $api->get('/install/check', [\App\Controllers\InstallController::class, 'check']);
    $api->post('/install/database', [\App\Controllers\InstallController::class, 'testDatabase']);
    $api->post('/install/setup', [\App\Controllers\InstallController::class, 'setup']);

    // Authentication & CSRF
    $api->get('/auth/csrf', [AuthController::class, 'csrf']);
    $api->post('/auth/login', [AuthController::class, 'login'], [
        new RateLimitMiddleware(5, 60),
    ]);
    $api->get('/auth/me', [AuthController::class, 'me'], [
        AuthMiddleware::class,
    ]);
    $api->post('/auth/logout', [AuthController::class, 'logout'], [
        AuthMiddleware::class,
    ]);

    // Self Profile & Permissions (Phase 11)
    $api->get('/me/profile', [ProfileController::class, 'me'], [AuthMiddleware::class]);
    $api->patch('/me/profile', [ProfileController::class, 'updateProfile'], [AuthMiddleware::class]);
    $api->post('/me/password', [ProfileController::class, 'changePassword'], [AuthMiddleware::class]);
    $api->post('/me/avatar', [ProfileController::class, 'uploadAvatar'], [AuthMiddleware::class]);
    $api->get('/me/permissions', [ProfileController::class, 'myPermissions'], [AuthMiddleware::class]);
    $api->get('/me/stores', [ProfileController::class, 'myStores'], [AuthMiddleware::class]);

    // Notifications & Activity Center (Phase 12)
    // Note: static sub-paths are registered before {id} wildcard
    $api->get('/notifications', [NotificationController::class, 'index'], [AuthMiddleware::class]);
    $api->get('/notifications/unread-count', [NotificationController::class, 'unreadCount'], [AuthMiddleware::class]);
    $api->get('/notifications/preferences', [NotificationController::class, 'getPreferences'], [AuthMiddleware::class]);
    $api->put('/notifications/preferences', [NotificationController::class, 'updatePreferences'], [AuthMiddleware::class]);
    $api->post('/notifications/read-all', [NotificationController::class, 'readAll'], [AuthMiddleware::class]);
    $api->post('/notifications/test-generate', [NotificationController::class, 'testGenerate'], [AuthMiddleware::class]);
    $api->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], [AuthMiddleware::class]);
    $api->delete('/notifications/{id}', [NotificationController::class, 'destroy'], [AuthMiddleware::class]);

    // Users Management (Phase 11)
    $api->get('/users', [UserController::class, 'index'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.view'),
    ]);
    $api->post('/users', [UserController::class, 'store'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.create'),
    ]);
    $api->get('/users/{id}', [UserController::class, 'show'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.view'),
    ]);
    $api->patch('/users/{id}', [UserController::class, 'update'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.update'),
    ]);
    $api->delete('/users/{id}', [UserController::class, 'destroy'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.delete'),
    ]);
    $api->post('/users/{id}/activate', [UserController::class, 'activate'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.manage'),
    ]);
    $api->post('/users/{id}/deactivate', [UserController::class, 'deactivate'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.manage'),
    ]);
    $api->post('/users/{id}/reset-password', [UserController::class, 'resetPassword'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.manage'),
    ]);
    $api->get('/users/{id}/activity', [UserController::class, 'activity'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('users.view'),
    ]);

    // Roles & Permissions Management (Phase 11)
    $api->get('/roles', [RoleController::class, 'index'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('roles.view'),
    ]);
    $api->post('/roles', [RoleController::class, 'store'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('roles.create'),
    ]);
    $api->get('/roles/{id}', [RoleController::class, 'show'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('roles.view'),
    ]);
    $api->patch('/roles/{id}', [RoleController::class, 'update'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('roles.update'),
    ]);
    $api->delete('/roles/{id}', [RoleController::class, 'destroy'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('roles.delete'),
    ]);
    $api->post('/roles/{id}/duplicate', [RoleController::class, 'duplicate'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('roles.create'),
    ]);
    $api->get('/roles/{id}/permissions', [RoleController::class, 'permissions'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('roles.view'),
    ]);
    $api->put('/roles/{id}/permissions', [RoleController::class, 'syncPermissions'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('roles.manage'),
    ]);

    $api->get('/permissions', [RoleController::class, 'allPermissions'], [
        AuthMiddleware::class,
    ]);

    // Stores Management & Connection Core (Phase 2)
    // Note: static sub-paths registered before wildcard {id}
    $api->post('/stores/test-connection', [StoreController::class, 'testConnection'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.manage'),
    ]);

    $api->get('/stores', [StoreController::class, 'index'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.view'),
    ]);

    $api->post('/stores', [StoreController::class, 'store'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.manage'),
    ]);

    $api->get('/stores/{id}', [StoreController::class, 'show'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.view'),
    ]);

    $api->patch('/stores/{id}', [StoreController::class, 'update'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.manage'),
    ]);

    $api->delete('/stores/{id}', [StoreController::class, 'destroy'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.manage'),
    ]);

    $api->post('/stores/{id}/test', [StoreController::class, 'testSaved'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.manage'),
    ]);

    $api->get('/stores/{id}/capabilities', [StoreController::class, 'capabilities'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.view'),
    ]);

    $api->get('/stores/{id}/health', [StoreController::class, 'health'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.view'),
    ]);

    $api->get('/stores/{id}/crm-counts', [StoreController::class, 'crmCounts'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.view'),
    ]);

    $api->post('/stores/{id}/toggle-status', [StoreController::class, 'toggleStatus'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.manage'),
    ]);

    $api->get('/stores/{id}/users', [StoreController::class, 'users'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.view'),
    ]);

    $api->post('/stores/{id}/users', [StoreController::class, 'addUser'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.manage'),
    ]);

    $api->delete('/stores/{id}/users/{userId}', [StoreController::class, 'removeUser'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('stores.manage'),
    ]);

    // WooCommerce Webhooks & Store Health (Phase 13)
    $api->post('/stores/{id}/webhooks/auto-setup', [WebhookController::class, 'autoSetupWebhooks'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.manage'),
    ]);
    $api->get('/stores/{id}/webhooks', [WebhookController::class, 'storeWebhooks'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.view'),
    ]);
    $api->post('/stores/{id}/webhooks', [WebhookController::class, 'createStoreWebhook'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.manage'),
    ]);
    $api->delete('/stores/{id}/webhooks/{webhookId}', [WebhookController::class, 'deleteStoreWebhook'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.manage'),
    ]);

    // Store Reconciliation & Health (Phase 13)
    $api->post('/stores/{id}/reconcile', [ReconciliationController::class, 'reconcile'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.reconcile'),
    ]);
    $api->get('/stores/{id}/sync-logs', [ReconciliationController::class, 'syncLogs'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('sync.view'),
    ]);
    $api->get('/stores/{id}/webhook-health', [ReconciliationController::class, 'webhookHealth'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.view'),
    ]);

    // Webhooks Management & Logs (Phase 13)
    $api->get('/webhooks', [WebhookController::class, 'index'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.view'),
    ]);
    $api->get('/webhooks/{id}', [WebhookController::class, 'show'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.view'),
    ]);
    $api->post('/webhooks/{id}/retry', [WebhookController::class, 'retry'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.retry'),
    ]);
    $api->post('/webhooks/{id}/ignore', [WebhookController::class, 'ignore'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('webhooks.manage'),
    ]);

    // Public Webhook Receiver Endpoint (No Session/Auth required)
    $api->post('/webhooks/woocommerce/{store}', [WebhookController::class, 'handle']);

    // Customers Module (Phase 4)
    // Sub-resources first
    $api->get('/customers/{id}/orders', [\App\Controllers\CustomerController::class, 'orders'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('customers.view'),
    ]);

    $api->get('/customers/{id}/activities', [\App\Controllers\CustomerController::class, 'activities'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('customers.view'),
    ]);

    $api->get('/customers/{id}/notes', [\App\Controllers\CustomerController::class, 'notes'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('customers.view'),
    ]);

    $api->post('/customers/{id}/notes', [\App\Controllers\CustomerController::class, 'createNote'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('crm.manage'),
    ]);

    $api->patch('/customers/{id}/notes/{noteId}', [\App\Controllers\CustomerController::class, 'updateNote'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('crm.manage'),
    ]);

    $api->delete('/customers/{id}/notes/{noteId}', [\App\Controllers\CustomerController::class, 'deleteNote'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('crm.manage'),
    ]);

    $api->get('/customers/{id}/tags', [\App\Controllers\CustomerController::class, 'tags'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('customers.view'),
    ]);

    $api->post('/customers/{id}/tags', [\App\Controllers\CustomerController::class, 'addTag'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('crm.manage'),
    ]);

    $api->delete('/customers/{id}/tags/{tagId}', [\App\Controllers\CustomerController::class, 'removeTag'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('crm.manage'),
    ]);

    $api->get('/customers/{id}/tasks', [\App\Controllers\CustomerController::class, 'tasks'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('customers.view'),
    ]);

    $api->post('/customers/{id}/tasks', [\App\Controllers\CustomerController::class, 'createTask'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('crm.manage'),
    ]);

    $api->patch('/customers/{id}/tasks/{taskId}', [\App\Controllers\CustomerController::class, 'updateTask'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('crm.manage'),
    ]);

    // Main customer resources
    $api->get('/customers', [\App\Controllers\CustomerController::class, 'index'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('customers.view'),
    ]);

    $api->get('/customers/{id}', [\App\Controllers\CustomerController::class, 'show'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('customers.view'),
    ]);

    // Orders Module (Phase 5)
    // Statuses helper
    $api->get('/orders/statuses', [\App\Controllers\OrderController::class, 'statuses'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.view'),
    ]);

    // Sub-resources first
    $api->get('/orders/{id}/notes', [\App\Controllers\OrderController::class, 'notes'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.view'),
    ]);

    $api->post('/orders/{id}/notes', [\App\Controllers\OrderController::class, 'createNote'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.edit'),
    ]);

    $api->patch('/orders/{id}/status', [\App\Controllers\OrderController::class, 'updateStatus'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.edit'),
    ]);

    $api->post('/orders/{id}/refund', [\App\Controllers\OrderController::class, 'refund'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.refund'),
    ]);

    $api->get('/orders/{id}/activities', [\App\Controllers\OrderController::class, 'activities'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.view'),
    ]);

    $api->get('/orders/{id}/tasks', [\App\Controllers\OrderController::class, 'tasks'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.view'),
    ]);

    $api->post('/orders/{id}/tasks', [\App\Controllers\OrderController::class, 'createTask'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.edit'),
    ]);

    // Main orders resources
    $api->get('/orders', [\App\Controllers\OrderController::class, 'index'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.view'),
    ]);

    $api->get('/orders/{id}', [\App\Controllers\OrderController::class, 'show'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('orders.view'),
    ]);

    // Products Module (Phase 6)
    // Taxonomies
    $api->get('/product-categories', [\App\Controllers\ProductController::class, 'categories'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.view'),
    ]);

    $api->get('/product-tags', [\App\Controllers\ProductController::class, 'tags'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.view'),
    ]);

    $api->get('/product-attributes', [\App\Controllers\ProductController::class, 'attributes'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.view'),
    ]);

    // Product Variations
    $api->get('/products/{id}/variations', [\App\Controllers\ProductController::class, 'variations'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.view'),
    ]);

    $api->post('/products/{id}/variations', [\App\Controllers\ProductController::class, 'storeVariation'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.create'),
    ]);

    $api->get('/products/{id}/variations/{variationId}', [\App\Controllers\ProductController::class, 'showVariation'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.view'),
    ]);

    $api->patch('/products/{id}/variations/{variationId}', [\App\Controllers\ProductController::class, 'updateVariation'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.edit'),
    ]);

    $api->delete('/products/{id}/variations/{variationId}', [\App\Controllers\ProductController::class, 'destroyVariation'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.delete'),
    ]);

    // Main Product Resources
    $api->get('/products', [\App\Controllers\ProductController::class, 'index'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.view'),
    ]);

    $api->post('/products', [\App\Controllers\ProductController::class, 'store'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.create'),
    ]);

    $api->get('/products/{id}', [\App\Controllers\ProductController::class, 'show'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.view'),
    ]);

    $api->patch('/products/{id}', [\App\Controllers\ProductController::class, 'update'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.edit'),
    ]);

    $api->delete('/products/{id}', [\App\Controllers\ProductController::class, 'destroy'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('products.delete'),
    ]);

    // Bulk Operations Engine (Phase 7)
    $api->post('/bulk-operations/preview', [\App\Controllers\BulkOperationController::class, 'preview'], [
        AuthMiddleware::class,
    ]);

    $api->post('/bulk-operations', [\App\Controllers\BulkOperationController::class, 'store'], [
        AuthMiddleware::class,
    ]);

    $api->get('/bulk-operations', [\App\Controllers\BulkOperationController::class, 'index'], [
        AuthMiddleware::class,
    ]);

    $api->get('/bulk-operations/{id}', [\App\Controllers\BulkOperationController::class, 'show'], [
        AuthMiddleware::class,
    ]);

    $api->post('/bulk-operations/{id}/cancel', [\App\Controllers\BulkOperationController::class, 'cancel'], [
        AuthMiddleware::class,
    ]);

    // Inventory Management (Phase 8)
    $api->get('/inventory/metrics', [\App\Controllers\InventoryController::class, 'metrics'], [
        AuthMiddleware::class,
    ]);

    $api->get('/inventory/low-stock', [\App\Controllers\InventoryController::class, 'lowStock'], [
        AuthMiddleware::class,
    ]);

    $api->get('/inventory/out-of-stock', [\App\Controllers\InventoryController::class, 'outOfStock'], [
        AuthMiddleware::class,
    ]);

    $api->get('/inventory/{productId}/variations/{variationId}', [\App\Controllers\InventoryController::class, 'showVariation'], [
        AuthMiddleware::class,
    ]);

    $api->patch('/inventory/{productId}/variations/{variationId}', [\App\Controllers\InventoryController::class, 'updateVariation'], [
        AuthMiddleware::class,
    ]);

    $api->patch('/inventory/{productId}/stock', [\App\Controllers\InventoryController::class, 'updateStock'], [
        AuthMiddleware::class,
    ]);

    $api->patch('/inventory/{productId}/status', [\App\Controllers\InventoryController::class, 'updateStatus'], [
        AuthMiddleware::class,
    ]);

    $api->patch('/inventory/{productId}', [\App\Controllers\InventoryController::class, 'updateConfig'], [
        AuthMiddleware::class,
    ]);

    $api->get('/inventory/{productId}', [\App\Controllers\InventoryController::class, 'show'], [
        AuthMiddleware::class,
    ]);

    $api->get('/inventory', [\App\Controllers\InventoryController::class, 'index'], [
        AuthMiddleware::class,
    ]);

    // CRM Workspace (Phase 9)
    $api->get('/crm/summary', [\App\Controllers\CrmController::class, 'summary'], [AuthMiddleware::class]);
    $api->get('/crm/search', [\App\Controllers\CrmController::class, 'search'], [AuthMiddleware::class]);
    $api->get('/crm', [\App\Controllers\CrmController::class, 'summary'], [AuthMiddleware::class]);

    // Segments
    $api->post('/segments/preview', [\App\Controllers\SegmentController::class, 'preview'], [AuthMiddleware::class]);
    $api->get('/segments/{id}/customers', [\App\Controllers\SegmentController::class, 'customers'], [AuthMiddleware::class]);
    $api->get('/segments/{id}', [\App\Controllers\SegmentController::class, 'show'], [AuthMiddleware::class]);
    $api->patch('/segments/{id}', [\App\Controllers\SegmentController::class, 'update'], [AuthMiddleware::class]);
    $api->delete('/segments/{id}', [\App\Controllers\SegmentController::class, 'destroy'], [AuthMiddleware::class]);
    $api->get('/segments', [\App\Controllers\SegmentController::class, 'index'], [AuthMiddleware::class]);
    $api->post('/segments', [\App\Controllers\SegmentController::class, 'store'], [AuthMiddleware::class]);

    // Tags
    $api->get('/tags', [\App\Controllers\TagController::class, 'index'], [AuthMiddleware::class]);
    $api->post('/tags', [\App\Controllers\TagController::class, 'store'], [AuthMiddleware::class]);
    $api->patch('/tags/{id}', [\App\Controllers\TagController::class, 'update'], [AuthMiddleware::class]);
    $api->delete('/tags/{id}', [\App\Controllers\TagController::class, 'destroy'], [AuthMiddleware::class]);

    // Tasks
    $api->post('/tasks/{id}/complete', [\App\Controllers\TaskController::class, 'complete'], [AuthMiddleware::class]);
    $api->post('/tasks/{id}/reopen', [\App\Controllers\TaskController::class, 'reopen'], [AuthMiddleware::class]);
    $api->get('/tasks/{id}', [\App\Controllers\TaskController::class, 'show'], [AuthMiddleware::class]);
    $api->patch('/tasks/{id}', [\App\Controllers\TaskController::class, 'update'], [AuthMiddleware::class]);
    $api->delete('/tasks/{id}', [\App\Controllers\TaskController::class, 'destroy'], [AuthMiddleware::class]);
    $api->get('/tasks', [\App\Controllers\TaskController::class, 'index'], [AuthMiddleware::class]);
    $api->post('/tasks', [\App\Controllers\TaskController::class, 'store'], [AuthMiddleware::class]);

    // Activities
    $api->get('/activities/{id}', [\App\Controllers\ActivityController::class, 'show'], [AuthMiddleware::class]);
    $api->get('/activities', [\App\Controllers\ActivityController::class, 'index'], [AuthMiddleware::class]);

    // Automation & Workflow Engine (Phase 15)
    // Note: /automations/schema is placed before /automations/{id}
    $api->get('/automations/schema', [AutomationController::class, 'schema'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.view'),
    ]);
    $api->get('/automations', [AutomationController::class, 'index'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.view'),
    ]);
    $api->post('/automations', [AutomationController::class, 'store'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.create'),
    ]);
    $api->get('/automations/{id}', [AutomationController::class, 'show'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.view'),
    ]);
    $api->patch('/automations/{id}', [AutomationController::class, 'update'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.update'),
    ]);
    $api->delete('/automations/{id}', [AutomationController::class, 'destroy'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.delete'),
    ]);
    $api->post('/automations/{id}/enable', [AutomationController::class, 'enable'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.enable'),
    ]);
    $api->post('/automations/{id}/disable', [AutomationController::class, 'disable'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.disable'),
    ]);
    $api->post('/automations/{id}/test', [AutomationController::class, 'test'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.view'),
    ]);
    $api->post('/automations/{id}/run', [AutomationController::class, 'run'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.run'),
    ]);
    $api->get('/automations/{id}/runs', [AutomationController::class, 'runs'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.view_runs'),
    ]);
    $api->get('/automations/{id}/runs/{runId}', [AutomationController::class, 'showRun'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.view_runs'),
    ]);
    $api->post('/automations/{id}/runs/{runId}/retry', [AutomationController::class, 'retryRun'], [
        AuthMiddleware::class,
        PermissionMiddleware::for('automations.run'),
    ]);
});


