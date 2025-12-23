<?php
/**
 * Import Karyawan dari Excel/CSV
 * Menggunakan library SimpleXLSX untuk membaca file Excel
 */

require_once 'config.php';
require_once 'settings.php';

header('Content-Type: application/json');

// Download SimpleXLSX jika belum ada
if (!file_exists('SimpleXLSX.php')) {
    $xlsxLib = file_get_contents('https://raw.githubusercontent.com/shuchkin/simplexlsx/master/src/SimpleXLSX.php');
    if ($xlsxLib) file_put_contents('SimpleXLSX.php', $xlsxLib);
}

if (file_exists('SimpleXLSX.php')) {
    require_once 'SimpleXLSX.php';
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'template':
        // Download template Excel (CSV format untuk kompatibilitas)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_karyawan.csv"');
        
        $output = fopen('php://output', 'w');
        // BOM untuk Excel UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header sesuai kolom database
        fputcsv($output, ['badgenumber', 'name', 'Card', 'defaultdeptid', 'SN'], ';');
        
        // Contoh data
        fputcsv($output, ['001', 'John Doe', '628123456789', '1', 'DEVICE001'], ';');
        fputcsv($output, ['002', 'Jane Smith', '628987654321', '1', 'DEVICE001'], ';');
        fputcsv($output, ['003', 'Ahmad Rizki', '628555666777', '2', 'DEVICE001'], ';');
        
        fclose($output);
        exit;
        
    case 'import':
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File tidak valid atau tidak diupload']);
            exit;
        }
        
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $data = [];
        $errors = [];
        
        // Parse file berdasarkan ekstensi
        if ($ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            // Skip BOM jika ada
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
                rewind($handle);
            }
            
            $header = null;
            $lineNum = 0;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $lineNum++;
                if ($header === null) {
                    $header = array_map('trim', array_map('strtolower', $row));
                    continue;
                }
                if (count($row) >= 2) {
                    $rowData = array_combine($header, array_pad($row, count($header), ''));
                    $data[] = [
                        'line' => $lineNum,
                        'badgenumber' => trim($rowData['badgenumber'] ?? ''),
                        'name' => trim($rowData['name'] ?? ''),
                        'card' => trim($rowData['card'] ?? ''),
                        'defaultdeptid' => trim($rowData['defaultdeptid'] ?? '1'),
                        'sn' => trim($rowData['sn'] ?? '')
                    ];
                }
            }
            fclose($handle);
        } elseif (in_array($ext, ['xlsx', 'xls'])) {
            if (!class_exists('Shuchkin\SimpleXLSX')) {
                echo json_encode(['success' => false, 'message' => 'Library SimpleXLSX tidak tersedia. Gunakan format CSV.']);
                exit;
            }
            
            $xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);
            if ($xlsx) {
                $rows = $xlsx->rows();
                $header = null;
                $lineNum = 0;
                foreach ($rows as $row) {
                    $lineNum++;
                    if ($header === null) {
                        $header = array_map('trim', array_map('strtolower', $row));
                        continue;
                    }
                    if (count($row) >= 2) {
                        $rowData = array_combine($header, array_pad($row, count($header), ''));
                        $data[] = [
                            'line' => $lineNum,
                            'badgenumber' => trim($rowData['badgenumber'] ?? ''),
                            'name' => trim($rowData['name'] ?? ''),
                            'card' => trim($rowData['card'] ?? ''),
                            'defaultdeptid' => trim($rowData['defaultdeptid'] ?? '1'),
                            'sn' => trim($rowData['sn'] ?? '')
                        ];
                    }
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal membaca file Excel: ' . \Shuchkin\SimpleXLSX::parseError()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Gunakan CSV atau XLSX.']);
            exit;
        }
        
        if (empty($data)) {
            echo json_encode(['success' => false, 'message' => 'File kosong atau format tidak sesuai']);
            exit;
        }
        
        // Validasi dan import ke database
        $conn = getConnection();
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        $mode = $_POST['mode'] ?? 'skip'; // skip, update, replace
        
        foreach ($data as $row) {
            // Validasi
            if (empty($row['badgenumber']) || empty($row['name'])) {
                $errors[] = "Baris {$row['line']}: Badge number dan nama wajib diisi";
                $skipped++;
                continue;
            }
            
            // Cek apakah sudah ada
            $stmt = $conn->prepare("SELECT userid FROM userinfo WHERE badgenumber = ?");
            $stmt->bind_param("s", $row['badgenumber']);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                if ($mode === 'skip') {
                    $skipped++;
                    continue;
                } elseif ($mode === 'update') {
                    // Update data yang ada
                    $stmt = $conn->prepare("UPDATE userinfo SET name = ?, Card = ?, defaultdeptid = ? WHERE badgenumber = ?");
                    $deptId = !empty($row['defaultdeptid']) ? (int)$row['defaultdeptid'] : 1;
                    $stmt->bind_param("ssis", $row['name'], $row['card'], $deptId, $row['badgenumber']);
                    $stmt->execute();
                    $stmt->close();
                    $updated++;
                }
            } else {
                // Insert baru
                $stmt = $conn->prepare("INSERT INTO userinfo (badgenumber, name, Card, defaultdeptid, sn) VALUES (?, ?, ?, ?, ?)");
                $deptId = !empty($row['defaultdeptid']) ? (int)$row['defaultdeptid'] : 1;
                $sn = !empty($row['sn']) ? $row['sn'] : '';
                $stmt->bind_param("sssis", $row['badgenumber'], $row['name'], $row['card'], $deptId, $sn);
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $errors[] = "Baris {$row['line']}: Gagal menyimpan - " . $stmt->error;
                    $skipped++;
                }
                $stmt->close();
            }
        }
        
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => "Import selesai: $imported baru, $updated diupdate, $skipped dilewati",
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
        exit;
        
    case 'columns':
        // Get kolom dari tabel userinfo
        $conn = getConnection();
        $result = $conn->query("DESCRIBE userinfo");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = [
                'name' => $row['Field'],
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default']
            ];
        }
        $conn->close();
        echo json_encode(['success' => true, 'columns' => $columns]);
        exit;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
