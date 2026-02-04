<?php
/**
 * Cron Job: Notifikasi Absensi (Masuk/Pulang/Terlambat)
 * Optimized Version
 */

require_once 'config.php';
require_once 'wa_queue.php';
require_once 'settings.php';
require_once 'log_cleaner.php';

// Bersihkan log lama (hanya sekali per hari)
$lastCleanFile = __DIR__ . '/last_clean.txt';
$today = date('Y-m-d');
if (!file_exists($lastCleanFile) || trim(file_get_contents($lastCleanFile)) !== $today) {
    cleanAllCronLogs();
    file_put_contents($lastCleanFile, $today);
}

// Cek hari libur dulu sebelum load settings lainnya
if (isHoliday()) exit;

// Load settings sekali saja
$settings = getSettings();
$WA_API_URL = $settings['wa_api_url'] ?? 'http://127.0.0.1:8000/api/send-message';
$WA_TOKEN = $settings['wa_api_token'] ?? '';
$BATAS_JAM_MASUK = $settings['jam_masuk'] ?? '07:00:00';
$BATAS_JAM_TIDAK_HADIR = $settings['jam_batas_terlambat'] ?? '08:00:00';
$JAM_AUTO_CHECKOUT_HARIAN = $settings['jam_auto_checkout_harian'] ?? [
    '1' => '14:00', '2' => '14:00', '3' => '14:00', '4' => '14:00',
    '5' => '14:00', '6' => '12:00', '7' => '12:00'
];

define('LAST_ID_FILE', __DIR__ . '/last_checkin_id.txt');
define('SENT_LOG_FILE', __DIR__ . '/sent_notification_log.json');

// Cache sent log dalam memory
$sentLogCache = null;

function getJamAutoCheckout($checktime) {
    global $JAM_AUTO_CHECKOUT_HARIAN;
    $day = date('N', strtotime($checktime));
    $jam = $JAM_AUTO_CHECKOUT_HARIAN[$day] ?? '14:00';
    return strlen($jam) === 5 ? $jam . ':00' : $jam;
}

function getLastProcessedId() {
    return file_exists(LAST_ID_FILE) ? (int)trim(file_get_contents(LAST_ID_FILE)) : 0;
}

function getSentLog() {
    global $sentLogCache;
    if ($sentLogCache === null) {
        $sentLogCache = file_exists(SENT_LOG_FILE) ? (json_decode(file_get_contents(SENT_LOG_FILE), true) ?: []) : [];
    }
    return $sentLogCache;
}

function saveSentLog() {
    global $sentLogCache;
    if ($sentLogCache !== null) {
        // Cleanup old entries
        $cutoff = date('Y-m-d', strtotime('-7 days'));
        foreach ($sentLogCache as $k => $v) {
            $parts = explode('_', $k);
            if (isset($parts[1]) && $parts[1] < $cutoff) unset($sentLogCache[$k]);
        }
        file_put_contents(SENT_LOG_FILE, json_encode($sentLogCache), LOCK_EX);
    }
}

function isNotified($userid, $checktime, $checktype) {
    $key = "{$userid}_" . date('Y-m-d', strtotime($checktime)) . "_{$checktype}";
    return isset(getSentLog()[$key]);
}

function markNotified($userid, $checktime, $checktype) {
    global $sentLogCache;
    $key = "{$userid}_" . date('Y-m-d', strtotime($checktime)) . "_{$checktype}";
    if ($sentLogCache === null) getSentLog();
    $sentLogCache[$key] = time();
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
    $httpCode = 0;
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

function logMsg($msg) {
    file_put_contents(__DIR__ . '/cron_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Main
$conn = getConnection();
$lastId = getLastProcessedId();

$stmt = $conn->prepare("SELECT c.id, c.userid, c.checktime, c.checktype, u.name, u.badgenumber, u.FPHONE 
    FROM checkinout c LEFT JOIN userinfo u ON c.userid = u.userid 
    WHERE c.id > ? ORDER BY c.id ASC LIMIT 50");
$stmt->bind_param("i", $lastId);
$stmt->execute();
$result = $stmt->get_result();

$maxId = $lastId;
$processed = 0;

while ($row = $result->fetch_assoc()) {
    $maxId = max($maxId, (int)$row['id']);
    $phone = trim($row['FPHONE'] ?? '');
    
    if (empty($phone) || isNotified($row['userid'], $row['checktime'], $row['checktype'])) {
        markNotified($row['userid'], $row['checktime'], $row['checktype']);
        continue;
    }
    
    $jamAbsen = date('H:i:s', strtotime($row['checktime']));
    $jamAutoCheckout = getJamAutoCheckout($row['checktime']);
    $isAutoCheckout = ($row['checktype'] == '0' && $jamAbsen >= $jamAutoCheckout);
    
    // Build message
    if ($row['checktype'] == '0' && !$isAutoCheckout) {
        if ($jamAbsen > $BATAS_JAM_TIDAK_HADIR) {
            $message = "❌ *NOTIFIKASI TIDAK HADIR*\n\nNama: {$row['name']}\nNIS: {$row['badgenumber']}\nJam Masuk: {$jamAbsen}\nTanggal: " . date('d/m/Y', strtotime($row['checktime'])) . "\n\n_Masuk lebih dari jam " . substr($BATAS_JAM_TIDAK_HADIR, 0, 5) . "_";
        } elseif ($jamAbsen > $BATAS_JAM_MASUK) {
            $message = "⚠️ *NOTIFIKASI TERLAMBAT*\n\nNama: {$row['name']}\nNIS: {$row['badgenumber']}\nJam Masuk: {$jamAbsen}\nTanggal: " . date('d/m/Y', strtotime($row['checktime'])) . "\n\n_Anda tercatat TERLAMBAT_";
        } else {
            $message = "✅ *NOTIFIKASI MASUK*\n\nNama: {$row['name']}\nNIS: {$row['badgenumber']}\nWaktu: " . date('d/m/Y H:i:s', strtotime($row['checktime'])) . "\n\n_Hadir tepat waktu_";
        }
    } else {
        $message = "🏠 *NOTIFIKASI PULANG*\n\nNama: {$row['name']}\nNIS: {$row['badgenumber']}\nWaktu: " . date('d/m/Y H:i:s', strtotime($row['checktime'])) . "\n\n_Hati-hati di jalan_";
    }
    
    if (!sendWA($phone, $message)) {
        addToWaQueue($phone, $message, 'absensi', $row['userid'], date('Y-m-d', strtotime($row['checktime'])));
    }
    
    markNotified($row['userid'], $row['checktime'], $row['checktype']);
    $processed++;
}

// Save all at once
if ($maxId > $lastId) file_put_contents(LAST_ID_FILE, $maxId, LOCK_EX);
saveSentLog();

$stmt->close();
