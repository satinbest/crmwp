<?php

declare(strict_types=1);

/**
 * Production Release Packaging & Verification Script
 * Generates an isolated, production-ready release folder and standalone ZIP package:
 * CRM-Production-Release-1.1.0.zip
 */

$rootDir = __DIR__;
require_once $rootDir . '/config/app.php';
$releaseDir = $rootDir . '/release';
$buildDir = $releaseDir . '/app_package';
$version = defined('CRM_APP_VERSION') ? CRM_APP_VERSION : '1.1.0';
$primaryZipFile = $releaseDir . "/CRM-Production-Release-{$version}.zip";
$workspaceZipFile = $rootDir . "/CRM-Production-Release-{$version}.zip";
$legacyZipFile = $releaseDir . '/crmwp-production-release.zip';

echo "========================================================\n";
echo "   BUILDING PRODUCTION RELEASE PACKAGE: {$version}\n";
echo "========================================================\n";

// 1. Clean previous release artifacts
if (is_dir($releaseDir)) {
    echo " - Removing previous release artifact directory...\n";
    if (PHP_OS_FAMILY === 'Windows') {
        exec("rmdir /s /q " . escapeshellarg($releaseDir));
    } else {
        exec("rm -rf " . escapeshellarg($releaseDir));
    }
}
@mkdir($buildDir, 0755, true);

// 2. Directories to copy to build package
$dirs = ['app', 'config', 'vendor', 'public', 'routes'];
foreach ($dirs as $dir) {
    echo " - Copying {$dir}/...\n";
    $src = $rootDir . '/' . $dir;
    $dst = $buildDir . '/' . $dir;
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
    @mkdir($buildDir . '/storage/' . $sub, 0755, true);
    file_put_contents($buildDir . '/storage/' . $sub . '/.gitkeep', '');
}
if (file_exists($rootDir . '/storage/.htaccess')) {
    copy($rootDir . '/storage/.htaccess', $buildDir . '/storage/.htaccess');
}
if (file_exists($rootDir . '/storage/cacert.pem')) {
    copy($rootDir . '/storage/cacert.pem', $buildDir . '/storage/cacert.pem');
}

// 4. Copy standalone root production files
$files = [
    '.htaccess',
    '.env.example',
    'cron.php',
    'cli.php',
    'CHANGELOG.md',
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
    'composer.json',
];

foreach ($files as $file) {
    if (file_exists($rootDir . '/' . $file)) {
        copy($rootDir . '/' . $file, $buildDir . '/' . $file);
        echo " - Copied {$file}\n";
    }
}

// Clean any leftover dev avatars from public/storage/avatars
$avatarFiles = glob($buildDir . '/public/storage/avatars/*.{jpg,png,jpeg,webp}', GLOB_BRACE) ?: [];
foreach ($avatarFiles as $af) {
    @unlink($af);
}

// Also place RELEASE-MANIFEST.md and CHANGELOG.md directly in releaseDir alongside the ZIP
copy($rootDir . '/RELEASE-MANIFEST.md', $releaseDir . '/RELEASE-MANIFEST.md');
if (file_exists($rootDir . '/CHANGELOG.md')) {
    copy($rootDir . '/CHANGELOG.md', $releaseDir . '/CHANGELOG.md');
}

// 5. Verification of Excluded Items in the package
echo "\nVerifying Release Integrity:\n";
$forbiddenItems = [
    'node_modules',
    '.git',
    '.github',
    '.env',
    'tests',
    'scratch',
    'storage/installed.lock',
    'storage/logs/cron.log',
];
$allClean = true;
foreach ($forbiddenItems as $item) {
    $path = $buildDir . '/' . $item;
    if (file_exists($path)) {
        echo " [FAIL] Forbidden item found in release package: {$item}\n";
        $allClean = false;
    } else {
        echo " [OK] Excluded: {$item}\n";
    }
}

if (!$allClean) {
    echo "\n>>> ERROR: Release packaging failed integrity verification.\n";
    exit(1);
}

// 6. Generate Standalone ZIP Archive
echo "\nGenerating Standalone ZIP: {$primaryZipFile}...\n";
$sha256 = null;
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($primaryZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $filesToZip = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($filesToZip as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($buildDir) + 1);
                $zip->addFile($filePath, str_replace('\\', '/', $relativePath));
            }
        }
        $zip->close();
        $zipSizeMb = round(filesize($primaryZipFile) / (1024 * 1024), 2);
        echo " [OK] Standalone ZIP created successfully: {$primaryZipFile} ({$zipSizeMb} MB)\n";

        // Also duplicate to root workspace for immediate access
        copy($primaryZipFile, $workspaceZipFile);
        echo " [OK] Copied ZIP to workspace root: {$workspaceZipFile}\n";

        // Also duplicate to legacy zip name for compatibility
        copy($primaryZipFile, $legacyZipFile);

        $sha256 = hash_file('sha256', $primaryZipFile);
        $checksumContent = $sha256 . "  CRM-Production-Release-{$version}.zip\n";
        file_put_contents($releaseDir . "/CRM-Production-Release-{$version}.sha256", $checksumContent);
        file_put_contents($rootDir . "/CRM-Production-Release-{$version}.sha256", $checksumContent);
        file_put_contents($releaseDir . "/CRM-Production-Release-{$version}.zip.sha256", $checksumContent);
    } else {
        echo " [WARNING] Could not open ZipArchive for writing.\n";
    }
} else {
    echo " [INFO] ZipArchive extension not present in CLI. Skipping ZIP generation.\n";
}

echo "\n========================================================\n";
echo ">>> PRODUCTION ARTIFACT SUCCESSFULLY CREATED AT:\n";
echo "    Folder: {$releaseDir}\n";
if (file_exists($primaryZipFile)) {
    echo "    Archive: {$primaryZipFile}\n";
    echo "    Workspace Archive: {$workspaceZipFile}\n";
    echo "    SHA-256: {$sha256}\n";
    echo "    Manifest: {$releaseDir}/RELEASE-MANIFEST.md\n";
}
echo "========================================================\n";
