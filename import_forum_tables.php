<?php
// Script to import forum tables - Simple version
// Run: php import_forum_tables.php

echo "Importing Forum Tables to SkillORA Database\n";
echo str_repeat("=", 60) . "\n\n";

// Read .env file manually
$envContent = file_get_contents(__DIR__ . '/.env');

// Extract DATABASE_URL
if (!preg_match('/DATABASE_URL="([^"]+)"/', $envContent, $matches)) {
    echo "Error: DATABASE_URL not found in .env\n";
    exit(1);
}

$dbUrl = $matches[1];

// Parse the database URL
preg_match('/mysql:\/\/([^:@]+)(?::([^@]*))?@([^:]+):(\d+)\/([^?]+)/', $dbUrl, $parts);

if (!$parts) {
    // Try alternative format
    preg_match('/mysql:\/\/([^:]+):([^@]*)@([^:]+):(\d+)\/(.+)/', $dbUrl, $parts);
}

if (!$parts) {
    echo "Error: Could not parse DATABASE_URL\n";
    echo "URL format: {$dbUrl}\n";
    exit(1);
}

$user = $parts[1];
$pass = $parts[2] ?? '';
$host = $parts[3];
$port = $parts[4];
$dbname = $parts[5];

echo "Database: {$dbname}\n";
echo "Host: {$host}:{$port}\n";
echo "User: {$user}\n\n";

// Connect to database
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✓ Connected to database successfully\n\n";
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Define table creation SQL
$tables = [
    'tag' => "CREATE TABLE IF NOT EXISTS tag (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        slug VARCHAR(120) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'post' => "CREATE TABLE IF NOT EXISTS post (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        topic VARCHAR(100) DEFAULT NULL,
        content LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'post_tag' => "CREATE TABLE IF NOT EXISTS post_tag (
        post_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (post_id, tag_id),
        INDEX idx_tag (tag_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'reply' => "CREATE TABLE IF NOT EXISTS reply (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        author_name VARCHAR(180) DEFAULT NULL,
        upvotes INT DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX idx_post (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'reaction' => "CREATE TABLE IF NOT EXISTS reaction (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_identifier VARCHAR(191) NOT NULL,
        type VARCHAR(20) NOT NULL,
        created_at DATETIME NOT NULL,
        post_id INT DEFAULT NULL,
        reply_id INT DEFAULT NULL,
        UNIQUE KEY uniq_user_post (user_identifier, post_id),
        UNIQUE KEY uniq_user_reply (user_identifier, reply_id),
        INDEX idx_post (post_id),
        INDEX idx_reply (reply_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
];

echo "Creating forum tables...\n";
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
            echo "✗ Error: " . substr($e->getMessage(), 0, 50) . "...\n";
        }
    }
}

echo str_repeat("-", 60) . "\n";
echo "Tables: {$created} created, {$existing} already existed\n\n";

// Insert default tags
$defaultTags = [
    ['javascript', 'javascript'],
    ['python', 'python'],
    ['php', 'php'],
    ['symfony', 'symfony'],
    ['react', 'react'],
    ['vue', 'vue'],
    ['angular', 'angular'],
    ['node', 'node'],
    ['career', 'career'],
    ['devops', 'devops'],
    ['database', 'database'],
    ['testing', 'testing']
];

echo "Adding default tags...\n";
$tagsAdded = 0;

foreach ($defaultTags as [$name, $slug]) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO tag (name, slug) VALUES (?, ?)");
        $stmt->execute([$name, $slug]);
        if ($stmt->rowCount() > 0) {
            $tagsAdded++;
        }
    } catch (PDOException $e) {
        // Ignore errors
    }
}

echo "{$tagsAdded} tags added/updated\n\n";

// Verify all tables
echo "Verifying installation:\n";
$tableNames = ['tag', 'post', 'post_tag', 'reply', 'reaction'];
$allOk = true;

foreach ($tableNames as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        if ($table === 'tag') {
            echo "  ✓ {$table}: {$count} tags\n";
        } else {
            echo "  ✓ {$table}: {$count} rows\n";
        }
    } catch (PDOException $e) {
        echo "  ✗ {$table}: Not found\n";
        $allOk = false;
    }
}

echo "\n";
if ($allOk) {
    echo str_repeat("✓", 20) . "\n";
    echo "SUCCESS! Forum tables imported!\n";
    echo str_repeat("✓", 20) . "\n";
    echo "\nYour database now has:\n";
    echo "  • tag - Categories for posts\n";
    echo "  • post - Forum posts/questions\n";
    echo "  • post_tag - Links posts to tags\n";
    echo "  • reply - Replies to posts\n";
    echo "  • reaction - Likes/loves on content\n";
} else {
    echo "⚠ Some tables may have issues\n";
}

echo "\n✓ Import completed!\n";
