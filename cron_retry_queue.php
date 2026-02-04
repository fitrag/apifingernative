<?php
/**
 * Cron Job: Retry Failed WA Messages
 * Optimized Version
 */

require_once 'config.php';
require_once 'wa_queue.php';
require_once 'settings.php';
require_once 'log_cleaner.php';

$settings = getSettings();
$WA_API_URL = $settings['wa_api_url'] ?? 'http://127.0.0.1:8000/api/send-message';
$WA_TOKEN = $settings['wa_api_token'] ?? '';
define('MAX_RETRY', 5);

function sendWA($phone, $message) {
    global $WA_API_URL, $WA_TOKEN;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $WA_API_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['phone' => $phone, 'message' => $message]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $WA_TOKEN],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

function logMsg($msg) {
    file_put_contents(__DIR__ . '/cron_retry_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Main
$queue = getWaQueue();
if (empty($queue)) exit;

$success = 0;
$failed = 0;
$removed = 0;

// Process from end to avoid index issues
for ($i = count($queue) - 1; $i >= 0; $i--) {
    $item = $queue[$i];
    
    if ($item['retry_count'] >= MAX_RETRY) {
        removeFromQueue($i);
        $removed++;
        continue;
    }
    
    if (sendWA($item['phone'], $item['message'])) {
        removeFromQueue($i);
        $success++;
    } else {
        updateRetryCount($i);
        $failed++;
    }
    
    usleep(500000); // 500ms delay
}

if ($success + $failed + $removed > 0) {
    logMsg("Retry: {$success} success, {$failed} failed, {$removed} removed");
}
