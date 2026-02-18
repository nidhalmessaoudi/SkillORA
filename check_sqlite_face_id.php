<?php

// Check SQLite database for Face ID users

$db = new PDO('sqlite:var/skillora.db');

echo "=== SQLite Face ID Check ===\n\n";

// Find user table
$stmt = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name LIKE "%user%"');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "User-related tables: " . implode(', ', $tables) . "\n\n";

// Check for users with face_id_enabled
if (!in_array('user', $tables)) {
    echo "❌ 'user' table not found!\n";
    exit;
}

// Get table structure
echo "User table structure:\n";
$stmt = $db->query('PRAGMA table_info(user)');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['name']} ({$col['type']})\n";
}
echo "\n";

// Check if face_id_enabled column exists
$hasColumnsFaceId = false;
foreach ($columns as $col) {
    if ($col['name'] === 'face_id_enabled') {
        $hasColumnsFaceId = true;
        break;
    }
}

if (!$hasColumnsFaceId) {
    echo "❌ face_id_enabled column NOT FOUND in user table\n";
    echo "   You need to add Face ID columns to the database.\n\n";
    exit;
}

// Get users with Face ID
$stmt = $db->query('SELECT id, email, username, face_id_enabled, LENGTH(face_data) as data_length FROM user WHERE face_id_enabled = 1');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Users with Face ID enabled: " . count($users) . "\n\n";

if (empty($users)) {
    echo "❌ No users have Face ID enabled.\n";
    echo "   Please register a new account with Face ID.\n\n";
    exit;
}

foreach ($users as $user) {
    echo "User: {$user['email']}\n";
    echo "  - ID: {$user['id']}\n";
    echo "  - Face data length: " . ($user['data_length'] ?? 0) . " bytes\n";
    
    // Get full face data
    $stmt = $db->prepare('SELECT face_data FROM user WHERE id = ?');
    $stmt->execute([$user['id']]);
    $faceData = $stmt->fetchColumn();
    
    if (empty($faceData)) {
        echo "  ❌ Face data is EMPTY\n\n";
        continue;
    }
    
    // Check if it's valid JSON
    $parsed = json_decode($faceData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "  ❌ Face data is NOT valid JSON: " . json_last_error_msg() . "\n";
        echo "  First 100 chars: " . substr($faceData, 0, 100) . "...\n\n";
        continue;
    }
    
    echo "  ✓ Face data is valid JSON\n";
    
    // Check structure
    if (!isset($parsed['descriptors'])) {
        echo "  ❌ Missing 'descriptors' field\n";
        echo "  Available keys: " . implode(', ', array_keys($parsed)) . "\n\n";
        continue;
    }
    
    $descriptors = $parsed['descriptors'];
    
    if (!is_array($descriptors)) {
        echo "  ❌ 'descriptors' is not an array\n\n";
        continue;
    }
    
    echo "  ✓ Found " . count($descriptors) . " descriptors\n";
    
    // Check first descriptor structure
    if (!empty($descriptors)) {
        $firstDesc = $descriptors[0];
        echo "  Descriptor structure:\n";
        echo "    - Has 'hash': " . (isset($firstDesc['hash']) ? 'YES (' . strlen($firstDesc['hash']) . ' chars)' : 'NO') . "\n";
        echo "    - Has 'grayscale': " . (isset($firstDesc['grayscale']) ? 'YES (' . count($firstDesc['grayscale']) . ' values)' : 'NO') . "\n";
        echo "    - Has 'edges': " . (isset($firstDesc['edges']) ? 'YES (' . strlen($firstDesc['edges']) . ' chars)' : 'NO') . "\n";
        
        if (isset($firstDesc['hash']) && isset($firstDesc['grayscale']) && isset($firstDesc['edges'])) {
            echo "  ✅ Face ID data looks GOOD!\n\n";
        } else {
            echo "  ❌ Face ID data is INCOMPLETE\n\n";
        }
    }
}

echo "=== Check Complete ===\n";
