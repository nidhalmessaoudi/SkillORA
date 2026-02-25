<!DOCTYPE html>
<html>
<head>
    <title>Test 3D Model Upload</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #45a049;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #2196F3;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #4CAF50;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #f44336;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🎮 Test 3D Model Upload</h1>
        
        <div class="info">
            <strong>Instructions:</strong>
            <ol>
                <li>Select your .glb file from: <code>C:\Users\alare\OneDrive\Desktop\3d model\</code></li>
                <li>Fill in all required fields</li>
                <li>Click "Test Upload"</li>
                <li>Check console (F12) for detailed messages</li>
            </ol>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo '<div class="results">';
            
            // Check if file was uploaded
            if (!isset($_FILES['model_file']) || $_FILES['model_file']['error'] !== UPLOAD_ERR_OK) {
                $error = $_FILES['model_file']['error'] ?? 'unknown';
                echo '<div class="error">❌ Upload failed! Error code: ' . $error . '</div>';
                
                switch ($error) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        echo '<div class="error">File is too large!</div>';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        echo '<div class="error">No file was selected!</div>';
                        break;
                    default:
                        echo '<div class="error">Upload error occurred.</div>';
                }
            } else {
                $file = $_FILES['model_file'];
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                echo '<div class="success">';
                echo '✅ File received successfully!<br>';
                echo 'Original name: ' . htmlspecialchars($file['name']) . '<br>';
                echo 'Size: ' . number_format($file['size'] / 1024 / 1024, 2) . ' MB<br>';
                echo 'Extension: .' . $extension . '<br>';
                echo '</div>';
                
                // Check extension
                if (!in_array($extension, ['glb', 'gltf'])) {
                    echo '<div class="error">❌ Invalid extension! Only .glb or .gltf allowed.</div>';
                } else {
                    // Try to move file
                    $targetDir = __DIR__ . '/uploads/salles';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }
                    
                    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
                    $targetPath = $targetDir . '/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        echo '<div class="success">';
                        echo '✅✅✅ SUCCESS! File uploaded!<br>';
                        echo 'Saved as: /uploads/salles/' . $filename . '<br>';
                        echo 'Full path: ' . $targetPath . '<br>';
                        echo 'File size on disk: ' . number_format(filesize($targetPath) / 1024 / 1024, 2) . ' MB<br>';
                        echo '</div>';
                        
                        echo '<div class="info">';
                        echo '<strong>Next steps:</strong><br>';
                        echo '1. Your file is now uploaded!<br>';
                        echo '2. The path to use: <code>/uploads/salles/' . $filename . '</code><br>';
                        echo '3. Now test with real event creation at /admin/events/new<br>';
                        echo '</div>';
                    } else {
                        echo '<div class="error">❌ Failed to move file! Check permissions.</div>';
                        echo '<div class="error">Target directory writable: ' . (is_writable($targetDir) ? 'YES' : 'NO') . '</div>';
                    }
                }
            }
            
            echo '</div>';
        }
        ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>3D Model File (.glb or .gltf):</label>
                <input type="file" name="model_file" accept=".glb,.gltf" required>
            </div>

            <div class="form-group">
                <label>File info:</label>
                <p style="font-size: 12px; color: #666;">
                    Your file: C:\Users\alare\OneDrive\Desktop\3d model\uploads_files_2561492_RuBronyCon-2015.glb (5.9MB)
                </p>
            </div>

            <button type="submit">🚀 Test Upload</button>
        </form>

        <div class="info" style="margin-top: 30px;">
            <strong>PHP Upload Settings:</strong><br>
            upload_max_filesize: <?php echo ini_get('upload_max_filesize'); ?><br>
            post_max_size: <?php echo ini_get('post_max_size'); ?><br>
            max_file_uploads: <?php echo ini_get('max_file_uploads'); ?><br>
        </div>
    </div>
</body>
</html>
