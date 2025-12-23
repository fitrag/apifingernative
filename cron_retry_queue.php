<?php
require_once 'config.php';
require_once 'wa_queue.php';
require_once 'settings.php';

// Ambil konfigurasi WA API dari settings
$WA_API_URL = getSetting('wa_api_url', 'http://127.0.0.1:8000/api/send-message');
$WA_TOKEN = getSetting('wa_api_token', '');
define('MAX_RETRY', 5);

function sendWaRetry($phone, $message) {
    global $WA_API_URL, $WA_TOKEN;
    
    $ch = curl_init();
    $data = array('phone' => $phone, 'message' => $message);
    curl_setopt($ch, CURLOPT_URL, $WA_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $WA_TOKEN
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        logRetry('CURL Error: ' . $error);
        return false;
    }
    logRetry('WA Response [' . $httpCode . ']: ' . $response);
    return $httpCode >= 200 && $httpCode < 300;
}

function logRetry($msg) {
    $ts = date('Y-m-d H:i:s');
    $f = __DIR__ . '/cron_retry_log.txt';
    file_put_contents($f, '[' . $ts . '] ' . $msg . "\n", FILE_APPEND);
}

// Main Process
$queue = getWaQueue();
$totalQueue = count($queue);

if ($totalQueue == 0) {
    exit; // Tidak ada antrian
}

logRetry("=== Memproses " . $totalQueue . " pesan dalam antrian ===");

$successCount = 0;
$failCount = 0;
$removedCount = 0;

// Proses dari belakang supaya index tidak berubah saat remove
for ($i = $totalQueue - 1; $i >= 0; $i--) {
    $item = $queue[$i];
    
    // Cek jika sudah melebihi max retry
    if ($item['retry_count'] >= MAX_RETRY) {
        logRetry("REMOVED: " . $item['phone'] . " - " . $item['type'] . " (exceeded max retry)");
        removeFromQueue($i);
        $removedCount++;
        continue;
    }
    
    logRetry("RETRY #" . ($item['retry_count'] + 1) . ": " . $item['phone'] . " - " . $item['type']);
    
    $sent = sendWaRetry($item['phone'], $item['message']);
    
    if ($sent) {
        logRetry("SUCCESS: Sent to " . $item['phone']);
        removeFromQueue($i);
        $successCount++;
    } else {
        logRetry("FAILED: " . $item['phone'] . " - will retry later");
        updateRetryCount($i);
        $failCount++;
    }
    
    // Delay 1 detik antar pengiriman
    sleep(1);
}

logRetry("=== Selesai: Success=" . $successCount . ", Failed=" . $failCount . ", Removed=" . $removedCount . " ===");
?>
