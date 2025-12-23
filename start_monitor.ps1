# ============================================
#    ABSENSI MONITOR - WhatsApp Notifier
#    Cron Job Runner
# ============================================

$Host.UI.RawUI.WindowTitle = "Absensi Monitor"

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  ABSENSI MONITOR - WhatsApp Notifier" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Monitoring absensi setiap 5 detik..." -ForegroundColor Yellow
Write-Host "Cek ketidakhadiran setiap 60 detik..." -ForegroundColor Yellow
Write-Host "Cek bolos setiap 60 detik..." -ForegroundColor Yellow
Write-Host "Retry queue setiap 60 detik..." -ForegroundColor Yellow
Write-Host ""
Write-Host "Tekan Ctrl+C untuk berhenti" -ForegroundColor Red
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$phpScript = Join-Path $scriptPath "cron_absensi.php"
$phpAbsent = Join-Path $scriptPath "cron_tidak_hadir.php"
$phpBolos = Join-Path $scriptPath "cron_bolos.php"
$phpRetry = Join-Path $scriptPath "cron_retry_queue.php"

# Check PHP
$phpPath = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpPath) {
    Write-Host "[ERROR] PHP tidak ditemukan!" -ForegroundColor Red
    Read-Host "Tekan Enter untuk keluar"
    exit 1
}

$counter = 0
$startTime = Get-Date

Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Monitor dimulai..." -ForegroundColor Green

while ($true) {
    try {
        # Cek absensi baru setiap 5 detik
        $result = php $phpScript 2>&1
        
        # Cek ketidakhadiran, bolos, dan retry queue setiap 60 detik
        $counter += 5
        if ($counter -ge 60) {
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Running periodic checks..." -ForegroundColor Gray
            php $phpAbsent 2>&1 | Out-Null
            php $phpBolos 2>&1 | Out-Null
            php $phpRetry 2>&1 | Out-Null
            $counter = 0
        }
    } catch {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Error: $_" -ForegroundColor Red
    }
    
    Start-Sleep -Seconds 5
}
