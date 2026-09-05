@echo off
color 0A
echo ===================================================
echo     Auto GitHub Push - AI Powered LMS
echo ===================================================
echo.

REM Navigate to the project directory just in case it is run as administrator from system32
cd /d "c:\wamp64\www\CMP_Course_Module"

REM Check if the git repository is already initialized
IF NOT EXIST ".git" (
    echo [INFO] Initializing new Git repository...
    git init
    git remote add origin https://github.com/kiwixcompo/ai-powered-lms.git
    git branch -M main
) ELSE (
    REM Ensure the remote origin is correct if it was already initialized
    git remote set-url origin https://github.com/kiwixcompo/ai-powered-lms.git
)

echo [INFO] Staging all files...
git add .

echo.
REM Ask for a commit message, default to a timestamp if left blank
set /p msg="Enter commit message (Press Enter to use auto-timestamp): "

IF "%msg%"=="" (
    set msg=Auto-commit on %date% at %time%
)

echo.
echo [INFO] Committing changes...
git commit -m "%msg%"

echo.
echo [INFO] Pushing to GitHub...
git push -u origin main

echo.
echo ===================================================
echo     Push Complete!
echo ===================================================
pause
