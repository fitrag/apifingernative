<?php
require_once 'config.php';
require_once 'wa_queue.php';
require_once 'settings.php';

$WA_API_URL = getSetting('wa_api_url', 'http://127.0.0.1:8000/api/send-message');
$WA_TOKEN = getSetting('wa_api_token', '');

define('BOLOS_LOG_FILE', __DIR__ . '/sent_bolos_log.json');

$BATAS_JAM_MASUK_BOLOS = getSetting('jam_batas_terlambat', '08:00:00');
$BATAS_JAM_CHECKOUT = getSetting('jam_batas_pulang', '17:00:00');

function getBolosLog() {
    if (file_exists(BOLOS_LOG_FILE)) {
        $content = file_get_contents(BOLOS_LOG_FILE);
        $data = json_decode($content, true);
        if (is_array($data)) return $data;
    }
    return [];
}

function saveBolosLog($data) {
    file_put_contents(BOLOS_LOG_FILE, json_encode($data), LOCK_EX);
}

function isBolosNotified($userid, $tanggal) {
    $key = $userid . '_' . $tanggal . '_bolos';
    $log = getBolosLog();
    return isset($log[$key]);
}

function markBolosNotified($userid, $tanggal) {
    $key = $userid . '_' . $tanggal . '_bolos';
    $log = getBolosLog();
    $cutoff = date('Y-m-d', strtotime('-7 days'));
    foreach ($log as $k => $v) {
        $parts = explode('_', $k);
        if (isset($parts[1]) && $parts[1] < $cutoff) unset($log[$k]);
    }
    $log[$key] = time();
    saveBolosLog($log);
}

function sendWABolos($phone, $message) {
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
    
    if ($error) { logBolos('CURL Error: ' . $error); return false; }
    logBolos('WA Response [' . $httpCode . ']: ' . $response);
    return $httpCode >= 200 && $httpCode < 300;
}

function logBolos($msg) {
    $ts = date('Y-m-d H:i:s');
    file_put_contents(__DIR__ . '/cron_bolos_log.txt', '[' . $ts . '] ' . $msg . "\n", FILE_APPEND);
}

$conn = getConnection();
$hariIni = date('Y-m-d');
$jamSekarang = date('H:i:s');

// Cek apakah hari ini libur
if (isHoliday()) {
    logBolos("Skip: Hari ini adalah hari libur (" . getNamaHari(date('N')) . ")");
    exit;
}

$tanggal30HariLalu = date('Y-m-d', strtotime('-30 days'));

// OPTIMIZED: Single query untuk mendapatkan semua user yang bolos
// Menggabungkan check in dan check out dalam satu query
$sql = "SELECT 
            u.userid,
            u.name,
            u.badgenumber,
            u.Card as phone,
            ci.tanggal,
            ci.jam_masuk
        FROM userinfo u
        INNER JOIN (
            SELECT userid, DATE(checktime) as tanggal, MIN(TIME(checktime)) as jam_masuk
            FROM checkinout 
            WHERE checktype = '0' 
            AND DATE(checktime) >= ?
            AND DATE(checktime) <= ?
            GROUP BY userid, DATE(checktime)
            HAVING MIN(TIME(checktime)) <= ?
        ) ci ON u.userid = ci.userid
        LEFT JOIN (
            SELECT DISTINCT userid, DATE(checktime) as tanggal
            FROM checkinout 
            WHERE checktype = '1' 
            AND DATE(checktime) >= ?
        ) co ON u.userid = co.userid AND ci.tanggal = co.tanggal
        WHERE u.Card IS NOT NULL AND u.Card != ''
        AND co.userid IS NULL
        ORDER BY ci.tanggal DESC";

// Untuk hari ini, hanya proses jika sudah lewat jam checkout
$tanggalBatas = ($jamSekarang >= $BATAS_JAM_CHECKOUT) ? $hariIni : date('Y-m-d', strtotime('-1 day'));

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $tanggal30HariLalu, $tanggalBatas, $BATAS_JAM_MASUK_BOLOS, $tanggal30HariLalu);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $uid = $row['userid'];
    $tgl = $row['tanggal'];
    $hp = trim($row['phone']);
    
    // Skip jika tanggal tersebut adalah hari libur
    if (isHoliday($tgl)) {
        logBolos("Skip tanggal " . $tgl . ": Hari libur (" . getNamaHari(date('N', strtotime($tgl))) . ")");
        continue;
    }
    
    if (isBolosNotified($uid, $tgl)) continue;
    
    $msg = "🚫 *NOTIFIKASI BOLOS*\n\n";
    $msg .= "Nama: " . $row['name'] . "\n";
    $msg .= "Badge: " . $row['badgenumber'] . "\n";
    $msg .= "Jam Masuk: " . $row['jam_masuk'] . "\n";
    $msg .= "Tanggal: " . date('d/m/Y', strtotime($tgl)) . "\n\n";
    $msg .= "_Anda tercatat BOLOS karena tidak melakukan check out_";
    
    logBolos("[" . $tgl . "] User " . $row['name'] . " BOLOS - masuk " . $row['jam_masuk'] . " tanpa checkout");
    
    $sent = sendWABolos($hp, $msg);
    if ($sent) {
        logBolos("SUCCESS: Sent to " . $hp);
    } else {
        logBolos("FAILED: Added to queue - " . $hp);
        addToWaQueue($hp, $msg, 'bolos', $uid, $tgl);
    }
    markBolosNotified($uid, $tgl);
}

$stmt->close();
$conn->close();
?>
