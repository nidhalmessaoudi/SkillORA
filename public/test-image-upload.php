<?php
// Simple test to diagnose upload issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['error' => 'No file uploaded', 'files' => $_FILES]);
    exit;
}

$file = $_FILES['image'];
$uploadDir = __DIR__ . '/uploads/community';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = uniqid('test_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
$destination = $uploadDir . '/' . $filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode([
        'ok' => true,
        'url' => '/uploads/community/' . $filename,
        'file_info' => [
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type']
        ]
    ]);
} else {
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to move uploaded file',
        'upload_info' => [
            'error' => $file['error'],
            'tmp_name' => $file['tmp_name'],
            'destination' => $destination
        ]
    ]);
}
