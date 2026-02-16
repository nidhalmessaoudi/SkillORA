@echo off
echo Fixing Symfony cache permissions...

REM Remove cache and log contents
echo Clearing cache directory...
rmdir /s /q var\cache 2>nul
rmdir /s /q var\log 2>nul

REM Recreate directories
echo Creating directories...
mkdir var\cache 2>nul
mkdir var\log 2>nul

REM Create gitkeep files
echo. > var\cache\.gitkeep
echo. > var\log\.gitkeep

REM Set permissions
echo Setting permissions...
icacls var /grant %USERNAME%:(OI)(CI)F /T /Q

echo Done! Cache permissions fixed.
pause
