<?php
// Create user_roles table
// Run this with: php create_user_roles_table.php

$host = '127.0.0.1';
$db = 'skillora';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully!\n\n";
    
    // Create user_roles table
    echo "Creating user_roles table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_roles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_role (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ user_roles table created\n\n";
    
    // Check if users already have roles assigned
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_roles");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        echo "Assigning roles to existing users...\n";
        
        // Get all users
        $users = $pdo->query("SELECT id, email FROM users")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            // Assign admin role to admin email
            if (strpos($user['email'], 'admin') !== false) {
                $role = 'admin';
            } else {
                $role = 'student';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO user_roles (user_id, role, created_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE role = ?
            ");
            $stmt->execute([$user['id'], $role, $role]);
            
            echo "  ✓ User ID {$user['id']} ({$user['email']}): {$role}\n";
        }
        echo "\n";
    } else {
        echo "Roles already assigned ({$count} entries found)\n\n";
    }
    
    // Show current roles
    echo "Current user roles:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-5s %-35s %-20s %-15s\n", "ID", "Email", "Role", "Created");
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $pdo->query("
        SELECT ur.id, u.email, ur.role, ur.created_at 
        FROM user_roles ur
        JOIN users u ON ur.user_id = u.id
        ORDER BY ur.id
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("%-5s %-35s %-20s %-15s\n", 
            $row['id'], 
            $row['email'], 
            $row['role'],
            date('Y-m-d H:i', strtotime($row['created_at']))
        );
    }
    
    echo str_repeat("-", 80) . "\n";
    echo "\n✓ user_roles table setup complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
