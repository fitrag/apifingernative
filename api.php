<?php
require_once 'config.php';
require_once 'settings.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? '';
$conn = getConnection();

switch ($action) {
    case 'dashboard':
        $tanggal = date('Y-m-d');
        $jamMasuk = getSetting('jam_masuk', '07:00:00');
        $jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');
        
        $sql = "SELECT 
            (SELECT COUNT(*) FROM userinfo) as total_karyawan,
            (SELECT COUNT(DISTINCT userid) FROM checkinout WHERE DATE(checktime) = ? AND checktype = '0') as hadir,
            (SELECT COUNT(DISTINCT userid) FROM checkinout WHERE DATE(checktime) = ? AND checktype = '0' AND TIME(checktime) > ? AND TIME(checktime) <= ?) as terlambat";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $tanggal, $tanggal, $jamMasuk, $jamTerlambat);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
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
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'absensi' => $absensi,
            'settings' => ['jam_masuk' => $jamMasuk, 'jam_terlambat' => $jamTerlambat]
        ]);
        break;
        
    case 'absensi':
        $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
        $sql = "SELECT c.*, u.name, u.badgenumber FROM checkinout c 
                LEFT JOIN userinfo u ON c.userid = u.userid 
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
        echo json_encode(['success' => true, 'data' => $data, 'tanggal' => $tanggal]);
        break;
        
    case 'karyawan':
        $result = $conn->query("SELECT * FROM userinfo ORDER BY name");
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    
    case 'karyawan_get':
        $userid = $_GET['userid'] ?? 0;
        $stmt = $conn->prepare("SELECT * FROM userinfo WHERE userid = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        echo json_encode(['success' => (bool)$data, 'data' => $data]);
        break;
    
    case 'karyawan_save':
        $input = json_decode(file_get_contents('php://input'), true);
        $userid = $input['userid'] ?? 0;
        $badgenumber = $input['badgenumber'] ?? '';
        $name = $input['name'] ?? '';
        $card = $input['Card'] ?? '';
        $defaultdeptid = $input['defaultdeptid'] ?? 1;
        
        if (empty($badgenumber) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Badge number dan nama wajib diisi']);
            break;
        }
        
        if ($userid > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE userinfo SET badgenumber = ?, name = ?, Card = ?, defaultdeptid = ? WHERE userid = ?");
            $stmt->bind_param("sssii", $badgenumber, $name, $card, $defaultdeptid, $userid);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Data karyawan berhasil diupdate']);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO userinfo (badgenumber, name, Card, defaultdeptid) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $badgenumber, $name, $card, $defaultdeptid);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Data karyawan berhasil ditambahkan', 'userid' => $conn->insert_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $stmt->error]);
            }
        }
        break;
    
    case 'karyawan_delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $userid = $input['userid'] ?? 0;
        
        if ($userid > 0) {
            $stmt = $conn->prepare("DELETE FROM userinfo WHERE userid = ?");
            $stmt->bind_param("i", $userid);
            $stmt->execute();
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
            $types = str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
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
                FROM checkinout c LEFT JOIN userinfo u ON c.userid = u.userid
                WHERE c.checktype = '0' AND DATE(c.checktime) BETWEEN ? AND ?
                AND TIME(c.checktime) > ? AND TIME(c.checktime) <= ?
                GROUP BY c.userid, DATE(c.checktime) ORDER BY c.checktime DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $mulai, $selesai, $jamMasuk, $jamTerlambat);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data, 'jam_masuk' => $jamMasuk]);
        break;
        
    case 'laporan_tidak_hadir':
        $mulai = $_GET['mulai'] ?? date('Y-m-01');
        $selesai = $_GET['selesai'] ?? date('Y-m-d');
        $jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');
        
        $sql = "SELECT u.userid, u.name, u.badgenumber, d.tanggal, c.jam_masuk,
                CASE WHEN c.jam_masuk IS NULL THEN 'Tidak Check In'
                     WHEN c.jam_masuk > ? THEN 'Check In Terlalu Lambat' ELSE NULL END as status
                FROM userinfo u
                CROSS JOIN (SELECT DISTINCT DATE(checktime) as tanggal FROM checkinout WHERE DATE(checktime) BETWEEN ? AND ?) d
                LEFT JOIN (SELECT userid, DATE(checktime) as tgl, MIN(TIME(checktime)) as jam_masuk
                           FROM checkinout WHERE checktype = '0' AND DATE(checktime) BETWEEN ? AND ?
                           GROUP BY userid, DATE(checktime)) c ON u.userid = c.userid AND d.tanggal = c.tgl
                WHERE u.Card IS NOT NULL AND u.Card != '' AND (c.jam_masuk IS NULL OR c.jam_masuk > ?)
                ORDER BY d.tanggal DESC, u.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $jamTerlambat, $mulai, $selesai, $mulai, $selesai, $jamTerlambat);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
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
                            FROM checkinout WHERE checktype = '0' AND DATE(checktime) BETWEEN ? AND ?
                            GROUP BY userid, DATE(checktime) HAVING MIN(TIME(checktime)) <= ?) ci ON u.userid = ci.userid
                LEFT JOIN (SELECT DISTINCT userid, DATE(checktime) as tanggal FROM checkinout 
                           WHERE checktype = '1' AND DATE(checktime) BETWEEN ? AND ?) co 
                ON u.userid = co.userid AND ci.tanggal = co.tanggal
                WHERE u.Card IS NOT NULL AND u.Card != '' AND co.userid IS NULL
                ORDER BY ci.tanggal DESC, u.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $mulai, $selesai, $jamTerlambat, $mulai, $selesai);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data, 'jam_pulang' => $jamPulang]);
        break;
        
    case 'settings':
        echo json_encode([
            'success' => true,
            'data' => [
                'jam_masuk' => getSetting('jam_masuk', '07:00:00'),
                'jam_batas_terlambat' => getSetting('jam_batas_terlambat', '08:00:00'),
                'jam_batas_pulang' => getSetting('jam_batas_pulang', '17:00:00'),
                'wa_api_url' => getSetting('wa_api_url', ''),
                'wa_api_token' => getSetting('wa_api_token', ''),
                'timezone' => getSetting('timezone', 'Asia/Jakarta'),
                'hari_libur' => getSetting('hari_libur', ['6', '7'])
            ],
            'timezone_list' => getTimezoneList(),
            'days_list' => getDaysList()
        ]);
        break;
        
    case 'save_settings':
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? '';
        
        if ($type == 'jam') {
            setSetting('jam_masuk', $input['jam_masuk'] . ':00');
            setSetting('jam_batas_terlambat', $input['jam_batas_terlambat'] . ':00');
            setSetting('jam_batas_pulang', $input['jam_batas_pulang'] . ':00');
        } elseif ($type == 'wa') {
            setSetting('wa_api_url', $input['wa_api_url']);
            setSetting('wa_api_token', $input['wa_api_token']);
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
            'cron_retry_log.txt' => ['label' => 'Retry', 'color' => '#8b5cf6']
        ];
        foreach ($logFiles as $file => $info) {
            if (file_exists($file)) {
                $lines = array_filter(array_slice(explode("\n", file_get_contents($file)), -100));
                foreach ($lines as $line) {
                    if (!empty(trim($line))) {
                        preg_match('/^\[(.*?)\]\s*(.*)$/', $line, $m);
                        $status = 'info';
                        $lower = strtolower($line);
                        if (strpos($lower, 'success') !== false) $status = 'success';
                        elseif (strpos($lower, 'failed') !== false || strpos($lower, 'error') !== false) $status = 'error';
                        elseif (strpos($lower, 'skip') !== false) $status = 'skip';
                        elseif (strpos($lower, 'bolos') !== false || strpos($lower, 'terlambat') !== false) $status = 'warning';
                        
                        $logs[] = [
                            'type' => $info['label'], 'color' => $info['color'],
                            'timestamp' => $m[1] ?? '', 'message' => $m[2] ?? $line, 'status' => $status
                        ];
                    }
                }
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

$conn->close();
