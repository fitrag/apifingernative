<?php
require_once 'settings.php';
require_once 'layout.php';

$message = '';
$messageType = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';
    
    if ($formType == 'jam') {
        $jamMasuk = $_POST['jam_masuk'] ?? '';
        $jamTerlambat = $_POST['jam_batas_terlambat'] ?? '';
        $jamPulang = $_POST['jam_batas_pulang'] ?? '';
        
        $valid = true;
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jamMasuk)) $valid = false;
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jamTerlambat)) $valid = false;
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jamPulang)) $valid = false;
        
        if ($valid) {
            if (strlen($jamMasuk) == 5) $jamMasuk .= ':00';
            if (strlen($jamTerlambat) == 5) $jamTerlambat .= ':00';
            if (strlen($jamPulang) == 5) $jamPulang .= ':00';
            
            setSetting('jam_masuk', $jamMasuk);
            setSetting('jam_batas_terlambat', $jamTerlambat);
            setSetting('jam_batas_pulang', $jamPulang);
            
            $message = 'Pengaturan jam berhasil disimpan!';
            $messageType = 'success';
        } else {
            $message = 'Format jam tidak valid!';
            $messageType = 'error';
        }
    } elseif ($formType == 'wa_api') {
        $waApiUrl = trim($_POST['wa_api_url'] ?? '');
        $waApiToken = trim($_POST['wa_api_token'] ?? '');
        
        if (!empty($waApiUrl)) {
            setSetting('wa_api_url', $waApiUrl);
            setSetting('wa_api_token', $waApiToken);
            
            $message = 'Pengaturan WhatsApp API berhasil disimpan!';
            $messageType = 'success';
        } else {
            $message = 'URL API tidak boleh kosong!';
            $messageType = 'error';
        }
    } elseif ($formType == 'timezone') {
        $timezone = $_POST['timezone'] ?? 'Asia/Jakarta';
        $timezoneList = getTimezoneList();
        
        if (array_key_exists($timezone, $timezoneList)) {
            setSetting('timezone', $timezone);
            applyTimezone();
            
            $message = 'Zona waktu berhasil diubah ke ' . $timezoneList[$timezone] . '!';
            $messageType = 'success';
        } else {
            $message = 'Zona waktu tidak valid!';
            $messageType = 'error';
        }
    } elseif ($formType == 'hari_libur') {
        $hariLibur = $_POST['hari_libur'] ?? [];
        
        // Validasi input
        $validDays = ['1', '2', '3', '4', '5', '6', '7'];
        $hariLibur = array_filter($hariLibur, function($day) use ($validDays) {
            return in_array($day, $validDays);
        });
        
        setSetting('hari_libur', array_values($hariLibur));
        
        $namaHari = array_map(function($d) { return getNamaHari($d); }, $hariLibur);
        $message = 'Hari libur berhasil disimpan: ' . (empty($namaHari) ? 'Tidak ada' : implode(', ', $namaHari));
        $messageType = 'success';
    }
}

$jamMasuk = getSetting('jam_masuk', '07:00:00');
$jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');
$jamPulang = getSetting('jam_batas_pulang', '17:00:00');
$waApiUrl = getSetting('wa_api_url', 'http://127.0.0.1:8000/api/send-message');
$waApiToken = getSetting('wa_api_token', '');
$currentTimezone = getSetting('timezone', 'Asia/Jakarta');
$timezoneList = getTimezoneList();
$hariLibur = getSetting('hari_libur', ['6', '7']);
$daysList = getDaysList();

renderHeader('Pengaturan', 'pengaturan');
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>"><span class="lnr lnr-<?= $messageType == 'success' ? 'checkmark-circle' : 'warning' ?>"></span> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">

<div class="card">
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-clock"></span> Pengaturan Jam Absensi</div>
        <div class="card-subtitle">Atur batas waktu untuk notifikasi</div>
    </div>
    
    <form method="POST">
        <input type="hidden" name="form_type" value="jam">
        <div class="form-group">
            <label class="form-label"><span class="lnr lnr-enter"></span> Jam Masuk (Tepat Waktu)</label>
            <input type="time" name="jam_masuk" value="<?= substr($jamMasuk, 0, 5) ?>" class="form-input" required>
            <div class="form-hint">Check in sebelum jam ini = Tepat Waktu</div>
        </div>
        
        <div class="form-group">
            <label class="form-label"><span class="lnr lnr-history"></span> Jam Batas Terlambat</label>
            <input type="time" name="jam_batas_terlambat" value="<?= substr($jamTerlambat, 0, 5) ?>" class="form-input" required>
            <div class="form-hint">Check in setelah jam masuk s/d jam ini = Terlambat</div>
        </div>
        
        <div class="form-group">
            <label class="form-label"><span class="lnr lnr-exit"></span> Jam Batas Pulang</label>
            <input type="time" name="jam_batas_pulang" value="<?= substr($jamPulang, 0, 5) ?>" class="form-input" required>
            <div class="form-hint">Setelah jam ini tanpa checkout = Bolos</div>
        </div>
        
        <button type="submit" class="btn btn-primary"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-bubble"></span> Pengaturan WhatsApp API</div>
        <div class="card-subtitle">Konfigurasi endpoint untuk notifikasi WA</div>
    </div>
    
    <form method="POST">
        <input type="hidden" name="form_type" value="wa_api">
        <div class="form-group">
            <label class="form-label"><span class="lnr lnr-link"></span> API URL</label>
            <input type="url" name="wa_api_url" value="<?= htmlspecialchars($waApiUrl) ?>" class="form-input" placeholder="http://127.0.0.1:8000/api/send-message" required>
            <div class="form-hint">Endpoint URL untuk mengirim pesan WhatsApp</div>
        </div>
        
        <div class="form-group">
            <label class="form-label"><span class="lnr lnr-lock"></span> API Token</label>
            <input type="text" name="wa_api_token" value="<?= htmlspecialchars($waApiToken) ?>" class="form-input" placeholder="Bearer token">
            <div class="form-hint">Token autentikasi (Bearer token)</div>
        </div>
        
        <button type="submit" class="btn btn-primary"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
    </form>
</div>

</div>

<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-earth"></span> Pengaturan Zona Waktu</div>
        <div class="card-subtitle">Atur zona waktu untuk aplikasi</div>
    </div>
    
    <form method="POST">
        <input type="hidden" name="form_type" value="timezone">
        <div class="form-group">
            <label class="form-label"><span class="lnr lnr-clock"></span> Zona Waktu</label>
            <select name="timezone" class="form-input">
                <?php foreach ($timezoneList as $tz => $label): ?>
                <option value="<?= $tz ?>" <?= $currentTimezone == $tz ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-hint">Zona waktu saat ini: <strong><?= $timezoneList[$currentTimezone] ?? $currentTimezone ?></strong></div>
        </div>
        
        <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 16px;">
            <div style="background: #f1f5f9; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                <span class="lnr lnr-clock" style="font-size: 24px; color: #3b82f6;"></span>
                <div>
                    <div style="font-size: 12px; color: #64748b;">Waktu Server Saat Ini</div>
                    <div style="font-size: 18px; font-weight: 600; color: #1e293b;" id="currentTime"><?= date('H:i:s') ?></div>
                </div>
            </div>
            <div style="background: #f1f5f9; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                <span class="lnr lnr-calendar-full" style="font-size: 24px; color: #22c55e;"></span>
                <div>
                    <div style="font-size: 12px; color: #64748b;">Tanggal</div>
                    <div style="font-size: 18px; font-weight: 600; color: #1e293b;"><?= date('d M Y') ?></div>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary"><span class="lnr lnr-checkmark-circle"></span> Simpan Zona Waktu</button>
    </form>
</div>

<script>
// Update current time every second
setInterval(function() {
    fetch('api_time.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('currentTime').textContent = data.time;
        })
        .catch(() => {});
}, 1000);
</script>

<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-calendar-full"></span> Pengaturan Hari Libur</div>
        <div class="card-subtitle">Pilih hari yang tidak mengirim notifikasi WhatsApp</div>
    </div>
    
    <form method="POST">
        <input type="hidden" name="form_type" value="hari_libur">
        <div class="form-group">
            <label class="form-label"><span class="lnr lnr-calendar-full"></span> Hari Libur</label>
            <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px;">
                <?php foreach ($daysList as $dayNum => $dayName): ?>
                <label style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: <?= in_array($dayNum, $hariLibur) ? '#fee2e2' : '#f1f5f9' ?>; border-radius: 8px; cursor: pointer; border: 2px solid <?= in_array($dayNum, $hariLibur) ? '#ef4444' : 'transparent' ?>; transition: all 0.2s;">
                    <input type="checkbox" name="hari_libur[]" value="<?= $dayNum ?>" <?= in_array($dayNum, $hariLibur) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #ef4444;">
                    <span style="font-weight: 500; color: <?= in_array($dayNum, $hariLibur) ? '#991b1b' : '#475569' ?>;"><?= $dayName ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="form-hint" style="margin-top: 12px;">Notifikasi WhatsApp tidak akan dikirim pada hari yang dipilih</div>
        </div>
        
        <div style="background: #fef3c7; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
            <span class="lnr lnr-warning" style="color: #92400e; font-size: 20px;"></span>
            <div style="color: #92400e; font-size: 13px;">
                <strong>Hari libur saat ini:</strong> 
                <?php 
                $namaHariLibur = array_map(function($d) use ($daysList) { return $daysList[$d] ?? ''; }, $hariLibur);
                echo empty($namaHariLibur) ? 'Tidak ada (semua hari kerja)' : implode(', ', $namaHariLibur);
                ?>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary"><span class="lnr lnr-checkmark-circle"></span> Simpan Hari Libur</button>
    </form>
</div>

<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-file-empty"></span> Keterangan Notifikasi</div>
    </div>
    <ul style="padding-left: 20px; color: #475569; line-height: 2;">
        <li><span class="lnr lnr-checkmark-circle" style="color: #22c55e;"></span> <strong>Tepat Waktu:</strong> Check in ≤ <?= substr($jamMasuk, 0, 5) ?></li>
        <li><span class="lnr lnr-clock" style="color: #eab308;"></span> <strong>Terlambat:</strong> Check in > <?= substr($jamMasuk, 0, 5) ?> s/d ≤ <?= substr($jamTerlambat, 0, 5) ?></li>
        <li><span class="lnr lnr-cross-circle" style="color: #ef4444;"></span> <strong>Tidak Hadir:</strong> Check in > <?= substr($jamTerlambat, 0, 5) ?> atau tidak check in</li>
        <li><span class="lnr lnr-warning" style="color: #ef4444;"></span> <strong>Bolos:</strong> Hadir tapi tidak check out setelah <?= substr($jamPulang, 0, 5) ?></li>
    </ul>
</div>

<?php renderFooter(); ?>
