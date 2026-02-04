<?php
/**
 * Cron Job: Notifikasi Izin/Sakit dari API SIAKAD
 * Optimized Version
 */

require_once 'config.php';
require_once 'wa_queue.php';
require_once 'settings.php';
require_once 'log_cleaner.php';

$settings = getSettings();
$WA_API_URL = $settings['wa_api_url'] ?? 'http://127.0.0.1:8000/api/send-message';
$WA_TOKEN = $settings['wa_api_token'] ?? '';
$SIAKAD_API_URL = $settings['siakad_api_url'] ?? 'https://siakads.kurikulum-skansa.id/api/attendance/absences';
$SIAKAD_API_TOKEN = $settings['siakad_api_token'] ?? '';

define('SENT_IZIN_LOG_FILE', __DIR__ . '/sent_izin_log.json');

$izinLogCache = null;

function getIzinLog() {
    global $izinLogCache;
    if ($izinLogCache === null) {
        $izinLogCache = file_exists(SENT_IZIN_LOG_FILE) ? (json_decode(file_get_contents(SENT_IZIN_LOG_FILE), true) ?: []) : [];
    }
    return $izinLogCache;
}

function saveIzinLog() {
    global $izinLogCache;
    if ($izinLogCache !== null) {
        $cutoff = time() - (7 * 24 * 60 * 60);
        foreach ($izinLogCache as $k => $v) {
            if (is_numeric($v) && $v < $cutoff) unset($izinLogCache[$k]);
        }
        file_put_contents(SENT_IZIN_LOG_FILE, json_encode($izinLogCache), LOCK_EX);
    }
}

function isIzinNotified($id) {
    return isset(getIzinLog()[strval($id)]);
}

function markIzinNotified($id) {
    global $izinLogCache;
    if ($izinLogCache === null) getIzinLog();
    $izinLogCache[strval($id)] = time();
}

function fetchAPI() {
    global $SIAKAD_API_URL, $SIAKAD_API_TOKEN;
    $ch = curl_init();
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (!empty($SIAKAD_API_TOKEN)) $headers[] = 'Authorization: Bearer ' . $SIAKAD_API_TOKEN;
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $SIAKAD_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200 ? json_decode($response, true) : null;
}

function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return substr($phone, 0, 1) === '0' ? '62' . substr($phone, 1) : $phone;
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
    file_put_contents(__DIR__ . '/cron_izin_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Main
$apiData = fetchAPI();
if (!$apiData || !isset($apiData['success']) || !$apiData['success']) exit;

$absences = $apiData['data'] ?? [];
if (empty($absences)) exit;

$tanggal = $apiData['summary']['date'] ?? date('Y-m-d');
$formattedDate = date('d/m/Y', strtotime($tanggal));

$processed = 0;
foreach ($absences as $absence) {
    $id = strval($absence['id']);
    
    if (isIzinNotified($id)) continue;
    markIzinNotified($id); // Mark early to prevent duplicates
    
    $phone = $absence['no_tlp'] ?? '';
    if (empty($phone)) continue;
    
    $phone = formatPhone($phone);
    $nama = $absence['nama'];
    $keterangan = $absence['keterangan'];
    $isSakit = strtolower($keterangan) === 'sakit';
    
    $emoji = $isSakit ? '🏥' : '📝';
    $message = "{$emoji} *NOTIFIKASI " . strtoupper($keterangan) . "*\n\nNama: {$nama}\nStatus: {$keterangan}\nTanggal: {$formattedDate}\n\n_" . ($isSakit ? "Semoga lekas sembuh" : "Terima kasih telah menginformasikan") . "_";
    
    if (!sendWA($phone, $message)) {
        addToWaQueue($phone, $message, 'izin_' . strtolower($keterangan), $id, $tanggal);
    }
    
    $processed++;
    usleep(300000); // 300ms delay
}

saveIzinLog();
if ($processed > 0) logMsg("Processed $processed izin/sakit notifications");
