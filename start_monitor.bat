@echo off
title Absensi Monitor
echo ========================================
echo   ABSENSI MONITOR - WhatsApp Notifier
echo ========================================
echo.
echo Monitoring absensi setiap detik...
echo Cek ketidakhadiran setiap 60 detik (setelah jam 08:00)...
echo Cek bolos setiap 60 detik (setelah jam 17:00)...
echo Retry queue setiap 60 detik...
echo Tekan Ctrl+C untuk berhenti
echo.

set counter=0

:loop
php "%~dp0cron_absensi.php"

set /a counter+=1
if %counter% geq 60 (
    php "%~dp0cron_tidak_hadir.php"
    php "%~dp0cron_bolos.php"
    php "%~dp0cron_retry_queue.php"
    set counter=0
)

timeout /t 1 /nobreak >nul
goto loop
