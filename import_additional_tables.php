<?php
// Import additional SQL tables (quiz, assessment, events)
// Run: php import_additional_tables.php

echo "Importing Additional Tables to SkillORA Database\n";
echo str_repeat("=", 60) . "\n\n";

// Read database credentials from .env
$envFile = file(__DIR__ . '/.env');
$dbUrl = null;

foreach ($envFile as $line) {
    if (strpos($line, 'DATABASE_URL=') === 0) {
        $dbUrl = trim(substr($line, strlen('DATABASE_URL=')), '"');
        break;
    }
}

if (!$dbUrl) {
    echo "Error: DATABASE_URL not found\n";
    exit(1);
}

// Parse database URL
preg_match('/mysql:\/\/([^:@]+)(?::([^@]*))?@([^:]+):(\d+)\/([^?]+)/', $dbUrl, $parts);

if (!$parts) {
    echo "Error: Could not parse database URL\n";
    exit(1);
}

$user = $parts[1];
$pass = $parts[2] ?? '';
$host = $parts[3];
$port = $parts[4];
$dbname = $parts[5];

echo "Database: {$dbname}\n";
echo "Host: {$host}:{$port}\n\n";

// Connect
try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database\n\n";
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Define new tables to create
$tables = [
    'evaluation' => "CREATE TABLE IF NOT EXISTS evaluation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        type VARCHAR(20) NOT NULL,
        duration INT NOT NULL,
        total_score INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    
    'question' => "CREATE TABLE IF NOT EXISTS question (
        id INT AUTO_INCREMENT PRIMARY KEY,
        content TEXT NOT NULL,
        type VARCHAR(20) NOT NULL,
        score INT NOT NULL,
        evaluation_id INT NOT NULL,
        INDEX idx_evaluation (evaluation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    
    'answer' => "CREATE TABLE IF NOT EXISTS answer (
        id INT AUTO_INCREMENT PRIMARY KEY,
        content TEXT NOT NULL,
        is_correct TINYINT(1) DEFAULT NULL,
        role VARCHAR(20) NOT NULL,
        question_id INT NOT NULL,
        student_id INT(10) UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_question (question_id),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    
    'event' => "CREATE TABLE IF NOT EXISTS event (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        price_type VARCHAR(50) NOT NULL,
        image VARCHAR(255) DEFAULT NULL,
        salle_id INT(11) DEFAULT NULL,
        INDEX idx_salle (salle_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    
    'message' => "CREATE TABLE IF NOT EXISTS message (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contenu TEXT NOT NULL,
        type VARCHAR(20) NOT NULL,
        fichier VARCHAR(255) DEFAULT NULL,
        url VARCHAR(255) DEFAULT NULL,
        lu TINYINT(1) NOT NULL DEFAULT 0,
        cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        rendez_vous_id INT(11) NOT NULL,
        INDEX idx_rendez_vous (rendez_vous_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    
    'rendez_vous' => "CREATE TABLE IF NOT EXISTS rendez_vous (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titre VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        date DATETIME NOT NULL,
        duree INT NOT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT 'planifie',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
];

echo "Creating quiz and event tables...\n";
echo str_repeat("-", 60) . "\n";

$created = 0;
$existing = 0;

foreach ($tables as $name => $sql) {
    echo "→ Creating {$name}... ";
    
    try {
        $pdo->exec($sql);
        echo "✓ Created\n";
        $created++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ Already exists\n";
            $existing++;
        } else {
            echo "✗ Error\n";
        }
    }
}

echo str_repeat("-", 60) . "\n";
echo "Tables: {$created} created, {$existing} already existed\n\n";

// Verify
echo "Verifying tables:\n";
$tableNames = ['evaluation', 'question', 'answer', 'event', 'message', 'rendez_vous'];
$allOk = true;

foreach ($tableNames as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "  ✓ {$table}: {$count} rows\n";
    } catch (PDOException $e) {
        echo "  ✗ {$table}: Error\n";
        $allOk = false;
    }
}

echo "\n";
if ($allOk) {
    echo str_repeat("✓", 20) . "\n";
    echo "SUCCESS! Additional tables imported!\n";
    echo str_repeat("✓", 20) . "\n";
    echo "\nYour database now has:\n";
    echo "  • evaluation - Exams and quizzes\n";
    echo "  • question - Quiz questions\n";
    echo "  • answer - Answers and submissions\n";
    echo "  • event - Events and hackathons\n";
    echo "  • message - Messages\n";
    echo "  • rendez_vous - Appointments\n";
} else {
    echo "⚠ Some issues detected\n";
}

echo "\n✓ Import completed!\n";
