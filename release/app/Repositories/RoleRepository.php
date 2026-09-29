<?php

namespace App\Repositories;

use App\Database\Connection;
use App\Models\Role;
use PDO;

class RoleRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function all(array $filters = []): array
    {
        $sql = "SELECT r.*, COUNT(ur.user_id) as users_count 
                FROM `roles` r 
                LEFT JOIN `user_roles` ur ON r.id = ur.role_id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (r.name LIKE :search OR r.display_name LIKE :search OR r.description LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive'], true)) {
            $sql .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }

        $sql .= " GROUP BY r.id ORDER BY r.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $roles = [];
        foreach ($rows as $row) {
            $roleId = (int)$row['id'];
            $row['permissions'] = $this->getPermissionsForRole($roleId);
            $roles[] = Role::fromArray($row);
        }

        return $roles;
    }

    public function findById(int $id): ?Role
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, COUNT(ur.user_id) as users_count 
            FROM `roles` r 
            LEFT JOIN `user_roles` ur ON r.id = ur.role_id 
            WHERE r.id = ? 
            GROUP BY r.id
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['permissions'] = $this->getPermissionsForRole($id);
        return Role::fromArray($row);
    }

    public function findBySlug(string $slug): ?Role
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, COUNT(ur.user_id) as users_count 
            FROM `roles` r 
            LEFT JOIN `user_roles` ur ON r.id = ur.role_id 
            WHERE r.slug = ? OR r.name = ?
            GROUP BY r.id
        ");
        $stmt->execute([$slug, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['permissions'] = $this->getPermissionsForRole((int)$row['id']);
        return Role::fromArray($row);
    }

    public function create(array $data): Role
    {
        $name = trim($data['name']);
        $slug = !empty($data['slug']) ? trim($data['slug']) : strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        $displayName = !empty($data['display_name']) ? trim($data['display_name']) : $name;
        $description = $data['description'] ?? null;
        $status = in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? $data['status'] : 'active';

        $stmt = $this->pdo->prepare("
            INSERT INTO `roles` (`name`, `slug`, `display_name`, `description`, `status`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$name, $slug, $displayName, $description, $status]);

        $id = (int)$this->pdo->lastInsertId();

        if (!empty($data['permissions']) && is_array($data['permissions'])) {
            $this->syncPermissions($id, $data['permissions']);
        }

        return $this->findById($id);
    }

    public function update(int $id, array $data): ?Role
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return null;
        }

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = "`name` = ?";
            $params[] = trim($data['name']);
        }

        if (isset($data['slug'])) {
            $fields[] = "`slug` = ?";
            $params[] = trim($data['slug']);
        }

        if (isset($data['display_name'])) {
            $fields[] = "`display_name` = ?";
            $params[] = trim($data['display_name']);
        }

        if (array_key_exists('description', $data)) {
            $fields[] = "`description` = ?";
            $params[] = $data['description'];
        }

        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive'], true)) {
            $fields[] = "`status` = ?";
            $params[] = $data['status'];
        }

        if (!empty($fields)) {
            $fields[] = "`updated_at` = NOW()";
            $params[] = $id;
            $sql = "UPDATE `roles` SET " . implode(', ', $fields) . " WHERE `id` = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $this->syncPermissions($id, $data['permissions']);
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `roles` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    public function duplicate(int $id, string $newName, ?string $newSlug = null): ?Role
    {
        $source = $this->findById($id);
        if (!$source) {
            return null;
        }

        $slug = $newSlug ?: (strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $newName)) . '_' . rand(100, 999));
        $permIds = array_column($source->permissions, 'id');

        return $this->create([
            'name' => $newName,
            'slug' => $slug,
            'display_name' => $newName,
            'description' => "کپی از نقش {$source->display_name}",
            'status' => 'active',
            'permissions' => $permIds,
        ]);
    }

    public function getPermissionsForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.name, p.group_name, p.display_name, p.description
            FROM `permissions` p
            JOIN `role_permissions` rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.group_name ASC, p.name ASC
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncPermissions(int $roleId, array $permissionIdsOrNames): void
    {
        // Clear existing permissions
        $stmt = $this->pdo->prepare("DELETE FROM `role_permissions` WHERE `role_id` = ?");
        $stmt->execute([$roleId]);

        if (empty($permissionIdsOrNames)) {
            return;
        }

        // Support either integer IDs or string permission names
        $pids = [];
        $names = [];
        foreach ($permissionIdsOrNames as $item) {
            if (is_numeric($item)) {
                $pids[] = (int)$item;
            } elseif (is_string($item) && !empty($item)) {
                $names[] = $item;
            }
        }

        if (!empty($names)) {
            $in = implode(',', array_map(fn($n) => $this->pdo->quote($n), $names));
            $resolved = $this->pdo->query("SELECT id FROM `permissions` WHERE `name` IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
            $pids = array_unique(array_merge($pids, array_map('intval', $resolved)));
        }

        if (empty($pids)) {
            return;
        }

        $insert = $this->pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
        foreach ($pids as $pid) {
            $insert->execute([$roleId, $pid]);
        }
    }

    public function countUsersWithRole(int $roleId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `user_roles` WHERE `role_id` = ?");
        $stmt->execute([$roleId]);
        return (int)$stmt->fetchColumn();
    }
}
