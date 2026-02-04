<?php
// File JSON untuk settings dengan caching
define('SETTINGS_FILE', __DIR__ . '/settings.json');

// Cache settings dalam memory untuk menghindari file read berulang
class SettingsCache {
    private static $cache = null;
    private static $lastModified = 0;
    
    public static function get() {
        $currentMtime = file_exists(SETTINGS_FILE) ? filemtime(SETTINGS_FILE) : 0;
        
        // Reload jika file berubah atau cache kosong
        if (self::$cache === null || self::$lastModified !== $currentMtime) {
            if (file_exists(SETTINGS_FILE)) {
                $content = file_get_contents(SETTINGS_FILE);
                self::$cache = json_decode($content, true) ?: [];
            } else {
                self::$cache = [];
            }
            self::$lastModified = $currentMtime;
        }
        return self::$cache;
    }
    
    public static function set($data) {
        self::$cache = $data;
        file_put_contents(SETTINGS_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
        self::$lastModified = filemtime(SETTINGS_FILE);
    }
    
    public static function invalidate() {
        self::$cache = null;
    }
}

function getSettings() {
    return SettingsCache::get();
}

function saveSettings($data) {
    SettingsCache::set($data);
}

function getSetting($key, $default = null) {
    $settings = getSettings();
    return $settings[$key] ?? $default;
}

function setSetting($key, $value) {
    $settings = getSettings();
    $settings[$key] = $value;
    $settings['updated_at'] = date('Y-m-d H:i:s');
    saveSettings($settings);
}

function getAllSettings() {
    return getSettings();
}

// Batch update settings untuk mengurangi file writes
function setSettings($keyValues) {
    $settings = getSettings();
    foreach ($keyValues as $key => $value) {
        $settings[$key] = $value;
    }
    $settings['updated_at'] = date('Y-m-d H:i:s');
    saveSettings($settings);
}

// Default values
function initDefaultSettings() {
    $defaults = array(
        'jam_masuk' => '07:00:00',
        'jam_batas_terlambat' => '08:00:00',
        'jam_batas_pulang' => '17:00:00',
        'wa_api_url' => 'http://127.0.0.1:8000/api/send-message',
        'wa_api_token' => 'wag_kH8tKPAFqn2lSQi80SkvLhJI4tzrAhqR',
        'timezone' => 'Asia/Jakarta'
    );
    
    foreach ($defaults as $key => $value) {
        if (getSetting($key) === null) {
            setSetting($key, $value);
        }
    }
}

// Apply timezone setting
function applyTimezone() {
    $timezone = getSetting('timezone', 'Asia/Jakarta');
    date_default_timezone_set($timezone);
}

// Get list of common timezones
function getTimezoneList() {
    return [
        'Asia/Jakarta' => 'WIB - Jakarta (UTC+7)',
        'Asia/Makassar' => 'WITA - Makassar (UTC+8)',
        'Asia/Jayapura' => 'WIT - Jayapura (UTC+9)',
        'Asia/Singapore' => 'Singapore (UTC+8)',
        'Asia/Kuala_Lumpur' => 'Kuala Lumpur (UTC+8)',
        'Asia/Bangkok' => 'Bangkok (UTC+7)',
        'Asia/Ho_Chi_Minh' => 'Ho Chi Minh (UTC+7)',
        'Asia/Manila' => 'Manila (UTC+8)',
        'Asia/Tokyo' => 'Tokyo (UTC+9)',
        'Asia/Seoul' => 'Seoul (UTC+9)',
        'Asia/Shanghai' => 'Shanghai (UTC+8)',
        'Asia/Hong_Kong' => 'Hong Kong (UTC+8)',
        'Asia/Kolkata' => 'India (UTC+5:30)',
        'Asia/Dubai' => 'Dubai (UTC+4)',
        'Europe/London' => 'London (UTC+0)',
        'Europe/Paris' => 'Paris (UTC+1)',
        'Europe/Berlin' => 'Berlin (UTC+1)',
        'America/New_York' => 'New York (UTC-5)',
        'America/Los_Angeles' => 'Los Angeles (UTC-8)',
        'Australia/Sydney' => 'Sydney (UTC+10)',
        'Pacific/Auckland' => 'Auckland (UTC+12)'
    ];
}

// Check if today is a holiday (weekend)
function isHoliday($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
    $hariLibur = getSetting('hari_libur', ['6', '7']); // Default: Sabtu(6), Minggu(7)
    
    return in_array($dayOfWeek, $hariLibur);
}

// Get day name in Indonesian
function getNamaHari($dayNumber) {
    $days = [
        '1' => 'Senin',
        '2' => 'Selasa',
        '3' => 'Rabu',
        '4' => 'Kamis',
        '5' => 'Jumat',
        '6' => 'Sabtu',
        '7' => 'Minggu'
    ];
    return $days[$dayNumber] ?? '';
}

// Get all days list
function getDaysList() {
    return [
        '1' => 'Senin',
        '2' => 'Selasa',
        '3' => 'Rabu',
        '4' => 'Kamis',
        '5' => 'Jumat',
        '6' => 'Sabtu',
        '7' => 'Minggu'
    ];
}

// Initialize defaults
initDefaultSettings();

// Apply timezone on load
applyTimezone();
