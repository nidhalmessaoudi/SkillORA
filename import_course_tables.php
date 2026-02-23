<?php
// Import Course and Reservation Tables
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_NAME'] ?? 'skillora';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

echo "Importing Course & Reservation Tables\n";
echo str_repeat("=", 60) . "\n\n";
echo "Database: {$dbname}\n";
echo "Host: {$host}:{$port}\n\n";

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname}", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database\n\n";
    
    $tables = [
        'course' => "CREATE TABLE IF NOT EXISTS course (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            thumbnail VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'course_section' => "CREATE TABLE IF NOT EXISTS course_section (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            position INT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            course_id INT NOT NULL,
            INDEX idx_course (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'lesson' => "CREATE TABLE IF NOT EXISTS lesson (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL,
            content LONGTEXT DEFAULT NULL,
            file_path VARCHAR(255) DEFAULT NULL,
            position INT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            section_id INT NOT NULL,
            INDEX idx_section (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'reservation' => "CREATE TABLE IF NOT EXISTS reservation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            salle_id INT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            telephone VARCHAR(20) NOT NULL,
            adresse VARCHAR(255) DEFAULT NULL,
            nombre_places INT NOT NULL,
            date_reservation DATETIME NOT NULL,
            user_id INT UNSIGNED DEFAULT NULL,
            INDEX idx_event (event_id),
            INDEX idx_salle (salle_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    echo "Creating course system tables...\n";
    echo str_repeat("-", 60) . "\n";
    
    $created = 0;
    foreach ($tables as $table => $sql) {
        try {
            $check = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($check->rowCount() > 0) {
                echo "→ {$table}: Already exists ✓\n";
            } else {
                $pdo->exec($sql);
                echo "→ {$table}: Created ✓\n";
                $created++;
            }
        } catch (PDOException $e) {
            echo "→ {$table}: ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    echo str_repeat("-", 60) . "\n";
    echo "Tables: {$created} created, " . (count($tables) - $created) . " already existed\n\n";
    
    // Verify
    echo "Verifying tables:\n";
    foreach (array_keys($tables) as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
        $count = $stmt->fetchColumn();
        echo "  ✓ {$table}: {$count} rows\n";
    }
    
    echo "\n" . str_repeat("✓", 25) . "\n";
    echo "SUCCESS! Course system imported!\n";
    echo str_repeat("✓", 25) . "\n\n";
    echo "Your database now has:\n";
    echo "  • course - Courses catalog\n";
    echo "  • course_section - Course sections/modules\n";
    echo "  • lesson - Individual lessons\n";
    echo "  • reservation - Event/salle reservations\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Import completed!\n";
