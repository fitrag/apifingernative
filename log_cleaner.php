<?php
/**
 * Log Cleaner - Membersihkan log yang lebih dari 7 hari
 * Include file ini di semua cron job
 */

function cleanOldLogs($logFile, $days = 7) {
    if (!file_exists($logFile)) return;
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($lines)) return;
    
    $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
    $newLines = [];
    
    foreach ($lines as $line) {
        // Format log: [2026-01-19 10:30:00] message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
            $logDate = $matches[1];
            if ($logDate >= $cutoffDate) {
                $newLines[] = $line;
            }
        } else {
            // Keep lines tanpa tanggal
            $newLines[] = $line;
        }
    }
    
    // Tulis ulang file jika ada yang dihapus
    if (count($newLines) < count($lines)) {
        file_put_contents($logFile, implode("\n", $newLines) . "\n", LOCK_EX);
    }
}

function cleanAllCronLogs() {
    $logFiles = [
        __DIR__ . '/cron_log.txt',
        __DIR__ . '/cron_absent_log.txt',
        __DIR__ . '/cron_bolos_log.txt',
        __DIR__ . '/cron_izin_log.txt',
        __DIR__ . '/cron_retry_log.txt',
        __DIR__ . '/cron_sync_attendance_log.txt'
    ];
    
    foreach ($logFiles as $logFile) {
        cleanOldLogs($logFile, 7);
    }
}
