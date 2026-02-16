<?php
// Simple PHP script to add verification columns to users table
// Run this with: php add_verification_columns.php

$host = '127.0.0.1';
$db = 'skillora';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully!\n\n";
    
    // Step 1: Add verification_token column if it doesn't exist
    echo "Adding verification_token column...\n";
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) NULL AFTER is_verified
    ");
    echo "✓ verification_token column added\n\n";
    
    // Step 2: Add verification_token_expires_at column if it doesn't exist
    echo "Adding verification_token_expires_at column...\n";
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS verification_token_expires_at DATETIME NULL AFTER verification_token
    ");
    echo "✓ verification_token_expires_at column added\n\n";
    
    // Step 3: Set all existing users as verified
    echo "Setting all existing users as verified...\n";
    $stmt = $pdo->exec("UPDATE users SET is_verified = 1 WHERE is_verified = 0 OR is_verified IS NULL");
    echo "✓ Updated $stmt users to verified status\n\n";
    
    // Step 4: Show sample of updated users
    echo "Sample of updated users:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-5s %-30s %-30s %-10s %-10s\n", "ID", "Email", "Name", "Active", "Verified");
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $pdo->query("
        SELECT id, email, CONCAT(first_name, ' ', last_name) as name, is_active, is_verified 
        FROM users 
        LIMIT 10
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("%-5s %-30s %-30s %-10s %-10s\n", 
            $row['id'], 
            $row['email'], 
            $row['name'],
            $row['is_active'] ? 'Yes' : 'No',
            $row['is_verified'] ? 'Yes' : 'No'
        );
    }
    
    echo str_repeat("-", 100) . "\n\n";
    
    // Step 5: Show statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_users
        FROM users
    ");
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Database Statistics:\n";
    echo "  Total Users: " . $stats['total_users'] . "\n";
    echo "  Verified Users: " . $stats['verified_users'] . "\n";
    echo "  Unverified Users: " . $stats['unverified_users'] . "\n\n";
    
    echo "✓ Email verification system setup complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
