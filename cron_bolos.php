<?php
/**
 * Cron Job: Notifikasi Bolos
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
$BATAS_JAM_PULANG = $settings['jam_batas_pulang'] ?? '17:00:00';
$JAM_AUTO_CHECKOUT_HARIAN = $settings['jam_auto_checkout_harian'] ?? [
    '1' => '14:00', '2' => '14:00', '3' => '14:00', '4' => '14:00',
    '5' => '14:00', '6' => '12:00', '7' => '12:00'
];

// Hanya jalankan setelah jam batas pulang
if (date('H:i:s') < $BATAS_JAM_PULANG) exit;

define('BOLOS_LOG_FILE', __DIR__ . '/sent_bolos_log.json');

$bolosLogCache = null;

function getJamAutoCheckout($tanggal) {
    global $JAM_AUTO_CHECKOUT_HARIAN;
    $day = date('N', strtotime($tanggal));
    $jam = $JAM_AUTO_CHECKOUT_HARIAN[$day] ?? '14:00';
    return strlen($jam) === 5 ? $jam . ':00' : $jam;
}

function getBolosLog() {
    global $bolosLogCache;
    if ($bolosLogCache === null) {
        $bolosLogCache = file_exists(BOLOS_LOG_FILE) ? (json_decode(file_get_contents(BOLOS_LOG_FILE), true) ?: []) : [];
    }
    return $bolosLogCache;
}

function saveBolosLog() {
    global $bolosLogCache;
    if ($bolosLogCache !== null) {
        $cutoff = date('Y-m-d', strtotime('-7 days'));
        foreach ($bolosLogCache as $k => $v) {
            $parts = explode('_', $k);
            if (isset($parts[1]) && $parts[1] < $cutoff) unset($bolosLogCache[$k]);
        }
        file_put_contents(BOLOS_LOG_FILE, json_encode($bolosLogCache), LOCK_EX);
    }
}

function isBolosNotified($userid, $tanggal) {
    return isset(getBolosLog()["{$userid}_{$tanggal}_bolos"]);
}

function markBolosNotified($userid, $tanggal) {
    global $bolosLogCache;
    if ($bolosLogCache === null) getBolosLog();
    $bolosLogCache["{$userid}_{$tanggal}_bolos"] = time();
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
    file_put_contents(__DIR__ . '/cron_bolos_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Main
$conn = getConnection();
$hariIni = date('Y-m-d');
$tanggal7HariLalu = date('Y-m-d', strtotime('-7 days'));

$stmt = $conn->prepare("SELECT u.userid, u.name, u.badgenumber, u.FPHONE as phone, ci.tanggal, ci.jam_masuk, co.jam_pulang
    FROM userinfo u
    INNER JOIN (SELECT userid, DATE(checktime) as tanggal, MIN(TIME(checktime)) as jam_masuk
        FROM checkinout WHERE checktype = '0' AND DATE(checktime) >= ? AND DATE(checktime) <= ?
        GROUP BY userid, DATE(checktime)) ci ON u.userid = ci.userid
    LEFT JOIN (SELECT userid, DATE(checktime) as tanggal, MAX(TIME(checktime)) as jam_pulang
        FROM checkinout WHERE checktype = '0' AND DATE(checktime) >= ?
        GROUP BY userid, DATE(checktime)) co ON u.userid = co.userid AND ci.tanggal = co.tanggal
    WHERE u.FPHONE IS NOT NULL AND u.FPHONE != ''
    ORDER BY ci.tanggal DESC");
$stmt->bind_param("sss", $tanggal7HariLalu, $hariIni, $tanggal7HariLalu);
$stmt->execute();
$result = $stmt->get_result();

$processed = 0;
while ($row = $result->fetch_assoc()) {
    $tgl = $row['tanggal'];
    
    // Skip hari libur dan yang sudah dinotifikasi
    if (isHoliday($tgl) || isBolosNotified($row['userid'], $tgl)) continue;
    
    $jamAutoCheckout = getJamAutoCheckout($tgl);
    
    // Jika ada absen setelah jam auto checkout = PULANG (tidak bolos)
    if ($row['jam_pulang'] !== null && $row['jam_pulang'] >= $jamAutoCheckout) continue;
    
    $message = "🚫 *NOTIFIKASI BOLOS*\n\nNama: {$row['name']}\nBadge: {$row['badgenumber']}\nJam Masuk: {$row['jam_masuk']}\nTanggal: " . date('d/m/Y', strtotime($tgl)) . "\n\n_Tidak ada absensi pulang_";
    
    if (!sendWA(trim($row['phone']), $message)) {
        addToWaQueue(trim($row['phone']), $message, 'bolos', $row['userid'], $tgl);
    }
    
    markBolosNotified($row['userid'], $tgl);
    logMsg("[{$tgl}] {$row['name']} BOLOS");
    $processed++;
}

saveBolosLog();
$stmt->close();
