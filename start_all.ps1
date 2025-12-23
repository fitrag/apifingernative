# ============================================
#    SISTEM ABSENSI - FULL STACK LAUNCHER
#    PowerShell Version
# ============================================

$Host.UI.RawUI.WindowTitle = "Sistem Absensi - Full Stack"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$port = 8080

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "   SISTEM ABSENSI - FULL STACK LAUNCHER" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Check PHP
$phpPath = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpPath) {
    Write-Host "[ERROR] PHP tidak ditemukan!" -ForegroundColor Red
    Write-Host "Pastikan PHP sudah terinstall dan ada di PATH." -ForegroundColor Yellow
    Read-Host "Tekan Enter untuk keluar"
    exit 1
}

Write-Host "[INFO] PHP ditemukan: $($phpPath.Source)" -ForegroundColor Green
Write-Host ""

# Check if port is available
$portInUse = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue
if ($portInUse) {
    Write-Host "[WARNING] Port $port sudah digunakan, mencoba port 8081..." -ForegroundColor Yellow
    $port = 8081
}

# Create log directory if not exists
$logDir = Join-Path $scriptDir "logs"
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

# Start Cron Job Monitor as background job
Write-Host "[INFO] Memulai Cron Job Monitor..." -ForegroundColor Yellow

$cronJob = Start-Job -ScriptBlock {
    param($dir)
    Set-Location $dir
    
    while ($true) {
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        
        # Run cron scripts
        try {
            php cron_absensi.php 2>&1 | Out-Null
            php cron_tidak_hadir.php 2>&1 | Out-Null
            php cron_bolos.php 2>&1 | Out-Null
            php cron_retry_queue.php 2>&1 | Out-Null
        } catch {
            # Ignore errors
        }
        
        Start-Sleep -Seconds 5
    }
} -ArgumentList $scriptDir

Write-Host "[OK] Cron Job Monitor berjalan (Job ID: $($cronJob.Id))" -ForegroundColor Green
Write-Host ""

# Start PHP Server
Write-Host "[INFO] Memulai PHP Server di port $port..." -ForegroundColor Yellow
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "   SERVER AKTIF" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "   URL Aplikasi : " -NoNewline; Write-Host "http://localhost:$port/app.php" -ForegroundColor Cyan
Write-Host "   URL Installer: " -NoNewline; Write-Host "http://localhost:$port/install.php" -ForegroundColor Cyan
Write-Host ""
Write-Host "   Tekan Ctrl+C untuk menghentikan semua" -ForegroundColor Yellow
Write-Host "============================================" -ForegroundColor Green
Write-Host ""

# Open browser
Start-Process "http://localhost:$port/app.php"

# Handle Ctrl+C
$null = Register-EngineEvent -SourceIdentifier PowerShell.Exiting -Action {
    Write-Host "`n[INFO] Menghentikan semua proses..." -ForegroundColor Yellow
    Get-Job | Stop-Job
    Get-Job | Remove-Job
}

try {
    # Run PHP server (foreground)
    Set-Location $scriptDir
    php -S localhost:$port -t $scriptDir
} finally {
    # Cleanup
    Write-Host ""
    Write-Host "[INFO] Menghentikan Cron Job Monitor..." -ForegroundColor Yellow
    Stop-Job -Job $cronJob -ErrorAction SilentlyContinue
    Remove-Job -Job $cronJob -Force -ErrorAction SilentlyContinue
    Write-Host "[OK] Semua proses dihentikan" -ForegroundColor Green
}
