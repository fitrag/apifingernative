# ============================================
#    ABSENSI MONITOR - Optimized Cron Runner
# ============================================

$ErrorActionPreference = "SilentlyContinue"
$Host.UI.RawUI.WindowTitle = "Absensi Monitor"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

# Quick PHP check
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { exit 1 }

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

Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Monitor started (sync interval: $syncInterval min)" -ForegroundColor Green

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
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Running sync attendance..." -ForegroundColor Cyan
        php cron_sync_attendance.php 2>&1 | Out-Null
        $lastSync = $now
        $firstRun = $false
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Sync done. Next in $syncInterval minutes" -ForegroundColor Gray
    }
    
    Start-Sleep -Seconds 3
}
