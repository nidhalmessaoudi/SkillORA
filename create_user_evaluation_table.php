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
    
    // Check if user_evaluation table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_evaluation'");
    if ($stmt->rowCount() > 0) {
        echo "✅ user_evaluation table already exists.\n";
    } else {
        echo "Creating user_evaluation table...\n";
        
        $sql = "CREATE TABLE user_evaluation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            evaluation_id INT NOT NULL,
            started_at DATETIME NOT NULL,
            submitted_at DATETIME NULL,
            score INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (evaluation_id) REFERENCES evaluation(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_evaluation (evaluation_id),
            UNIQUE KEY unique_user_eval (user_id, evaluation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ user_evaluation table created successfully!\n";
    }
    
    echo "\n🎉 Database ready for evaluation system!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
