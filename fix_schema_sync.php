<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// Database connection
$dbHost = '127.0.0.1';
$dbName = 'skillora';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database '$dbName' successfully.\n\n";
    
    // Fix Reply table - drop old column if exists, ensure correct column exists
    echo "Fixing Reply table...\n";
    
    // Check if updatedAt column exists (old column)
    $stmt = $pdo->query("SHOW COLUMNS FROM reply LIKE 'updatedAt'");
    if ($stmt->rowCount() > 0) {
        echo "  - Dropping old 'updatedAt' column\n";
        $pdo->exec("ALTER TABLE reply DROP COLUMN updatedAt");
    }
    
    // Check if updated_at exists
    $stmt = $pdo->query("SHOW COLUMNS FROM reply LIKE 'updated_at'");
    if ($stmt->rowCount() == 0) {
        echo "  - Adding 'updated_at' column\n";
        $pdo->exec("ALTER TABLE reply ADD COLUMN updated_at DATETIME DEFAULT NULL AFTER created_at");
    }
    
    // Fix Reaction table - ensure user_identifier is dropped
    echo "\nFixing Reaction table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM reaction LIKE 'user_identifier'");
    if ($stmt->rowCount() > 0) {
        echo "  - Dropping 'user_identifier' column\n";
        try {
            $pdo->exec("ALTER TABLE reaction DROP COLUMN user_identifier");
        } catch (Exception $e) {
            echo "  - Column already dropped or doesn't exist\n";
        }
    } else {
        echo "  - user_identifier column already removed\n";
    }
    
    // Fix unique constraints on reaction
    echo "  - Fixing unique constraints\n";
    
    // Drop old constraints if exist
    try {
        $pdo->exec("ALTER TABLE reaction DROP INDEX uniq_user_reply");
    } catch (Exception $e) {
        // Constraint might not exist
    }
    
    try {
        $pdo->exec("ALTER TABLE reaction DROP INDEX uniq_user_post");
    } catch (Exception $e) {
        // Constraint might not exist
    }
    
    // Add correct unique constraints
    try {
        $pdo->exec("ALTER TABLE reaction ADD UNIQUE KEY uniq_user_reply (user_id, reply_id)");
        echo "  - Added uniq_user_reply constraint\n";
    } catch (Exception $e) {
        echo "  - uniq_user_reply already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE reaction ADD UNIQUE KEY uniq_user_post (user_id, post_id)");
        echo "  - Added uniq_user_post constraint\n";
    } catch (Exception $e) {
        echo "  - uniq_user_post already exists\n";
    }
    
    // Fix Answer table indexes (rename idx_question to proper format)
    echo "\nFixing Answer table indexes...\n";
    
    // Check if new index already exists
    $stmt = $pdo->query("SHOW INDEX FROM answer WHERE Key_name = 'IDX_DADD4A251E27F6BF'");
    if ($stmt->rowCount() == 0) {
        // Only need to rename if new one doesn't exist
        $stmt = $pdo->query("SHOW INDEX FROM answer WHERE Key_name = 'idx_question'");
        if ($stmt->rowCount() > 0) {
            echo "  - Index idx_question exists, new index will be created alongside it\n";
            try {
                $pdo->exec("ALTER TABLE answer ADD INDEX IDX_DADD4A251E27F6BF (question_id)");
                echo "  - Created new IDX_DADD4A251E27F6BF index\n";
            } catch (Exception $e) {
                echo "  - Index already exists or error: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "  - IDX_DADD4A251E27F6BF index already exists\n";
    }
    
    // Fix Question table indexes
    echo "\nFixing Question table indexes...\n";
    
    $stmt = $pdo->query("SHOW INDEX FROM question WHERE Key_name = 'IDX_B6F7494E456C5646'");
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->query("SHOW INDEX FROM question WHERE Key_name = 'idx_evaluation'");
        if ($stmt->rowCount() > 0) {
            echo "  - Index idx_evaluation exists, creating new index alongside it\n";
            try {
                $pdo->exec("ALTER TABLE question ADD INDEX IDX_B6F7494E456C5646 (evaluation_id)");
                echo "  - Created new IDX_B6F7494E456C5646 index\n";
            } catch (Exception $e) {
                echo "  - Index already exists or error: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "  - IDX_B6F7494E456C5646 index already exists\n";
    }
    
    // Fix Post table indexes
    echo "\nFixing Post table indexes...\n";
    // Foreign key indexes cannot be dropped, just verify the expected index exists
    $stmt = $pdo->query("SHOW INDEX FROM post WHERE Key_name = 'IDX_5A8A6C8DA76ED395'");
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->query("SHOW INDEX FROM post WHERE Key_name = 'FK_post_user'");
        if ($stmt->rowCount() > 0) {
            echo "  - FK_post_user index exists (required for foreign key)\n";
        }
    } else {
        echo "  - IDX_5A8A6C8DA76ED395 index exists\n";
    }
    
    // Fix Reply table indexes
    echo "\nFixing Reply table indexes...\n";
    
    // Check user_id index
    $stmt = $pdo->query("SHOW INDEX FROM reply WHERE Key_name = 'IDX_FDA8C6E0A76ED395'");
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->query("SHOW INDEX FROM reply WHERE Key_name = 'FK_reply_user'");
        if ($stmt->rowCount() > 0) {
            echo "  - FK_reply_user index exists (required for foreign key)\n";
        }
    } else {
        echo "  - IDX_FDA8C6E0A76ED395 index exists\n";
    }
    
    // Check post_id index
    $stmt = $pdo->query("SHOW INDEX FROM reply WHERE Key_name = 'IDX_FDA8C6E04B89032C'");
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->query("SHOW INDEX FROM reply WHERE Key_name = 'idx_post'");
        if ($stmt->rowCount() > 0) {
            echo "  - idx_post index exists\n";
        }
    } else {
        echo "  - IDX_FDA8C6E04B89032C index exists\n";
    }
    
    // Fix Reaction table indexes
    echo "\nFixing Reaction table indexes...\n";
    
    $stmt = $pdo->query("SHOW INDEX FROM reaction WHERE Key_name = 'IDX_A4D707F7A76ED395'");
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->query("SHOW INDEX FROM reaction WHERE Key_name = 'FK_reaction_user'");
        if ($stmt->rowCount() > 0) {
            echo "  - FK_reaction_user index exists (required for foreign key)\n";
        }
    } else {
        echo "  - IDX_A4D707F7A76ED395 index exists\n";
    }
    
    echo "\n✅ Schema fixes completed successfully!\n";
    echo "\nNow run: php bin/console doctrine:schema:validate\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
