<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

$pdo = \App\Database\Connection::get();
echo "STORES:\n";
print_r($pdo->query('SELECT id, name, url, status, is_demo, currency FROM stores')->fetchAll(PDO::FETCH_ASSOC));
echo "\nUSERS:\n";
print_r($pdo->query('SELECT id, username, email FROM users')->fetchAll(PDO::FETCH_ASSOC));
echo "\nCRM COUNTS BY STORE:\n";
print_r($pdo->query('SELECT store_id, count(*) as count FROM tags GROUP BY store_id')->fetchAll(PDO::FETCH_ASSOC));
echo "Segments:\n";
print_r($pdo->query('SELECT store_id, count(*) as count FROM segments GROUP BY store_id')->fetchAll(PDO::FETCH_ASSOC));
echo "Tasks:\n";
print_r($pdo->query('SELECT store_id, count(*) as count FROM tasks GROUP BY store_id')->fetchAll(PDO::FETCH_ASSOC));
echo "Notifications:\n";
print_r($pdo->query('SELECT store_id, count(*) as count FROM notifications GROUP BY store_id')->fetchAll(PDO::FETCH_ASSOC));

