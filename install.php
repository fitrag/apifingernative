<?php
/**
 * Installer Script - Sistem Absensi dengan Notifikasi WhatsApp
 * Jalankan file ini di browser untuk menginstall aplikasi
 */

session_start();
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_requirements') {
        header('Location: install.php?step=2');
        exit;
    }
    
    if ($action === 'test_database') {
        $host = $_POST['db_host'] ?? 'localhost';
        $port = $_POST['db_port'] ?? '3306';
        $user = $_POST['db_user'] ?? 'root';
        $pass = $_POST['db_pass'] ?? '';
        $name = $_POST['db_name'] ?? 'adms_db';
        
        try {
            $conn = new mysqli($host, $user, $pass, $name, (int)$port);
            if ($conn->connect_error) {
                throw new Exception($conn->connect_error);
            }
            
            // Check required tables
            $tables = ['userinfo', 'checkinout'];
            $missing = [];
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result->num_rows === 0) $missing[] = $table;
            }
            
            if (!empty($missing)) {
                throw new Exception("Tabel tidak ditemukan: " . implode(', ', $missing));
            }
            
            // Save to session
            $_SESSION['db'] = compact('host', 'port', 'user', 'pass', 'name');
            $conn->close();
            
            header('Location: install.php?step=3');
            exit;
        } catch (Exception $e) {
            $error = "Koneksi gagal: " . $e->getMessage();
            $step = 2;
        }
    }
    
    if ($action === 'save_settings') {
        $settings = [
            'jam_masuk' => ($_POST['jam_masuk'] ?? '07:00') . ':00',
            'jam_batas_terlambat' => ($_POST['jam_batas_terlambat'] ?? '08:00') . ':00',
            'jam_batas_pulang' => ($_POST['jam_batas_pulang'] ?? '17:00') . ':00',
            'wa_api_url' => $_POST['wa_api_url'] ?? '',
            'wa_api_token' => $_POST['wa_api_token'] ?? '',
            'timezone' => $_POST['timezone'] ?? 'Asia/Jakarta',
            'hari_libur' => $_POST['hari_libur'] ?? ['6', '7']
        ];
        $_SESSION['settings'] = $settings;
        header('Location: install.php?step=4');
        exit;
    }
    
    if ($action === 'finish_install') {
        // Create config.php
        $db = $_SESSION['db'];
        $configContent = "<?php\n";
        $configContent .= "function getConnection() {\n";
        $configContent .= "    \$conn = new mysqli('{$db['host']}', '{$db['user']}', '{$db['pass']}', '{$db['name']}', {$db['port']});\n";
        $configContent .= "    if (\$conn->connect_error) die('Connection failed: ' . \$conn->connect_error);\n";
        $configContent .= "    \$conn->set_charset('utf8mb4');\n";
        $configContent .= "    return \$conn;\n";
        $configContent .= "}\n";
        
        file_put_contents('config.php', $configContent);
        
        // Create settings.json
        $settings = $_SESSION['settings'];
        $settings['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents('settings.json', json_encode($settings, JSON_PRETTY_PRINT));
        
        // Create necessary directories and files
        $files = ['cron_log.txt', 'cron_bolos_log.txt', 'cron_absent_log.txt', 'cron_retry_log.txt', 'last_checkin_id.txt'];
        foreach ($files as $file) {
            if (!file_exists($file)) file_put_contents($file, '');
        }
        
        $jsonFiles = ['sent_notification_log.json', 'sent_bolos_log.json', 'sent_absent_log.json', 'wa_queue.json'];
        foreach ($jsonFiles as $file) {
            if (!file_exists($file)) file_put_contents($file, '{}');
        }
        
        // Clear session
        session_destroy();
        
        header('Location: install.php?step=5');
        exit;
    }
}

// Check requirements
function checkRequirements() {
    $requirements = [];
    $requirements['PHP Version >= 7.4'] = version_compare(PHP_VERSION, '7.4.0', '>=');
    $requirements['MySQLi Extension'] = extension_loaded('mysqli');
    $requirements['cURL Extension'] = extension_loaded('curl');
    $requirements['JSON Extension'] = extension_loaded('json');
    $requirements['Directory Writable'] = is_writable(__DIR__);
    return $requirements;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer - Sistem Absensi</title>
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .installer { background: #fff; border-radius: 16px; box-shadow: 0 25px 50px rgba(0,0,0,0.3); max-width: 600px; width: 100%; overflow: hidden; }
        .installer-header { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; padding: 32px; text-align: center; }
        .installer-header h1 { font-size: 24px; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .installer-header p { opacity: 0.9; font-size: 14px; }
        .installer-body { padding: 32px; }
        .steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 32px; }
        .step-dot { width: 12px; height: 12px; border-radius: 50%; background: #e2e8f0; transition: all 0.3s; }
        .step-dot.active { background: #3b82f6; transform: scale(1.2); }
        .step-dot.done { background: #22c55e; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: #374151; font-size: 14px; }
        .form-hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .form-input { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.2s; }
        .form-input:focus { outline: none; border-color: #3b82f6; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 28px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; width: 100%; }
        .btn-primary { background: #3b82f6; color: #fff; } .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #22c55e; color: #fff; } .btn-success:hover { background: #16a34a; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .check-list { list-style: none; }
        .check-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .check-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .check-icon.success { background: #dcfce7; color: #166534; }
        .check-icon.error { background: #fee2e2; color: #991b1b; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .checkbox-item { display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: #f1f5f9; border-radius: 6px; font-size: 13px; cursor: pointer; }
        .checkbox-item input { accent-color: #3b82f6; }
        .success-icon { width: 80px; height: 80px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .success-icon .lnr { font-size: 40px; color: #22c55e; }
        h2 { font-size: 20px; color: #1e293b; margin-bottom: 8px; }
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; }
    </style>
</head>
<body>
<div class="installer">
    <div class="installer-header">
        <h1><span class="lnr lnr-calendar-full"></span> Sistem Absensi</h1>
        <p>Installer - Notifikasi WhatsApp Otomatis</p>
    </div>
    <div class="installer-body">
        <div class="steps">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="step-dot <?= $i < $step ? 'done' : ($i == $step ? 'active' : '') ?>"></div>
            <?php endfor; ?>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><span class="lnr lnr-warning"></span> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <!-- Step 1: Welcome -->
        <h2>Selamat Datang!</h2>
        <p class="subtitle">Installer akan membantu Anda mengkonfigurasi aplikasi Sistem Absensi dengan Notifikasi WhatsApp.</p>
        
        <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <h3 style="font-size: 14px; color: #374151; margin-bottom: 12px;">Fitur Aplikasi:</h3>
            <ul style="color: #64748b; font-size: 13px; padding-left: 20px; line-height: 1.8;">
                <li>Dashboard statistik absensi realtime</li>
                <li>Notifikasi WhatsApp otomatis (check in/out, terlambat, tidak hadir, bolos)</li>
                <li>Laporan keterlambatan, tidak hadir, dan bolos</li>
                <li>Pengaturan jam kerja dan hari libur</li>
                <li>Log notifikasi dengan filter</li>
            </ul>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="check_requirements">
            <button type="submit" class="btn btn-primary"><span class="lnr lnr-arrow-right"></span> Mulai Instalasi</button>
        </form>
        
        <?php elseif ($step == 2): ?>
        <!-- Step 2: Database Configuration -->
        <h2>Konfigurasi Database</h2>
        <p class="subtitle">Masukkan informasi koneksi database MySQL Anda.</p>
        
        <?php 
        $requirements = checkRequirements();
        $allPassed = !in_array(false, $requirements);
        ?>
        
        <div style="margin-bottom: 24px;">
            <h3 style="font-size: 14px; color: #374151; margin-bottom: 12px;">System Requirements:</h3>
            <ul class="check-list">
                <?php foreach ($requirements as $name => $passed): ?>
                <li class="check-item">
                    <span class="check-icon <?= $passed ? 'success' : 'error' ?>">
                        <span class="lnr lnr-<?= $passed ? 'checkmark-circle' : 'cross-circle' ?>"></span>
                    </span>
                    <span><?= $name ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if ($allPassed): ?>
        <form method="POST">
            <input type="hidden" name="action" value="test_database">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Database Host</label>
                    <input type="text" name="db_host" value="localhost" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Port</label>
                    <input type="text" name="db_port" value="3306" class="form-input" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="db_user" value="root" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="db_pass" value="" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Database Name</label>
                <input type="text" name="db_name" value="adms_db" class="form-input" required>
                <div class="form-hint">Database harus sudah ada dengan tabel userinfo dan checkinout</div>
            </div>
            <button type="submit" class="btn btn-primary"><span class="lnr lnr-database"></span> Test Koneksi & Lanjutkan</button>
        </form>
        <?php else: ?>
        <div class="alert alert-error"><span class="lnr lnr-warning"></span> Mohon penuhi semua requirements sebelum melanjutkan.</div>
        <?php endif; ?>
        
        <?php elseif ($step == 3): ?>
        <!-- Step 3: Application Settings -->
        <h2>Pengaturan Aplikasi</h2>
        <p class="subtitle">Konfigurasi jam kerja dan WhatsApp API.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            
            <h3 style="font-size: 14px; color: #374151; margin-bottom: 12px;">Jam Kerja</h3>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Jam Masuk</label>
                    <input type="time" name="jam_masuk" value="07:00" class="form-input" required>
                    <div class="form-hint">Batas tepat waktu</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Batas Terlambat</label>
                    <input type="time" name="jam_batas_terlambat" value="08:00" class="form-input" required>
                    <div class="form-hint">Lebih dari ini = tidak hadir</div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Jam Pulang</label>
                <input type="time" name="jam_batas_pulang" value="17:00" class="form-input" required>
                <div class="form-hint">Tidak checkout setelah jam ini = bolos</div>
            </div>
            
            <h3 style="font-size: 14px; color: #374151; margin: 24px 0 12px;">WhatsApp API</h3>
            <div class="form-group">
                <label class="form-label">API URL</label>
                <input type="url" name="wa_api_url" value="http://127.0.0.1:8000/api/send-message" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">API Token</label>
                <input type="text" name="wa_api_token" class="form-input" placeholder="Bearer token (opsional)">
            </div>
            
            <h3 style="font-size: 14px; color: #374151; margin: 24px 0 12px;">Zona Waktu & Hari Libur</h3>
            <div class="form-group">
                <label class="form-label">Zona Waktu</label>
                <select name="timezone" class="form-input">
                    <option value="Asia/Jakarta">WIB - Jakarta (UTC+7)</option>
                    <option value="Asia/Makassar">WITA - Makassar (UTC+8)</option>
                    <option value="Asia/Jayapura">WIT - Jayapura (UTC+9)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Hari Libur</label>
                <div class="checkbox-group">
                    <?php $days = ['1'=>'Senin','2'=>'Selasa','3'=>'Rabu','4'=>'Kamis','5'=>'Jumat','6'=>'Sabtu','7'=>'Minggu']; ?>
                    <?php foreach ($days as $num => $name): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="hari_libur[]" value="<?= $num ?>" <?= in_array($num, ['6','7']) ? 'checked' : '' ?>>
                        <?= $name ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><span class="lnr lnr-cog"></span> Simpan & Lanjutkan</button>
        </form>
        
        <?php elseif ($step == 4): ?>
        <!-- Step 4: Confirm Installation -->
        <h2>Konfirmasi Instalasi</h2>
        <p class="subtitle">Periksa konfigurasi sebelum menyelesaikan instalasi.</p>
        
        <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <h3 style="font-size: 14px; color: #374151; margin-bottom: 12px;">Database:</h3>
            <p style="color: #64748b; font-size: 13px;"><?= $_SESSION['db']['host'] ?>:<?= $_SESSION['db']['port'] ?> / <?= $_SESSION['db']['name'] ?></p>
            
            <h3 style="font-size: 14px; color: #374151; margin: 16px 0 12px;">Jam Kerja:</h3>
            <p style="color: #64748b; font-size: 13px;">
                Masuk: <?= substr($_SESSION['settings']['jam_masuk'], 0, 5) ?> | 
                Terlambat: <?= substr($_SESSION['settings']['jam_batas_terlambat'], 0, 5) ?> | 
                Pulang: <?= substr($_SESSION['settings']['jam_batas_pulang'], 0, 5) ?>
            </p>
            
            <h3 style="font-size: 14px; color: #374151; margin: 16px 0 12px;">WhatsApp API:</h3>
            <p style="color: #64748b; font-size: 13px;"><?= $_SESSION['settings']['wa_api_url'] ?: '(Belum dikonfigurasi)' ?></p>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="finish_install">
            <button type="submit" class="btn btn-success"><span class="lnr lnr-checkmark-circle"></span> Selesaikan Instalasi</button>
        </form>
        
        <?php elseif ($step == 5): ?>
        <!-- Step 5: Complete -->
        <div style="text-align: center;">
            <div class="success-icon"><span class="lnr lnr-checkmark-circle"></span></div>
            <h2>Instalasi Berhasil!</h2>
            <p class="subtitle">Aplikasi Sistem Absensi siap digunakan.</p>
            
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin: 24px 0; text-align: left;">
                <h3 style="font-size: 14px; color: #374151; margin-bottom: 12px;">Langkah Selanjutnya:</h3>
                <ol style="color: #64748b; font-size: 13px; padding-left: 20px; line-height: 2;">
                    <li>Hapus file <code>install.php</code> untuk keamanan</li>
                    <li>Jalankan <code>start_monitor.bat</code> untuk memulai cron job</li>
                    <li>Pastikan WhatsApp API sudah berjalan</li>
                </ol>
            </div>
            
            <a href="app.php" class="btn btn-primary" style="text-decoration: none;"><span class="lnr lnr-home"></span> Buka Aplikasi</a>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
