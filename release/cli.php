<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\MigrationRunner;
use App\Database\Seeders\DatabaseSeeder;
use App\Support\Env;

Env::load(__DIR__ . '/.env');

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'migrate':
        echo "Running migrations...\n";
        try {
            $runner = new MigrationRunner();
            $applied = $runner->migrate();
            if (empty($applied)) {
                echo "Nothing to migrate. All migrations are up to date.\n";
            } else {
                foreach ($applied as $m) {
                    echo " - Applied: {$m}\n";
                }
                echo "Migration completed successfully (" . count($applied) . " applied).\n";
            }
        } catch (Exception $e) {
            echo "Migration error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'migrate:rollback':
        echo "Rolling back last batch of migrations...\n";
        try {
            $runner = new MigrationRunner();
            $rolledBack = $runner->rollback();
            if (empty($rolledBack)) {
                echo "No migrations to roll back.\n";
            } else {
                foreach ($rolledBack as $m) {
                    echo " - Rolled back: {$m}\n";
                }
                echo "Rollback completed (" . count($rolledBack) . " rolled back).\n";
            }
        } catch (Exception $e) {
            echo "Rollback error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'db:seed':
        echo "Seeding database with default roles, permissions, and initial users...\n";
        try {
            $seeder = new DatabaseSeeder();
            $seeder->run();
            echo "Database seeded successfully!\n";
            echo "Default Admin: admin / AdminPassword123!\n";
            echo "Default Manager: manager / ManagerPassword123!\n";
        } catch (Exception $e) {
            echo "Seeding error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'key:generate':
        $key = bin2hex(random_bytes(16));
        echo "Generated secret: crmwp_secret_{$key}\n";
        break;

    case 'automations:run-scheduled':
        echo "Processing scheduled automations batch...\n";
        try {
            $engine = new \App\Automation\AutomationEngine();
            $res = $engine->runScheduled(50);
            echo "Processed {$res['processed']} of {$res['scheduled_automations_count']} scheduled automations.\n";
        } catch (Exception $e) {
            echo "Scheduled automations error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'help':
    default:
        echo "WooCommerce Management & CRM Platform CLI\n";
        echo "Usage: php cli.php [command]\n";
        echo "Commands:\n";
        echo "  migrate                     Run outstanding database migrations\n";
        echo "  migrate:rollback            Rollback the last batch of migrations\n";
        echo "  db:seed                     Seed database with roles, permissions, and users\n";
        echo "  key:generate                Generate a new application encryption secret\n";
        echo "  automations:run-scheduled   Process scheduled workflow automations\n";
        break;
}
