<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$dbHost = '127.0.0.1';
$dbName = 'skillora';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database '$dbName' successfully.\n\n";
    
    // Check if user_roles table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_roles'");
    if ($stmt->rowCount() > 0) {
        echo "✅ user_roles table already exists.\n";
    } else {
        echo "Creating user_roles table...\n";
        
        $sql = "CREATE TABLE user_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_role (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ user_roles table created successfully!\n";
        
        // Populate with existing users
        echo "\nPopulating user_roles with existing users...\n";
        $stmt = $pdo->query("SELECT id FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $insertStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'user') ON DUPLICATE KEY UPDATE role = role");
        $count = 0;
        foreach ($users as $user) {
            $insertStmt->execute([$user['id']]);
            $count++;
        }
        echo "✅ Added $count users to user_roles table with default 'user' role.\n";
    }
    
    // Check if badges table exists (mentioned in earlier schema output)
    $stmt = $pdo->query("SHOW TABLES LIKE 'badges'");
    if ($stmt->rowCount() == 0) {
        echo "\n⚠️  badges table doesn't exist. Creating it...\n";
        
        $sql = "CREATE TABLE badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            icon VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ badges table created successfully!\n";
    } else {
        echo "\n✅ badges table already exists.\n";
    }
    
    // Check if user_badges table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_badges'");
    if ($stmt->rowCount() == 0) {
        echo "\n⚠️  user_badges table doesn't exist. Creating it...\n";
        
        $sql = "CREATE TABLE user_badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            badge_id INT NOT NULL,
            awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_badge (user_id, badge_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ user_badges table created successfully!\n";
    } else {
        echo "\n✅ user_badges table already exists.\n";
    }
    
    // Check if user_settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_settings'");
    if ($stmt->rowCount() == 0) {
        echo "\n⚠️  user_settings table doesn't exist. Creating it...\n";
        
        $sql = "CREATE TABLE user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_setting (user_id, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ user_settings table created successfully!\n";
    } else {
        echo "\n✅ user_settings table already exists.\n";
    }
    
    // Check if user_social_links table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_social_links'");
    if ($stmt->rowCount() == 0) {
        echo "\n⚠️  user_social_links table doesn't exist. Creating it...\n";
        
        $sql = "CREATE TABLE user_social_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            platform VARCHAR(50) NOT NULL,
            url VARCHAR(255) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_platform (user_id, platform)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ user_social_links table created successfully!\n";
    } else {
        echo "\n✅ user_social_links table already exists.\n";
    }
    
    echo "\n🎉 All missing tables have been created successfully!\n";
    echo "\nYou can now access the admin backend.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
