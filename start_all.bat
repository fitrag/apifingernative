@echo off
title Sistem Absensi - Full Stack
color 0A

echo ============================================
echo    SISTEM ABSENSI - FULL STACK LAUNCHER
echo ============================================
echo.
echo Script ini akan menjalankan:
echo   1. PHP Local Server (port 8080)
echo   2. Cron Job Monitor (background)
echo.
echo ============================================
echo.

:: Set variables
set PORT=8080
set SCRIPT_DIR=%~dp0

:: Check PHP
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] PHP tidak ditemukan! Pastikan PHP sudah terinstall dan ada di PATH.
    pause
    exit /b 1
)

echo [INFO] PHP ditemukan: 
php -v | findstr /i "php"
echo.

:: Check if port is available
netstat -ano | findstr ":%PORT%" >nul
if %errorlevel%==0 (
    echo [WARNING] Port %PORT% sudah digunakan, mencoba port 8081...
    set PORT=8081
)

:: Start Cron Monitor in background
echo [INFO] Memulai Cron Job Monitor...
start /B cmd /c "cd /d %SCRIPT_DIR% && powershell -ExecutionPolicy Bypass -File start_monitor.ps1 > nul 2>&1"
echo [OK] Cron Job Monitor berjalan di background
echo.

:: Wait a moment
timeout /t 2 /nobreak >nul

:: Start PHP Server
echo [INFO] Memulai PHP Server di port %PORT%...
echo.
echo ============================================
echo    SERVER AKTIF
echo ============================================
echo.
echo    URL Aplikasi: http://localhost:%PORT%/app.php
echo    URL Installer: http://localhost:%PORT%/install.php
echo.
echo    Tekan Ctrl+C untuk menghentikan semua
echo ============================================
echo.

:: Open browser automatically
start http://localhost:%PORT%/app.php

:: Run PHP server (foreground)
php -S localhost:%PORT% -t "%SCRIPT_DIR%"

:: When server stops, also stop cron
echo.
echo [INFO] Menghentikan semua proses...
taskkill /F /IM php.exe >nul 2>nul

pause
