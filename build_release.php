<?php

declare(strict_types=1);

/**
 * Production Release Packaging Script
 */

$rootDir = __DIR__;
$releaseDir = $rootDir . '/release';

echo "Building Production Release Package...\n";

// 1. Clean previous release directory if exists
if (is_dir($releaseDir)) {
    exec("rmdir /s /q " . escapeshellarg($releaseDir));
}
@mkdir($releaseDir, 0755, true);

// 2. Directories to copy
$dirs = ['app', 'config', 'vendor', 'public', 'routes'];
foreach ($dirs as $dir) {
    echo " - Copying {$dir}/...\n";
    $src = $rootDir . '/' . $dir;
    $dst = $releaseDir . '/' . $dir;
    exec("robocopy " . escapeshellarg($src) . " " . escapeshellarg($dst) . " /E /NFL /NDL /NJH /NJS /nc /ns /np");
}

// 3. Prepare storage structure (clean directories without local dev logs/caches)
echo " - Setting up clean storage/...\n";
$storageSubdirs = ['cache', 'logs', 'uploads', 'locks', 'temp'];
foreach ($storageSubdirs as $sub) {
    @mkdir($releaseDir . '/storage/' . $sub, 0755, true);
    file_put_contents($releaseDir . '/storage/' . $sub . '/.gitkeep', '');
}
copy($rootDir . '/storage/.htaccess', $releaseDir . '/storage/.htaccess');

// 4. Copy standalone root production files
$files = [
    'cron.php',
    'cli.php',
    '.env.example',
    'SECURITY.md',
    'PRODUCTION_CHECKLIST.md',
    'BACKUP_RESTORE.md',
    'DEPLOYMENT.md',
    'RELEASE.md',
];

foreach ($files as $file) {
    if (file_exists($rootDir . '/' . $file)) {
        copy($rootDir . '/' . $file, $releaseDir . '/' . $file);
        echo " - Copied {$file}\n";
    }
}

// 5. Verification
echo "\nVerifying Release Integrity:\n";
$forbiddenItems = ['node_modules', '.git', '.env', 'tests', 'scratch', 'storage/installed.lock'];
$allClean = true;
foreach ($forbiddenItems as $item) {
    $path = $releaseDir . '/' . $item;
    if (file_exists($path)) {
        echo " [FAIL] Forbidden item found in release: {$item}\n";
        $allClean = false;
    } else {
        echo " [OK] Excluded: {$item}\n";
    }
}

if ($allClean) {
    echo "\n>>> PRODUCTION ARTIFACT SUCCESSFULLY CREATED AT: release/\n";
} else {
    echo "\n>>> WARNING: Release packaging had issues.\n";
    exit(1);
}
