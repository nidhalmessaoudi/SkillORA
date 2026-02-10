@echo off
echo ========================================
echo PHP Upload Limit Fix for 3D Models
echo ========================================
echo.

set PHP_INI=C:\tools\php85\php.ini

echo Current settings:
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"
php -r "echo 'max_file_uploads: ' . ini_get('max_file_uploads') . PHP_EOL;"
echo.

echo Backing up php.ini...
copy "%PHP_INI%" "%PHP_INI%.backup" >nul
echo Backup created: %PHP_INI%.backup
echo.

echo Updating PHP settings...
powershell -Command "(Get-Content '%PHP_INI%') -replace '^upload_max_filesize = 2M', 'upload_max_filesize = 50M' | Set-Content '%PHP_INI%'"
powershell -Command "(Get-Content '%PHP_INI%') -replace '^post_max_size = 8M', 'post_max_size = 50M' | Set-Content '%PHP_INI%'"
echo.

echo New settings:
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"
echo.

echo ========================================
echo SUCCESS! PHP upload limit increased to 50MB
echo ========================================
echo.
echo IMPORTANT: Restart your PHP server now!
echo   - If using Symfony CLI: Stop and restart "symfony serve"
echo   - If using PHP built-in: Restart the server
echo.
pause
