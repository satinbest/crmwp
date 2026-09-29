<?php

namespace App\Repositories;

use App\Database\Connection;
use App\Models\Permission;
use PDO;

class PermissionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `permissions` ORDER BY `group_name` ASC, `name` ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => Permission::fromArray($row), $rows);
    }

    public function groupedByModule(): array
    {
        $all = $this->all();
        $grouped = [];

        foreach ($all as $perm) {
            $group = $perm->group_name;
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $perm->toArray();
        }

        return $grouped;
    }

    public function groupedWithMetadata(): array
    {
        $groupTitles = [
            'dashboard' => 'داشبورد و آمار پایه',
            'customers' => 'مشتریان',
            'orders' => 'سفارش‌ها',
            'products' => 'محصولات و متغیرها',
            'inventory' => 'انبار و مدیریت موجودی',
            'crm' => 'مدیریت ارتباط با مشتری (CRM)',
            'segments' => 'بخش‌بندی‌های پویا (Segments)',
            'tags' => 'برچسب‌ها (Tags)',
            'tasks' => 'وظایف تیم (Tasks)',
            'activities' => 'تایم‌لاین فعالیت‌ها',
            'reports' => 'گزارش‌ها و خروجی داده',
            'bulk' => 'موتور عملیات گروهی',
            'stores' => 'اتصالات و فروشگاه‌ها',
            'users' => 'کاربران و اعضای تیم',
            'roles' => 'نقش‌ها و دسترسی‌ها (RBAC)',
            'settings' => 'تنظیمات سامانه',
            'logs' => 'لاگ‌های بازرسی و امنیت',
        ];

        $raw = $this->groupedByModule();
        $result = [];

        foreach ($raw as $groupKey => $perms) {
            $result[] = [
                'group_key' => $groupKey,
                'title' => $groupTitles[$groupKey] ?? ucfirst($groupKey),
                'permissions' => $perms,
            ];
        }

        return $result;
    }

    public function findByName(string $name): ?Permission
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `permissions` WHERE `name` = ?");
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Permission::fromArray($row) : null;
    }

    public function findById(int $id): ?Permission
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `permissions` WHERE `id` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Permission::fromArray($row) : null;
    }

    public function listAllNames(): array
    {
        return $this->pdo->query("SELECT `name` FROM `permissions` ORDER BY `name` ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
