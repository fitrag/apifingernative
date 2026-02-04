@echo off
title Sistem Absensi
color 0A

echo.
echo   SISTEM ABSENSI - LAUNCHER
echo.

:: Set variables
set PORT=8080
set SCRIPT_DIR=%~dp0

:: Quick PHP check
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] PHP tidak ditemukan!
    pause
    exit /b 1
)

:: Quick port check
netstat -ano | findstr ":%PORT%" >nul
if %errorlevel%==0 set PORT=8081

:: Start optimized cron in background (single process)
echo [OK] Starting Cron Monitor...
start /B powershell -ExecutionPolicy Bypass -WindowStyle Hidden -File "%SCRIPT_DIR%start_monitor.ps1"

timeout /t 1 /nobreak >nul

echo [OK] Server: http://localhost:%PORT%/app.php
echo.
echo Tekan Ctrl+C untuk stop
echo.

:: Open browser
start http://localhost:%PORT%/app.php

:: Run PHP server
php -S localhost:%PORT% -t "%SCRIPT_DIR%"

:: Cleanup on exit
taskkill /F /FI "WINDOWTITLE eq Absensi Monitor*" >nul 2>nul
