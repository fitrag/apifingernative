@echo off
title Sistem Absensi - Server & Monitor
color 0A

echo ============================================
echo    SISTEM ABSENSI - LOCAL SERVER
echo ============================================
echo.

:: Set port (default 8080)
set PORT=8080

:: Check if port is in use
netstat -ano | findstr ":%PORT%" >nul
if %errorlevel%==0 (
    echo [WARNING] Port %PORT% sudah digunakan!
    echo Mencoba port 8081...
    set PORT=8081
)

echo [INFO] Memulai PHP Built-in Server di port %PORT%...
echo [INFO] Akses aplikasi di: http://localhost:%PORT%/app.php
echo.
echo [INFO] Tekan Ctrl+C untuk menghentikan server
echo ============================================
echo.

:: Start PHP built-in server
php -S localhost:%PORT% -t "%~dp0"

pause
