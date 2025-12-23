@echo off
title Sistem Absensi - Monitor
color 0A

echo ============================================
echo    ABSENSI MONITOR - WhatsApp Notifier
echo ============================================
echo.
echo Monitoring absensi setiap 5 detik...
echo Cek ketidakhadiran setiap 60 detik...
echo Cek bolos setiap 60 detik...
echo Retry queue setiap 60 detik...
echo.
echo Tekan Ctrl+C untuk berhenti
echo ============================================
echo.

set counter=0

:loop
php "%~dp0cron_absensi.php"

set /a counter+=5
if %counter% geq 60 (
    echo [%time%] Running periodic checks...
    php "%~dp0cron_tidak_hadir.php"
    php "%~dp0cron_bolos.php"
    php "%~dp0cron_retry_queue.php"
    set counter=0
)

timeout /t 5 /nobreak >nul
goto loop
