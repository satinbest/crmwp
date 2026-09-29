<?php

class UpdateProductPermissions
{
    public function up(PDO $pdo): void
    {
        $newPerms = [
            ['name' => 'products.update', 'group_name' => 'products', 'display_name' => 'بروزرسانی محصول', 'description' => 'ویرایش و بروزرسانی مشخصات و قیمت محصولات'],
            ['name' => 'products.manage_inventory', 'group_name' => 'products', 'display_name' => 'مدیریت موجودی کالا', 'description' => 'تغییر موجودی، وضعیت انبار و هشدارهای کاهش موجودی'],
            ['name' => 'products.manage_variations', 'group_name' => 'products', 'display_name' => 'مدیریت متغیرها', 'description' => 'ایجاد، ویرایش و حذف تنوع‌های کالایی در محصولات متغیر'],
            ['name' => 'products.bulk', 'group_name' => 'products', 'display_name' => 'عملیات دسته‌ای محصولات', 'description' => 'تغییر گروهی قیمت، موجودی و وضعیت محصولات'],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO `permissions` (`name`, `group_name`, `display_name`, `description`, `created_at`) VALUES (:name, :group_name, :display_name, :description, NOW())");

        foreach ($newPerms as $perm) {
            $stmt->execute($perm);
        }

        // Grant to Admin role (id 1)
        $adminRole = $pdo->query("SELECT id FROM `roles` WHERE `name` = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($adminRole) {
            $adminId = (int)$adminRole['id'];
            $assignStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) SELECT :role_id, id FROM `permissions` WHERE `name` IN ('products.update', 'products.manage_inventory', 'products.manage_variations', 'products.bulk')");
            $assignStmt->execute([':role_id' => $adminId]);
        }

        // Grant to Manager role (id 2)
        $managerRole = $pdo->query("SELECT id FROM `roles` WHERE `name` = 'manager' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($managerRole) {
            $managerId = (int)$managerRole['id'];
            $assignStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) SELECT :role_id, id FROM `permissions` WHERE `name` IN ('products.update', 'products.manage_inventory', 'products.manage_variations')");
            $assignStmt->execute([':role_id' => $managerId]);
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DELETE FROM `permissions` WHERE `name` IN ('products.update', 'products.manage_inventory', 'products.manage_variations', 'products.bulk')");
    }
}
