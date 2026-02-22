<?php
// Test Event Image Upload System
echo "Testing Event Image Upload System\n";
echo str_repeat("=", 50) . "\n\n";

// Check if upload directory exists
$uploadDir = __DIR__ . '/public/uploads/events';
echo "1. Upload Directory Check:\n";
echo "   Path: $uploadDir\n";
echo "   Exists: " . (is_dir($uploadDir) ? "✓ YES" : "✗ NO") . "\n";
echo "   Writable: " . (is_writable($uploadDir) ? "✓ YES" : "✗ NO") . "\n\n";

// Check database for events with images
echo "2. Database Event Images:\n";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=skillora', 'root', '');
    $stmt = $pdo->query('SELECT id, title, image, created_at FROM event ORDER BY id DESC LIMIT 5');
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "   No events found in database\n";
    } else {
        foreach ($events as $event) {
            echo "   Event #{$event['id']}: {$event['title']}\n";
            echo "   Image: " . ($event['image'] ?: 'NULL') . "\n";
            if ($event['image']) {
                $fullPath = __DIR__ . '/public' . $event['image'];
                echo "   File exists: " . (file_exists($fullPath) ? "✓ YES" : "✗ NO") . "\n";
            }
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "   Database Error: " . $e->getMessage() . "\n";
}

echo "\n3. Files in uploads/events/:\n";
$files = array_diff(scandir($uploadDir), ['.', '..']);
if (empty($files)) {
    echo "   Directory is empty\n";
} else {
    foreach ($files as $file) {
        echo "   - $file\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "TEST COMPLETE\n\n";

echo "To test upload manually:\n";
echo "1. Go to: http://localhost:8000/admin/events/new\n";
echo "2. Fill in required fields\n";
echo "3. Select an image file\n";
echo "4. Submit form\n";
echo "5. Check if flash message appears\n";
echo "6. Check if image displays on event page\n";
