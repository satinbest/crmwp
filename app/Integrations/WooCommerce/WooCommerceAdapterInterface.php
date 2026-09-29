<?php

namespace App\Integrations\WooCommerce;

interface WooCommerceAdapterInterface
{
    public function getStoreId(): int;

    public function testConnection(): array;

    public function detectCapabilities(): array;

    // Customers
    public function listCustomers(array $params = []): array;

    public function getCustomer(int $id): ?array;

    public function getCustomerOrders(int $customerId, array $params = []): array;

    // Orders
    public function listOrders(array $params = []): array;

    public function getOrder(int $id): ?array;

    public function updateOrderStatus(int $id, string $status): array;

    public function addOrderNote(int $id, string $note, bool $isCustomerNote = false): array;

    public function refundOrder(int $id, array $data): array;

    // Products
    public function listProducts(array $params = []): array;

    public function getProduct(int $id): ?array;

    public function createProduct(array $data): array;

    public function updateProduct(int $id, array $data): array;

    public function deleteProduct(int $id, bool $force = false): array;

    public function listVariations(int $productId, array $params = []): array;

    public function getVariation(int $productId, int $variationId): ?array;

    public function updateVariation(int $productId, int $variationId, array $data): array;

    // Inventory
    public function getInventoryMetrics(): array;

    public function getLowStockProducts(int $limit = 20): array;

    public function getOutOfStockProducts(int $limit = 20): array;

    public function updateStock(int $productId, int $quantity, ?string $stockStatus = null): array;

    // Taxonomies
    public function getCategories(array $params = []): array;

    public function getTags(array $params = []): array;

    public function getAttributes(): array;

    // Webhooks
    public function listWebhooks(array $params = []): array;

    public function createWebhook(array $data): array;

    public function deleteWebhook(int $webhookId): bool;
}
