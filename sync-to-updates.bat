@echo off
REM Sync Modified Files to Updates Folder
REM This script copies all platform files to the updates folder

echo ========================================
echo Syncing Files to Updates Folder
echo ========================================
echo.
echo Target: C:\Users\HYLINK\Desktop\music - Copy\updates
echo.

cd /d "%~dp0"

REM Try to find PHP in common XAMPP locations
set PHP_PATH=
if exist "C:\xampp\php\php.exe" set PHP_PATH=C:\xampp\php\php.exe
if exist "C:\Program Files\xampp\php\php.exe" set PHP_PATH=C:\Program Files\xampp\php\php.exe

if "%PHP_PATH%"=="" (
    echo PHP not found. Please install XAMPP or add PHP to PATH.
    echo Trying to use 'php' command...
    php sync-to-updates.php
) else (
    "%PHP_PATH%" sync-to-updates.php
)

echo.
echo ========================================
echo Sync Complete!
echo ========================================
pause

