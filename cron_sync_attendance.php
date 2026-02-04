<?php
/**
 * Cron Job: Sync Attendance to External API
 * Optimized Version
 */

require_once 'config.php';
require_once 'settings.php';
require_once 'log_cleaner.php';

$settings = getSettings();
$SYNC_API_URL = $settings['sync_api_url'] ?? 'http://127.0.0.1:8000/api/attendance/sync';
$SYNC_API_TOKEN = $settings['sync_api_token'] ?? '';
$JAM_AUTO_CHECKOUT_HARIAN = $settings['jam_auto_checkout_harian'] ?? [
    '1' => '14:00', '2' => '14:00', '3' => '14:00', '4' => '14:00',
    '5' => '14:00', '6' => '12:00', '7' => '12:00'
];

define('LAST_SYNC_ID_FILE', __DIR__ . '/last_sync_attendance_id.txt');

function getJamAutoCheckout($checktime) {
    global $JAM_AUTO_CHECKOUT_HARIAN;
    $day = date('N', strtotime($checktime));
    $jam = $JAM_AUTO_CHECKOUT_HARIAN[$day] ?? '14:00';
    return strlen($jam) === 5 ? $jam . ':00' : $jam;
}

function getChecktype($checktime) {
    $jamAbsen = date('H:i:s', strtotime($checktime));
    return $jamAbsen >= getJamAutoCheckout($checktime) ? 1 : 0;
}

function getLastSyncId() {
    return file_exists(LAST_SYNC_ID_FILE) ? (int)trim(file_get_contents(LAST_SYNC_ID_FILE)) : 0;
}

function sendToApi($data) {
    global $SYNC_API_URL, $SYNC_API_TOKEN;
    $ch = curl_init();
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (!empty($SYNC_API_TOKEN)) $headers[] = 'Authorization: Bearer ' . $SYNC_API_TOKEN;
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $SYNC_API_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

function logMsg($msg) {
    file_put_contents(__DIR__ . '/cron_sync_attendance_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Main
$conn = getConnection();
$lastId = getLastSyncId();

$stmt = $conn->prepare("SELECT c.id, c.checktime, u.title as nis, u.name 
    FROM checkinout c LEFT JOIN userinfo u ON c.userid = u.userid 
    WHERE c.id > ? ORDER BY c.id ASC LIMIT 100");
$stmt->bind_param("i", $lastId);
$stmt->execute();
$result = $stmt->get_result();

$maxId = $lastId;
$success = 0;
$failed = 0;

while ($row = $result->fetch_assoc()) {
    $currentId = (int)$row['id'];
    
    if (empty($row['nis'])) {
        $maxId = max($maxId, $currentId);
        continue;
    }
    
    $checktype = getChecktype($row['checktime']);
    $payload = ['nis' => $row['nis'], 'checktime' => $row['checktime'], 'checktype' => $checktype];
    
    if (sendToApi($payload)) {
        $maxId = max($maxId, $currentId);
        $success++;
    } else {
        $failed++;
        logMsg("FAILED ID {$currentId}");
        break; // Stop on failure for retry
    }
}

if ($maxId > $lastId) {
    file_put_contents(LAST_SYNC_ID_FILE, $maxId, LOCK_EX);
}

if ($success > 0) logMsg("Synced {$success} records" . ($failed > 0 ? ", {$failed} failed" : ""));

$stmt->close();
