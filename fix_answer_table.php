<?php

require __DIR__ . '/vendor/autoload.php';

$pdo = new PDO("mysql:host=127.0.0.1;dbname=skillora;charset=utf8mb4", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Checking answer table structure...\n\n";

// Get current columns
$stmt = $pdo->query("SHOW COLUMNS FROM answer");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Current columns:\n";
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

// Check if we need to rename or add column
$hasIsCorrectAnswer = false;
$hasIsCorrect = false;

foreach ($columns as $col) {
    if ($col['Field'] === 'is_correct_answer') $hasIsCorrectAnswer = true;
    if ($col['Field'] === 'is_correct') $hasIsCorrect = true;
}

echo "\n";

if ($hasIsCorrectAnswer && !$hasIsCorrect) {
    echo "Renaming 'is_correct_answer' to 'is_correct'...\n";
    $pdo->exec("ALTER TABLE answer CHANGE is_correct_answer is_correct TINYINT(1) DEFAULT NULL");
    echo "✓ Column renamed\n";
} elseif (!$hasIsCorrect) {
    echo "Adding 'is_correct' column...\n";
    $pdo->exec("ALTER TABLE answer ADD COLUMN is_correct TINYINT(1) DEFAULT NULL AFTER content");
    echo "✓ Column added\n";
} else {
    echo "✓ Column 'is_correct' already exists\n";
}

echo "\nNow creating sample answers...\n";

try {
    $pdo->exec("INSERT INTO answer (question_id, content, is_correct, role, created_at) VALUES 
        (1, 'A programming language', 1, 'CHOICE', NOW()),
        (1, 'A database', 0, 'CHOICE', NOW()),
        (1, 'An operating system', 0, 'CHOICE', NOW()),
        (2, 'Model View Controller', 1, 'CHOICE', NOW()),
        (2, 'Most Valuable Code', 0, 'CHOICE', NOW()),
        (3, 'A PHP Framework', 1, 'CHOICE', NOW()),
        (3, 'A JavaScript Library', 0, 'CHOICE', NOW())
    ");
    
    echo "✓ Created 7 sample answers for 3 questions\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo "⚠ Answers already exist\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Done!\n";
