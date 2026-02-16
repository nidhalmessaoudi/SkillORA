<?php
// Update user roles to include professors
// Run this with: php update_user_roles.php

$host = '127.0.0.1';
$db = 'skillora';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully!\n\n";
    
    // Define role assignments
    $roleAssignments = [
        // ID 2: Admin
        2 => 'admin',
        
        // ID 4 and 7: Professors
        4 => 'professor',
        7 => 'professor',
        
        // ID 1, 5, 8: Students
        1 => 'student',
        5 => 'student',
        8 => 'student',
    ];
    
    echo "Updating user roles...\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($roleAssignments as $userId => $role) {
        // Get user details
        $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Update role
            $stmt = $pdo->prepare("
                UPDATE user_roles 
                SET role = ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$role, $userId]);
            
            $roleDisplay = strtoupper($role);
            echo sprintf("✓ User ID %d: %-35s → %s\n", 
                $userId, 
                $user['email'], 
                $roleDisplay
            );
        }
    }
    
    echo str_repeat("-", 80) . "\n\n";
    
    // Show final role distribution
    echo "Final user roles:\n";
    echo str_repeat("=", 100) . "\n";
    printf("%-5s %-35s %-25s %-15s\n", "ID", "Email", "Name", "Role");
    echo str_repeat("=", 100) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            u.id, 
            u.email, 
            CONCAT(u.first_name, ' ', u.last_name) as name,
            ur.role 
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        ORDER BY 
            CASE ur.role 
                WHEN 'admin' THEN 1 
                WHEN 'professor' THEN 2 
                WHEN 'student' THEN 3 
                ELSE 4 
            END,
            u.id
    ");
    
    $stats = ['admin' => 0, 'professor' => 0, 'student' => 0];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $roleDisplay = strtoupper($row['role']);
        
        // Color coding
        if ($row['role'] === 'admin') {
            $roleDisplay = "🔴 ADMIN";
        } elseif ($row['role'] === 'professor') {
            $roleDisplay = "🟢 PROFESSOR";
        } else {
            $roleDisplay = "🔵 STUDENT";
        }
        
        printf("%-5s %-35s %-25s %-15s\n", 
            $row['id'], 
            $row['email'], 
            $row['name'],
            $roleDisplay
        );
        
        $stats[$row['role']]++;
    }
    
    echo str_repeat("=", 100) . "\n\n";
    
    // Show statistics
    echo "Role Distribution:\n";
    echo "  Admins:     {$stats['admin']} user(s)\n";
    echo "  Professors: {$stats['professor']} user(s)\n";
    echo "  Students:   {$stats['student']} user(s)\n";
    echo "  TOTAL:      " . array_sum($stats) . " user(s)\n\n";
    
    echo "✓ User roles updated successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
