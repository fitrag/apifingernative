<?php
require_once 'config.php';
require_once 'settings.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: private, max-age=0');

$action = $_GET['action'] ?? '';
$conn = getConnection();

switch ($action) {
    case 'dashboard':
        $tanggal = date('Y-m-d');
        $jamMasuk = getSetting('jam_masuk', '07:00:00');
        $jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');
        
        // Optimasi: Single query dengan conditional count
        $sql = "SELECT 
            (SELECT COUNT(*) FROM userinfo) as total_karyawan,
            COUNT(DISTINCT CASE WHEN c.checktype = '0' THEN c.userid END) as hadir,
            COUNT(DISTINCT CASE WHEN c.checktype = '0' AND TIME(c.checktime) > ? AND TIME(c.checktime) <= ? THEN c.userid END) as terlambat
            FROM checkinout c WHERE DATE(c.checktime) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $jamMasuk, $jamTerlambat, $tanggal);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $sql2 = "SELECT c.id, c.userid, c.checktime, c.checktype, u.name, u.badgenumber 
                 FROM checkinout c LEFT JOIN userinfo u ON c.userid = u.userid 
                 ORDER BY c.id DESC LIMIT 10";
        $result = $conn->query($sql2);
        $absensi = [];
        while ($row = $result->fetch_assoc()) {
            $row['jam'] = date('H:i:s', strtotime($row['checktime']));
            $row['tanggal'] = date('d/m/Y', strtotime($row['checktime']));
            $absensi[] = $row;
        }
        $result->free();
        
        echo json_encode(['success' => true, 'stats' => $stats, 'absensi' => $absensi,
            'settings' => ['jam_masuk' => $jamMasuk, 'jam_terlambat' => $jamTerlambat]]);
        break;
        
    case 'absensi':
        $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
        $sql = "SELECT c.id, c.userid, c.checktime, c.checktype, u.name, u.badgenumber 
                FROM checkinout c LEFT JOIN userinfo u ON c.userid = u.userid 
                WHERE DATE(c.checktime) = ? ORDER BY c.checktime DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $tanggal);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $row['jam'] = date('H:i:s', strtotime($row['checktime']));
            $data[] = $row;
        }
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $data, 'tanggal' => $tanggal]);
        break;
        
    case 'karyawan':
        $result = $conn->query("SELECT userid, badgenumber, title, name, Card, FPHONE, defaultdeptid FROM userinfo ORDER BY name");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    
    case 'karyawan_get':
        $userid = (int)($_GET['userid'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM userinfo WHERE userid = ? LIMIT 1");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => (bool)$data, 'data' => $data]);
        break;
    
    case 'karyawan_save':
        $input = json_decode(file_get_contents('php://input'), true);
        $userid = (int)($input['userid'] ?? 0);
        $badgenumber = trim($input['badgenumber'] ?? '');
        $title = trim($input['title'] ?? '');
        $name = trim($input['name'] ?? '');
        $card = trim($input['Card'] ?? '');
        $fphone = trim($input['FPHONE'] ?? '');
        $defaultdeptid = (int)($input['defaultdeptid'] ?? 1);
        
        if (empty($badgenumber) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Badge number dan nama wajib diisi']);
            break;
        }
        
        if ($userid > 0) {
            $stmt = $conn->prepare("UPDATE userinfo SET badgenumber=?, title=?, name=?, Card=?, FPHONE=?, defaultdeptid=? WHERE userid=?");
            $stmt->bind_param("sssssii", $badgenumber, $title, $name, $card, $fphone, $defaultdeptid, $userid);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Data karyawan berhasil diupdate']);
        } else {
            $stmt = $conn->prepare("INSERT INTO userinfo (badgenumber, title, name, Card, FPHONE, defaultdeptid) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $badgenumber, $title, $name, $card, $fphone, $defaultdeptid);
            if ($stmt->execute()) {
                $newUserId = $conn->insert_id;
                $stmt->close();
                echo json_encode(['success' => true, 'message' => 'Data karyawan berhasil ditambahkan', 'userid' => $newUserId]);
            } else {
                $error = $stmt->error;
                $stmt->close();
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $error]);
            }
        }
        break;
    
    case 'karyawan_delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $userid = (int)($input['userid'] ?? 0);
        if ($userid > 0) {
            $stmt = $conn->prepare("DELETE FROM userinfo WHERE userid = ?");
            $stmt->bind_param("i", $userid);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Data karyawan berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        }
        break;
    
    case 'karyawan_delete_bulk':
        $input = json_decode(file_get_contents('php://input'), true);
        $userids = $input['userids'] ?? [];
        if (!empty($userids) && is_array($userids)) {
            $ids = array_map('intval', $userids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("DELETE FROM userinfo WHERE userid IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            echo json_encode(['success' => true, 'message' => "$deleted data karyawan berhasil dihapus", 'deleted' => $deleted]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tidak ada data yang dipilih']);
        }
        break;
        
    case 'laporan_terlambat':
        $mulai = $_GET['mulai'] ?? date('Y-m-01');
        $selesai = $_GET['selesai'] ?? date('Y-m-d');
        $jamMasuk = getSetting('jam_masuk', '07:00:00');
        $jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');
        
        $sql = "SELECT c.userid, u.name, u.badgenumber, DATE(c.checktime) as tanggal,
                MIN(c.checktime) as jam_masuk, TIME(MIN(c.checktime)) as jam_masuk_only
                FROM checkinout c INNER JOIN userinfo u ON c.userid = u.userid
                WHERE c.checktype = '0' AND c.checktime BETWEEN ? AND CONCAT(?, ' 23:59:59')
                AND TIME(c.checktime) > ? AND TIME(c.checktime) <= ?
                GROUP BY c.userid, DATE(c.checktime) ORDER BY tanggal DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $mulai, $selesai, $jamMasuk, $jamTerlambat);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $data, 'jam_masuk' => $jamMasuk]);
        break;
        
    case 'laporan_tidak_hadir':
        $mulai = $_GET['mulai'] ?? date('Y-m-01');
        $selesai = $_GET['selesai'] ?? date('Y-m-d');
        $jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');
        
        $sql = "SELECT u.userid, u.name, u.badgenumber, d.tanggal, c.jam_masuk,
                CASE WHEN c.jam_masuk IS NULL THEN 'Tidak Check In'
                     WHEN c.jam_masuk > ? THEN 'Check In Terlalu Lambat' END as status
                FROM userinfo u
                CROSS JOIN (SELECT DISTINCT DATE(checktime) as tanggal FROM checkinout 
                            WHERE checktime BETWEEN ? AND CONCAT(?, ' 23:59:59')) d
                LEFT JOIN (SELECT userid, DATE(checktime) as tgl, MIN(TIME(checktime)) as jam_masuk
                           FROM checkinout WHERE checktype = '0' 
                           AND checktime BETWEEN ? AND CONCAT(?, ' 23:59:59')
                           GROUP BY userid, DATE(checktime)) c ON u.userid = c.userid AND d.tanggal = c.tgl
                WHERE u.Card IS NOT NULL AND u.Card != '' AND (c.jam_masuk IS NULL OR c.jam_masuk > ?)
                ORDER BY d.tanggal DESC, u.name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $jamTerlambat, $mulai, $selesai, $mulai, $selesai, $jamTerlambat);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'laporan_bolos':
        $mulai = $_GET['mulai'] ?? date('Y-m-01');
        $selesai = $_GET['selesai'] ?? date('Y-m-d');
        $jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');
        $jamPulang = getSetting('jam_batas_pulang', '17:00:00');
        
        $sql = "SELECT u.userid, u.name, u.badgenumber, ci.tanggal, ci.jam_masuk
                FROM userinfo u
                INNER JOIN (SELECT userid, DATE(checktime) as tanggal, MIN(TIME(checktime)) as jam_masuk
                            FROM checkinout WHERE checktype = '0' 
                            AND checktime BETWEEN ? AND CONCAT(?, ' 23:59:59')
                            GROUP BY userid, DATE(checktime) HAVING MIN(TIME(checktime)) <= ?) ci ON u.userid = ci.userid
                LEFT JOIN (SELECT DISTINCT userid, DATE(checktime) as tanggal FROM checkinout 
                           WHERE checktype = '1' AND checktime BETWEEN ? AND CONCAT(?, ' 23:59:59')) co 
                ON u.userid = co.userid AND ci.tanggal = co.tanggal
                WHERE u.Card IS NOT NULL AND u.Card != '' AND co.userid IS NULL
                ORDER BY ci.tanggal DESC, u.name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $mulai, $selesai, $jamTerlambat, $mulai, $selesai);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $data, 'jam_pulang' => $jamPulang]);
        break;
        
    case 'settings':
        $s = getSettings();
        echo json_encode(['success' => true, 'data' => [
            'jam_masuk' => $s['jam_masuk'] ?? '07:00:00',
            'jam_batas_terlambat' => $s['jam_batas_terlambat'] ?? '08:00:00',
            'jam_batas_pulang' => $s['jam_batas_pulang'] ?? '17:00:00',
            'jam_auto_checkout' => $s['jam_auto_checkout'] ?? '14:00:00',
            'jam_auto_checkout_harian' => $s['jam_auto_checkout_harian'] ?? [
                '1' => '14:00', '2' => '14:00', '3' => '14:00', '4' => '14:00',
                '5' => '14:00', '6' => '12:00', '7' => '12:00'
            ],
            'wa_api_url' => $s['wa_api_url'] ?? '',
            'wa_api_token' => $s['wa_api_token'] ?? '',
            'sync_api_url' => $s['sync_api_url'] ?? 'http://127.0.0.1:8000/api/attendance/sync',
            'sync_api_token' => $s['sync_api_token'] ?? '',
            'sync_interval' => $s['sync_interval'] ?? 60,
            'siakad_api_url' => $s['siakad_api_url'] ?? 'https://siakads.kurikulum-skansa.id/api/attendance/absences',
            'siakad_api_token' => $s['siakad_api_token'] ?? '',
            'timezone' => $s['timezone'] ?? 'Asia/Jakarta',
            'hari_libur' => $s['hari_libur'] ?? ['6', '7']
        ], 'timezone_list' => getTimezoneList(), 'days_list' => getDaysList()]);
        break;
        
    case 'save_settings':
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? '';
        
        if ($type == 'jam') {
            setSettings([
                'jam_masuk' => $input['jam_masuk'] . ':00',
                'jam_batas_terlambat' => $input['jam_batas_terlambat'] . ':00',
                'jam_batas_pulang' => $input['jam_batas_pulang'] . ':00',
                'jam_auto_checkout' => ($input['jam_auto_checkout'] ?? '14:00') . ':00'
            ]);
        } elseif ($type == 'jam_auto_checkout_harian') {
            setSetting('jam_auto_checkout_harian', $input['jam_auto_checkout_harian']);
        } elseif ($type == 'wa') {
            setSettings(['wa_api_url' => $input['wa_api_url'], 'wa_api_token' => $input['wa_api_token']]);
        } elseif ($type == 'sync_api') {
            setSettings([
                'sync_api_url' => $input['sync_api_url'],
                'sync_api_token' => $input['sync_api_token'],
                'sync_interval' => max(1, min(1440, intval($input['sync_interval'] ?? 60)))
            ]);
        } elseif ($type == 'siakad_api') {
            setSettings([
                'siakad_api_url' => $input['siakad_api_url'],
                'siakad_api_token' => $input['siakad_api_token']
            ]);
        } elseif ($type == 'timezone') {
            setSetting('timezone', $input['timezone']);
            applyTimezone();
        } elseif ($type == 'hari_libur') {
            setSetting('hari_libur', $input['hari_libur']);
        }
        echo json_encode(['success' => true, 'message' => 'Pengaturan berhasil disimpan']);
        break;
        
    case 'logs':
        $logs = [];
        $logFiles = [
            'cron_log.txt' => ['label' => 'Absensi', 'color' => '#3b82f6'],
            'cron_absent_log.txt' => ['label' => 'Tidak Hadir', 'color' => '#ef4444'],
            'cron_bolos_log.txt' => ['label' => 'Bolos', 'color' => '#f59e0b'],
            'cron_izin_log.txt' => ['label' => 'Izin/Sakit', 'color' => '#8b5cf6'],
            'cron_retry_log.txt' => ['label' => 'Retry', 'color' => '#6b7280']
        ];
        
        foreach ($logFiles as $file => $info) {
            if (!file_exists($file)) continue;
            $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -100);
            foreach ($lines as $line) {
                preg_match('/^\[(.*?)\]\s*(.*)$/', $line, $m);
                $lower = strtolower($line);
                $status = 'info';
                if (strpos($lower, 'success') !== false) $status = 'success';
                elseif (strpos($lower, 'failed') !== false || strpos($lower, 'error') !== false) $status = 'error';
                elseif (strpos($lower, 'skip') !== false) $status = 'skip';
                elseif (strpos($lower, 'bolos') !== false || strpos($lower, 'terlambat') !== false) $status = 'warning';
                
                $logs[] = ['type' => $info['label'], 'color' => $info['color'],
                    'timestamp' => $m[1] ?? '', 'message' => $m[2] ?? $line, 'status' => $status];
            }
        }
        usort($logs, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
        echo json_encode(['success' => true, 'logs' => array_slice($logs, 0, 200)]);
        break;
        
    case 'time':
        echo json_encode(['time' => date('H:i:s'), 'date' => date('d M Y'), 'day' => date('l')]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
