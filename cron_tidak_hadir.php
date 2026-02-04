<?php
/**
 * Cron Job: Notifikasi Tidak Hadir
 * Optimized Version
 */

require_once 'config.php';
require_once 'wa_queue.php';
require_once 'settings.php';
require_once 'log_cleaner.php';

// Cek hari libur dulu
if (isHoliday()) exit;

$settings = getSettings();
$WA_API_URL = $settings['wa_api_url'] ?? 'http://127.0.0.1:8000/api/send-message';
$WA_TOKEN = $settings['wa_api_token'] ?? '';
$BATAS_JAM = $settings['jam_batas_terlambat'] ?? '08:00:00';

// Hanya jalankan setelah jam batas
if (date('H:i:s') < $BATAS_JAM) exit;

define('ABSENT_LOG_FILE', __DIR__ . '/sent_absent_log.json');

$absentLogCache = null;

function getAbsentLog() {
    global $absentLogCache;
    if ($absentLogCache === null) {
        $absentLogCache = file_exists(ABSENT_LOG_FILE) ? (json_decode(file_get_contents(ABSENT_LOG_FILE), true) ?: []) : [];
    }
    return $absentLogCache;
}

function saveAbsentLog() {
    global $absentLogCache;
    if ($absentLogCache !== null) {
        $cutoff = date('Y-m-d', strtotime('-7 days'));
        foreach ($absentLogCache as $k => $v) {
            $parts = explode('_', $k);
            if (isset($parts[1]) && $parts[1] < $cutoff) unset($absentLogCache[$k]);
        }
        file_put_contents(ABSENT_LOG_FILE, json_encode($absentLogCache), LOCK_EX);
    }
}

function isAbsentNotified($userid, $tanggal) {
    return isset(getAbsentLog()["{$userid}_{$tanggal}_absent"]);
}

function markAbsentNotified($userid, $tanggal) {
    global $absentLogCache;
    if ($absentLogCache === null) getAbsentLog();
    $absentLogCache["{$userid}_{$tanggal}_absent"] = time();
}

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
    file_put_contents(__DIR__ . '/cron_absent_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Main
$conn = getConnection();
$tanggal = date('Y-m-d');

$stmt = $conn->prepare("SELECT u.userid, u.name, u.badgenumber, u.FPHONE as phone
    FROM userinfo u
    LEFT JOIN (SELECT DISTINCT userid FROM checkinout WHERE DATE(checktime) = ? AND checktype = '0') c ON u.userid = c.userid
    WHERE u.FPHONE IS NOT NULL AND u.FPHONE != '' AND c.userid IS NULL");
$stmt->bind_param("s", $tanggal);
$stmt->execute();
$result = $stmt->get_result();

$processed = 0;
while ($user = $result->fetch_assoc()) {
    if (isAbsentNotified($user['userid'], $tanggal)) continue;
    
    $message = "❌ *NOTIFIKASI TIDAK HADIR*\n\nNama: {$user['name']}\nBadge: {$user['badgenumber']}\nTanggal: " . date('d/m/Y') . "\n\n_Anda tercatat TIDAK HADIR hari ini_";
    
    if (!sendWA(trim($user['phone']), $message)) {
        addToWaQueue(trim($user['phone']), $message, 'tidak_hadir', $user['userid'], $tanggal);
    }
    
    markAbsentNotified($user['userid'], $tanggal);
    $processed++;
}

saveAbsentLog();
if ($processed > 0) logMsg("Processed $processed tidak hadir notifications");

$stmt->close();
