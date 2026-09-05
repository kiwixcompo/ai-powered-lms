@echo off
setlocal enabledelayedexpansion
color 0B
echo ===================================================
echo     Deploying to Live Server (lms.tsuniversity.ng)
echo ===================================================
echo.

REM Navigate to project directory
cd /d "c:\wamp64\www\CMP_Course_Module"

echo [INFO] Staging all files...
git add .

echo.
set msg=Live server deployment on %date% at %time%
echo [INFO] Committing changes (Auto-note: !msg!)...
git commit -m "!msg!"

echo.
echo [INFO] Pushing to GitHub (Auto-deploys to Live Server)...
git push origin main

echo.
echo ===================================================
echo     Deployment to lms.tsuniversity.ng Complete!
echo ===================================================
pause
