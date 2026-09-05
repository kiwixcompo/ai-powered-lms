@echo off
setlocal enabledelayedexpansion
color 0B
echo ===================================================
echo     Deploy to Live Server (lms.tsuniversity.ng)
echo ===================================================
echo.

REM Navigate to project directory
cd /d "c:\wamp64\www\CMP_Course_Module"

REM Check if the 'production' remote exists
git remote -v | find "production" >nul
if not errorlevel 1 goto skip_setup

echo [SETUP] Production remote not found.
echo To push directly to your server via Git, you need your server's Git Repository URL.
echo For example: ssh://user@host...
echo.
set /p prodUrl="Enter your server's Git deployment URL: "
git remote add production !prodUrl!
echo.

:skip_setup
echo [INFO] Staging all files...
git add .

echo.
set /p msg="Enter commit message (Press Enter for auto-timestamp): "
if "!msg!"=="" set msg=Live server deployment on %date% at %time%

echo.
echo [INFO] Committing changes...
git commit -m "!msg!"

echo.
echo [INFO] Pushing to GitHub Backup (origin)...
git push origin main

echo.
echo [INFO] Pushing to Live Server (production)...
git push production main

echo.
echo ===================================================
echo     Deployment to lms.tsuniversity.ng Complete!
echo ===================================================
pause
