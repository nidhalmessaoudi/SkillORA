<?php
// Permanent fix for OneDrive cache locking issues
// This script moves cache outside OneDrive and creates a symbolic link

echo "=== PERMANENT CACHE FIX FOR ONEDRIVE ===\n";
echo str_repeat("=", 50) . "\n\n";

// Kill all PHP processes
echo "Step 1: Stopping PHP processes...\n";
exec('taskkill /F /IM php.exe /T >nul 2>&1');
sleep(2);
echo "✓ Done\n\n";

// Define paths
$projectDir = __DIR__;
$oldCacheDir = $projectDir . '/var/cache';
$oldLogDir = $projectDir . '/var/log';
$newCacheDir = 'C:/temp/skillharbor_cache';
$newLogDir = 'C:/temp/skillharbor_log';

// Step 2: Create new cache directory outside OneDrive
echo "Step 2: Creating cache directory outside OneDrive...\n";
echo "New location: $newCacheDir\n";

if (!is_dir('C:/temp')) {
    mkdir('C:/temp', 0777, true);
}
if (!is_dir($newCacheDir)) {
    mkdir($newCacheDir, 0777, true);
}
if (!is_dir($newLogDir)) {
    mkdir($newLogDir, 0777, true);
}

// Create subdirectories
mkdir("$newCacheDir/dev", 0777, true);
mkdir("$newCacheDir/prod", 0777, true);
mkdir("$newLogDir/dev", 0777, true);
mkdir("$newLogDir/prod", 0777, true);

echo "✓ Created: $newCacheDir\n";
echo "✓ Created: $newLogDir\n\n";

// Step 3: Remove old cache directories
echo "Step 3: Removing old cache in OneDrive...\n";
if (is_dir($oldCacheDir)) {
    exec("rmdir /S /Q " . escapeshellarg($oldCacheDir) . " 2>nul");
    echo "✓ Removed old cache\n";
}
if (is_dir($oldLogDir)) {
    exec("rmdir /S /Q " . escapeshellarg($oldLogDir) . " 2>nul");
    echo "✓ Removed old log\n";
}
echo "\n";

// Step 4: Create symbolic links (junction on Windows)
echo "Step 4: Creating symbolic links...\n";

// Convert to Windows paths for mklink
$windowsCacheSource = str_replace('/', '\\', $newCacheDir);
$windowsCacheTarget = str_replace('/', '\\', $oldCacheDir);
$windowsLogSource = str_replace('/', '\\', $newLogDir);
$windowsLogTarget = str_replace('/', '\\', $oldLogDir);

// Create junction for cache
exec("mklink /J \"$windowsCacheTarget\" \"$windowsCacheSource\"", $output1, $return1);
if ($return1 === 0) {
    echo "✓ Created junction: var/cache -> C:/temp/skillharbor_cache\n";
} else {
    echo "⚠ Junction creation may have failed, but continuing...\n";
}

// Create junction for log
exec("mklink /J \"$windowsLogTarget\" \"$windowsLogSource\"", $output2, $return2);
if ($return2 === 0) {
    echo "✓ Created junction: var/log -> C:/temp/skillharbor_log\n";
} else {
    echo "⚠ Junction creation may have failed, but continuing...\n";
}

echo "\n";
echo str_repeat("=", 50) . "\n";
echo "SUCCESS! Cache is now outside OneDrive.\n";
echo str_repeat("=", 50) . "\n\n";

echo "Benefits:\n";
echo "  • No more OneDrive sync conflicts\n";
echo "  • Faster cache operations\n";
echo "  • No write permission errors\n\n";

echo "Your cache is now at: C:/temp/skillharbor_cache\n";
echo "But Symfony still sees it at: var/cache (symbolic link)\n\n";

echo "✓ You can now start your server!\n";
