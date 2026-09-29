<?php

class CreatePhase8InventoryPermissions
{
    public function up(PDO $pdo): void
    {
        $perms = [
            ['name' => 'inventory.view', 'group_name' => 'inventory', 'display_name' => 'مشاهده انبار و موجودی', 'description' => 'مشاهده داشبورد انبار، موجودی کالاها، تنوع‌ها و هشدارهای کمبود کالا'],
            ['name' => 'inventory.update', 'group_name' => 'inventory', 'display_name' => 'ویرایش موجودی و وضعیت انبار', 'description' => 'تنظیم موجودی، افزایش یا کاهش موجودی و تغییر وضعیت کالا در انبار'],
            ['name' => 'inventory.manage_stock', 'group_name' => 'inventory', 'display_name' => 'پیکربندی انبارداری کالا', 'description' => 'فعال/غیرفعال‌سازی مدیریت انبار، آستانه کمبود کالا، پیش‌خرید و فروش تکی'],
            ['name' => 'inventory.bulk', 'group_name' => 'inventory', 'display_name' => 'عملیات گروهی انبار', 'description' => 'تغییر دسته‌ای موجودی و وضعیت کالاهای انبار'],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO `permissions` (`name`, `group_name`, `display_name`, `description`, `created_at`)
            VALUES (:name, :group_name, :display_name, :description, NOW())
        ");

        foreach ($perms as $perm) {
            $stmt->execute($perm);
        }

        // Grant to Admin role
        $adminRole = $pdo->query("SELECT id FROM `roles` WHERE `name` = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($adminRole) {
            $adminId = (int)$adminRole['id'];
            $assignStmt = $pdo->prepare("
                INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
                SELECT :role_id, id FROM `permissions`
                WHERE `name` IN ('inventory.view', 'inventory.update', 'inventory.manage_stock', 'inventory.bulk')
            ");
            $assignStmt->execute([':role_id' => $adminId]);
        }

        // Grant to Manager role
        $managerRole = $pdo->query("SELECT id FROM `roles` WHERE `name` = 'manager' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($managerRole) {
            $managerId = (int)$managerRole['id'];
            $assignStmt = $pdo->prepare("
                INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
                SELECT :role_id, id FROM `permissions`
                WHERE `name` IN ('inventory.view', 'inventory.update', 'inventory.manage_stock', 'inventory.bulk')
            ");
            $assignStmt->execute([':role_id' => $managerId]);
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DELETE FROM `permissions` WHERE `name` IN ('inventory.view', 'inventory.update', 'inventory.manage_stock', 'inventory.bulk')");
    }
}
