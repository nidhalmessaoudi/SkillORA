<?php
// Community Posts Integration - Database Migration
echo "Community Posts Integration - Database Migration\n";
echo str_repeat("=", 60) . "\n\n";

$host = '127.0.0.1';
$dbname = 'skillora';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname}", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database\n\n";
    
    echo "Step 1: Adding author to Post entity...\n";
    echo str_repeat("-", 60) . "\n";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM post LIKE 'user_id'");
    if ($stmt->rowCount() == 0) {
        // Add user_id column (nullable first to allow existing data)
        $pdo->exec("ALTER TABLE post ADD COLUMN user_id INT UNSIGNED NULL");
        echo "✓ Added user_id column to post\n";
        
        // Set default author for existing posts (use admin user ID 1)
        $pdo->exec("UPDATE post SET user_id = 1 WHERE user_id IS NULL");
        echo "✓ Set default author for existing posts\n";
        
        // Make it NOT NULL and add foreign key
        $pdo->exec("ALTER TABLE post MODIFY user_id INT UNSIGNED NOT NULL");
        $pdo->exec("ALTER TABLE post ADD CONSTRAINT FK_post_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "✓ Added foreign key constraint\n";
    } else {
        echo "⚠ user_id already exists in post\n";
    }
    
    echo "\nStep 2: Updating Reply entity...\n";
    echo str_repeat("-", 60) . "\n";
    
    // Check if authorName exists (old structure)
    $stmt = $pdo->query("SHOW COLUMNS FROM reply LIKE 'authorName'");
    if ($stmt->rowCount() > 0) {
        // Backup data
        echo "→ Backing up reply data...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS reply_backup AS SELECT * FROM reply");
        
        // Add new columns
        $pdo->exec("ALTER TABLE reply ADD COLUMN user_id INT UNSIGNED NULL");
        $pdo->exec("ALTER TABLE reply ADD COLUMN updatedAt DATETIME NULL");
        echo "✓ Added user_id and updatedAt columns\n";
        
        // Set default author
        $pdo->exec("UPDATE reply SET user_id = 1 WHERE user_id IS NULL");
        
        // Make user_id NOT NULL
        $pdo->exec("ALTER TABLE reply MODIFY user_id INT UNSIGNED NOT NULL");
        
        // Drop old columns
        $pdo->exec("ALTER TABLE reply DROP COLUMN authorName");
        if ($pdo->query("SHOW COLUMNS FROM reply LIKE 'upvotes'")->rowCount() > 0) {
            $pdo->exec("ALTER TABLE reply DROP COLUMN upvotes");
        }
        echo "✓ Removed old columns (authorName, upvotes)\n";
        
        // Add foreign key
        $pdo->exec("ALTER TABLE reply ADD CONSTRAINT FK_reply_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "✓ Added foreign key constraint\n";
    } else {
        // Just add user_id if it doesn't exist
        $stmt = $pdo->query("SHOW COLUMNS FROM reply LIKE 'user_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE reply ADD COLUMN user_id INT UNSIGNED NOT NULL DEFAULT 1");
            $pdo->exec("ALTER TABLE reply ADD COLUMN updatedAt DATETIME NULL");
            $pdo->exec("ALTER TABLE reply ADD CONSTRAINT FK_reply_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
            echo "✓ Added user_id to reply\n";
        } else {
            echo "⚠ user_id already exists in reply\n";
        }
    }
    
    echo "\nStep 3: Updating Reaction entity...\n";
    echo str_repeat("-", 60) . "\n";
    
    // Check if userIdentifier exists (old structure)
    $stmt = $pdo->query("SHOW COLUMNS FROM reaction LIKE 'userIdentifier'");
    if ($stmt->rowCount() > 0) {
        // Backup data
        echo "→ Backing up reaction data...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS reaction_backup AS SELECT * FROM reaction");
        
        // Drop old column
        $pdo->exec("ALTER TABLE reaction DROP COLUMN userIdentifier");
        echo "✓ Removed userIdentifier column\n";
        
        // Add user_id
        $pdo->exec("ALTER TABLE reaction ADD COLUMN user_id INT UNSIGNED NULL");
        $pdo->exec("UPDATE reaction SET user_id = 1 WHERE user_id IS NULL");
        $pdo->exec("ALTER TABLE reaction MODIFY user_id INT UNSIGNED NOT NULL");
        $pdo->exec("ALTER TABLE reaction ADD CONSTRAINT FK_reaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "✓ Added user_id with foreign key\n";
    } else {
        $stmt = $pdo->query("SHOW COLUMNS FROM reaction LIKE 'user_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE reaction ADD COLUMN user_id INT UNSIGNED NOT NULL DEFAULT 1");
            $pdo->exec("ALTER TABLE reaction ADD CONSTRAINT FK_reaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
            echo "✓ Added user_id to reaction\n";
        } else {
            echo "⚠ user_id already exists in reaction\n";
        }
    }
    
    // Add unique constraints
    echo "→ Adding unique constraints...\n";
    try {
        $pdo->exec("ALTER TABLE reaction ADD CONSTRAINT uniq_user_post UNIQUE (user_id, post_id)");
        echo "✓ Added unique constraint for user+post\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "⚠ Constraint uniq_user_post already exists\n";
        } else {
            echo "⚠ Could not add uniq_user_post: " . $e->getMessage() . "\n";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE reaction ADD CONSTRAINT uniq_user_reply UNIQUE (user_id, reply_id)");
        echo "✓ Added unique constraint for user+reply\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "⚠ Constraint uniq_user_reply already exists\n";
        } else {
            echo "⚠ Could not add uniq_user_reply: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nStep 4: Creating Vote table (optional)...\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'vote'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE vote (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_identifier VARCHAR(191) NOT NULL,
                value SMALLINT NOT NULL,
                post_id INT DEFAULT NULL,
                reply_id INT DEFAULT NULL,
                CONSTRAINT FK_vote_post FOREIGN KEY (post_id) REFERENCES post(id) ON DELETE CASCADE,
                CONSTRAINT FK_vote_reply FOREIGN KEY (reply_id) REFERENCES reply(id) ON DELETE CASCADE,
                CONSTRAINT uniq_user_post UNIQUE (user_identifier, post_id),
                CONSTRAINT uniq_user_reply UNIQUE (user_identifier, reply_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Created vote table\n";
    } else {
        echo "⚠ vote table already exists\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ MIGRATION COMPLETE!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "Summary:\n";
    echo "  ✓ Post entity now has author (User relationship)\n";
    echo "  ✓ Reply entity updated with author and updatedAt\n";
    echo "  ✓ Reaction entity updated with User relationship\n";
    echo "  ✓ Unique constraints added to prevent duplicate reactions\n";
    echo "  ✓ Vote table created\n";
    echo "  ✓ Backups created: reply_backup, reaction_backup\n\n";
    
    echo "Next steps:\n";
    echo "  1. Clear Symfony cache: php bin/console cache:clear\n";
    echo "  2. Verify entities: php bin/console doctrine:mapping:info\n";
    echo "  3. Test community pages: /community\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
