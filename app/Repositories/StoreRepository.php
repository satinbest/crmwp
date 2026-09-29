<?php

namespace App\Repositories;

use App\Database\Connection;
use App\Models\Store;
use App\Support\Security;
use PDO;

class StoreRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `stores` ORDER BY `id` ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => Store::fromArray($row), $rows);
    }

    public function findById(int $id): ?Store
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `stores` WHERE `id` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Store::fromArray($row) : null;
    }

    public function findByName(string $name): ?Store
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `stores` WHERE `name` = ? LIMIT 1");
        $stmt->execute([trim($name)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Store::fromArray($row) : null;
    }

    public function create(array $data): Store
    {
        $consumerKeyEncrypted = Security::encrypt($data['consumer_key'] ?? '');
        $consumerSecretEncrypted = Security::encrypt($data['consumer_secret'] ?? '');
        $webhookSecretEncrypted = !empty($data['webhook_secret']) ? Security::encrypt($data['webhook_secret']) : null;

        $wcVersion = $data['woocommerce_version'] ?? ($data['wc_version'] ?? null);
        $wpVersion = $data['wordpress_version'] ?? ($data['wp_version'] ?? null);

        $stmt = $this->pdo->prepare("
            INSERT INTO `stores` (
                `name`, `icon`, `url`, `consumer_key_encrypted`, `consumer_secret_encrypted`, `webhook_secret_encrypted`,
                `status`, `is_demo`, `wc_version`, `wp_version`, `woocommerce_version`, `wordpress_version`,
                `hpos_enabled`, `currency`, `timezone`, `capabilities`, `last_connection_check`, `created_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $data['name'],
            $data['icon'] ?? null,
            rtrim($data['url'], '/'),
            $consumerKeyEncrypted,
            $consumerSecretEncrypted,
            $webhookSecretEncrypted,
            $data['status'] ?? 'inactive',
            (int)($data['is_demo'] ?? 0),
            $wcVersion,
            $wpVersion,
            $wcVersion,
            $wpVersion,
            (int)($data['hpos_enabled'] ?? 0),
            $data['currency'] ?? 'IRR',
            $data['timezone'] ?? 'Asia/Tehran',
            isset($data['capabilities']) ? json_encode($data['capabilities'], JSON_UNESCAPED_UNICODE) : null,
            $data['last_connection_check'] ?? null,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, array $data): ?Store
    {
        $fields = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $fields[] = "`name` = ?";
            $params[] = $data['name'];
        }

        if (array_key_exists('icon', $data)) {
            $fields[] = "`icon` = ?";
            $params[] = $data['icon'];
        }

        if (array_key_exists('url', $data)) {
            $fields[] = "`url` = ?";
            $params[] = rtrim($data['url'], '/');
        }

        if (!empty($data['consumer_key'])) {
            $fields[] = "`consumer_key_encrypted` = ?";
            $params[] = Security::encrypt($data['consumer_key']);
        }

        if (!empty($data['consumer_secret'])) {
            $fields[] = "`consumer_secret_encrypted` = ?";
            $params[] = Security::encrypt($data['consumer_secret']);
        }

        if (array_key_exists('webhook_secret', $data)) {
            $fields[] = "`webhook_secret_encrypted` = ?";
            $params[] = !empty($data['webhook_secret']) ? Security::encrypt($data['webhook_secret']) : null;
        }

        if (array_key_exists('status', $data)) {
            $fields[] = "`status` = ?";
            $params[] = $data['status'];
        }

        if (array_key_exists('is_demo', $data)) {
            $fields[] = "`is_demo` = ?";
            $params[] = (int)(bool)$data['is_demo'];
        }

        if (array_key_exists('currency', $data)) {
            $fields[] = "`currency` = ?";
            $params[] = $data['currency'];
        }

        if (array_key_exists('timezone', $data)) {
            $fields[] = "`timezone` = ?";
            $params[] = $data['timezone'];
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $params[] = $id;
        $sql = "UPDATE `stores` SET " . implode(', ', $fields) . " WHERE `id` = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function updateConnectionCheck(
        int $id,
        string $status,
        ?string $wcVersion,
        ?string $wpVersion,
        bool $hpos,
        ?array $capabilities,
        ?string $error = null
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE `stores`
            SET `status` = ?,
                `wc_version` = ?,
                `wp_version` = ?,
                `woocommerce_version` = ?,
                `wordpress_version` = ?,
                `hpos_enabled` = ?,
                `capabilities` = ?,
                `last_connection_check` = NOW(),
                `last_error` = ?
            WHERE `id` = ?
        ");

        $stmt->execute([
            $status,
            $wcVersion,
            $wpVersion,
            $wcVersion,
            $wpVersion,
            $hpos ? 1 : 0,
            $capabilities ? json_encode($capabilities, JSON_UNESCAPED_UNICODE) : null,
            $error,
            $id,
        ]);
    }

    public function countActiveStores(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM `stores` WHERE `status` = 'active'");
        return (int)$stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `stores` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    public function listForUser(int $userId, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            return $this->all();
        }

        $stmt = $this->pdo->prepare("
            SELECT s.*
            FROM `stores` s
            JOIN `user_stores` us ON s.id = us.store_id
            WHERE us.user_id = ?
            ORDER BY s.id ASC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => Store::fromArray($row), $rows);
    }

    public function userHasAccess(int $userId, int $storeId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return true;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM `user_stores`
            WHERE `user_id` = ? AND `store_id` = ?
        ");
        $stmt->execute([$userId, $storeId]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function getStoreUsers(int $storeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.avatar, u.is_active
            FROM `users` u
            JOIN `user_stores` us ON u.id = us.user_id
            WHERE us.store_id = ?
            ORDER BY u.id ASC
        ");
        $stmt->execute([$storeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignUserToStore(int $storeId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `user_stores` (`user_id`, `store_id`) VALUES (?, ?)");
        return $stmt->execute([$userId, $storeId]);
    }

    public function removeUserFromStore(int $storeId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `user_stores` WHERE `store_id` = ? AND `user_id` = ?");
        return $stmt->execute([$storeId, $userId]);
    }

    public function getStoreCrmDataCounts(int $storeId): array
    {
        $counts = [];

        $tables = [
            'customer_notes' => 'یادداشت‌های مشتریان',
            'customer_tags' => 'برچسب‌های مشتریان',
            'tasks' => 'وظایف CRM',
            'activities' => 'فعالیت‌های ثبت شده',
            'segments' => 'سگمنت‌ها',
            'webhook_logs' => 'لاگ‌های وب‌هوک',
            'sync_logs' => 'لاگ‌های همگام‌سازی',
            'audit_logs' => 'لاگ‌های ممیزی امنیتی',
            'bulk_operations' => 'عملیات دسته‌جمعی',
        ];

        $totalCrmRecords = 0;

        foreach ($tables as $tbl => $label) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE `store_id` = ?");
            $stmt->execute([$storeId]);
            $c = (int)$stmt->fetchColumn();
            $counts[$tbl] = [
                'label' => $label,
                'count' => $c,
            ];
            $totalCrmRecords += $c;
        }

        $counts['total_records'] = $totalCrmRecords;
        $counts['total'] = $totalCrmRecords;
        $counts['tasks'] = $counts['tasks']['count'] ?? 0;
        $counts['tags'] = ($counts['customer_tags']['count'] ?? 0);
        $counts['customer_notes'] = $counts['customer_notes']['count'] ?? 0;
        $counts['activities'] = $counts['activities']['count'] ?? 0;
        $counts['segments'] = $counts['segments']['count'] ?? 0;
        $counts['webhook_logs'] = $counts['webhook_logs']['count'] ?? 0;
        $counts['sync_logs'] = $counts['sync_logs']['count'] ?? 0;
        $counts['audit_logs'] = $counts['audit_logs']['count'] ?? 0;
        $counts['bulk_operations'] = $counts['bulk_operations']['count'] ?? 0;

        return $counts;
    }
}
