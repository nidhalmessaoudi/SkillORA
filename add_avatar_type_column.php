<?php
/**
 * Add avatar_type column to users table
 * Run this script once to add 3D avatar selection functionality
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
    
    // Check if column already exists
    $sql = "SHOW COLUMNS FROM users LIKE 'avatar_type'";
    $result = $conn->executeQuery($sql);
    
    if ($result->rowCount() > 0) {
        echo "avatar_type column already exists. Skipping...\n";
        exit(0);
    }
    
    echo "Adding avatar_type column...\n";
    
    // Add avatar_type column after avatar
    $sql = "ALTER TABLE users 
            ADD COLUMN avatar_type VARCHAR(50) NULL AFTER avatar";
    
    $conn->executeStatement($sql);
    
    echo "✓ Successfully added avatar_type column to users table.\n";
    echo "\nColumn added:\n";
    echo "  - avatar_type (VARCHAR 50, nullable)\n";
    echo "\nYou can now use the 3D avatar selection during registration!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
