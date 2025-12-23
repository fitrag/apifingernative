<?php
require_once 'config.php';
require_once 'wa_queue.php';
require_once 'settings.php';

// Ambil konfigurasi WA API dari settings
$WA_API_URL = getSetting('wa_api_url', 'http://127.0.0.1:8000/api/send-message');
$WA_TOKEN = getSetting('wa_api_token', '');

// File untuk menyimpan ID absensi terakhir yang diproses
define('LAST_ID_FILE', __DIR__ . '/last_checkin_id.txt');
// File untuk menyimpan user+tanggal+tipe yang sudah dikirim notifikasinya
define('SENT_LOG_FILE', __DIR__ . '/sent_notification_log.json');

// Ambil jam dari settings database
$BATAS_JAM_MASUK = getSetting('jam_masuk', '07:00:00');
$BATAS_JAM_TIDAK_HADIR = getSetting('jam_batas_terlambat', '08:00:00');

function getLastProcessedId()
{
    if (file_exists(LAST_ID_FILE)) {
        return (int) trim(file_get_contents(LAST_ID_FILE));
    }
    return 0;
}

function saveLastProcessedId($id)
{
    file_put_contents(LAST_ID_FILE, $id, LOCK_EX);
}

function getSentLog()
{
    if (file_exists(SENT_LOG_FILE)) {
        $content = file_get_contents(SENT_LOG_FILE);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

function saveSentLog($data)
{
    file_put_contents(SENT_LOG_FILE, json_encode($data), LOCK_EX);
}

// Buat key unik: userid_tanggal_checktype (misal: 2_2025-12-19_0)
function makeNotifKey($userid, $checktime, $checktype)
{
    $tanggal = date('Y-m-d', strtotime($checktime));
    return "{$userid}_{$tanggal}_{$checktype}";
}

function isAlreadyNotified($userid, $checktime, $checktype)
{
    $key = makeNotifKey($userid, $checktime, $checktype);
    $log = getSentLog();
    return isset($log[$key]);
}

function markAsNotified($userid, $checktime, $checktype)
{
    $key = makeNotifKey($userid, $checktime, $checktype);
    $log = getSentLog();
    
    // Hapus data lebih dari 7 hari untuk hemat storage
    $cutoff = date('Y-m-d', strtotime('-7 days'));
    foreach ($log as $k => $v) {
        $parts = explode('_', $k);
        if (isset($parts[1]) && $parts[1] < $cutoff) {
            unset($log[$k]);
        }
    }
    
    $log[$key] = time();
    saveSentLog($log);
}

function sendWhatsApp($phone, $message) {
    global $WA_API_URL, $WA_TOKEN;
    
    $ch = curl_init();
    
    $data = [
        'phone' => $phone,
        'message' => $message
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $WA_API_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $WA_TOKEN
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logMessage("CURL Error: $error");
        return false;
    }
    
    logMessage("WA Response [$httpCode]: $response");
    return $httpCode >= 200 && $httpCode < 300;
}

function logMessage($msg) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/cron_log.txt';
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

// Main Process
$conn = getConnection();
$lastId = getLastProcessedId();

// Cek apakah hari ini libur
if (isHoliday()) {
    logMessage("Skip: Hari ini adalah hari libur (" . getNamaHari(date('N')) . ")");
    exit;
}

// Query absensi baru
$sql = "SELECT 
            c.id,
            c.userid,
            c.checktime,
            c.checktype,
            u.name,
            u.badgenumber,
            u.Card
        FROM checkinout c
        LEFT JOIN userinfo u ON c.userid = u.userid
        WHERE c.id > ?
        ORDER BY c.id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lastId);
$stmt->execute();
$result = $stmt->get_result();

$maxId = $lastId;

while ($row = $result->fetch_assoc()) {
    $currentId = (int) $row['id'];
    $maxId = max($maxId, $currentId);
    
    $userid = $row['userid'];
    $checktime = $row['checktime'];
    $checktype = $row['checktype'];
    $tipe = $checktype == '0' ? 'Check In' : 'Check Out';
    
    // Cek apakah user ini sudah pernah dapat notif untuk tipe ini hari ini
    if (isAlreadyNotified($userid, $checktime, $checktype)) {
        logMessage("Skip ID {$currentId}: User {$row['name']} already notified for {$tipe} today");
        continue;
    }
    
    // Cek apakah nomor WA tersedia di kolom Card
    $phone = trim($row['Card'] ?? '');
    
    if (empty($phone)) {
        logMessage("Skip ID {$currentId}: No phone number for user {$row['name']}");
        markAsNotified($userid, $checktime, $checktype); // Tandai sudah diproses
        continue;
    }
    
    // Format pesan
    $waktu = date('d/m/Y H:i:s', strtotime($checktime));
    $jamAbsen = date('H:i:s', strtotime($checktime));
    
    // Cek apakah Check In
    if ($checktype == '0') {
        if ($jamAbsen > $BATAS_JAM_TIDAK_HADIR) {
            // Check In lebih dari jam batas = TIDAK HADIR
            $message = "❌ *NOTIFIKASI TIDAK HADIR*\n\n";
            $message .= "Nama: {$row['name']}\n";
            $message .= "Badge: {$row['badgenumber']}\n";
            $message .= "Jam Masuk: {$jamAbsen}\n";
            $message .= "Batas Jam: " . $BATAS_JAM_TIDAK_HADIR . "\n";
            $message .= "Tanggal: " . date('d/m/Y', strtotime($checktime)) . "\n\n";
            $message .= "_Anda tercatat TIDAK HADIR karena check in lebih dari jam " . substr($BATAS_JAM_TIDAK_HADIR, 0, 5) . "_";
            
            logMessage("User {$row['name']} TIDAK HADIR - masuk jam {$jamAbsen}");
        } elseif ($jamAbsen > $BATAS_JAM_MASUK) {
            // Check In lebih dari jam masuk tapi sebelum batas = TERLAMBAT
            $message = "⚠️ *NOTIFIKASI KETERLAMBATAN*\n\n";
            $message .= "Nama: {$row['name']}\n";
            $message .= "Badge: {$row['badgenumber']}\n";
            $message .= "Jam Masuk: {$jamAbsen}\n";
            $message .= "Batas Jam: " . $BATAS_JAM_MASUK . "\n";
            $message .= "Tanggal: " . date('d/m/Y', strtotime($checktime)) . "\n\n";
            $message .= "_Anda tercatat TERLAMBAT hari ini_";
            
            logMessage("User {$row['name']} TERLAMBAT - masuk jam {$jamAbsen}");
        } else {
            // Check In tepat waktu
            $message = "📋 *NOTIFIKASI ABSENSI*\n\n";
            $message .= "Nama: {$row['name']}\n";
            $message .= "Badge: {$row['badgenumber']}\n";
            $message .= "Tipe: {$tipe}\n";
            $message .= "Waktu: {$waktu}\n\n";
            $message .= "_Pesan otomatis dari sistem absensi_";
        }
    } else {
        // Check Out
        $message = "📋 *NOTIFIKASI ABSENSI*\n\n";
        $message .= "Nama: {$row['name']}\n";
        $message .= "Badge: {$row['badgenumber']}\n";
        $message .= "Tipe: {$tipe}\n";
        $message .= "Waktu: {$waktu}\n\n";
        $message .= "_Pesan otomatis dari sistem absensi_";
    }
    
    // Kirim WhatsApp
    $sent = sendWhatsApp($phone, $message);
    
    if ($sent) {
        logMessage("SUCCESS: Sent notification to {$phone} for {$row['name']} ({$tipe})");
    } else {
        logMessage("FAILED: Added to queue - {$phone} for {$row['name']}");
        $tanggal = date('Y-m-d', strtotime($checktime));
        addToWaQueue($phone, $message, 'absensi_' . $tipe, $userid, $tanggal);
    }
    
    // Tandai sudah diproses (baik berhasil maupun gagal) supaya tidak kirim ulang
    markAsNotified($userid, $checktime, $checktype);
}

// Simpan ID terakhir
if ($maxId > $lastId) {
    saveLastProcessedId($maxId);
    logMessage("Updated last ID from $lastId to $maxId");
}

$stmt->close();
$conn->close();
?>
