<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\MigrationRunner;
use App\Support\Config;
use App\Support\Env;

Env::load(__DIR__ . '/../.env');
Config::setPath(__DIR__ . '/../config');

$runner = new MigrationRunner();
$applied = $runner->migrate();
echo "Applied migrations: " . implode(', ', $applied) . "\n";
echo "Migrations finished successfully!\n";
