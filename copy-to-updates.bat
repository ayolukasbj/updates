@echo off
REM Copy changed files to updates folder

set SOURCE_DIR=%~dp0
set UPDATES_DIR=C:\Users\HYLINK\Desktop\music - Copy\updates

echo Preparing update package...
echo.

REM Create directory structure
if not exist "%UPDATES_DIR%\config" mkdir "%UPDATES_DIR%\config"
if not exist "%UPDATES_DIR%\includes" mkdir "%UPDATES_DIR%\includes"
if not exist "%UPDATES_DIR%\admin" mkdir "%UPDATES_DIR%\admin"
if not exist "%UPDATES_DIR%\admin\includes" mkdir "%UPDATES_DIR%\admin\includes"
if not exist "%UPDATES_DIR%\admin\api" mkdir "%UPDATES_DIR%\admin\api"
if not exist "%UPDATES_DIR%\install" mkdir "%UPDATES_DIR%\install"
if not exist "%UPDATES_DIR%\helpers" mkdir "%UPDATES_DIR%\helpers"

REM Copy files
echo Copying files...
echo.

REM Core files
copy "%SOURCE_DIR%login.php" "%UPDATES_DIR%\" >nul && echo [OK] login.php || echo [FAIL] login.php
copy "%SOURCE_DIR%index.php" "%UPDATES_DIR%\" >nul && echo [OK] index.php || echo [FAIL] index.php
copy "%SOURCE_DIR%news.php" "%UPDATES_DIR%\" >nul && echo [OK] news.php || echo [FAIL] news.php
copy "%SOURCE_DIR%news-details.php" "%UPDATES_DIR%\" >nul && echo [OK] news-details.php || echo [FAIL] news-details.php
copy "%SOURCE_DIR%song-details.php" "%UPDATES_DIR%\" >nul && echo [OK] song-details.php || echo [FAIL] song-details.php
copy "%SOURCE_DIR%artist-profile.php" "%UPDATES_DIR%\" >nul && echo [OK] artist-profile.php || echo [FAIL] artist-profile.php
copy "%SOURCE_DIR%artist-profile-mobile.php" "%UPDATES_DIR%\" >nul && echo [OK] artist-profile-mobile.php || echo [FAIL] artist-profile-mobile.php
copy "%SOURCE_DIR%album-details.php" "%UPDATES_DIR%\" >nul && echo [OK] album-details.php || echo [FAIL] album-details.php
copy "%SOURCE_DIR%.htaccess" "%UPDATES_DIR%\" >nul && echo [OK] .htaccess || echo [FAIL] .htaccess

REM Config files
copy "%SOURCE_DIR%config\config.php" "%UPDATES_DIR%\config\" >nul && echo [OK] config\config.php || echo [FAIL] config\config.php
copy "%SOURCE_DIR%config\license.php" "%UPDATES_DIR%\config\" >nul && echo [OK] config\license.php || echo [FAIL] config\license.php

REM Includes
copy "%SOURCE_DIR%includes\header.php" "%UPDATES_DIR%\includes\" >nul && echo [OK] includes\header.php || echo [FAIL] includes\header.php
copy "%SOURCE_DIR%includes\ads.php" "%UPDATES_DIR%\includes\" >nul && echo [OK] includes\ads.php || echo [FAIL] includes\ads.php

REM Admin files
copy "%SOURCE_DIR%admin\settings.php" "%UPDATES_DIR%\admin\" >nul && echo [OK] admin\settings.php || echo [FAIL] admin\settings.php
copy "%SOURCE_DIR%admin\settings-advanced.php" "%UPDATES_DIR%\admin\" >nul && echo [OK] admin\settings-advanced.php || echo [FAIL] admin\settings-advanced.php
copy "%SOURCE_DIR%admin\includes\footer.php" "%UPDATES_DIR%\admin\includes\" >nul && echo [OK] admin\includes\footer.php || echo [FAIL] admin\includes\footer.php
copy "%SOURCE_DIR%admin\api\install-update.php" "%UPDATES_DIR%\admin\api\" >nul && echo [OK] admin\api\install-update.php || echo [FAIL] admin\api\install-update.php

REM Install files
copy "%SOURCE_DIR%install\install-database.php" "%UPDATES_DIR%\install\" >nul && echo [OK] install\install-database.php || echo [FAIL] install\install-database.php

REM Helper files
copy "%SOURCE_DIR%helpers\EmailHelper.php" "%UPDATES_DIR%\helpers\" >nul && echo [OK] helpers\EmailHelper.php || echo [FAIL] helpers\EmailHelper.php

REM Debug files (for troubleshooting)
copy "%SOURCE_DIR%debug-live.php" "%UPDATES_DIR%\" >nul && echo [OK] debug-live.php || echo [FAIL] debug-live.php
copy "%SOURCE_DIR%sync-database-tables.php" "%UPDATES_DIR%\" >nul && echo [OK] sync-database-tables.php || echo [FAIL] sync-database-tables.php

echo.
echo Update package prepared in: %UPDATES_DIR%
echo Ready to create ZIP file!
pause

