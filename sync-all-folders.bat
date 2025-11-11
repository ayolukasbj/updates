@echo off
REM Sync files to all 3 folders:
REM 1. D:\HyLink Music Platform\music - Live production
REM 2. C:\xampp\htdocs\music - Localhost development
REM 3. C:\Users\HYLINK\Desktop\music - Copy\updates - Updates folder

set SOURCE_DIR=%~dp0
set LIVE_DIR=D:\HyLink Music Platform\music
set LOCAL_DIR=C:\xampp\htdocs\music
set UPDATES_DIR=C:\Users\HYLINK\Desktop\music - Copy\updates

echo ========================================
echo Syncing Files to All Folders
echo ========================================
echo.

REM Check if directories exist
if not exist "%LIVE_DIR%" (
    echo [WARNING] Live directory not found: %LIVE_DIR%
    echo Creating directory...
    mkdir "%LIVE_DIR%"
)

if not exist "%LOCAL_DIR%" (
    echo [WARNING] Local directory not found: %LOCAL_DIR%
    echo Creating directory...
    mkdir "%LOCAL_DIR%"
)

if not exist "%UPDATES_DIR%" (
    echo [WARNING] Updates directory not found: %UPDATES_DIR%
    echo Creating directory...
    mkdir "%UPDATES_DIR%"
)

echo.
echo [1/3] Copying to Live Production: %LIVE_DIR%
xcopy /E /I /Y /EXCLUDE:exclude-list.txt "%SOURCE_DIR%*" "%LIVE_DIR%\" >nul
echo [OK] Live production folder updated

echo.
echo [2/3] Copying to Local Development: %LOCAL_DIR%
xcopy /E /I /Y /EXCLUDE:exclude-list.txt "%SOURCE_DIR%*" "%LOCAL_DIR%\" >nul
echo [OK] Local development folder updated

echo.
echo [3/3] Copying to Updates Folder: %UPDATES_DIR%
REM Only copy changed files to updates folder
call copy-to-updates.bat
echo [OK] Updates folder updated

echo.
echo ========================================
echo Sync Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Test on localhost: %LOCAL_DIR%
echo 2. Deploy to live: %LIVE_DIR%
echo 3. Create update package from: %UPDATES_DIR%
echo.
pause












