<?php
require_once 'config.php';
require_once 'wa_queue.php';
require_once 'settings.php';

$WA_API_URL = getSetting('wa_api_url', 'http://127.0.0.1:8000/api/send-message');
$WA_TOKEN = getSetting('wa_api_token', '');

define('ABSENT_LOG_FILE', __DIR__ . '/sent_absent_log.json');

$BATAS_JAM = getSetting('jam_batas_terlambat', '08:00:00');

function getAbsentLog() {
    if (file_exists(ABSENT_LOG_FILE)) {
        $content = file_get_contents(ABSENT_LOG_FILE);
        $data = json_decode($content, true);
        if (is_array($data)) return $data;
    }
    return [];
}

function saveAbsentLog($data) {
    file_put_contents(ABSENT_LOG_FILE, json_encode($data), LOCK_EX);
}

function isAbsentNotified($userid, $tanggal) {
    $key = "{$userid}_{$tanggal}_absent";
    $log = getAbsentLog();
    return isset($log[$key]);
}

function markAbsentNotified($userid, $tanggal) {
    $key = "{$userid}_{$tanggal}_absent";
    $log = getAbsentLog();
    $cutoff = date('Y-m-d', strtotime('-7 days'));
    foreach ($log as $k => $v) {
        $parts = explode('_', $k);
        if (isset($parts[1]) && $parts[1] < $cutoff) unset($log[$k]);
    }
    $log[$key] = time();
    saveAbsentLog($log);
}

function sendWhatsApp($phone, $message) {
    global $WA_API_URL, $WA_TOKEN;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $WA_API_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['phone' => $phone, 'message' => $message]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $WA_TOKEN],
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) { logMessage("CURL Error: $error"); return false; }
    logMessage("WA Response [$httpCode]: $response");
    return $httpCode >= 200 && $httpCode < 300;
}

function logMessage($msg) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(__DIR__ . '/cron_absent_log.txt', "[$timestamp] $msg\n", FILE_APPEND);
}

// Main Process
$conn = getConnection();
$tanggalHariIni = date('Y-m-d');
$jamSekarang = date('H:i:s');

// Cek apakah hari ini libur
if (isHoliday()) {
    logMessage("Skip: Hari ini adalah hari libur (" . getNamaHari(date('N')) . ")");
    exit;
}

// Hanya jalankan setelah jam batas
if ($jamSekarang < $BATAS_JAM) {
    exit;
}

// OPTIMIZED: Single query untuk mendapatkan semua user yang belum absen
// Menggunakan LEFT JOIN untuk menghindari N+1 query
$sql = "SELECT u.userid, u.name, u.badgenumber, u.Card as phone
        FROM userinfo u
        LEFT JOIN (
            SELECT DISTINCT userid 
            FROM checkinout 
            WHERE DATE(checktime) = ? AND checktype = '0'
        ) c ON u.userid = c.userid
        WHERE u.Card IS NOT NULL AND u.Card != ''
        AND c.userid IS NULL";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tanggalHariIni);
$stmt->execute();
$result = $stmt->get_result();

while ($user = $result->fetch_assoc()) {
    $userid = $user['userid'];
    $phone = trim($user['phone']);
    
    if (isAbsentNotified($userid, $tanggalHariIni)) {
        continue;
    }
    
    $message = "❌ *NOTIFIKASI TIDAK HADIR*\n\n";
    $message .= "Nama: {$user['name']}\n";
    $message .= "Badge: {$user['badgenumber']}\n";
    $message .= "Tanggal: " . date('d/m/Y') . "\n";
    $message .= "Batas Jam: " . $BATAS_JAM . "\n\n";
    $message .= "_Anda tercatat TIDAK HADIR hari ini_";
    
    logMessage("User {$user['name']} TIDAK HADIR - belum absen sama sekali");
    
    $sent = sendWhatsApp($phone, $message);
    
    if ($sent) {
        logMessage("SUCCESS: Sent absent notification to {$phone} for {$user['name']}");
    } else {
        logMessage("FAILED: Added to queue - {$phone} for {$user['name']}");
        addToWaQueue($phone, $message, 'tidak_hadir', $userid, $tanggalHariIni);
    }
    
    markAbsentNotified($userid, $tanggalHariIni);
}

$stmt->close();
$conn->close();
?>
