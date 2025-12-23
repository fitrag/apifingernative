# Absensi Monitor - PowerShell Version
Write-Host "========================================"
Write-Host "  ABSENSI MONITOR - WhatsApp Notifier"
Write-Host "========================================"
Write-Host ""
Write-Host "Monitoring absensi setiap detik..."
Write-Host "Cek ketidakhadiran setiap 60 detik..."
Write-Host "Cek bolos setiap 60 detik (setelah jam 17:00)..."
Write-Host "Retry queue setiap 60 detik..."
Write-Host "Tekan Ctrl+C untuk berhenti"
Write-Host ""

$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$phpScript = Join-Path $scriptPath "cron_absensi.php"
$phpAbsent = Join-Path $scriptPath "cron_tidak_hadir.php"
$phpBolos = Join-Path $scriptPath "cron_bolos.php"
$phpRetry = Join-Path $scriptPath "cron_retry_queue.php"

$counter = 0

while ($true) {
    # Cek absensi baru setiap detik
    php $phpScript
    
    # Cek ketidakhadiran, bolos, dan retry queue setiap 60 detik
    $counter++
    if ($counter -ge 60) {
        php $phpAbsent
        php $phpBolos
        php $phpRetry
        $counter = 0
    }
    
    Start-Sleep -Seconds 1
}
