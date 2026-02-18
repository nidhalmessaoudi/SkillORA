<?php
/**
 * Quick script to check face data in database
 * Run: php check_face_data.php
 */

require_once __DIR__.'/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

// Database connection
$connectionParams = [
    'dbname' => 'skillharbor',
    'user' => 'root',
    'password' => '',
    'host' => '127.0.0.1',
    'driver' => 'pdo_mysql',
];

try {
    $conn = DriverManager::getConnection($connectionParams);
    
    echo "=== Face ID Users in Database ===\n\n";
    
    $sql = "SELECT 
        id,
        email,
        face_id_enabled,
        CHAR_LENGTH(face_data) as data_length,
        CASE 
            WHEN face_data IS NULL THEN '❌ NULL'
            WHEN CHAR_LENGTH(face_data) < 66000 THEN '❌ TRUNCATED (65KB limit)'
            WHEN CHAR_LENGTH(face_data) > 100000 THEN '✅ COMPLETE'
            ELSE '⚠️ UNKNOWN'
        END as status,
        LEFT(face_data, 100) as data_sample
    FROM users 
    WHERE face_id_enabled = 1
    ORDER BY id DESC";
    
    $result = $conn->executeQuery($sql);
    $users = $result->fetchAllAssociative();
    
    if (empty($users)) {
        echo "No users with Face ID enabled found.\n";
        echo "\nPlease register a new account with Face ID!\n";
    } else {
        foreach ($users as $user) {
            echo "User ID: {$user['id']}\n";
            echo "Email: {$user['email']}\n";
            echo "Face ID Enabled: " . ($user['face_id_enabled'] ? 'YES' : 'NO') . "\n";
            echo "Data Length: " . number_format($user['data_length']) . " characters\n";
            echo "Status: {$user['status']}\n";
            echo "Sample: " . substr($user['data_sample'], 0, 80) . "...\n";
            echo "\n";
            
            // Check JSON validity
            if ($user['data_length'] > 0) {
                $faceData = $conn->executeQuery("SELECT face_data FROM users WHERE id = ?", [$user['id']])->fetchOne();
                $decoded = json_decode($faceData, true);
                
                if ($decoded === null) {
                    echo "⚠️ WARNING: JSON is INVALID (truncated data)\n";
                } else {
                    $imageCount = isset($decoded['images']) ? count($decoded['images']) : 0;
                    echo "✅ JSON is valid - Contains {$imageCount} images\n";
                    
                    if ($imageCount > 0) {
                        echo "   Image 1 length: " . number_format(strlen($decoded['images'][0])) . " chars\n";
                        if ($imageCount > 1) {
                            echo "   Image 2 length: " . number_format(strlen($decoded['images'][1])) . " chars\n";
                        }
                        if ($imageCount > 2) {
                            echo "   Image 3 length: " . number_format(strlen($decoded['images'][2])) . " chars\n";
                        }
                    }
                }
            }
            
            echo str_repeat('-', 70) . "\n\n";
        }
    }
    
    // Check column type
    echo "=== Database Column Info ===\n\n";
    $columnInfo = $conn->executeQuery("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'face_data'")->fetchAssociative();
    
    echo "Column Type: {$columnInfo['DATA_TYPE']}\n";
    echo "Max Length: " . number_format($columnInfo['CHARACTER_MAXIMUM_LENGTH']) . " characters\n";
    
    if ($columnInfo['DATA_TYPE'] === 'longtext') {
        echo "✅ Column type is correct (LONGTEXT)\n";
    } else {
        echo "❌ Column type is wrong! Should be LONGTEXT\n";
        echo "   Run: php bin/console doctrine:query:sql \"ALTER TABLE users MODIFY COLUMN face_data LONGTEXT\"\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
