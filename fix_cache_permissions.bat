@echo off
echo Fixing Symfony Cache Permissions...
echo.

REM Kill all PHP processes
taskkill /F /IM php.exe /T >nul 2>&1

REM Wait a moment
timeout /t 2 /nobreak >nul

REM Remove cache and log directories
rmdir /S /Q var\cache >nul 2>&1
rmdir /S /Q var\log >nul 2>&1

REM Create fresh directories
mkdir var\cache
mkdir var\log
mkdir var\cache\dev
mkdir var\cache\prod
mkdir var\log\dev
mkdir var\log\prod

REM Set permissions (full control for everyone)
icacls var /grant Everyone:(OI)(CI)F /T >nul 2>&1

REM Also set for cache and log specifically
icacls var\cache /grant Everyone:(OI)(CI)F /T >nul 2>&1
icacls var\log /grant Everyone:(OI)(CI)F /T >nul 2>&1

echo.
echo [SUCCESS] Cache directories recreated with full permissions!
echo.
echo You can now start your Symfony server.
echo.
pause
