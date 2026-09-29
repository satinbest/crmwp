<?php

namespace App\Repositories;

use App\Database\Connection;
use App\Models\User;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['roles'] = $this->getUserRoles($id);
        $row['stores'] = $this->getUserStores($id);
        $row['permissions'] = $this->getUserPermissions($id);

        return User::fromArray($row);
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `username` = ?");
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $id = (int)$row['id'];
        $row['roles'] = $this->getUserRoles($id);
        $row['stores'] = $this->getUserStores($id);
        $row['permissions'] = $this->getUserPermissions($id);

        return User::fromArray($row);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
        $stmt->execute([trim($email)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $id = (int)$row['id'];
        $row['roles'] = $this->getUserRoles($id);
        $row['stores'] = $this->getUserStores($id);
        $row['permissions'] = $this->getUserPermissions($id);

        return User::fromArray($row);
    }

    public function findByUsernameOrEmail(string $identifier): ?User
    {
        $clean = trim($identifier);
        $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `username` = ? OR `email` = ?");
        $stmt->execute([$clean, $clean]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $id = (int)$row['id'];
        $row['roles'] = $this->getUserRoles($id);
        $row['stores'] = $this->getUserStores($id);
        $row['permissions'] = $this->getUserPermissions($id);

        return User::fromArray($row);
    }

    public function listFiltered(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "SELECT DISTINCT u.* FROM `users` u WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        if (isset($filters['status']) && in_array($filters['status'], ['active', 'inactive'], true)) {
            $sql .= " AND u.is_active = :status";
            $params['status'] = $filters['status'] === 'active' ? 1 : 0;
        }

        if (!empty($filters['role_id'])) {
            $sql .= " AND u.id IN (SELECT ur.user_id FROM `user_roles` ur WHERE ur.role_id = :role_id)";
            $params['role_id'] = (int)$filters['role_id'];
        }

        if (!empty($filters['store_id'])) {
            $sql .= " AND u.id IN (SELECT us.user_id FROM `user_stores` us WHERE us.store_id = :store_id)";
            $params['store_id'] = (int)$filters['store_id'];
        }

        $sql .= " ORDER BY u.id ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $row['roles'] = $this->getUserRoles($id);
            $row['stores'] = $this->getUserStores($id);
            $row['permissions'] = $this->getUserPermissions($id);
            $users[] = User::fromArray($row);
        }

        return $users;
    }

    public function countFiltered(array $filters = []): int
    {
        $sql = "SELECT COUNT(DISTINCT u.id) FROM `users` u WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        if (isset($filters['status']) && in_array($filters['status'], ['active', 'inactive'], true)) {
            $sql .= " AND u.is_active = :status";
            $params['status'] = $filters['status'] === 'active' ? 1 : 0;
        }

        if (!empty($filters['role_id'])) {
            $sql .= " AND u.id IN (SELECT ur.user_id FROM `user_roles` ur WHERE ur.role_id = :role_id)";
            $params['role_id'] = (int)$filters['role_id'];
        }

        if (!empty($filters['store_id'])) {
            $sql .= " AND u.id IN (SELECT us.user_id FROM `user_stores` us WHERE us.store_id = :store_id)";
            $params['store_id'] = (int)$filters['store_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): User
    {
        $username = trim($data['username']);
        $email = trim($data['email']);
        $passwordHash = $data['password_hash'];
        $firstName = !empty($data['first_name']) ? trim($data['first_name']) : null;
        $lastName = !empty($data['last_name']) ? trim($data['last_name']) : null;
        $avatar = $data['avatar'] ?? null;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        $stmt = $this->pdo->prepare("
            INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `avatar`, `is_active`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $avatar, $isActive]);

        $id = (int)$this->pdo->lastInsertId();

        if (isset($data['roles']) && is_array($data['roles'])) {
            $this->syncRoles($id, $data['roles']);
        }

        if (isset($data['stores']) && is_array($data['stores'])) {
            $this->syncStores($id, $data['stores']);
        }

        return $this->findById($id);
    }

    public function update(int $id, array $data): ?User
    {
        $fields = [];
        $params = [];

        if (isset($data['username'])) {
            $fields[] = "`username` = ?";
            $params[] = trim($data['username']);
        }

        if (isset($data['email'])) {
            $fields[] = "`email` = ?";
            $params[] = trim($data['email']);
        }

        if (isset($data['password_hash'])) {
            $fields[] = "`password_hash` = ?";
            $params[] = $data['password_hash'];
        }

        if (array_key_exists('first_name', $data)) {
            $fields[] = "`first_name` = ?";
            $params[] = $data['first_name'];
        }

        if (array_key_exists('last_name', $data)) {
            $fields[] = "`last_name` = ?";
            $params[] = $data['last_name'];
        }

        if (array_key_exists('avatar', $data)) {
            $fields[] = "`avatar` = ?";
            $params[] = $data['avatar'];
        }

        if (isset($data['is_active'])) {
            $fields[] = "`is_active` = ?";
            $params[] = (int)(bool)$data['is_active'];
        }

        if (!empty($fields)) {
            $fields[] = "`updated_at` = NOW()";
            $params[] = $id;
            $sql = "UPDATE `users` SET " . implode(', ', $fields) . " WHERE `id` = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        if (isset($data['roles']) && is_array($data['roles'])) {
            $this->syncRoles($id, $data['roles']);
        }

        if (isset($data['stores']) && is_array($data['stores'])) {
            $this->syncStores($id, $data['stores']);
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `users` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->pdo->prepare("UPDATE `users` SET `is_active` = ?, `updated_at` = NOW() WHERE `id` = ?");
        return $stmt->execute([(int)$active, $id]);
    }

    public function updatePassword(int $id, string $newHash): bool
    {
        $stmt = $this->pdo->prepare("UPDATE `users` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?");
        return $stmt->execute([$newHash, $id]);
    }

    public function updateLastLogin(int $userId, string $ip): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `users`
            SET `last_login_at` = NOW(), `last_login_ip` = ?
            WHERE `id` = ?
        ");
        $stmt->execute([$ip, $userId]);
    }

    public function getUserRoles(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.name, r.slug, r.display_name, r.description, r.status
            FROM `roles` r
            JOIN `user_roles` ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserStores(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.name, s.url, s.status, s.currency
            FROM `stores` s
            JOIN `user_stores` us ON s.id = us.store_id
            WHERE us.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserPermissions(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT p.name
            FROM `permissions` p
            JOIN `role_permissions` rp ON p.id = rp.permission_id
            JOIN `user_roles` ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function syncRoles(int $userId, array $roleIdsOrSlugs): void
    {
        $this->pdo->prepare("DELETE FROM `user_roles` WHERE `user_id` = ?")->execute([$userId]);

        if (empty($roleIdsOrSlugs)) {
            return;
        }

        $rids = [];
        $slugs = [];
        foreach ($roleIdsOrSlugs as $item) {
            if (is_numeric($item)) {
                $rids[] = (int)$item;
            } elseif (is_string($item) && !empty($item)) {
                $slugs[] = $item;
            }
        }

        if (!empty($slugs)) {
            $in = implode(',', array_map(fn($s) => $this->pdo->quote($s), $slugs));
            $resolved = $this->pdo->query("SELECT id FROM `roles` WHERE `slug` IN ($in) OR `name` IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
            $rids = array_unique(array_merge($rids, array_map('intval', $resolved)));
        }

        $insert = $this->pdo->prepare("INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (?, ?)");
        foreach ($rids as $rid) {
            $insert->execute([$userId, $rid]);
        }
    }

    public function syncStores(int $userId, array $storeIds): void
    {
        $this->pdo->prepare("DELETE FROM `user_stores` WHERE `user_id` = ?")->execute([$userId]);

        if (empty($storeIds)) {
            return;
        }

        $insert = $this->pdo->prepare("INSERT IGNORE INTO `user_stores` (`user_id`, `store_id`) VALUES (?, ?)");
        foreach ($storeIds as $sid) {
            $insert->execute([$userId, (int)$sid]);
        }
    }

    public function countActiveAdmins(): int
    {
        $sql = "
            SELECT COUNT(DISTINCT u.id)
            FROM `users` u
            JOIN `user_roles` ur ON u.id = ur.user_id
            JOIN `roles` r ON ur.role_id = r.id
            WHERE u.is_active = 1
              AND (r.slug = 'admin' OR r.name = 'Admin')
        ";
        return (int)$this->pdo->query($sql)->fetchColumn();
    }

    public function isUserAdmin(int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM `user_roles` ur
            JOIN `roles` r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND (r.slug = 'admin' OR r.name = 'Admin')
        ");
        $stmt->execute([$userId]);
        return ((int)$stmt->fetchColumn()) > 0;
    }
}
