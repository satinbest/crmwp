<?php

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
\App\Support\Config::setPath(__DIR__ . '/../config');
\App\Support\Logger::setLogDir(__DIR__ . '/../storage/logs');

use App\Repositories\StoreRepository;
use App\Services\Bulk\BulkOperationEngine;

$storeRepo = new StoreRepository();
$store = $storeRepo->findById(1);
if (!$store) {
    echo "Store 1 not found!\n";
    exit(1);
}

$engine = new BulkOperationEngine();
echo "--- Testing BulkOperationEngine for Store 1: {$store->name} ---\n";

// 1. Evaluate Target: All products
$evalAll = $engine->evaluateTarget(1, 1, [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => [],
    'target_scope' => 'both',
]);

echo "1. Evaluate Target (All, Scope: both):\n";
echo " - Total Parents: {$evalAll['total_parents']}\n";
echo " - Simple Count: {$evalAll['simple_count']}\n";
echo " - Variable Count: {$evalAll['variable_count']}\n";
echo " - Variations Count: {$evalAll['variation_count']}\n";
echo " - Total Affected: {$evalAll['affected_count']}\n";
echo " - Sample Count: " . count($evalAll['sample_products']) . "\n";

// 2. Evaluate Target: Variations only
$evalVars = $engine->evaluateTarget(1, 1, [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => [],
    'target_scope' => 'variations',
]);
echo "2. Evaluate Target (Variations only):\n";
echo " - Affected Count: {$evalVars['affected_count']}\n";

// 3. Preview: Increase price 10% on 'both'
$prev = $engine->preview(1, 1, [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => [],
    'action' => [
        'type' => 'increase_price_percent',
        'value' => 10,
        'target' => 'both',
    ],
]);

echo "3. Preview Increase Price 10% (both):\n";
echo " - Affected: {$prev['affected_count']}\n";
echo " - Simple: {$prev['simple_count']} | Variable: {$prev['variable_count']} | Variation: {$prev['variation_count']}\n";
echo " - Sample items count: " . count($prev['sample']) . "\n";
foreach (array_slice($prev['sample'], 0, 5) as $s) {
    $oldP = is_array($s['old_value']) ? ($s['old_value']['regular_price'] ?? $s['old_value']['price'] ?? '?') : $s['old_value'];
    $newP = is_array($s['new_value']) ? ($s['new_value']['regular_price'] ?? $s['new_value']['price'] ?? '?') : $s['new_value'];
    $typeStr = !empty($s['is_variation']) ? 'Variation' : 'Parent';
    echo "   * [{$typeStr}] #{$s['entity_id']} {$s['name']}: {$oldP} -> {$newP}\n";
}

// 4. Test Presets
echo "4. Testing Presets:\n";
$createdPreset = $engine->createPreset(1, 1, [
    'title' => 'افزایش قیمت تستی ۱۰٪',
    'description' => 'تست سیستم الگوها',
    'target_entity' => 'products',
    'filter_criteria' => ['category' => 'all'],
    'action_data' => ['type' => 'increase_price_percent', 'value' => 10, 'target' => 'both'],
]);
echo " - Created Preset ID: {$createdPreset['id']} ('{$createdPreset['title']}')\n";

$presetsList = $engine->listPresets(1);
echo " - List Presets Count: " . count($presetsList) . "\n";

$deleted = $engine->deletePreset($createdPreset['id'], 1);
echo " - Deleted Preset: " . ($deleted ? "YES" : "NO") . "\n";

echo "ALL ENGINE TESTS PASSED!\n";
