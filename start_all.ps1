# ============================================
#    SISTEM ABSENSI - OPTIMIZED LAUNCHER
# ============================================

$ErrorActionPreference = "SilentlyContinue"
$Host.UI.RawUI.WindowTitle = "Sistem Absensi"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$port = 8080

Write-Host "`n  SISTEM ABSENSI - LAUNCHER`n" -ForegroundColor Cyan

# Quick PHP check
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "[ERROR] PHP tidak ditemukan!" -ForegroundColor Red
    exit 1
}

# Quick port check
if (Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue) { $port = 8081 }

# Start optimized cron job
$cronJob = Start-Job -ScriptBlock {
    param($dir)
    Set-Location $dir
    
    # Helper function to get sync interval via PHP
    function Get-SyncInterval {
        try {
            $result = php -r "require 'settings.php'; echo getSetting('sync_interval', 60);" 2>&1
            $interval = [int]$result
            if ($interval -ge 1 -and $interval -le 1440) { return $interval }
        } catch {}
        return 60
    }
    
    $lastSync = [DateTime]::MinValue
    $lastPeriodic = [DateTime]::MinValue
    $syncInterval = Get-SyncInterval
    $firstRun = $true
    
    while ($true) {
        $now = Get-Date
        
        # Absensi check - setiap 3 detik
        php cron_absensi.php 2>&1 | Out-Null
        
        # Periodic checks - setiap 30 detik
        if (($now - $lastPeriodic).TotalSeconds -ge 30) {
            php cron_tidak_hadir.php 2>&1 | Out-Null
            php cron_bolos.php 2>&1 | Out-Null
            php cron_izin_sakit.php 2>&1 | Out-Null
            php cron_retry_queue.php 2>&1 | Out-Null
            $lastPeriodic = $now
            
            # Reload sync interval via PHP
            $syncInterval = Get-SyncInterval
        }
        
        # Sync attendance - jalankan pertama kali, lalu sesuai interval
        if ($firstRun -or ($now - $lastSync).TotalMinutes -ge $syncInterval) {
            php cron_sync_attendance.php 2>&1 | Out-Null
            $lastSync = $now
            $firstRun = $false
        }
        
        Start-Sleep -Seconds 3
    }
} -ArgumentList $scriptDir

Write-Host "[OK] Cron Monitor aktif" -ForegroundColor Green
Write-Host "[OK] Server: http://localhost:$port/app.php" -ForegroundColor Cyan
Write-Host "`nTekan Ctrl+C untuk stop`n" -ForegroundColor Yellow

# Open browser
Start-Process "http://localhost:$port/app.php"

try {
    Set-Location $scriptDir
    php -S localhost:$port -t $scriptDir
} finally {
    Stop-Job -Job $cronJob -ErrorAction SilentlyContinue
    Remove-Job -Job $cronJob -Force -ErrorAction SilentlyContinue
    Write-Host "`n[OK] Stopped" -ForegroundColor Green
}
