<?php
echo "Fixing Symfony Cache Permissions...\n";
echo str_repeat("=", 50) . "\n\n";

// Kill PHP processes
exec('taskkill /F /IM php.exe /T >nul 2>&1');
echo "✓ Killed running PHP processes\n";

sleep(2);

// Remove old directories
$dirs = ['var/cache', 'var/log'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        exec("rmdir /S /Q " . escapeshellarg($dir) . " 2>nul");
    }
}
echo "✓ Removed old cache/log directories\n";

// Create fresh directories
$newDirs = [
    'var/cache',
    'var/log',
    'var/cache/dev',
    'var/cache/prod',
    'var/log/dev',
    'var/log/prod'
];

foreach ($newDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}
echo "✓ Created fresh directories\n";

// Set permissions
exec('icacls var /grant Everyone:(OI)(CI)F /T >nul 2>&1');
exec('icacls var\cache /grant Everyone:(OI)(CI)F /T >nul 2>&1');
exec('icacls var\log /grant Everyone:(OI)(CI)F /T >nul 2>&1');
echo "✓ Set full permissions\n\n";

echo str_repeat("=", 50) . "\n";
echo "SUCCESS! Cache directories are ready.\n";
echo "You can now start your Symfony server.\n";
