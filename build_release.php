<?php

declare(strict_types=1);

/**
 * Production Release Packaging & Verification Script
 * Generates an isolated, production-ready release folder and standalone ZIP package.
 */

$rootDir = __DIR__;
$releaseDir = $rootDir . '/release';
$zipFile = $rootDir . '/release/crmwp-production-release.zip';

echo "========================================================\n";
echo "   BUILDING PRODUCTION RELEASE PACKAGE (crmwp)\n";
echo "========================================================\n";

// 1. Clean previous release directory if exists
if (is_dir($releaseDir)) {
    echo " - Removing previous release artifact...\n";
    if (PHP_OS_FAMILY === 'Windows') {
        exec("rmdir /s /q " . escapeshellarg($releaseDir));
    } else {
        exec("rm -rf " . escapeshellarg($releaseDir));
    }
}
@mkdir($releaseDir, 0755, true);

// 2. Directories to copy
$dirs = ['app', 'config', 'vendor', 'public', 'routes'];
foreach ($dirs as $dir) {
    echo " - Copying {$dir}/...\n";
    $src = $rootDir . '/' . $dir;
    $dst = $releaseDir . '/' . $dir;
    if (PHP_OS_FAMILY === 'Windows') {
        exec("robocopy " . escapeshellarg($src) . " " . escapeshellarg($dst) . " /E /NFL /NDL /NJH /NJS /nc /ns /np");
    } else {
        @mkdir($dst, 0755, true);
        exec("cp -r " . escapeshellarg($src) . "/* " . escapeshellarg($dst) . "/");
    }
}

// 3. Prepare storage structure (clean directories without local dev logs/caches)
echo " - Setting up clean storage/...\n";
$storageSubdirs = ['cache', 'logs', 'uploads', 'locks', 'temp'];
foreach ($storageSubdirs as $sub) {
    @mkdir($releaseDir . '/storage/' . $sub, 0755, true);
    file_put_contents($releaseDir . '/storage/' . $sub . '/.gitkeep', '');
}
if (file_exists($rootDir . '/storage/.htaccess')) {
    copy($rootDir . '/storage/.htaccess', $releaseDir . '/storage/.htaccess');
}

// 4. Copy standalone root production files
$files = [
    '.htaccess',
    '.env.example',
    'cron.php',
    'cli.php',
    'README.md',
    'INSTALL.md',
    'UPGRADE.md',
    'SECURITY.md',
    'RELEASE-MANIFEST.md',
    'RELEASE_MANIFEST.json',
    'RELEASE_NOTES.md',
    'DEPLOYMENT.md',
    'BACKUP.md',
    'BACKUP_RESTORE.md',
    'PRODUCTION_CHECKLIST.md',
];

foreach ($files as $file) {
    if (file_exists($rootDir . '/' . $file)) {
        copy($rootDir . '/' . $file, $releaseDir . '/' . $file);
        echo " - Copied {$file}\n";
    }
}

// 5. Verification of Excluded Items
echo "\nVerifying Release Integrity:\n";
$forbiddenItems = [
    'node_modules',
    '.git',
    '.env',
    'tests',
    'scratch',
    'storage/installed.lock',
    'storage/logs/cron.log',
];
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

if (!$allClean) {
    echo "\n>>> ERROR: Release packaging failed integrity verification.\n";
    exit(1);
}

// 6. Generate Standalone ZIP Archive for File Manager / FTP Upload
echo "\nGenerating Standalone ZIP Package: crmwp-production-release.zip...\n";
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $filesToZip = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($releaseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($filesToZip as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($releaseDir) + 1);
                // Do not include the zip file itself if already created
                if ($filePath !== realpath($zipFile)) {
                    $zip->addFile($filePath, str_replace('\\', '/', $relativePath));
                }
            }
        }
        $zip->close();
        $zipSizeMb = round(filesize($zipFile) / (1024 * 1024), 2);
        echo " [OK] Standalone ZIP created successfully: {$zipFile} ({$zipSizeMb} MB)\n";
    } else {
        echo " [WARNING] Could not open ZipArchive for writing.\n";
    }
} else {
    echo " [INFO] ZipArchive extension not present in CLI. Skipping ZIP generation.\n";
}

echo "\n========================================================\n";
echo ">>> PRODUCTION ARTIFACT SUCCESSFULLY CREATED AT:\n";
echo "    Folder: {$releaseDir}\n";
if (file_exists($zipFile)) {
    echo "    Archive: {$zipFile}\n";
}
echo "========================================================\n";
