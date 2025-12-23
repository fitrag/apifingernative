<?php
header('Content-Type: application/json');

// Read log files
$logs = [];

$logFiles = [
    'cron_log.txt' => ['label' => 'Absensi', 'color' => '#3b82f6'],
    'cron_absent_log.txt' => ['label' => 'Tidak Hadir', 'color' => '#ef4444'],
    'cron_bolos_log.txt' => ['label' => 'Bolos', 'color' => '#f59e0b'],
    'cron_retry_log.txt' => ['label' => 'Retry', 'color' => '#8b5cf6']
];

foreach ($logFiles as $file => $info) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $lines = array_filter(explode("\n", $content));
        $lines = array_slice($lines, -100);
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $logs[] = [
                    'type' => $info['label'],
                    'color' => $info['color'],
                    'line' => $line
                ];
            }
        }
    }
}

// Sort by timestamp (newest first)
usort($logs, function($a, $b) {
    preg_match('/\[(.*?)\]/', $a['line'], $matchA);
    preg_match('/\[(.*?)\]/', $b['line'], $matchB);
    $timeA = isset($matchA[1]) ? strtotime($matchA[1]) : 0;
    $timeB = isset($matchB[1]) ? strtotime($matchB[1]) : 0;
    return $timeB - $timeA;
});

$logs = array_slice($logs, 0, 200);

// Parse log line
function parseLogLine($line) {
    $result = [
        'timestamp' => '',
        'message' => $line,
        'status' => 'info'
    ];
    
    if (preg_match('/^\[(.*?)\]\s*(.*)$/', $line, $matches)) {
        $result['timestamp'] = $matches[1];
        $result['message'] = $matches[2];
    }
    
    $lowerLine = strtolower($line);
    if (strpos($lowerLine, 'success') !== false || strpos($lowerLine, 'berhasil') !== false) {
        $result['status'] = 'success';
    } elseif (strpos($lowerLine, 'failed') !== false || strpos($lowerLine, 'error') !== false || strpos($lowerLine, 'gagal') !== false) {
        $result['status'] = 'error';
    } elseif (strpos($lowerLine, 'skip') !== false) {
        $result['status'] = 'skip';
    } elseif (strpos($lowerLine, 'bolos') !== false || strpos($lowerLine, 'tidak hadir') !== false || strpos($lowerLine, 'terlambat') !== false) {
        $result['status'] = 'warning';
    }
    
    return $result;
}

// Process logs
$processedLogs = [];
$stats = ['success' => 0, 'error' => 0, 'warning' => 0, 'total' => count($logs)];

foreach ($logs as $log) {
    $parsed = parseLogLine($log['line']);
    
    $iconClass = 'lnr-bubble';
    if ($parsed['status'] == 'success') { $iconClass = 'lnr-checkmark-circle'; $stats['success']++; }
    elseif ($parsed['status'] == 'error') { $iconClass = 'lnr-cross-circle'; $stats['error']++; }
    elseif ($parsed['status'] == 'warning') { $iconClass = 'lnr-warning'; $stats['warning']++; }
    elseif ($parsed['status'] == 'skip') $iconClass = 'lnr-arrow-right-circle';
    
    $processedLogs[] = [
        'type' => $log['type'],
        'color' => $log['color'],
        'timestamp' => $parsed['timestamp'],
        'message' => $parsed['message'],
        'status' => $parsed['status'],
        'icon' => $iconClass
    ];
}

echo json_encode([
    'success' => true,
    'logs' => $processedLogs,
    'stats' => $stats,
    'lastUpdate' => date('Y-m-d H:i:s')
]);
