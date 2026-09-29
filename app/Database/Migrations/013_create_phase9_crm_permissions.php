<?php

class CreatePhase9CrmPermissions
{
    public function up(PDO $pdo): void
    {
        // 1. Update tasks table priority enum to include 'normal'
        try {
            $pdo->exec("
                ALTER TABLE `tasks` 
                MODIFY COLUMN `priority` ENUM('low', 'normal', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'normal';
            ");
        } catch (Throwable $e) {
            // Already altered or compatible
        }

        // 2. Define Phase 9 CRM Permissions
        $perms = [
            ['name' => 'crm.view', 'group_name' => 'crm', 'display_name' => 'مشاهده پیشخوان CRM', 'description' => 'مشاهده داشبورد جامع ارتباط با مشتریان، گزارش‌های کلیدی و شاخص‌های CRM'],
            ['name' => 'crm.manage', 'group_name' => 'crm', 'display_name' => 'مدیریت کلی CRM', 'description' => 'دسترسی کامل مدیریتی به تمام امکانات ماژول CRM'],

            ['name' => 'segments.view', 'group_name' => 'segments', 'display_name' => 'مشاهده بخش‌بندی‌ها', 'description' => 'مشاهده سگمنت‌ها و گروه‌های هوشمند مشتریان'],
            ['name' => 'segments.create', 'group_name' => 'segments', 'display_name' => 'ایجاد بخش‌بندی', 'description' => 'ساخت سگمنت هوشمند جدید با تعریف قوانین دینامیک'],
            ['name' => 'segments.update', 'group_name' => 'segments', 'display_name' => 'ویرایش بخش‌بندی', 'description' => 'تغییر نام، توضیحات و قوانین سگمنت‌ها'],
            ['name' => 'segments.delete', 'group_name' => 'segments', 'display_name' => 'حذف بخش‌بندی', 'description' => 'حذف سگمنت‌های مشتریان'],

            ['name' => 'tags.view', 'group_name' => 'tags', 'display_name' => 'مشاهده برچسب‌ها', 'description' => 'مشاهده لیست برچسب‌های مشتریان'],
            ['name' => 'tags.create', 'group_name' => 'tags', 'display_name' => 'ایجاد برچسب', 'description' => 'تعریف برچسب جدید با رنگ اختصاصی'],
            ['name' => 'tags.update', 'group_name' => 'tags', 'display_name' => 'ویرایش برچسب', 'description' => 'تغییر نام و رنگ برچسب‌ها'],
            ['name' => 'tags.delete', 'group_name' => 'tags', 'display_name' => 'حذف برچسب', 'description' => 'حذف برچسب‌های مشتریان'],

            ['name' => 'tasks.view', 'group_name' => 'tasks', 'display_name' => 'مشاهده وظایف', 'description' => 'مشاهده وظایف و پیگیری‌های کاری'],
            ['name' => 'tasks.create', 'group_name' => 'tasks', 'display_name' => 'ایجاد وظیفه', 'description' => 'ثبت وظیفه جدید برای مشتری یا سفارش'],
            ['name' => 'tasks.update', 'group_name' => 'tasks', 'display_name' => 'ویرایش وظیفه', 'description' => 'ویرایش عنوان، شرح، مهلت، اولویت و وضعیت وظیفه'],
            ['name' => 'tasks.delete', 'group_name' => 'tasks', 'display_name' => 'حذف وظیفه', 'description' => 'حذف وظایف ثبت شده'],
            ['name' => 'tasks.assign', 'group_name' => 'tasks', 'display_name' => 'ارجاع وظیفه', 'description' => 'ارجاع و تخصیص وظیفه به سایر کاربران و همکاران'],

            ['name' => 'activities.view', 'group_name' => 'activities', 'display_name' => 'مشاهده تاریخچه فعالیت‌ها', 'description' => 'مشاهده تایم‌لاین فعالیت‌ها و رخدادهای مشتریان و سیستم'],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO `permissions` (`name`, `group_name`, `display_name`, `description`, `created_at`)
            VALUES (:name, :group_name, :display_name, :description, NOW())
        ");

        foreach ($perms as $perm) {
            $stmt->execute($perm);
        }

        $allPermNames = array_column($perms, 'name');
        $placeholders = implode(',', array_fill(0, count($allPermNames), '?'));

        // Assign to Admin role
        $adminRole = $pdo->query("SELECT id FROM `roles` WHERE `name` = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($adminRole) {
            $adminId = (int)$adminRole['id'];
            $assignStmt = $pdo->prepare("
                INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
                SELECT ?, id FROM `permissions`
                WHERE `name` IN ($placeholders)
            ");
            $assignStmt->execute(array_merge([$adminId], $allPermNames));
        }

        // Assign to Manager role
        $managerRole = $pdo->query("SELECT id FROM `roles` WHERE `name` = 'manager' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($managerRole) {
            $managerId = (int)$managerRole['id'];
            $assignStmt = $pdo->prepare("
                INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
                SELECT ?, id FROM `permissions`
                WHERE `name` IN ($placeholders)
            ");
            $assignStmt->execute(array_merge([$managerId], $allPermNames));
        }
    }

    public function down(PDO $pdo): void
    {
        $perms = [
            'crm.view', 'crm.manage',
            'segments.view', 'segments.create', 'segments.update', 'segments.delete',
            'tags.view', 'tags.create', 'tags.update', 'tags.delete',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign',
            'activities.view'
        ];
        $placeholders = implode(',', array_fill(0, count($perms), '?'));
        $stmt = $pdo->prepare("DELETE FROM `permissions` WHERE `name` IN ($placeholders)");
        $stmt->execute($perms);
    }
}
