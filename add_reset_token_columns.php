<?php
/**
 * Add reset token columns to users table
 * Run this script once to add the password reset functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

// Database configuration
$connectionParams = [
    'dbname' => 'skillora',
    'user' => 'root',
    'password' => '',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
];

try {
    $conn = DriverManager::getConnection($connectionParams);
    
    echo "Connected to database successfully.\n\n";
    
    // Check if columns already exist
    $sql = "SHOW COLUMNS FROM users LIKE 'reset_token'";
    $result = $conn->executeQuery($sql);
    
    if ($result->rowCount() > 0) {
        echo "Reset token columns already exist. Skipping...\n";
        exit(0);
    }
    
    echo "Adding reset_token and reset_token_expires_at columns...\n";
    
    // Add reset_token column
    $sql = "ALTER TABLE users 
            ADD COLUMN reset_token VARCHAR(255) NULL AFTER verification_token_expires_at,
            ADD COLUMN reset_token_expires_at DATETIME NULL AFTER reset_token";
    
    $conn->executeStatement($sql);
    
    echo "✓ Successfully added reset token columns to users table.\n";
    echo "\nColumns added:\n";
    echo "  - reset_token (VARCHAR 255, nullable)\n";
    echo "  - reset_token_expires_at (DATETIME, nullable)\n";
    echo "\nYou can now use the forgot password functionality!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
