<?php

// Check Face ID setup
require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->bootEnv(__DIR__.'/.env');

// Create Symfony kernel
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Get entity manager
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

echo "=== Face ID Diagnostic Check ===\n\n";

// Find users with Face ID enabled
$conn = $entityManager->getConnection();
$users = $conn->executeQuery('SELECT id, email, username, face_id_enabled, LENGTH(face_data) as data_length FROM user WHERE face_id_enabled = 1')->fetchAllAssociative();

echo "Users with Face ID enabled: " . count($users) . "\n\n";

if (empty($users)) {
    echo "❌ No users have Face ID enabled.\n";
    echo "   Please register with Face ID first.\n\n";
    exit;
}

foreach ($users as $user) {
    echo "User: {$user['email']}\n";
    echo "  - ID: {$user['id']}\n";
    echo "  - Username: {$user['username']}\n";
    echo "  - Face data length: {$user['data_length']} bytes\n";
    
    // Get full face data
    $faceData = $conn->executeQuery('SELECT face_data FROM user WHERE id = ?', [$user['id']])->fetchOne();
    
    if (empty($faceData)) {
        echo "  ❌ Face data is EMPTY\n\n";
        continue;
    }
    
    // Try to parse face data
    $parsed = json_decode($faceData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "  ❌ Face data is NOT valid JSON: " . json_last_error_msg() . "\n";
        echo "  First 200 chars: " . substr($faceData, 0, 200) . "...\n\n";
        continue;
    }
    
    echo "  ✓ Face data is valid JSON\n";
    
    // Check descriptor structure
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
    
    echo "  ✓ Found {count($descriptors)} descriptors\n";
    
    // Check each descriptor
    $validDescriptors = 0;
    foreach ($descriptors as $i => $desc) {
        if (!is_array($desc)) {
            echo "  ❌ Descriptor {$i} is not an array\n";
            continue;
        }
        
        $hasHash = isset($desc['hash']) && is_string($desc['hash']);
        $hasGrayscale = isset($desc['grayscale']) && is_array($desc['grayscale']);
        $hasEdges = isset($desc['edges']) && is_string($desc['edges']);
        
        if ($hasHash && $hasGrayscale && $hasEdges) {
            $validDescriptors++;
        } else {
            echo "  ⚠ Descriptor {$i}: hash=" . ($hasHash ? '✓' : '✗') . " grayscale=" . ($hasGrayscale ? '✓' : '✗') . " edges=" . ($hasEdges ? '✓' : '✗') . "\n";
        }
    }
    
    if ($validDescriptors > 0) {
        echo "  ✓ {$validDescriptors} valid descriptors found\n";
        echo "  ✅ Face ID data looks GOOD for this user!\n\n";
    } else {
        echo "  ❌ No valid descriptors found\n\n";
    }
}

echo "\n=== Diagnostic Complete ===\n";
