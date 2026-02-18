<?php
// Add Face ID columns to users table
// Run this with: php add_face_id_columns.php

$host = '127.0.0.1';
$db = 'skillora';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully!\n\n";
    
    // Add face_data column
    echo "Adding face_data column...\n";
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS face_data TEXT NULL AFTER avatar
    ");
    echo "✓ face_data column added\n\n";
    
    // Add face_id_enabled column
    echo "Adding face_id_enabled column...\n";
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS face_id_enabled TINYINT(1) DEFAULT 0 AFTER face_data
    ");
    echo "✓ face_id_enabled column added\n\n";
    
    // Show table structure
    echo "Updated table structure:\n";
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $pdo->query("DESCRIBE users");
    printf("%-25s %-20s %-10s %-10s\n", "Field", "Type", "Null", "Default");
    echo str_repeat("-", 100) . "\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (in_array($row['Field'], ['face_data', 'face_id_enabled', 'avatar'])) {
            printf("%-25s %-20s %-10s %-10s\n", 
                $row['Field'], 
                $row['Type'], 
                $row['Null'],
                $row['Default'] ?? 'NULL'
            );
        }
    }
    
    echo str_repeat("-", 100) . "\n\n";
    echo "✓ Face ID columns added successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
