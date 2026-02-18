<?php
// View Face ID authentication logs

$logFile = __DIR__ . '/var/log/face_auth.log';

if (!file_exists($logFile)) {
    echo "Log file not found: $logFile\n";
    echo "Try logging in with Face ID first to generate logs.\n";
    exit;
}

echo "=== Face ID Authentication Log ===\n\n";
echo file_get_contents($logFile);
echo "\n=== End of Log ===\n";
